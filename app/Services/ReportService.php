<?php

namespace App\Services;

use App\Models\DamageCategory;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Report;
use App\Models\ReportAssignment;
use App\Models\ReportConfirmation;
use App\Models\ReportLike;
use App\Models\ReportPhoto;
use App\Models\ReportTimeline;
use App\Models\User;
use App\Models\UserBuildingScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ReportService
{
    /**
     * Generate sequential ticket number RPT-YYYY-XXXX (e.g. RPT-2026-0001)
     */
    public function generateTicketNumber(): string
    {
        $year = date('Y');
        $prefix = "RPT-{$year}-";

        $latestTicket = Report::withTrashed()
            ->where('ticket_no', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->value('ticket_no');

        if (!$latestTicket) {
            $nextSeq = 1;
        } else {
            $parts = explode('-', $latestTicket);
            $nextSeq = (int) end($parts) + 1;
        }

        return $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Mask name for public viewing (e.g. "Ahmad Dahlan" -> "A*** D***")
     */
    public static function maskName(string $name): string
    {
        $words = explode(' ', trim($name));
        $masked = array_map(function ($word) {
            $len = mb_strlen($word);
            if ($len <= 1) {
                return $word;
            }
            return mb_substr($word, 0, 1) . str_repeat('*', min(3, $len - 1));
        }, $words);

        return implode(' ', $masked);
    }

    /**
     * Submit a new report
     */
    public function createReport(User $user, array $data, ?UploadedFile $photo = null): Report
    {
        $building = Location::where('uuid', $data['building_uuid'])->firstOrFail();
        $category = DamageCategory::where('uuid', $data['category_uuid'])->firstOrFail();

        return DB::transaction(function () use ($user, $building, $category, $data, $photo) {
            $ticketNo = $this->generateTicketNumber();

            $report = Report::create([
                'ticket_no' => $ticketNo,
                'title' => $data['title'] ?? $data['nama_kerusakan'] ?? ($category->name ?? 'Kerusakan Fasilitas'),
                'reporter_id' => $user->id,
                'building_id' => $building->id,
                'room_name' => $data['room_name'],
                'floor' => $data['floor'] ?? null,
                'category_id' => $category->id,
                'description' => $data['description'],
                'status' => 'DITERIMA',
                'priority' => 'NORMAL',
                'like_count' => 0,
            ]);

            // Save Photo if uploaded
            if ($photo) {
                $path = $photo->store('reports/photos', 'public');
                ReportPhoto::create([
                    'report_id' => $report->id,
                    'uploaded_by' => $user->id,
                    'photo_type' => 'KERUSAKAN',
                    'file_path' => $path,
                    'file_size_bytes' => $photo->getSize(),
                    'mime_type' => $photo->getMimeType() ?: 'image/jpeg',
                ]);
            }

            // Timeline Entry
            ReportTimeline::create([
                'report_id' => $report->id,
                'actor_id' => $user->id,
                'event_type' => 'SUBMITTED',
                'to_status' => 'DITERIMA',
                'note' => 'Laporan dibuat oleh pelapor.',
                'visible_to_reporter' => true,
            ]);

            // Notify technicians and kaur for this building
            $scopedUserIds = UserBuildingScope::where('building_id', $building->id)->pluck('user_id');
            foreach ($scopedUserIds as $scopedUserId) {
                Notification::create([
                    'recipient_id' => $scopedUserId,
                    'report_id' => $report->id,
                    'type' => 'NEW_REPORT',
                    'message' => "Laporan kerusakan baru di {$building->name} ({$report->room_name}): {$report->ticket_no}",
                ]);
            }

            return $report->load(['building', 'category', 'reporter', 'photos']);
        });
    }

    /**
     * Self-assign report by technician (race condition protected)
     */
    public function selfAssign(User $technician, string $reportUuid): Report
    {
        return DB::transaction(function () use ($technician, $reportUuid) {
            $report = Report::where('uuid', $reportUuid)
                ->where('status', 'DITERIMA')
                ->whereNull('assigned_technician_id')
                ->lockForUpdate()
                ->first();

            if (!$report) {
                throw ValidationException::withMessages([
                    'report' => ['Tugas tidak tersedia atau sudah diambil teknisi lain.'],
                ]);
            }

            // Check building scope
            $hasScope = UserBuildingScope::where('user_id', $technician->id)
                ->where('building_id', $report->building_id)
                ->exists();

            if (!$hasScope) {
                throw ValidationException::withMessages([
                    'scope' => ['Gedung laporan ini di luar lingkup penugasan Anda.'],
                ]);
            }

            $report->update([
                'assigned_technician_id' => $technician->id,
                'status' => 'DIPROSES',
            ]);

            ReportAssignment::create([
                'report_id' => $report->id,
                'technician_id' => $technician->id,
                'assigned_by' => null,
                'assignment_type' => 'SELF_TAKEN',
                'response_status' => 'ACCEPTED',
                'assigned_at' => now(),
                'responded_at' => now(),
            ]);

            ReportTimeline::create([
                'report_id' => $report->id,
                'actor_id' => $technician->id,
                'event_type' => 'STATUS_CHANGED',
                'from_status' => 'DITERIMA',
                'to_status' => 'DIPROSES',
                'note' => "Tugas diambil langsung oleh teknisi {$technician->full_name}.",
                'visible_to_reporter' => true,
            ]);

            // Notify reporter
            Notification::create([
                'recipient_id' => $report->reporter_id,
                'report_id' => $report->id,
                'type' => 'STATUS_CHANGED',
                'message' => "Laporan {$report->ticket_no} telah diambil oleh teknisi {$technician->full_name} dan mulai diproses.",
            ]);

            return $report->load(['assignedTechnician', 'building']);
        });
    }

    /**
     * Assign report to technician by Kaur / Admin Unit
     */
    public function assignByKaur(User $kaur, string $reportUuid, string $technicianUuid): Report
    {
        return DB::transaction(function () use ($kaur, $reportUuid, $technicianUuid) {
            $report = Report::where('uuid', $reportUuid)->firstOrFail();
            $technician = User::where('uuid', $technicianUuid)->firstOrFail();

            // Validate kaur scope
            $kaurHasScope = UserBuildingScope::where('user_id', $kaur->id)
                ->where('building_id', $report->building_id)
                ->exists();

            if (!$kaurHasScope) {
                throw ValidationException::withMessages([
                    'scope' => ['Laporan ini di luar gedung cakupan Anda.'],
                ]);
            }

            // Validate technician scope
            $techHasScope = UserBuildingScope::where('user_id', $technician->id)
                ->where('building_id', $report->building_id)
                ->exists();

            if (!$techHasScope) {
                throw ValidationException::withMessages([
                    'technician' => ['Teknisi ini tidak mencakup gedung laporan ini.'],
                ]);
            }

            // Replace any pending assignment
            ReportAssignment::where('report_id', $report->id)
                ->where('response_status', 'PENDING')
                ->update(['response_status' => 'REPLACED']);

            ReportAssignment::create([
                'report_id' => $report->id,
                'technician_id' => $technician->id,
                'assigned_by' => $kaur->id,
                'assignment_type' => 'ASSIGNED_BY_ADMIN_UNIT',
                'response_status' => 'PENDING',
                'assigned_at' => now(),
            ]);

            $report->update([
                'assigned_technician_id' => $technician->id,
                'status' => 'DITUGASKAN',
            ]);

            ReportTimeline::create([
                'report_id' => $report->id,
                'actor_id' => $kaur->id,
                'event_type' => 'ASSIGNED',
                'note' => "Laporan ditugaskan ke teknisi {$technician->full_name}.",
                'visible_to_reporter' => true,
            ]);

            Notification::create([
                'recipient_id' => $technician->id,
                'report_id' => $report->id,
                'type' => 'ASSIGNED',
                'message' => "Anda mendapat penugasan perbaikan baru di {$report->building->name}: {$report->ticket_no}",
            ]);

            return $report;
        });
    }

    /**
     * Technician responds to assigned task (ACCEPT or REJECT)
     */
    public function respondAssignment(User $technician, string $reportUuid, string $action, ?string $rejectReason = null): Report
    {
        return DB::transaction(function () use ($technician, $reportUuid, $action, $rejectReason) {
            $report = Report::where('uuid', $reportUuid)->lockForUpdate()->firstOrFail();

            $assignment = ReportAssignment::where('report_id', $report->id)
                ->where('technician_id', $technician->id)
                ->where('response_status', 'PENDING')
                ->latest('assigned_at')
                ->first();

            if (!$assignment) {
                throw ValidationException::withMessages([
                    'assignment' => ['Tidak ada penugasan pending atas nama Anda pada laporan ini.'],
                ]);
            }

            if ($action === 'ACCEPT') {
                $assignment->update([
                    'response_status' => 'ACCEPTED',
                    'responded_at' => now(),
                ]);

                $report->update([
                    'assigned_technician_id' => $technician->id,
                    'status' => 'DIPROSES',
                ]);

                ReportTimeline::create([
                    'report_id' => $report->id,
                    'actor_id' => $technician->id,
                    'event_type' => 'STATUS_CHANGED',
                    'from_status' => 'DITERIMA',
                    'to_status' => 'DIPROSES',
                    'note' => "Penugasan diterima oleh teknisi {$technician->full_name}.",
                    'visible_to_reporter' => true,
                ]);

                Notification::create([
                    'recipient_id' => $report->reporter_id,
                    'report_id' => $report->id,
                    'type' => 'STATUS_CHANGED',
                    'message' => "Laporan {$report->ticket_no} mulai dikerjakan oleh teknisi {$technician->full_name}.",
                ]);
            } else {
                if (empty($rejectReason)) {
                    throw ValidationException::withMessages([
                        'reject_reason' => ['Alasan penolakan tugas wajib diisi.'],
                    ]);
                }

                $assignment->update([
                    'response_status' => 'REJECTED',
                    'reject_reason' => $rejectReason,
                    'responded_at' => now(),
                ]);

                $report->update([
                    'assigned_technician_id' => null,
                    'status' => 'DITERIMA',
                ]);

                ReportTimeline::create([
                    'report_id' => $report->id,
                    'actor_id' => $technician->id,
                    'event_type' => 'ASSIGNMENT_REJECTED',
                    'note' => "Penugasan ditolak oleh teknisi: {$rejectReason}",
                    'visible_to_reporter' => false, // Hanya terlihat oleh internal teknisi & kaur
                ]);

                if ($assignment->assigned_by) {
                    Notification::create([
                        'recipient_id' => $assignment->assigned_by,
                        'report_id' => $report->id,
                        'type' => 'ASSIGNMENT_REJECTED',
                        'message' => "Teknisi {$technician->full_name} menolak penugasan tiket {$report->ticket_no}. Alasan: {$rejectReason}",
                    ]);
                }
            }

            return $report;
        });
    }

    /**
     * Update report progress / status
     */
    public function updateStatus(User $actor, string $reportUuid, array $data, ?UploadedFile $proofPhoto = null): Report
    {
        return DB::transaction(function () use ($actor, $reportUuid, $data, $proofPhoto) {
            $report = Report::where('uuid', $reportUuid)->firstOrFail();

            // Validasi hak akses teknisi / admin unit
            if ($actor->role === 'TEKNISI') {
                if ($report->assigned_technician_id !== $actor->id) {
                    throw ValidationException::withMessages([
                        'report' => ['Hanya teknisi yang ditugaskan pada laporan ini yang dapat memperbarui status.'],
                    ]);
                }
            } elseif ($actor->role === 'ADMIN_UNIT') {
                $hasScope = UserBuildingScope::where('user_id', $actor->id)
                    ->where('building_id', $report->building_id)
                    ->exists();
                if (!$hasScope) {
                    throw ValidationException::withMessages([
                        'report' => ['Laporan ini di luar lingkup gedung penugasan Anda.'],
                    ]);
                }
            }

            $newStatus = $data['status'] ?? $report->status;
            $note = $data['note'] ?? null;
            $isCannotBeRepaired = ($data['is_cannot_be_repaired'] ?? false) === true;

            $updatePayload = [];

            if (!empty($data['estimated_completion_date'])) {
                $updatePayload['estimated_completion_date'] = $data['estimated_completion_date'];
            }

            if ($newStatus === 'SELESAI') {
                if ($isCannotBeRepaired) {
                    // Penutupan sebagai Tidak Bisa Diperbaiki: Wajib alasan di note, tidak wajib foto bukti
                    if (empty($note)) {
                        throw ValidationException::withMessages([
                            'note' => ['Alasan penutupan (Tidak Bisa Diperbaiki) wajib diisi.'],
                        ]);
                    }
                } else {
                    // Selesai perbaikan fisik: Wajib minimal 1 foto bukti
                    if (!$proofPhoto && !$report->photos()->where('photo_type', 'BUKTI_PERBAIKAN')->exists()) {
                        throw ValidationException::withMessages([
                            'photo' => ['Foto bukti perbaikan wajib diunggah saat status selesai.'],
                        ]);
                    }
                }
                $updatePayload['status'] = 'SELESAI';
                $updatePayload['resolved_at'] = now();
            } else {
                $updatePayload['status'] = $newStatus;
            }

            $fromStatus = $report->status;
            $report->update($updatePayload);

            $timeline = ReportTimeline::create([
                'report_id' => $report->id,
                'actor_id' => $actor->id,
                'event_type' => ($fromStatus !== $newStatus) ? 'STATUS_CHANGED' : 'PROGRESS_NOTE',
                'from_status' => $fromStatus,
                'to_status' => $newStatus,
                'note' => $note ?: ($isCannotBeRepaired ? 'Ditutup: Tidak bisa diperbaiki' : 'Status diperbarui'),
                'visible_to_reporter' => true,
            ]);

            if ($proofPhoto) {
                $path = $proofPhoto->store('reports/proofs', 'public');
                ReportPhoto::create([
                    'report_id' => $report->id,
                    'timeline_id' => $timeline->id,
                    'uploaded_by' => $actor->id,
                    'photo_type' => 'BUKTI_PERBAIKAN',
                    'file_path' => $path,
                    'file_size_bytes' => $proofPhoto->getSize(),
                    'mime_type' => $proofPhoto->getMimeType() ?: 'image/jpeg',
                ]);
            }

            // Notification
            Notification::create([
                'recipient_id' => $report->reporter_id,
                'report_id' => $report->id,
                'type' => 'STATUS_CHANGED',
                'message' => "Status laporan {$report->ticket_no} telah diperbarui menjadi {$newStatus}.",
            ]);

            return $report->load(['photos', 'timelines']);
        });
    }

    /**
     * Toggle like report
     */
    public function toggleLike(User $user, string $reportUuid): array
    {
        return DB::transaction(function () use ($user, $reportUuid) {
            $report = Report::where('uuid', $reportUuid)->lockForUpdate()->firstOrFail();

            if (in_array($report->status, ['SELESAI'])) {
                throw ValidationException::withMessages([
                    'report' => ['Laporan yang sudah selesai tidak dapat di-like.'],
                ]);
            }

            $existingLike = ReportLike::where('report_id', $report->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existingLike) {
                $existingLike->delete();
                $report->decrement('like_count');
                $liked = false;
            } else {
                ReportLike::create([
                    'report_id' => $report->id,
                    'user_id' => $user->id,
                    'created_at' => now(),
                ]);
                $report->increment('like_count');
                $liked = true;
            }

            return [
                'liked' => $liked,
                'like_count' => (int) $report->fresh()->like_count,
            ];
        });
    }
}
