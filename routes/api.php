<?php

use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DamageCategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FacilityController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PANDUAN RUTE API - SISTEM PELAPORAN FASILITAS KAMPUS (SIRA)
|--------------------------------------------------------------------------
| Catatan Penting untuk Presentasi:
| 1. Seluruh endpoint menggunakan query param (?uuid=... / ?ticket_no=...)
| 2. ID internal angka (1, 2, 3) TIDAK BOLEH bocor ke publik (Zero ID Leakage).
| 3. Semua data publik menggunakan Ordered UUIDv7.
|--------------------------------------------------------------------------
*/

// =========================================================================
// BAGIAN 1: RUTE PUBLIK (BISA DIAKSES SIAPA SAJA TANPA PERLU LOGIN)
// =========================================================================

// --- A. Autentikasi Publik ---
Route::prefix('auth')->controller(AuthController::class)->group(function () {
    // POST /api/auth/register -> Pendaftaran mandiri akun Pelapor (Mahasiswa / Dosen / Staf)
    Route::post('/register', 'register');

    // POST /api/auth/login -> Masuk akun (Semua Peran), menghasilkan Token Sanctum
    Route::post('/login', 'login');
});

// --- B. Feed Laporan Publik ---
Route::prefix('reports')->controller(ReportController::class)->group(function () {
    // GET /api/reports/public -> Melihat daftar laporan fasilitas kampus (Nama pelapor disamarkan otomatis)
    Route::get('/public', 'publicFeed');
});

// --- C. Master Data Baca (Kategori & Gedung) ---
Route::prefix('damage-categories')->controller(DamageCategoryController::class)->group(function () {
    // GET /api/damage-categories -> Melihat daftar kategori kerusakan (AC, Proyektor, Listrik, dll)
    Route::get('/', 'index');

    // GET /api/damage-categories/detail?uuid=... -> Melihat detail satu kategori kerusakan
    Route::get('/detail', 'show');
});

Route::prefix('locations')->controller(LocationController::class)->group(function () {
    // GET /api/locations -> Melihat daftar gedung kampus (FTI, FBE, Rektorat, dll)
    Route::get('/', 'index');

    // GET /api/locations/detail?uuid=... -> Melihat detail gedung / lokasi
    Route::get('/detail', 'show');
});

// --- D. Modul Fasilitas (CRUD Demo Berbasis N-Tier Architecture) ---
Route::prefix('facilities')->controller(FacilityController::class)->group(function () {
    // GET /api/facilities -> Daftar fasilitas
    Route::get('/', 'index');

    // POST /api/facilities -> Tambah fasilitas baru
    Route::post('/', 'store');

    // GET /api/facilities/detail?uuid=... -> Detail fasilitas
    Route::get('/detail', 'show');

    // PUT /api/facilities?uuid=... -> Edit fasilitas
    Route::put('/', 'update');

    // DELETE /api/facilities?uuid=... -> Hapus fasilitas
    Route::delete('/', 'destroy');
});


// =========================================================================
// BAGIAN 2: RUTE TERPROTEKSI (WAJIB LOGIN / MEMBAWA TOKEN SANCTUM)
// =========================================================================
Route::middleware('auth:sanctum')->group(function () {

    // --- A. Manajemen Akun & Sesi Pengguna ---
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        // GET /api/auth/me -> Cek data profil pengguna yang sedang login & daftar gedung binaannya
        Route::get('/me', 'me');

        // POST /api/auth/change-password -> Ubah kata sandi akun
        Route::post('/change-password', 'changePassword');

        // POST /api/auth/logout -> Keluar dari sistem (Menghapus token sesi aktif)
        Route::post('/logout', 'logout');
    });

    // --- B. Kelola Master Gedung (Khusus Admin Universitas) ---
    Route::prefix('locations')->controller(LocationController::class)->group(function () {
        // POST /api/locations -> Tambah gedung baru di kampus
        Route::post('/', 'store');

        // PUT /api/locations?uuid=... -> Update data gedung (nama, fakultas, jumlah lantai)
        Route::put('/', 'update');

        // PATCH /api/locations/status?uuid=... -> Ubah status aktif/nonaktif gedung
        Route::patch('/status', 'toggleStatus');

        // DELETE /api/locations?uuid=... -> Hapus / Nonaktifkan gedung
        Route::delete('/', 'destroy');
    });

    // --- C. Modul Utama Laporan Fasilitas (SIRA) ---
    Route::prefix('reports')->controller(ReportController::class)->group(function () {
        
        // 1. Melihat Daftar Laporan:
        // GET /api/reports -> Kelola seluruh laporan kampus (Untuk Admin Unit & Admin Univ)
        Route::get('/', 'index');

        // GET /api/reports/technicians -> Melihat daftar teknisi & jumlah beban tugas aktifnya
        Route::get('/technicians', 'technicians');

        // GET /api/reports/my-reports -> Melihat daftar laporan yang pernah dibuat oleh pelapor
        Route::get('/my-reports', 'myReports');

        // GET /api/reports/new-update -> Feed update aktivitas laporan terbaru secara real-time
        Route::get('/new-update', 'newUpdates');

        // GET /api/reports/tasks -> Antrean tugas perbaikan khusus untuk teknisi sesuai gedungnya
        Route::get('/tasks', 'tasks');

        // GET /api/reports/detail?uuid=... atau ?ticket_no=... -> Melihat detail lengkap riwayat & foto laporan
        Route::get('/detail', 'show');

        // 2. Transaksi Laporan (Create, Update, Delete):
        // POST /api/reports -> Pelapor membuat laporan kerusakan baru (+ unggah foto kerusakan)
        Route::post('/', 'store');

        // PUT /api/reports?uuid=... -> Mengedit isi laporan (ruangan, lantai, deskripsi)
        Route::put('/', 'update');

        // DELETE /api/reports?uuid=... -> Menghapus / membatalkan laporan
        Route::delete('/', 'destroy');

        // 3. Aksi & Alur Kerja Perbaikan Fasilitas:
        // POST /api/reports/like -> Civitas memberi Like/Dukungan suara agar laporan lebih cepat direspons
        Route::post('/like', 'toggleLike');
        Route::post('/{uuid}/like', 'toggleLike');
        Route::post('/like/{uuid}', 'toggleLike');

        // POST /api/reports/self-assign?uuid=... -> Teknisi mengambil tugas mandiri (Diproteksi lockForUpdate anti-rebutan)
        Route::post('/self-assign', 'selfAssign');

        // POST /api/reports/assign?uuid=... -> Kaur Fakultas menugaskan teknisi tertentu untuk memperbaiki
        Route::post('/assign', 'assignByKaur');

        // POST /api/reports/respond-assignment?uuid=... -> Teknisi menerima (ACCEPT) atau menolak (REJECT) tugas dari Kaur
        Route::post('/respond-assignment', 'respondAssignment');

        // POST /api/reports/status?uuid=... -> Teknisi update status / selesaikan tugas (+ wajib foto bukti selesai)
        Route::post('/status', 'updateStatus');

        // POST /api/reports/priority?uuid=... -> Menetapkan tingkat prioritas (NORMAL atau URGEN)
        Route::post('/priority', 'setPriority');

        // POST /api/reports/major-damage?uuid=... -> Menandai bahwa kerusakan termasuk kategori Rusak Berat
        Route::post('/major-damage', 'markMajorDamage');

        // POST /api/reports/move-building?uuid=... -> Memindahkan tiket ke gedung lain jika pelapor salah pilih gedung
        Route::post('/move-building', 'moveBuilding');

        // POST /api/reports/confirm?uuid=... -> Pelapor konfirmasi perbaikan selesai & beri rating (1-5 bintang)
        Route::post('/confirm', 'confirmCompletion');

        // POST /api/reports/reopen?uuid=... -> Pelapor membuka kembali tiket jika perbaikan ternyata belum beres
        Route::post('/reopen', 'reopen');
    });

    // Endpoint alias untuk feed update terbaru
    Route::get('/new-update', [ReportController::class, 'newUpdates']);

    // --- D. Dashboard Analitik & Ekspor Integrasi Kampus ---
    // GET /api/reports/export -> Ekspor data laporan ke berkas CSV secara streaming untuk integrasi sistem Pronect UII
    Route::get('/reports/export', [DashboardController::class, 'export']);

    // GET /api/dashboard/summary -> Mengambil ringkasan angka untuk kartu widget dashboard (sesuai role)
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

    // GET /api/dashboard/statistics -> Grafik tren harian, persentase tuntas, dan rata-rata jam perbaikan (SLA) untuk Pimpinan
    Route::get('/dashboard/statistics', [DashboardController::class, 'statistics']);

    // --- E. Notifikasi Pengguna (In-App) ---
    Route::prefix('notifications')->controller(NotificationController::class)->group(function () {
        // GET /api/notifications -> Melihat daftar pemberitahuan pengguna yang sedang login
        Route::get('/', 'index');

        // POST /api/notifications/mark-read?uuid=... -> Menandai notifikasi sebagai sudah dibaca
        Route::post('/mark-read', 'markRead');
    });

    // --- F. Pengelolaan Akun Staf (Khusus Admin Universitas) ---
    Route::prefix('admin/users')->controller(AdminUserController::class)->group(function () {
        // GET /api/admin/users -> Melihat daftar seluruh staf kampus (Teknisi, Kaur, Admin)
        Route::get('/', 'index');

        // GET /api/admin/users/detail?uuid=... -> Melihat detail profil akun staf
        Route::get('/detail', 'show');

        // POST /api/admin/users -> Membuat akun staf baru (Teknisi / Kaur)
        Route::post('/', 'store');

        // PUT /api/admin/users?uuid=... -> Mengubah data profil staf
        Route::put('/', 'update');

        // POST /api/admin/users/scopes?uuid=... -> Mengatur penempatan gedung binaan staf (Multi-tenant scope)
        Route::post('/scopes', 'setScopes');

        // POST /api/admin/users/toggle-status?uuid=... -> Mengaktifkan atau menonaktifkan akun staf
        Route::post('/toggle-status', 'toggleStatus');
    });

});
