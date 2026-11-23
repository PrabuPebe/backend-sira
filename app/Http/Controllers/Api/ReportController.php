<?php

namespace App\Http\Controllers\Api;

use App\Models\Location;
use App\Models\Notification;
use App\Models\Report;
use App\Models\ReportConfirmation;
use App\Models\ReportLike;
use App\Models\ReportTimeline;
use App\Models\User;
use App\Models\UserBuildingScope;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Report', description: 'Manajemen laporan kerusakan fasilitas kampus')]
class ReportController extends BaseApiController
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    #[OA\Get(
        path: '/reports/public',
        summary: 'Daftar laporan publik per gedung (nama pelapor disamarkan)',
        tags: ['Report'],
        parameters: [
            new OA\Parameter(name: 'building_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Jumlah item per halaman (misal 5, 10, 15)', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar laporan publik berhasil diambil')
        ]
    )]
    public function publicFeed(Request $request): JsonResponse
    {
        $query = Report::with(['building', 'category', 'reporter', 'photos'])
            ->whereIn('status', ['DITERIMA', 'DIPROSES']);

        if ($request->filled('building_uuid')) {
            $buildingId = Location::where('uuid', $request->query('building_uuid'))->value('id');
            if ($buildingId) {
                $query->where('building_id', $buildingId);
            }
        }

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $like = $this->likeOperator();
            $query->where(function ($q) use ($search, $like) {
                $q->where('room_name', $like, $search)
                    ->orWhere('title', $like, $search)
                    ->orWhere('description', $like, $search)
                    ->orWhere('ticket_no', $like, $search);
            });
        }

        $perPage = max(1, (int) $request->query('per_page', 15));

        // Urutan prioritas: URGEN dulu, lalu like_count terbanyak, lalu created_at terlama (BR-02, BR-03)
        $reports = $query->orderByRaw("CASE WHEN priority = 'URGEN' THEN 0 ELSE 1 END")
            ->orderBy('like_count', 'desc')
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);

        $currentUser = $request->user('sanctum');
        $likedReportIds = [];
        if ($currentUser) {
            $reportIds = $reports->getCollection()->pluck('id')->filter()->toArray();
            if (!empty($reportIds)) {
                $likedReportIds = \App\Models\ReportLike::where('user_id', $currentUser->id)
                    ->whereIn('report_id', $reportIds)
                    ->pluck('report_id')
                    ->flip()
                    ->toArray();
            }
        }

        $reports->getCollection()->transform(function ($report) use ($currentUser, $likedReportIds) {
            $data = $report->toArray();
            if ($report->reporter) {
                $data['reporter'] = [
                    'uuid' => $report->reporter->uuid,
                    'full_name' => ReportService::maskName($report->reporter->full_name),
                    'reporter_type' => $report->reporter->reporter_type,
                ];
            }
            $isLiked = $currentUser ? isset($likedReportIds[$report->id]) : false;
            $data['is_liked'] = $isLiked;
            $data['is_liked_by_me'] = $isLiked;
            return $data;
        });

        return $this->getResponse($reports, 'Laporan publik berhasil diambil', 200);
    }

    #[OA\Get(
        path: '/reports',
        summary: 'Daftar semua laporan staf (Kelola Laporan untuk Kaur & Admin Univ)',
        description: 'Mengambil daftar laporan dengan filter lengkap status, prioritas, gedung, kategori, teknisi, rentang tanggal, dan pencarian sesuai cakupan peran staf.',
        tags: ['Report'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['DITERIMA', 'DITUGASKAN', 'DIPROSES', 'MENUNGGU', 'SELESAI', 'TIDAK_DAPAT_DIPERBAIKI'])),
            new OA\Parameter(name: 'priority', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['RENDAH', 'NORMAL', 'TINGGI', 'URGEN'])),
            new OA\Parameter(name: 'building_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'category_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'technician_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar laporan berhasil diambil'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Hanya staf yang berwenang')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['ADMIN_UNIT', 'ADMIN_UNIV'])) {
            return $this->errorResponse('Hanya staf unit atau admin universitas yang berwenang melihat daftar kelola laporan', 403);
        }

        $query = Report::with(['building', 'category', 'reporter', 'assignedTechnician', 'photos']);

        if ($user->role === 'ADMIN_UNIT') {
            $scopedBuildingIds = UserBuildingScope::where('user_id', $user->id)->pluck('building_id')->toArray();
            $query->whereIn('building_id', $scopedBuildingIds);
        }

        if ($request->filled('building_uuid')) {
            $buildingId = Location::where('uuid', $request->query('building_uuid'))->value('id');
            if ($buildingId) {
                $query->where('building_id', $buildingId);
            }
        }

        if ($request->filled('category_uuid')) {
            $categoryId = \App\Models\DamageCategory::where('uuid', $request->query('category_uuid'))->value('id');
            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }
        }

        if ($request->filled('technician_uuid')) {
            $techId = User::where('uuid', $request->query('technician_uuid'))->value('id');
            if ($techId) {
                $query->where('assigned_technician_id', $techId);
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->query('priority'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->query('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->query('end_date'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $like = $this->likeOperator();
            $query->where(function ($q) use ($search, $like) {
                $q->where('room_name', $like, $search)
                    ->orWhere('title', $like, $search)
                    ->orWhere('description', $like, $search)
                    ->orWhere('ticket_no', $like, $search)
                    ->orWhereHas('reporter', function ($r) use ($search, $like) {
                        $r->where('full_name', $like, $search);
                    });
            });
        }

        $perPage = (int) $request->query('per_page', 15);
        $reports = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->getResponse($reports, 'Daftar kelola laporan berhasil diambil', 200);
    }

    #[OA\Get(
        path: '/reports/technicians',
        summary: 'Daftar teknisi dan beban tugas aktif (Untuk modal penugasan teknisi oleh Kaur)',
        description: 'Menampilkan daftar teknisi aktif beserta gedung cakupan dan jumlah tugas yang sedang aktif ditangani.',
        tags: ['Report'],
        parameters: [
            new OA\Parameter(name: 'building_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar teknisi berhasil diambil'),
            new OA\Response(response: 403, description: 'Hanya Admin Unit atau Admin Univ yang berwenang')
        ]
    )]
    public function technicians(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['ADMIN_UNIT', 'ADMIN_UNIV'])) {
            return $this->errorResponse('Hanya Admin Unit atau Admin Universitas yang berwenang', 403);
        }

        $query = User::where('role', 'TEKNISI')->where('is_active', true)->with('buildings');

        $buildingUuid = $request->query('building_uuid');
        if (!empty($buildingUuid)) {
            $buildingId = Location::where('uuid', $buildingUuid)->value('id');
            if ($buildingId) {
                $query->whereHas('buildings', function ($b) use ($buildingId) {
                    $b->where('locations.id', $buildingId);
                });
            }
        } elseif ($user->role === 'ADMIN_UNIT') {
            $scopedBuildingIds = UserBuildingScope::where('user_id', $user->id)->pluck('building_id')->toArray();
            $query->whereHas('buildings', function ($b) use ($scopedBuildingIds) {
                $b->whereIn('locations.id', $scopedBuildingIds);
            });
        }

        $technicians = $query->get()->map(function ($tech) {
            $activeTasksCount = Report::where('assigned_technician_id', $tech->id)
                ->whereIn('status', ['DITUGASKAN', 'DIPROSES', 'MENUNGGU'])
                ->count();

            return [
                'uuid' => $tech->uuid,
                'full_name' => $tech->full_name,
                'email' => $tech->email,
                'role' => $tech->role,
                'buildings' => $tech->buildings,
                'active_tasks_count' => $activeTasksCount,
            ];
        });

        return $this->getResponse($technicians, 'Daftar teknisi penugasan berhasil diambil', 200);
    }

    #[OA\Get(
        path: '/reports/my-reports',
        summary: 'Daftar laporan milik pelapor yang login (mendukung filter status, gedung, pencarian, dan paginasi)',
        tags: ['Report'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['DITERIMA', 'DIPROSES', 'SELESAI'])),
            new OA\Parameter(name: 'building_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Jumlah item per halaman (misal 5, 10, 15)', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Laporan saya berhasil diambil'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function myReports(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Report::with(['building', 'category', 'assignedTechnician', 'photos'])
            ->where('reporter_id', $user->id);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('building_uuid')) {
            $buildingId = Location::where('uuid', $request->query('building_uuid'))->value('id');
            if ($buildingId) {
                $query->where('building_id', $buildingId);
            }
        }

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $like = $this->likeOperator();
            $query->where(function ($q) use ($search, $like) {
                $q->where('room_name', $like, $search)
                    ->orWhere('title', $like, $search)
                    ->orWhere('description', $like, $search)
                    ->orWhere('ticket_no', $like, $search);
            });
        }

        $perPage = max(1, (int) $request->query('per_page', 15));
        $reports = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->getResponse($reports, 'Daftar laporan saya berhasil diambil', 200);
    }

    #[OA\Get(
        path: '/reports/new-update',
        summary: 'Daftar pembaruan terbaru dari laporan yang dikirim pengguna',
        description: 'Mengambil daftar pembaruan terbaru dari laporan yang dikirim oleh pengguna saat ini dengan field wajib: no tiket, title kerusakan, lokasi, tanggal, status, dan uuid (seluruh ID internal dirahasiakan).',
        tags: ['Report'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['DITERIMA', 'DIPROSES', 'SELESAI'])),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'view', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['reports', 'timeline'], default: 'reports')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar pembaruan terbaru berhasil diambil'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function newUpdates(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        $limit = min(max((int) $request->query('limit', 15), 1), 50);
        $status = $request->query('status');
        $view = $request->query('view', 'reports');

        if ($view === 'timeline') {
            $query = ReportTimeline::whereHas('report', function ($q) use ($user, $status) {
                $q->where('reporter_id', $user->id);
                if (!empty($status)) {
                    $q->where('status', $status);
                }
            })
            ->where('visible_to_reporter', true)
            ->with(['report.building', 'report.category'])
            ->orderBy('created_at', 'desc');

            $paginator = $query->paginate($limit);
            $paginator->getCollection()->transform(function ($timeline) {
                $report = $timeline->report;
                $buildingName = $report?->building?->name ?? 'Kampus';
                $roomName = $report?->room_name ?? '';
                $floor = $report?->floor ? " (Lantai {$report->floor})" : '';
                $locationStr = trim("{$buildingName}" . ($roomName ? " - {$roomName}" : "") . $floor);

                return [
                    'uuid' => $report?->uuid,
                    'ticket_no' => $report?->ticket_no,
                    'no_tiket' => $report?->ticket_no,
                    'title_kerusakan' => $report?->category?->name ?? 'Kerusakan Fasilitas',
                    'title' => $report?->category?->name ?? 'Kerusakan Fasilitas',
                    'lokasi' => $locationStr,
                    'location' => $locationStr,
                    'tanggal' => $timeline->created_at?->format('Y-m-d H:i:s'),
                    'date' => $timeline->created_at?->toIso8601String(),
                    'status' => $timeline->to_status ?? $report?->status,
                    'event_type' => $timeline->event_type,
                    'note' => $timeline->note,
                ];
            });

            return $this->getResponse($paginator, 'Daftar pembaruan terbaru berhasil diambil', 200);
        }

        $query = Report::with([
            'building',
            'category',
            'timelines' => function ($q) {
                $q->where('visible_to_reporter', true)->orderBy('created_at', 'desc');
            },
        ])
        ->where('reporter_id', $user->id);

        if (!empty($status)) {
            $query->where('status', $status);
        }

        $paginator = $query->orderBy('updated_at', 'desc')->paginate($limit);

        $paginator->getCollection()->transform(function ($report) {
            $buildingName = $report->building?->name ?? 'Kampus';
            $roomName = $report->room_name ?? '';
            $floor = $report->floor ? " (Lantai {$report->floor})" : '';
            $locationStr = trim("{$buildingName}" . ($roomName ? " - {$roomName}" : "") . $floor);

            $latestTimeline = $report->timelines->first();
            $updateDate = $latestTimeline?->created_at ?? $report->updated_at ?? $report->created_at;

            return [
                'uuid' => $report->uuid,
                'ticket_no' => $report->ticket_no,
                'no_tiket' => $report->ticket_no,
                'title_kerusakan' => $report->category?->name ?? 'Kerusakan Fasilitas',
                'title' => $report->category?->name ?? 'Kerusakan Fasilitas',
                'lokasi' => $locationStr,
                'location' => $locationStr,
                'tanggal' => $updateDate?->format('Y-m-d H:i:s'),
                'date' => $updateDate?->toIso8601String(),
                'status' => $report->status,
                'priority' => $report->priority,
                'description' => $report->description,
                'like_count' => $report->like_count,
                'latest_update' => $latestTimeline ? [
                    'event_type' => $latestTimeline->event_type,
                    'note' => $latestTimeline->note,
                    'status' => $latestTimeline->to_status ?? $report->status,
                    'tanggal' => $latestTimeline->created_at?->format('Y-m-d H:i:s'),
                    'date' => $latestTimeline->created_at?->toIso8601String(),
                ] : null,
            ];
        });

        return $this->getResponse($paginator, 'Daftar pembaruan terbaru berhasil diambil', 200);
    }

    #[OA\Get(
        path: '/reports/tasks',
        summary: 'Daftar tugas teknisi / kaur (berdasarkan cakupan gedung, tab, pencarian, pengurutan, & paginasi)',
        description: 'Menampilkan daftar tugas untuk teknisi. Mendukung tab (all, available, mine), sorting (latest, oldest, priority), dan per_page (5, 10, 15).',
        tags: ['Report'],
        parameters: [
            new OA\Parameter(name: 'tab', in: 'query', required: false, description: 'Tab tugas: all, available, mine', schema: new OA\Schema(type: 'string', enum: ['all', 'available', 'mine'], default: 'all')),
            new OA\Parameter(name: 'sort', in: 'query', required: false, description: 'Pengurutan: latest (terbaru), oldest (terlama), priority (prioritas)', schema: new OA\Schema(type: 'string', enum: ['latest', 'oldest', 'priority'], default: 'priority')),
            new OA\Parameter(name: 'building_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['DITERIMA', 'DITUGASKAN', 'DIPROSES', 'MENUNGGU', 'SELESAI', 'TIDAK_DAPAT_DIPERBAIKI'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Pencarian tugas atau ruangan', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Jumlah item per halaman (misal 5, 10, 15)', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar tugas berhasil diambil')
        ]
    )]
    public function tasks(Request $request): JsonResponse
    {
        $user = $request->user();

        // Ambil ID gedung dalam cakupan user
        $scopedBuildingIds = UserBuildingScope::where('user_id', $user->id)->pluck('building_id')->toArray();

        // Admin Univ dapat melihat semua gedung
        if ($user->role === 'ADMIN_UNIV') {
            $query = Report::with(['building', 'category', 'reporter', 'assignedTechnician', 'photos']);
        } else {
            $query = Report::with(['building', 'category', 'reporter', 'assignedTechnician', 'photos'])
                ->whereIn('building_id', $scopedBuildingIds);
        }

        if ($request->filled('building_uuid')) {
            $filterBuildingId = Location::where('uuid', $request->query('building_uuid'))->value('id');
            if ($filterBuildingId) {
                $query->where('building_id', $filterBuildingId);
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        // Filter tab sesuai desain Figma Teknisi: Semua, Tersedia, Tugas Saya
        $tab = $request->query('tab', $request->query('filter'));
        if ($tab === 'available' || $tab === 'tersedia') {
            $query->whereNull('assigned_technician_id')->where('status', 'DITERIMA');
        } elseif ($tab === 'mine' || $tab === 'my_tasks' || $tab === 'saya') {
            $query->where('assigned_technician_id', $user->id);
        }

        // Pencarian tugas atau ruangan sesuai search bar Figma: "⌕ Cari tugas atau ruangan…"
        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $like = $this->likeOperator();
            $query->where(function ($q) use ($search, $like) {
                $q->where('room_name', $like, $search)
                    ->orWhere('title', $like, $search)
                    ->orWhere('description', $like, $search)
                    ->orWhere('ticket_no', $like, $search);
            });
        }

        $sort = $request->query('sort', 'priority');
        if ($sort === 'latest' || $sort === 'terbaru') {
            $query->orderBy('created_at', 'desc');
        } elseif ($sort === 'oldest' || $sort === 'terlama') {
            $query->orderBy('created_at', 'asc');
        } else {
            // Urutan prioritas sesuai US-09, BR-03, BR-11:
            // 1. Laporan yang ditugaskan kepada teknisi ini terlebih dahulu
            // 2. Prioritas URGEN
            // 3. Jumlah like terbanyak (like_count DESC)
            // 4. Waktu masuk paling awal (created_at ASC)
            $query->orderByRaw("CASE WHEN assigned_technician_id = ? THEN 0 ELSE 1 END", [$user->id])
                ->orderByRaw("CASE WHEN priority = 'URGEN' THEN 0 ELSE 1 END")
                ->orderBy('like_count', 'desc')
                ->orderBy('created_at', 'asc');
        }

        $perPage = max(1, (int) $request->query('per_page', 15));
        $tasks = $query->paginate($perPage);

        return $this->getResponse($tasks, 'Daftar tugas berhasil diambil', 200);
    }

    #[OA\Get(
        path: '/reports/detail',
        summary: 'Detail lengkap laporan berdasarkan UUID atau Nomor Tiket',
        tags: ['Report'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'ticket_no', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detail laporan berhasil diambil'),
            new OA\Response(response: 404, description: 'Laporan tidak ditemukan')
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        $uuid = $request->query('uuid');
        $ticketNo = $request->query('ticket_no');

        if (empty($uuid) && empty($ticketNo)) {
            return $this->errorResponse('Query parameter uuid atau ticket_no wajib diisi', 400);
        }

        $query = Report::with([
            'building',
            'category',
            'reporter',
            'assignedTechnician',
            'photos',
            'timelines.actor',
            'timelines.photos',
            'timelines.fromBuilding',
            'timelines.toBuilding',
            'confirmation',
        ]);

        if (!empty($uuid)) {
            $report = $query->where('uuid', $uuid)->first();
        } else {
            $report = $query->where('ticket_no', $ticketNo)->first();
        }

        if (!$report) {
            return $this->errorResponse('Laporan tidak ditemukan', 404);
        }

        $user = $request->user('sanctum');

        // Jika pelapor, filter timeline yang disembunyikan (visible_to_reporter == false)
        if ($user && $user->role === 'PELAPOR') {
            $report->setRelation('timelines', $report->timelines->filter(fn ($t) => $t->visible_to_reporter)->values());
            if ($report->reporter_id !== $user->id && $report->reporter) {
                $report->reporter->full_name = ReportService::maskName($report->reporter->full_name);
            }
        }

        $isLiked = false;
        if ($user) {
            $isLiked = ReportLike::where('report_id', $report->id)
                ->where('user_id', $user->id)
                ->exists();
        }

        $data = $report->toArray();
        $data['is_liked'] = $isLiked;
        $data['is_liked_by_me'] = $isLiked;

        return $this->getResponse($data, 'Detail laporan berhasil diambil', 200);
    }

    #[OA\Post(
        path: '/reports',
        summary: 'Kirim laporan kerusakan baru (Pelapor)',
        tags: ['Report'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['building_uuid', 'room_name', 'category_uuid', 'description', 'photo'],
                    properties: [
                        new OA\Property(property: 'title', type: 'string', example: 'AC tidak dingin', description: 'Nama kerusakan fasilitas'),
                        new OA\Property(property: 'nama_kerusakan', type: 'string', example: 'AC tidak dingin'),
                        new OA\Property(property: 'building_uuid', type: 'string', format: 'uuid', description: 'UUID gedung lokasi'),
                        new OA\Property(property: 'room_name', type: 'string', example: 'Ruang 3.12'),
                        new OA\Property(property: 'floor', type: 'string', example: '3'),
                        new OA\Property(property: 'category_uuid', type: 'string', format: 'uuid', description: 'UUID kategori kerusakan'),
                        new OA\Property(property: 'description', type: 'string', example: 'AC menyala tetapi tidak mengeluarkan udara dingin sejak pagi.'),
                        new OA\Property(property: 'photo', type: 'string', format: 'binary', description: 'Foto bukti kerusakan (JPG/PNG max 5MB)'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Laporan berhasil dibuat'),
            new OA\Response(response: 422, description: 'Validasi gagal')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:150'],
            'nama_kerusakan' => ['nullable', 'string', 'max:150'],
            'building_uuid' => ['required', 'string', 'exists:locations,uuid'],
            'room_name' => ['required', 'string', 'max:100'],
            'floor' => ['nullable', 'string', 'max:10'],
            'category_uuid' => ['required', 'string', 'exists:damage_categories,uuid'],
            'description' => ['required', 'string'],
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'], // Max 5MB
        ]);

        if (!empty($validated['floor'])) {
            $validated['floor'] = trim((string) preg_replace('/[^0-9]/', '', $validated['floor'])) ?: null;
        }

        $photo = $request->file('photo');
        $report = $this->reportService->createReport($user, $validated, $photo);

        return $this->postResponse($report, "Laporan berhasil dibuat dengan nomor tiket {$report->ticket_no}", 201);
    }

    #[OA\Put(
        path: '/reports',
        summary: 'Edit laporan (Hanya pelapor & status masih DITERIMA tanpa penugasan)',
        tags: ['Report'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Laporan berhasil diperbarui'),
            new OA\Response(response: 403, description: 'Tidak berwenang mengedit laporan ini')
        ]
    )]
    public function update(Request $request): JsonResponse
    {
        $uuid = (string) $request->query('uuid');
        if (empty($uuid)) {
            return $this->errorResponse('Query parameter uuid wajib diisi', 400);
        }

        $user = $request->user();
        $report = Report::where('uuid', $uuid)->firstOrFail();

        if ($report->reporter_id !== $user->id) {
            return $this->errorResponse('Anda hanya dapat mengedit laporan milik sendiri', 403);
        }

        if ($report->status !== 'DITERIMA' || $report->assigned_technician_id !== null) {
            return $this->errorResponse('Laporan yang sudah ditugaskan atau diproses tidak dapat diedit', 400);
        }

        $validated = $request->validate([
            'room_name' => ['sometimes', 'string', 'max:100'],
            'floor' => ['nullable', 'string', 'max:10'],
            'description' => ['sometimes', 'string'],
            'category_uuid' => ['sometimes', 'string', 'exists:damage_categories,uuid'],
            'building_uuid' => ['sometimes', 'string', 'exists:locations,uuid'],
        ]);

        if (!empty($validated['building_uuid'])) {
            $validated['building_id'] = Location::where('uuid', $validated['building_uuid'])->value('id');
            unset($validated['building_uuid']);
        }

        if (!empty($validated['category_uuid'])) {
            $validated['category_id'] = DamageCategory::where('uuid', $validated['category_uuid'])->value('id');
            unset($validated['category_uuid']);
        }

        $report->update($validated);

        ReportTimeline::create([
            'report_id' => $report->id,
            'actor_id' => $user->id,
            'event_type' => 'EDITED',
            'note' => 'Pelapor memperbarui data laporan.',
            'visible_to_reporter' => true,
        ]);

        return $this->putResponse($report->fresh()->load(['building', 'category']), 'Laporan berhasil diperbarui', 200);
    }

    #[OA\Delete(
        path: '/reports',
        summary: 'Hapus laporan (Soft delete - hanya pelapor & status masih DITERIMA)',
        tags: ['Report'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Laporan berhasil dihapus')
        ]
    )]
    public function destroy(Request $request): JsonResponse
    {
        $uuid = (string) $request->query('uuid');
        if (empty($uuid)) {
            return $this->errorResponse('Query parameter uuid wajib diisi', 400);
        }

        $user = $request->user();
        $report = Report::where('uuid', $uuid)->firstOrFail();

        if ($report->reporter_id !== $user->id) {
            return $this->errorResponse('Anda hanya dapat menghapus laporan milik sendiri', 403);
        }

        if ($report->status !== 'DITERIMA' || $report->assigned_technician_id !== null) {
            return $this->errorResponse('Laporan yang sudah ditugaskan atau diproses tidak dapat dihapus', 400);
        }

        ReportTimeline::create([
            'report_id' => $report->id,
            'actor_id' => $user->id,
            'event_type' => 'DELETED',
            'note' => 'Laporan dibatalkan/dihapus oleh pelapor.',
            'visible_to_reporter' => true,
        ]);

        $report->delete();

        return $this->deleteResponse('Laporan berhasil dihapus', 204);
    }

    #[OA\Post(
        path: '/reports/like',
        summary: 'Beri / batalkan like pada laporan publik (1 user 1 like)',
        tags: ['Report'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Status like berhasil diperbarui')
        ]
    )]
    public function toggleLike(Request $request): JsonResponse
    {
        $uuid = (string) ($request->query('uuid') ?: $request->input('uuid', $request->input('report_uuid', $request->route('uuid'))));
        if (empty($uuid)) {
            return $this->errorResponse('Parameter uuid wajib diisi', 400);
        }

        $result = $this->reportService->toggleLike($request->user(), $uuid);

        return $this->getResponse($result, $result['liked'] ? 'Like berhasil ditambahkan' : 'Like dibatalkan', 200);
    }

    #[OA\Post(
        path: '/reports/self-assign',
        summary: 'Teknisi mengambil tugas mandiri dari daftar tugas',
        tags: ['Report'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tugas berhasil diambil')
        ]
    )]
    public function selfAssign(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'TEKNISI') {
            return $this->errorResponse('Hanya teknisi yang dapat mengambil tugas', 403);
        }

        $uuid = (string) $request->query('uuid');
        if (empty($uuid)) {
            return $this->errorResponse('Query parameter uuid wajib diisi', 400);
        }

        $report = $this->reportService->selfAssign($user, $uuid);

        return $this->getResponse($report, 'Tugas berhasil diambil dan mulai diproses', 200);
    }

    #[OA\Post(
        path: '/reports/assign',
        summary: 'Kaur / Admin Unit menugaskan laporan ke teknisi tertentu',
        tags: ['Report'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['technician_uuid'],
                properties: [
                    new OA\Property(property: 'technician_uuid', type: 'string', format: 'uuid')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Penugasan berhasil disimpan')
        ]
    )]
    public function assignByKaur(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'ADMIN_UNIT' && $user->role !== 'ADMIN_UNIV') {
            return $this->errorResponse('Hanya Admin Unit yang dapat menugaskan teknisi', 403);
        }

        $uuid = (string) ($request->query('uuid') ?: $request->input('uuid', $request->input('report_uuid')));
        if (empty($uuid)) {
            return $this->errorResponse('Query parameter uuid wajib diisi', 400);
        }

        $request->merge(['report_uuid' => $uuid]);
        $validated = $request->validate([
            'report_uuid' => ['required', 'bail', 'uuid', 'exists:reports,uuid'],
            'technician_uuid' => ['required', 'bail', 'uuid', 'exists:users,uuid'],
        ], [
            'report_uuid.required' => 'UUID laporan wajib diisi',
            'report_uuid.uuid' => 'Format UUID laporan tidak valid',
            'report_uuid.exists' => 'Laporan tidak ditemukan',
            'technician_uuid.required' => 'UUID teknisi wajib diisi',
            'technician_uuid.uuid' => 'Format UUID teknisi tidak valid',
            'technician_uuid.exists' => 'Teknisi tidak ditemukan',
        ]);

        $report = $this->reportService->assignByKaur($user, $uuid, $validated['technician_uuid']);

        return $this->getResponse($report, 'Laporan berhasil ditugaskan ke teknisi', 200);
    }

    #[OA\Post(
        path: '/reports/respond-assignment',
        summary: 'Teknisi merespons penugasan (ACCEPT atau REJECT)',
        tags: ['Report'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['action'],
                properties: [
                    new OA\Property(property: 'action', type: 'string', enum: ['ACCEPT', 'REJECT']),
                    new OA\Property(property: 'reject_reason', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Respon penugasan berhasil disimpan')
        ]
    )]
    public function respondAssignment(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'TEKNISI') {
            return $this->errorResponse('Hanya teknisi penerima tugas yang dapat merespons', 403);
        }

        $uuid = (string) $request->query('uuid');
        if (empty($uuid)) {
            return $this->errorResponse('Query parameter uuid wajib diisi', 400);
        }

        $validated = $request->validate([
            'action' => ['required', 'in:ACCEPT,REJECT'],
            'reject_reason' => ['nullable', 'string'],
        ]);

        $report = $this->reportService->respondAssignment(
            $user,
            $uuid,
            $validated['action'],
            $validated['reject_reason'] ?? null
        );

        $msg = ($validated['action'] === 'ACCEPT')
            ? 'Penugasan diterima, status laporan menjadi DIPROSES'
            : 'Penugasan ditolak dan status kembali DITERIMA';

        return $this->getResponse($report, $msg, 200);
    }

    #[OA\Post(
        path: '/reports/status',
        summary: 'Teknisi memperbarui status penanganan (DIPROSES, MENUNGGU, TIDAK_DAPAT_DIPERBAIKI, SELESAI)',
        description: 'Teknisi memperbarui progres pengerjaan, mencatat kebutuhan suku cadang (MENUNGGU), menandai kerusakan tidak dapat diperbaiki (TIDAK_DAPAT_DIPERBAIKI), atau menyelesaikan perbaikan dengan mengunggah foto bukti (SELESAI).',
        tags: ['Report'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['status'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['DIPROSES', 'MENUNGGU', 'TIDAK_DAPAT_DIPERBAIKI', 'SELESAI'], example: 'SELESAI'),
                        new OA\Property(property: 'note', type: 'string', example: 'Filter dibersihkan dan tekanan refrigeran diperiksa. AC dingin kembali.'),
                        new OA\Property(property: 'estimated_completion_date', type: 'string', format: 'date'),
                        new OA\Property(property: 'is_cannot_be_repaired', type: 'boolean', example: false),
                        new OA\Property(property: 'proof_photo', type: 'string', format: 'binary', description: 'Foto bukti perbaikan (wajib saat status SELESAI)'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Status berhasil diperbarui')
        ]
    )]
    public function updateStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->role, ['TEKNISI', 'ADMIN_UNIT'])) {
            return $this->errorResponse('Hanya teknisi atau admin unit yang dapat memperbarui status', 403);
        }

        $uuid = (string) $request->query('uuid');
        if (empty($uuid)) {
            return $this->errorResponse('Query parameter uuid wajib diisi', 400);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:DIPROSES,MENUNGGU,TIDAK_DAPAT_DIPERBAIKI,SELESAI'],
            'note' => ['nullable', 'string'],
            'estimated_completion_date' => ['nullable', 'date', 'after:now'],
            'is_cannot_be_repaired' => ['nullable', 'boolean'],
            'proof_photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'completion_photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        $proofPhoto = $request->file('proof_photo') ?: ($request->file('completion_photo') ?: $request->file('photo'));
        $report = $this->reportService->updateStatus($user, $uuid, $validated, $proofPhoto);

        return $this->putResponse($report, 'Status laporan berhasil diperbarui', 200);
    }

    #[OA\Post(
        path: '/reports/priority',
        summary: 'Kaur menetapkan prioritas laporan (RENDAH, NORMAL, TINGGI, atau URGEN)',
        tags: ['Report'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['priority'],
                properties: [
                    new OA\Property(property: 'priority', type: 'string', enum: ['RENDAH', 'NORMAL', 'TINGGI', 'URGEN'])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Prioritas berhasil diperbarui')
        ]
    )]
    public function setPriority(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'ADMIN_UNIT' && $user->role !== 'ADMIN_UNIV') {
            return $this->errorResponse('Hanya Admin Unit yang berwenang menetapkan prioritas', 403);
        }

        $uuid = (string) $request->query('uuid');
        if (empty($uuid)) {
            return $this->errorResponse('Query parameter uuid wajib diisi', 400);
        }

        $validated = $request->validate([
            'priority' => ['required', 'in:RENDAH,NORMAL,TINGGI,URGEN'],
        ]);

        $report = Report::where('uuid', $uuid)->firstOrFail();

        if ($user->role === 'ADMIN_UNIT') {
            $hasScope = UserBuildingScope::where('user_id', $user->id)
                ->where('building_id', $report->building_id)
                ->exists();
            if (!$hasScope) {
                return $this->errorResponse('Laporan ini di luar lingkup gedung Anda', 403);
            }
        }

        $report->update(['priority' => $validated['priority']]);

        ReportTimeline::create([
            'report_id' => $report->id,
            'actor_id' => $user->id,
            'event_type' => 'PRIORITY_CHANGED',
            'note' => "Prioritas laporan diubah menjadi {$validated['priority']}.",
            'visible_to_reporter' => true,
        ]);

        return $this->putResponse($report, "Prioritas berhasil diubah menjadi {$validated['priority']}", 200);
    }

    #[OA\Post(
        path: '/reports/major-damage',
        summary: 'Kaur menandai kerusakan besar yang memerlukan anggaran/surat Badan Wakaf',
        tags: ['Report'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['major_damage_note'],
                properties: [
                    new OA\Property(property: 'major_damage_note', type: 'string')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Penanda kerusakan besar berhasil disimpan')
        ]
    )]
    public function markMajorDamage(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'ADMIN_UNIT' && $user->role !== 'ADMIN_UNIV') {
            return $this->errorResponse('Hanya Admin Unit yang dapat menandai kerusakan besar', 403);
        }

        $uuid = (string) $request->query('uuid');
        if (empty($uuid)) {
            return $this->errorResponse('Query parameter uuid wajib diisi', 400);
        }

        $validated = $request->validate([
            'major_damage_note' => ['required', 'string'],
        ]);

        $report = Report::where('uuid', $uuid)->firstOrFail();

        if ($user->role === 'ADMIN_UNIT') {
            $hasScope = UserBuildingScope::where('user_id', $user->id)
                ->where('building_id', $report->building_id)
                ->exists();
            if (!$hasScope) {
                return $this->errorResponse('Laporan ini di luar lingkup gedung Anda', 403);
            }
        }

        $report->update([
            'is_major_damage' => true,
            'major_damage_note' => $validated['major_damage_note'],
        ]);

        ReportTimeline::create([
            'report_id' => $report->id,
            'actor_id' => $user->id,
            'event_type' => 'MAJOR_DAMAGE_MARKED',
            'note' => "Ditandai sebagai Kerusakan Besar: {$validated['major_damage_note']}",
            'visible_to_reporter' => true,
        ]);

        return $this->putResponse($report, 'Laporan ditandai sebagai kerusakan besar', 200);
    }

    #[OA\Post(
        path: '/reports/move-building',
        summary: 'Kaur memindahkan laporan ke gedung lain jika salah lokasi',
        tags: ['Report'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['target_building_uuid'],
                properties: [
                    new OA\Property(property: 'target_building_uuid', type: 'string', format: 'uuid')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Laporan berhasil dipindahkan')
        ]
    )]
    public function moveBuilding(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'ADMIN_UNIT' && $user->role !== 'ADMIN_UNIV') {
            return $this->errorResponse('Hanya Admin Unit yang berwenang memindahkan laporan', 403);
        }

        $uuid = (string) $request->query('uuid');
        if (empty($uuid)) {
            return $this->errorResponse('Query parameter uuid wajib diisi', 400);
        }

        $validated = $request->validate([
            'target_building_uuid' => ['required', 'string', 'exists:locations,uuid'],
        ]);

        $report = Report::where('uuid', $uuid)->firstOrFail();

        if ($user->role === 'ADMIN_UNIT') {
            $hasScope = UserBuildingScope::where('user_id', $user->id)
                ->where('building_id', $report->building_id)
                ->exists();
            if (!$hasScope) {
                return $this->errorResponse('Laporan ini di luar lingkup gedung Anda', 403);
            }
        }
        $targetBuilding = Location::where('uuid', $validated['target_building_uuid'])->firstOrFail();

        $fromBuildingId = $report->building_id;
        $fromBuildingName = $report->building->name;

        $report->update([
            'building_id' => $targetBuilding->id,
            'assigned_technician_id' => null, // Lepas penugasan lama saat pindah gedung
        ]);

        ReportTimeline::create([
            'report_id' => $report->id,
            'actor_id' => $user->id,
            'event_type' => 'BUILDING_MOVED',
            'from_building_id' => $fromBuildingId,
            'to_building_id' => $targetBuilding->id,
            'note' => "Laporan dipindahkan dari {$fromBuildingName} ke {$targetBuilding->name}.",
            'visible_to_reporter' => true,
        ]);

        // Notifikasi ke staf di gedung tujuan
        $scopedUserIds = UserBuildingScope::where('building_id', $targetBuilding->id)->pluck('user_id');
        foreach ($scopedUserIds as $scopedUserId) {
            Notification::create([
                'recipient_id' => $scopedUserId,
                'report_id' => $report->id,
                'type' => 'REPORT_MOVED',
                'message' => "Laporan dipindahkan ke gedung Anda: {$report->ticket_no} ({$targetBuilding->name})",
            ]);
        }

        return $this->putResponse($report->fresh()->load('building'), "Laporan berhasil dipindahkan ke {$targetBuilding->name}", 200);
    }

    #[OA\Post(
        path: '/reports/confirm',
        summary: 'Pelapor mengonfirmasi perbaikan selesai dan memberikan rating (Could)',
        tags: ['Report'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'rating', type: 'integer', minimum: 1, maximum: 5),
                    new OA\Property(property: 'comment', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Konfirmasi berhasil disimpan')
        ]
    )]
    public function confirmCompletion(Request $request): JsonResponse
    {
        $user = $request->user();
        $uuid = (string) $request->query('uuid');
        if (empty($uuid)) {
            return $this->errorResponse('Query parameter uuid wajib diisi', 400);
        }

        $report = Report::where('uuid', $uuid)->firstOrFail();

        if ($report->reporter_id !== $user->id) {
            return $this->errorResponse('Hanya pelapor yang dapat mengonfirmasi penyelesaian laporan', 403);
        }

        if ($report->status !== 'SELESAI') {
            return $this->errorResponse('Hanya laporan yang sudah berstatus SELESAI yang dapat dikonfirmasi', 400);
        }

        $validated = $request->validate([
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string'],
        ]);

        $confirmation = ReportConfirmation::updateOrCreate(
            ['report_id' => $report->id],
            [
                'reporter_id' => $user->id,
                'rating' => $validated['rating'] ?? null,
                'comment' => $validated['comment'] ?? null,
                'confirmed_at' => now(),
            ]
        );

        ReportTimeline::create([
            'report_id' => $report->id,
            'actor_id' => $user->id,
            'event_type' => 'CONFIRMED',
            'note' => "Pelapor mengonfirmasi penyelesaian perbaikan" . (!empty($validated['rating']) ? " (Rating: {$validated['rating']}/5)" : "") . ".",
            'visible_to_reporter' => true,
        ]);

        return $this->postResponse($confirmation, 'Konfirmasi penyelesaian berhasil disimpan', 201);
    }

    #[OA\Post(
        path: '/reports/reopen',
        summary: 'Pelapor membuka kembali laporan jika perbaikan belum beres (Could)',
        tags: ['Report'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['reason'],
                properties: [
                    new OA\Property(property: 'reason', type: 'string', example: 'AC masih bocor air')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Laporan dibuka kembali')
        ]
    )]
    public function reopen(Request $request): JsonResponse
    {
        $user = $request->user();
        $uuid = (string) $request->query('uuid');
        if (empty($uuid)) {
            return $this->errorResponse('Query parameter uuid wajib diisi', 400);
        }

        $report = Report::where('uuid', $uuid)->firstOrFail();

        if ($report->reporter_id !== $user->id) {
            return $this->errorResponse('Hanya pelapor yang dapat membuka kembali laporan ini', 403);
        }

        if ($report->status !== 'SELESAI') {
            return $this->errorResponse('Hanya laporan yang berstatus SELESAI yang dapat dibuka kembali', 400);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string'],
        ]);

        $report->update([
            'status' => 'DIPROSES',
            'resolved_at' => null,
        ]);

        ReportTimeline::create([
            'report_id' => $report->id,
            'actor_id' => $user->id,
            'event_type' => 'REOPENED',
            'from_status' => 'SELESAI',
            'to_status' => 'DIPROSES',
            'note' => "Laporan dibuka kembali oleh pelapor. Alasan: {$validated['reason']}",
            'visible_to_reporter' => true,
        ]);

        if ($report->assigned_technician_id) {
            Notification::create([
                'recipient_id' => $report->assigned_technician_id,
                'report_id' => $report->id,
                'type' => 'REOPENED',
                'message' => "Laporan {$report->ticket_no} dibuka kembali oleh pelapor: {$validated['reason']}",
            ]);
        }

        return $this->putResponse($report, 'Laporan berhasil dibuka kembali untuk ditindaklanjuti', 200);
    }
}
