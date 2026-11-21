<?php

namespace App\Http\Controllers\Api;

use App\Models\Location;
use App\Models\User;
use App\Models\UserBuildingScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin', description: 'Pengelolaan pengguna dan cakupan gedung oleh Admin Universitas')]
class AdminUserController extends BaseApiController
{
    #[OA\Get(
        path: '/admin/users',
        summary: 'Daftar pengguna staf (Admin Universitas)',
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'role', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['TEKNISI', 'ADMIN_UNIT', 'ADMIN_UNIV', 'PELAPOR'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar pengguna berhasil diambil')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        if ($request->user()?->role !== 'ADMIN_UNIV') {
            return $this->errorResponse('Hanya Admin Universitas yang berwenang', 403);
        }

        $query = User::with('buildings');

        if ($request->filled('role')) {
            $query->where('role', $request->query('role'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $like = $this->likeOperator();
            $query->where(function ($q) use ($search, $like) {
                $q->where('full_name', $like, $search)
                    ->orWhere('email', $like, $search);
            });
        }

        $users = $query->orderBy('full_name', 'asc')->paginate(15);

        return $this->getResponse($users, 'Daftar pengguna berhasil diambil', 200);
    }

    #[OA\Post(
        path: '/admin/users',
        summary: 'Tambah akun staf baru (Teknisi, Admin Unit, Admin Univ)',
        tags: ['Admin'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['full_name', 'email', 'password', 'role'],
                properties: [
                    new OA\Property(property: 'full_name', type: 'string', example: 'Joko Widodo'),
                    new OA\Property(property: 'email', type: 'string', example: 'joko@uii.ac.id'),
                    new OA\Property(property: 'password', type: 'string', example: 'password123'),
                    new OA\Property(property: 'role', type: 'string', enum: ['TEKNISI', 'ADMIN_UNIT', 'ADMIN_UNIV']),
                    new OA\Property(property: 'building_uuids', type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Akun staf berhasil dibuat')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $creator = $request->user();
        if ($creator?->role !== 'ADMIN_UNIV') {
            return $this->errorResponse('Hanya Admin Universitas yang berwenang membuat akun staf', 403);
        }

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'in:TEKNISI,ADMIN_UNIT,ADMIN_UNIV'],
            'building_uuids' => ['nullable', 'array'],
            'building_uuids.*' => ['string', 'exists:locations,uuid'],
        ]);

        return DB::transaction(function () use ($creator, $validated) {
            $user = User::create([
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'reporter_type' => null, // NULL untuk staf non-pelapor (FR-20)
                'must_change_password' => true, // Wajib ganti password saat login pertama
                'is_active' => true,
            ]);

            if (!empty($validated['building_uuids'])) {
                $buildingIds = Location::whereIn('uuid', $validated['building_uuids'])->pluck('id');
                $records = $buildingIds->map(fn ($buildingId) => [
                    'user_id' => $user->id,
                    'building_id' => $buildingId,
                    'granted_by' => $creator->id,
                    'created_at' => now(),
                ])->toArray();

                DB::table('user_building_scopes')->insertOrIgnore($records);
            }

            return $this->postResponse($user->load('buildings'), 'Akun staf berhasil dibuat', 201);
        });
    }

    #[OA\Post(
        path: '/admin/users/scopes',
        summary: 'Atur cakupan gedung untuk teknisi atau admin unit',
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['building_uuids'],
                properties: [
                    new OA\Property(property: 'building_uuids', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'))
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cakupan gedung berhasil diperbarui')
        ]
    )]
    public function setScopes(Request $request): JsonResponse
    {
        $creator = $request->user();
        if ($creator?->role !== 'ADMIN_UNIV') {
            return $this->errorResponse('Hanya Admin Universitas yang berwenang mengatur cakupan gedung', 403);
        }

        $uuid = (string) $request->query('uuid');
        if (empty($uuid)) {
            return $this->errorResponse('Query parameter uuid wajib diisi', 400);
        }

        $user = User::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'building_uuids' => ['present', 'array'],
            'building_uuids.*' => ['string', 'exists:locations,uuid'],
        ]);

        return DB::transaction(function () use ($creator, $user, $validated) {
            DB::table('user_building_scopes')->where('user_id', $user->id)->delete();

            if (!empty($validated['building_uuids'])) {
                $buildingIds = Location::whereIn('uuid', $validated['building_uuids'])->pluck('id');
                $records = $buildingIds->map(fn ($buildingId) => [
                    'user_id' => $user->id,
                    'building_id' => $buildingId,
                    'granted_by' => $creator->id,
                    'created_at' => now(),
                ])->toArray();

                DB::table('user_building_scopes')->insertOrIgnore($records);
            }

            return $this->putResponse($user->fresh()->load('buildings'), 'Cakupan gedung berhasil diperbarui', 200);
        });
    }

    #[OA\Post(
        path: '/admin/users/toggle-status',
        summary: 'Aktifkan atau nonaktifkan akun pengguna',
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Status akun berhasil diubah')
        ]
    )]
    public function toggleStatus(Request $request): JsonResponse
    {
        if ($request->user()?->role !== 'ADMIN_UNIV') {
            return $this->errorResponse('Hanya Admin Universitas yang berwenang', 403);
        }

        $uuid = (string) $request->query('uuid');
        if (empty($uuid)) {
            return $this->errorResponse('Query parameter uuid wajib diisi', 400);
        }

        $user = User::where('uuid', $uuid)->firstOrFail();
        $user->update(['is_active' => !$user->is_active]);

        $statusStr = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return $this->putResponse($user, "Akun berhasil {$statusStr}", 200);
    }

    #[OA\Get(
        path: '/admin/users/detail',
        summary: 'Detail akun pengguna staf (Admin Universitas)',
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detail pengguna berhasil diambil'),
            new OA\Response(response: 404, description: 'Pengguna tidak ditemukan')
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        if ($request->user()?->role !== 'ADMIN_UNIV') {
            return $this->errorResponse('Hanya Admin Universitas yang berwenang', 403);
        }

        $uuid = (string) $request->query('uuid');
        if (empty($uuid)) {
            return $this->errorResponse('Query parameter uuid wajib diisi', 400);
        }

        $user = User::with('buildings')->where('uuid', $uuid)->first();
        if (!$user) {
            return $this->errorResponse('Pengguna tidak ditemukan', 404);
        }

        return $this->getResponse($user, 'Detail pengguna berhasil diambil', 200);
    }

    #[OA\Put(
        path: '/admin/users',
        summary: 'Perbarui akun pengguna staf dan cakupan gedung (Admin Universitas)',
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'full_name', type: 'string', example: 'Rina Handayani'),
                    new OA\Property(property: 'email', type: 'string', example: 'rina.handayani@uii.ac.id'),
                    new OA\Property(property: 'password', type: 'string', nullable: true),
                    new OA\Property(property: 'role', type: 'string', enum: ['TEKNISI', 'ADMIN_UNIT', 'ADMIN_UNIV', 'PELAPOR']),
                    new OA\Property(property: 'building_uuids', type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Pengguna berhasil diperbarui')
        ]
    )]
    public function update(Request $request): JsonResponse
    {
        $creator = $request->user();
        if ($creator?->role !== 'ADMIN_UNIV') {
            return $this->errorResponse('Hanya Admin Universitas yang berwenang memperbarui akun staf', 403);
        }

        $uuid = (string) $request->query('uuid');
        if (empty($uuid)) {
            return $this->errorResponse('Query parameter uuid wajib diisi', 400);
        }

        $user = User::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'email', 'max:150', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['sometimes', 'in:TEKNISI,ADMIN_UNIT,ADMIN_UNIV,PELAPOR'],
            'building_uuids' => ['nullable', 'array'],
            'building_uuids.*' => ['string', 'exists:locations,uuid'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return DB::transaction(function () use ($creator, $user, $validated) {
            $updateData = [];
            if (isset($validated['full_name'])) {
                $updateData['full_name'] = $validated['full_name'];
            }
            if (isset($validated['email'])) {
                $updateData['email'] = $validated['email'];
            }
            if (isset($validated['role'])) {
                $updateData['role'] = $validated['role'];
            }
            if (isset($validated['is_active'])) {
                $updateData['is_active'] = $validated['is_active'];
            }
            if (!empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }

            if (!empty($updateData)) {
                $user->update($updateData);
            }

            if (array_key_exists('building_uuids', $validated)) {
                DB::table('user_building_scopes')->where('user_id', $user->id)->delete();
                if (!empty($validated['building_uuids'])) {
                    $buildingIds = Location::whereIn('uuid', $validated['building_uuids'])->pluck('id');
                    $records = $buildingIds->map(fn ($buildingId) => [
                        'user_id' => $user->id,
                        'building_id' => $buildingId,
                        'granted_by' => $creator->id,
                        'created_at' => now(),
                    ])->toArray();
                    DB::table('user_building_scopes')->insertOrIgnore($records);
                }
            }

            return $this->putResponse($user->fresh()->load('buildings'), 'Akun staf berhasil diperbarui', 200);
        });
    }
}
