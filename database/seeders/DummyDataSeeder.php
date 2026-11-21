<?php

namespace Database\Seeders;

use App\Models\Asset;
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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultPassword = Hash::make('password123');

        // ============================================================
        // 1. MASTER LOCATIONS (GEDUNG KAMPUS)
        // ============================================================
        $locations = [
            ['code' => 'FTI', 'name' => 'Gedung KH Mas Mansyur (FTI)', 'type' => 'BUILDING'],
            ['code' => 'FBE', 'name' => 'Gedung Prof. Dr. Ace Partadiredja (FBE)', 'type' => 'BUILDING'],
            ['code' => 'REKTORAT', 'name' => 'Gedung GBPH Prabuningrat (Rektorat)', 'type' => 'BUILDING'],
            ['code' => 'FK', 'name' => 'Gedung Dr. Soekiman Wirjosandjojo (FK)', 'type' => 'BUILDING'],
            ['code' => 'FPSB', 'name' => 'Gedung Prof. Dr. Sardjito (FPSB)', 'type' => 'BUILDING'],
            ['code' => 'FIAI', 'name' => 'Gedung K.H.A. Wahid Hasyim (FIAI)', 'type' => 'BUILDING'],
            ['code' => 'GKU', 'name' => 'Gedung Kuliah Umum Prof. Dr. M. Sardjito (GKU)', 'type' => 'BUILDING'],
            ['code' => 'PERPUS', 'name' => 'Perpustakaan Pusat Moh. Hatta', 'type' => 'BUILDING'],
        ];

        $locMap = [];
        foreach ($locations as $locData) {
            $loc = Location::firstOrCreate(
                ['code' => $locData['code']],
                ['name' => $locData['name'], 'type' => $locData['type'], 'is_active' => true]
            );
            $locMap[$locData['code']] = $loc;
        }

        // ============================================================
        // 2. MASTER DAMAGE CATEGORIES (KATEGORI KERUSAKAN)
        // ============================================================
        $categories = [
            'AC / Pendingin Ruangan',
            'Proyektor & Layar',
            'Kelistrikan & Stopkontak',
            'Lampu & Penerangan',
            'Kursi & Meja Kelas',
            'Pintu, Jendela & Kunci',
            'Sanitasi & Toilet',
            'Lift & Eskalator',
            'Plafon & Atap Bocor',
            'Sound System & Audio',
        ];

        $catMap = [];
        foreach ($categories as $name) {
            $cat = DamageCategory::firstOrCreate(
                ['name' => $name],
                ['is_active' => true]
            );
            $catMap[$name] = $cat;
        }

        // ============================================================
        // 3. MASTER ASSETS (BARANG / FASILITAS KAMPUS DENGAN KODE QR)
        // ============================================================
        $assets = [
            [
                'asset_no' => 'AST-FTI-AC-01',
                'qr_code' => 'QR-FTI-AC-201',
                'name' => 'AC Daikin Inverter 2 PK',
                'category_id' => $catMap['AC / Pendingin Ruangan']->id,
                'building_id' => $locMap['FTI']->id,
                'room_name' => 'Ruang Sidang 2.1',
            ],
            [
                'asset_no' => 'AST-FTI-PRJ-01',
                'qr_code' => 'QR-FTI-PRJ-312',
                'name' => 'Proyektor Epson EB-X400',
                'category_id' => $catMap['Proyektor & Layar']->id,
                'building_id' => $locMap['FTI']->id,
                'room_name' => 'Ruang Kuliah 3.12',
            ],
            [
                'asset_no' => 'AST-FBE-AC-01',
                'qr_code' => 'QR-FBE-AC-102',
                'name' => 'AC Panasonic Standard 2 PK',
                'category_id' => $catMap['AC / Pendingin Ruangan']->id,
                'building_id' => $locMap['FBE']->id,
                'room_name' => 'Lab Akuntansi 1.2',
            ],
            [
                'asset_no' => 'AST-GKU-AUD-01',
                'qr_code' => 'QR-GKU-AUD-001',
                'name' => 'Sound System Mixer Yamaha MG16XU',
                'category_id' => $catMap['Sound System & Audio']->id,
                'building_id' => $locMap['GKU']->id,
                'room_name' => 'Auditorium Prof. Sardjito',
            ],
        ];

        $assetMap = [];
        foreach ($assets as $assetData) {
            $ast = Asset::firstOrCreate(
                ['asset_no' => $assetData['asset_no']],
                $assetData
            );
            $assetMap[$assetData['asset_no']] = $ast;
        }

        // ============================================================
        // 4. USERS (SEMUA ROLE KAMPUS)
        // ============================================================
        $users = [
            // Admin Universitas
            'admin.univ' => [
                'email' => 'admin.univ@uii.ac.id',
                'full_name' => 'Admin Universitas UII',
                'role' => 'ADMIN_UNIV',
                'must_change_password' => false,
            ],
            // Admin Unit / Kaur
            'kaur.fti' => [
                'email' => 'kaur.fti@uii.ac.id',
                'full_name' => 'Sukirno (Kaur Perbekalan FTI)',
                'role' => 'ADMIN_UNIT',
                'must_change_password' => false,
            ],
            'kaur.fbe' => [
                'email' => 'kaur.fbe@uii.ac.id',
                'full_name' => 'Haryanto (Kaur Sarpras FBE)',
                'role' => 'ADMIN_UNIT',
                'must_change_password' => false,
            ],
            'kaur.gku' => [
                'email' => 'kaur.gku@uii.ac.id',
                'full_name' => 'Suryadi (Kaur Umum & GKU)',
                'role' => 'ADMIN_UNIT',
                'must_change_password' => false,
            ],
            // Teknisi
            'teknisi.fti' => [
                'email' => 'teknisi.fti@uii.ac.id',
                'full_name' => 'Bambang (Teknisi Listrik & AC FTI)',
                'role' => 'TEKNISI',
                'must_change_password' => false,
            ],
            'teknisi2.fti' => [
                'email' => 'teknisi2.fti@uii.ac.id',
                'full_name' => 'Joko Widodo (Teknisi Sipil FTI)',
                'role' => 'TEKNISI',
                'must_change_password' => false,
            ],
            'teknisi.fbe' => [
                'email' => 'teknisi.fbe@uii.ac.id',
                'full_name' => 'Rudi Santoso (Teknisi FBE)',
                'role' => 'TEKNISI',
                'must_change_password' => false,
            ],
            'teknisi.gku' => [
                'email' => 'teknisi.gku@uii.ac.id',
                'full_name' => 'Agus Priyono (Teknisi GKU & Audio)',
                'role' => 'TEKNISI',
                'must_change_password' => false,
            ],
            // Pelapor
            'pelapor.mhs' => [
                'email' => 'pelapor.mhs@uii.ac.id',
                'full_name' => 'Ahmad Fauzi',
                'role' => 'PELAPOR',
                'reporter_type' => 'MAHASISWA',
                'must_change_password' => false,
            ],
            'siti.mhs' => [
                'email' => 'siti.mhs@uii.ac.id',
                'full_name' => 'Siti Rahmawati',
                'role' => 'PELAPOR',
                'reporter_type' => 'MAHASISWA',
                'must_change_password' => false,
            ],
            'dosen.fti' => [
                'email' => 'dosen.fti@uii.ac.id',
                'full_name' => 'Dr. Hendra Gunawan, S.T., M.Eng.',
                'role' => 'PELAPOR',
                'reporter_type' => 'DOSEN',
                'must_change_password' => false,
            ],
            'tendik.rek' => [
                'email' => 'dewi.tendik@uii.ac.id',
                'full_name' => 'Dewi Lestari (Staf BAAK)',
                'role' => 'PELAPOR',
                'reporter_type' => 'TENDIK',
                'must_change_password' => false,
            ],
        ];

        $userMap = [];
        foreach ($users as $key => $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                array_merge($userData, [
                    'password' => $defaultPassword,
                    'is_active' => true,
                ])
            );
            $userMap[$key] = $user;
        }

        // ============================================================
        // 5. USER BUILDING SCOPES (CAKUPAN GEDUNG KAUR & TEKNISI)
        // ============================================================
        $scopes = [
            // FTI
            ['user_id' => $userMap['kaur.fti']->id, 'building_id' => $locMap['FTI']->id],
            ['user_id' => $userMap['teknisi.fti']->id, 'building_id' => $locMap['FTI']->id],
            ['user_id' => $userMap['teknisi2.fti']->id, 'building_id' => $locMap['FTI']->id],
            // FBE
            ['user_id' => $userMap['kaur.fbe']->id, 'building_id' => $locMap['FBE']->id],
            ['user_id' => $userMap['teknisi.fbe']->id, 'building_id' => $locMap['FBE']->id],
            // GKU & REKTORAT
            ['user_id' => $userMap['kaur.gku']->id, 'building_id' => $locMap['GKU']->id],
            ['user_id' => $userMap['kaur.gku']->id, 'building_id' => $locMap['REKTORAT']->id],
            ['user_id' => $userMap['teknisi.gku']->id, 'building_id' => $locMap['GKU']->id],
            ['user_id' => $userMap['teknisi.gku']->id, 'building_id' => $locMap['REKTORAT']->id],
        ];

        foreach ($scopes as $scope) {
            UserBuildingScope::firstOrCreate([
                'user_id' => $scope['user_id'],
                'building_id' => $scope['building_id'],
            ], [
                'granted_by' => $userMap['admin.univ']->id,
                'created_at' => now(),
            ]);
        }

        // ============================================================
        // 6. DUMMY IMAGE PLACEHOLDERS
        // ============================================================
        Storage::disk('public')->makeDirectory('reports/photos');
        Storage::disk('public')->makeDirectory('reports/proofs');

        $dummy1x1Jpg = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');
        Storage::disk('public')->put('reports/photos/dummy_damage.jpg', $dummy1x1Jpg);
        Storage::disk('public')->put('reports/proofs/dummy_proof.jpg', $dummy1x1Jpg);

        // ============================================================
        // 7. DUMMY REPORTS (BERBAGAI STATUS & SKENARIO)
        // ============================================================
        $reportDefs = [
            // [1] DITERIMA - NORMAL (Laporan baru dari pelapor.mhs)
            [
                'ticket_no' => 'RPT-2026-0002',
                'reporter' => $userMap['pelapor.mhs'],
                'building' => $locMap['FTI'],
                'room_name' => 'Lab Jaringan Komputer 2.4',
                'floor' => '2',
                'category' => $catMap['Kelistrikan & Stopkontak'],
                'asset' => null,
                'description' => 'Stop kontak di meja praktikum baris ke-3 korsleting dan mati total. Mahasiswa tidak bisa mengisi daya laptop saat sesi praktikum berlangsung.',
                'status' => 'DITERIMA',
                'priority' => 'NORMAL',
                'like_count' => 6,
                'is_major_damage' => false,
                'created_at' => now()->subHours(5),
                'updated_at' => now()->subHours(5),
            ],
            // [2] DITERIMA - URGEN (Dosen melapor proyektor mati menjelang kuliah umum)
            [
                'ticket_no' => 'RPT-2026-0003',
                'reporter' => $userMap['dosen.fti'],
                'building' => $locMap['GKU'],
                'room_name' => 'Ruang Kuliah Umum 1.1',
                'floor' => '1',
                'category' => $catMap['Proyektor & Layar'],
                'asset' => null,
                'description' => 'Proyektor gantung mati total dan lampu indikator berkedip merah cepat. Jam 08.00 ada kuliah umum lintas prodi dengan 120 mahasiswa.',
                'status' => 'DITERIMA',
                'priority' => 'URGEN',
                'like_count' => 24,
                'is_major_damage' => false,
                'created_at' => now()->subHours(3),
                'updated_at' => now()->subHours(3),
            ],
            // [3] DIPROSES - Normal Assignment (Teknisi FTI sedang mengerjakan)
            [
                'ticket_no' => 'RPT-2026-0004',
                'reporter' => $userMap['pelapor.mhs'],
                'building' => $locMap['FTI'],
                'room_name' => 'Ruang Kuliah 3.10',
                'floor' => '3',
                'category' => $catMap['AC / Pendingin Ruangan'],
                'asset' => null,
                'description' => 'AC ruangan berisik dan meneteskan air deras membasahi meja dan lantai kelas.',
                'status' => 'DIPROSES',
                'priority' => 'NORMAL',
                'assigned_technician' => $userMap['teknisi.fti'],
                'estimated_completion_date' => now()->addDay()->setHour(16)->setMinute(0),
                'like_count' => 8,
                'is_major_damage' => false,
                'created_at' => now()->subHours(12),
                'updated_at' => now()->subHours(2),
            ],
            // [4] DIPROSES - Major Damage (Plafon Gypsum Jebol di FBE)
            [
                'ticket_no' => 'RPT-2026-0005',
                'reporter' => $userMap['siti.mhs'],
                'building' => $locMap['FBE'],
                'room_name' => 'Ruang Seminar Prof. Ace',
                'floor' => '1',
                'category' => $catMap['Plafon & Atap Bocor'],
                'asset' => null,
                'description' => 'Plafon gypsum jebol berdiameter sekitar 1 meter akibat rembesan air hujan lebat semalam.',
                'status' => 'DIPROSES',
                'priority' => 'URGEN',
                'assigned_technician' => $userMap['teknisi.fbe'],
                'is_major_damage' => true,
                'major_damage_note' => 'Perlu pengadaan 4 lembar gypsum baru dan perbaikan jalur talang air di atap luar oleh vendor atap.',
                'like_count' => 15,
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subHours(4),
            ],
            // [5] SELESAI - Selesai dengan rating bintang 5 dan feedback
            [
                'ticket_no' => 'RPT-2026-0006',
                'reporter' => $userMap['pelapor.mhs'],
                'building' => $locMap['FTI'],
                'room_name' => 'Ruang Sidang 2.1',
                'floor' => '2',
                'category' => $catMap['AC / Pendingin Ruangan'],
                'asset' => $assetMap['AST-FTI-AC-01'],
                'description' => 'Remote AC AC Daikin hilang dan unit indoor tidak merespons tombol manual.',
                'status' => 'SELESAI',
                'priority' => 'NORMAL',
                'assigned_technician' => $userMap['teknisi.fti'],
                'resolved_at' => now()->subDays(2),
                'like_count' => 3,
                'is_major_damage' => false,
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(2),
            ],
            // [6] SELESAI - Selesai dengan rating bintang 4
            [
                'ticket_no' => 'RPT-2026-0007',
                'reporter' => $userMap['tendik.rek'],
                'building' => $locMap['REKTORAT'],
                'room_name' => 'Lobby Sayap Barat BAAK',
                'floor' => '1',
                'category' => $catMap['Lampu & Penerangan'],
                'asset' => null,
                'description' => 'Downlight LED di atas loket pelayanan mahasiswa mati dan area pelayanan tampak remang-remang.',
                'status' => 'SELESAI',
                'priority' => 'NORMAL',
                'assigned_technician' => $userMap['teknisi.gku'],
                'resolved_at' => now()->subDays(1),
                'like_count' => 2,
                'is_major_damage' => false,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(1),
            ],
            // [7] DITERIMA - Urgen Sanitasi Toilet (FTI)
            [
                'ticket_no' => 'RPT-2026-0008',
                'reporter' => $userMap['pelapor.mhs'],
                'building' => $locMap['FTI'],
                'room_name' => 'Toilet Pria Lantai 1 Sayap Timur',
                'floor' => '1',
                'category' => $catMap['Sanitasi & Toilet'],
                'asset' => null,
                'description' => 'Pipa kran wastafel patah sehingga air menyembur deras dan membanjiri selasar depan toilet.',
                'status' => 'DITERIMA',
                'priority' => 'URGEN',
                'like_count' => 31,
                'is_major_damage' => false,
                'created_at' => now()->subMinutes(45),
                'updated_at' => now()->subMinutes(45),
            ],
            // [8] SELESAI - Pintu & Kunci Kelas (FBE)
            [
                'ticket_no' => 'RPT-2026-0009',
                'reporter' => $userMap['siti.mhs'],
                'building' => $locMap['FBE'],
                'room_name' => 'Ruang Kuliah B-201',
                'floor' => '2',
                'category' => $catMap['Pintu, Jendela & Kunci'],
                'asset' => null,
                'description' => 'Engsel pintu bawah terlepas sehingga pintu menggesek lantai dan sulit dikunci.',
                'status' => 'SELESAI',
                'priority' => 'NORMAL',
                'assigned_technician' => $userMap['teknisi.fbe'],
                'resolved_at' => now()->subDays(4),
                'like_count' => 1,
                'is_major_damage' => false,
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(4),
            ],
        ];

        foreach ($reportDefs as $def) {
            $report = Report::firstOrCreate(
                ['ticket_no' => $def['ticket_no']],
                [
                    'uuid' => (string) Str::uuid(),
                    'reporter_id' => $def['reporter']->id,
                    'assigned_technician_id' => isset($def['assigned_technician']) ? $def['assigned_technician']->id : null,
                    'building_id' => $def['building']->id,
                    'room_name' => $def['room_name'],
                    'floor' => $def['floor'],
                    'category_id' => $def['category']->id,
                    'asset_id' => isset($def['asset']) && $def['asset'] ? $def['asset']->id : null,
                    'description' => $def['description'],
                    'status' => $def['status'],
                    'priority' => $def['priority'],
                    'like_count' => $def['like_count'],
                    'is_major_damage' => $def['is_major_damage'],
                    'major_damage_note' => $def['major_damage_note'] ?? null,
                    'estimated_completion_date' => $def['estimated_completion_date'] ?? null,
                    'resolved_at' => $def['resolved_at'] ?? null,
                    'created_at' => $def['created_at'],
                    'updated_at' => $def['updated_at'],
                ]
            );

            // Foto Awal Laporan
            ReportPhoto::firstOrCreate([
                'report_id' => $report->id,
                'photo_type' => 'KERUSAKAN',
            ], [
                'uploaded_by' => $def['reporter']->id,
                'file_path' => 'reports/photos/dummy_damage.jpg',
                'file_size_bytes' => 15200,
                'mime_type' => 'image/jpeg',
                'created_at' => $def['created_at'],
            ]);

            // Timeline: SUBMITTED
            ReportTimeline::firstOrCreate([
                'report_id' => $report->id,
                'event_type' => 'SUBMITTED',
            ], [
                'actor_id' => $def['reporter']->id,
                'to_status' => 'DITERIMA',
                'note' => 'Laporan dibuat oleh pelapor.',
                'visible_to_reporter' => true,
                'created_at' => $def['created_at'],
            ]);

            // Jika Ditugaskan / Sedang Diproses
            if (in_array($def['status'], ['DIPROSES', 'SELESAI'])) {
                $tech = $def['assigned_technician'];
                ReportAssignment::firstOrCreate([
                    'report_id' => $report->id,
                    'technician_id' => $tech->id,
                ], [
                    'assigned_by' => $userMap['kaur.fti']->id,
                    'assignment_type' => 'ASSIGNED_BY_ADMIN_UNIT',
                    'response_status' => 'ACCEPTED',
                    'assigned_at' => $def['created_at']->copy()->addHours(1),
                    'responded_at' => $def['created_at']->copy()->addHours(2),
                ]);

                ReportTimeline::firstOrCreate([
                    'report_id' => $report->id,
                    'event_type' => 'STATUS_CHANGED',
                    'to_status' => 'DIPROSES',
                ], [
                    'actor_id' => $tech->id,
                    'from_status' => 'DITERIMA',
                    'to_status' => 'DIPROSES',
                    'note' => "Penugasan diterima oleh teknisi {$tech->full_name} dan mulai diproses.",
                    'visible_to_reporter' => true,
                    'created_at' => $def['created_at']->copy()->addHours(2),
                ]);
            }

            // Jika Major Damage
            if (!empty($def['is_major_damage'])) {
                ReportTimeline::firstOrCreate([
                    'report_id' => $report->id,
                    'event_type' => 'MAJOR_DAMAGE',
                ], [
                    'actor_id' => $userMap['kaur.fbe']->id,
                    'note' => "Ditandai sebagai Kerusakan Berat: {$def['major_damage_note']}",
                    'visible_to_reporter' => true,
                    'created_at' => $def['created_at']->copy()->addHours(3),
                ]);
            }

            // Jika Selesai
            if ($def['status'] === 'SELESAI') {
                $tech = $def['assigned_technician'];

                // Foto Bukti Perbaikan
                ReportPhoto::firstOrCreate([
                    'report_id' => $report->id,
                    'photo_type' => 'BUKTI_PERBAIKAN',
                ], [
                    'uploaded_by' => $tech->id,
                    'file_path' => 'reports/proofs/dummy_proof.jpg',
                    'file_size_bytes' => 18400,
                    'mime_type' => 'image/jpeg',
                    'created_at' => $def['resolved_at'],
                ]);

                ReportTimeline::firstOrCreate([
                    'report_id' => $report->id,
                    'event_type' => 'STATUS_CHANGED',
                    'to_status' => 'SELESAI',
                ], [
                    'actor_id' => $tech->id,
                    'from_status' => 'DIPROSES',
                    'to_status' => 'SELESAI',
                    'note' => 'Perbaikan fisik selesai dan telah diuji fungsional dengan baik.',
                    'visible_to_reporter' => true,
                    'created_at' => $def['resolved_at'],
                ]);

                // Rating & Review Pelapor
                if ($def['ticket_no'] === 'RPT-2026-0006') {
                    ReportConfirmation::firstOrCreate([
                        'report_id' => $report->id,
                    ], [
                        'reporter_id' => $def['reporter']->id,
                        'rating' => 5,
                        'comment' => 'Pekerjaan sangat cepat, remote AC diganti baru dan kelas sejuk kembali. Terima kasih banyak!',
                        'confirmed_at' => $def['resolved_at']->copy()->addHours(1),
                    ]);

                    ReportTimeline::firstOrCreate([
                        'report_id' => $report->id,
                        'event_type' => 'CONFIRMED',
                    ], [
                        'actor_id' => $def['reporter']->id,
                        'note' => 'Pelapor mengonfirmasi perbaikan selesai (Rating: 5/5).',
                        'visible_to_reporter' => true,
                        'created_at' => $def['resolved_at']->copy()->addHours(1),
                    ]);
                } elseif ($def['ticket_no'] === 'RPT-2026-0007') {
                    ReportConfirmation::firstOrCreate([
                        'report_id' => $report->id,
                    ], [
                        'reporter_id' => $def['reporter']->id,
                        'rating' => 4,
                        'comment' => 'Lampu sudah menyala terang, pelayanan staf ramah dan tanggap.',
                        'confirmed_at' => $def['resolved_at']->copy()->addHours(2),
                    ]);
                }
            }

            // Upvotes / Likes dari pelapor lain
            if ($def['like_count'] > 0) {
                ReportLike::firstOrCreate([
                    'report_id' => $report->id,
                    'user_id' => $userMap['siti.mhs']->id,
                ], [
                    'created_at' => now(),
                ]);
            }

            // Notifikasi In-App Realistis
            Notification::firstOrCreate([
                'recipient_id' => $def['reporter']->id,
                'report_id' => $report->id,
                'message' => "Update status laporan {$report->ticket_no}: status saat ini {$report->status}.",
            ], [
                'type' => 'STATUS_CHANGED',
                'is_read' => false,
                'created_at' => $report->updated_at,
            ]);
        }
    }
}
