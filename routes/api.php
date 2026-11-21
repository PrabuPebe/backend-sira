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
| API Routes - Sistem Pelaporan Fasilitas Kampus
|--------------------------------------------------------------------------
| Seluruh endpoint spesifik entitas MENGGUNAKAN QUERY PARAM (?uuid=... / ?ticket_no=...)
| TIDAK ADA path parameter {id} atau {uuid} sama sekali!
| ID integer internal database TIDAK BOLEH keluar ke JSON respons!
|--------------------------------------------------------------------------
*/

// ==========================================
// 1. Autentikasi Publik
// ==========================================
Route::prefix('auth')->controller(AuthController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
});

// ==========================================
// 2. Publik Feed & Master Data Baca
// ==========================================
Route::prefix('reports')->controller(ReportController::class)->group(function () {
    Route::get('/public', 'publicFeed'); // Publik feed tanpa login (nama disamarkan)
});

Route::prefix('damage-categories')->controller(DamageCategoryController::class)->group(function () {
    Route::get('/', 'index');
    Route::get('/detail', 'show'); // ?uuid=...
});

Route::prefix('locations')->controller(LocationController::class)->group(function () {
    Route::get('/', 'index');
    Route::get('/detail', 'show'); // ?uuid=...
});

// Facility CRUD (Demo CRUD)
Route::prefix('facilities')->controller(FacilityController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store');
    Route::get('/detail', 'show'); // ?uuid=...
    Route::put('/', 'update');     // ?uuid=...
    Route::delete('/', 'destroy'); // ?uuid=...
});

// ==========================================
// 3. Rute Terotentikasi (auth:sanctum)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {
    // Auth Profil & Manajemen Sesi
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::get('/me', 'me');
        Route::post('/change-password', 'changePassword');
        Route::post('/logout', 'logout');
    });

    // Master Data Lokasi (Khusus Admin kelola)
    Route::prefix('locations')->controller(LocationController::class)->group(function () {
        Route::post('/', 'store');
        Route::put('/', 'update');     // ?uuid=...
        Route::delete('/', 'destroy'); // ?uuid=...
    });

    // Laporan Kerusakan
    Route::prefix('reports')->controller(ReportController::class)->group(function () {
        Route::get('/', 'index'); // Kelola laporan (Admin Unit, Admin Univ)
        Route::get('/technicians', 'technicians'); // List teknisi & beban tugas aktif (penugasan)
        Route::get('/my-reports', 'myReports');
        Route::get('/new-update', 'newUpdates');
        Route::get('/tasks', 'tasks');
        Route::get('/detail', 'show'); // ?uuid=... atau ?ticket_no=...
        Route::post('/', 'store');
        Route::put('/', 'update');     // ?uuid=...
        Route::delete('/', 'destroy'); // ?uuid=...

        // Aksi Pelaporan
        Route::post('/like', 'toggleLike');                         // ?uuid=...
        Route::post('/self-assign', 'selfAssign');                   // ?uuid=...
        Route::post('/assign', 'assignByKaur');                      // ?uuid=...
        Route::post('/respond-assignment', 'respondAssignment');     // ?uuid=...
        Route::post('/status', 'updateStatus');                     // ?uuid=...
        Route::post('/priority', 'setPriority');                     // ?uuid=...
        Route::post('/major-damage', 'markMajorDamage');             // ?uuid=...
        Route::post('/move-building', 'moveBuilding');               // ?uuid=...
        Route::post('/confirm', 'confirmCompletion');               // ?uuid=...
        Route::post('/reopen', 'reopen');                           // ?uuid=...
    });

    // Endpoint alias /api/new-update
    Route::get('/new-update', [ReportController::class, 'newUpdates']);

    // Ekspor Data Laporan (Pronect CSV) & Dashboard
    Route::get('/reports/export', [DashboardController::class, 'export']);
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/statistics', [DashboardController::class, 'statistics']);

    // Notifikasi In-App
    Route::prefix('notifications')->controller(NotificationController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/mark-read', 'markRead'); // ?uuid=... atau ?all=1
    });

    // Manajemen Pengguna & Scope (Admin Universitas)
    Route::prefix('admin/users')->controller(AdminUserController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/detail', 'show');                     // ?uuid=...
        Route::post('/', 'store');
        Route::put('/', 'update');                         // ?uuid=...
        Route::post('/scopes', 'setScopes');               // ?uuid=...
        Route::post('/toggle-status', 'toggleStatus');     // ?uuid=...
    });
});
