<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

// Route fallback untuk menyajikan file publik storage secara aman dan reliable di Railway/Docker
Route::get('/storage/{path}', function (string $path) {
    $disk = Storage::disk('public');
    if (!$disk->exists($path)) {
        abort(404, 'File tidak ditemukan');
    }
    return $disk->response($path);
})->where('path', '.*');
