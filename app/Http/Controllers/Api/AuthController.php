<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Auth', description: 'Authentication and profile management')]
class AuthController extends BaseApiController
{
    #[OA\Post(
        path: '/auth/register',
        summary: 'Pendaftaran mandiri akun Pelapor (Mahasiswa, Dosen, Staf)',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['full_name', 'email', 'password', 'reporter_type'],
                properties: [
                    new OA\Property(property: 'full_name', type: 'string', example: 'Ahmad Fauzi'),
                    new OA\Property(property: 'email', type: 'string', example: 'ahmad@uii.ac.id'),
                    new OA\Property(property: 'password', type: 'string', example: 'secret123'),
                    new OA\Property(property: 'reporter_type', type: 'string', enum: ['MAHASISWA', 'DOSEN', 'STAF'], example: 'MAHASISWA'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Registrasi berhasil'),
            new OA\Response(response: 422, description: 'Validasi gagal')
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'PELAPOR', // Otomatis role PELAPOR di server-side (FR-02)
            'reporter_type' => $validated['reporter_type'],
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->postResponse([
            'user' => $user,
            'token' => $token,
        ], 'Pendaftaran akun pelapor berhasil', 201);
    }

    #[OA\Post(
        path: '/auth/login',
        summary: 'Masuk akun (Semua peran)',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'ahmad@uii.ac.id'),
                    new OA\Property(property: 'password', type: 'string', example: 'secret123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login berhasil'),
            new OA\Response(response: 401, description: 'Kredensial tidak valid'),
            new OA\Response(response: 403, description: 'Akun dinonaktifkan')
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return $this->errorResponse('Email atau password salah', 401);
        }

        if (!$user->is_active) {
            return $this->errorResponse('Akun Anda telah dinonaktifkan. Silakan hubungi administrator.', 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->getResponse([
            'user' => $user->load('buildings'),
            'token' => $token,
        ], 'Login berhasil', 200);
    }

    #[OA\Get(
        path: '/auth/me',
        summary: 'Ambil profil pengguna yang sedang login',
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Profil berhasil diambil'),
            new OA\Response(response: 401, description: 'Tidak terotentikasi')
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        return $this->getResponse([
            'user' => $user->load('buildings'),
        ], 'Profil berhasil diambil', 200);
    }

    #[OA\Post(
        path: '/auth/logout',
        summary: 'Keluar akun dan batalkan access token',
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Logout berhasil'),
            new OA\Response(response: 401, description: 'Tidak terotentikasi')
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return $this->postResponse(null, 'Logout berhasil', 200);
    }

    #[OA\Post(
        path: '/auth/change-password',
        summary: 'Ganti password (Wajib saat login pertama untuk staf)',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'new_password'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string'),
                    new OA\Property(property: 'new_password', type: 'string', minLength: 6),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password berhasil diubah'),
            new OA\Response(response: 400, description: 'Password saat ini salah'),
            new OA\Response(response: 401, description: 'Tidak terotentikasi')
        ]
    )]
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return $this->errorResponse('Password saat ini salah', 400);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
            'must_change_password' => false,
        ]);

        return $this->putResponse(null, 'Password berhasil diperbarui', 200);
    }
}
