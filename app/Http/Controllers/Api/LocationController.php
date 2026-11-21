<?php

namespace App\Http\Controllers\Api;

use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Location', description: 'Master data gedung dan lokasi')]
class LocationController extends BaseApiController
{
    #[OA\Get(
        path: '/locations',
        summary: 'Daftar lokasi/gedung (Mendukung filter status aktif, tipe, dan pencarian)',
        tags: ['Location'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['BUILDING', 'ROOM'])),
            new OA\Parameter(name: 'is_active', in: 'query', required: false, description: 'Filter status aktif: true, false, atau all', schema: new OA\Schema(type: 'string', default: 'true')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar lokasi berhasil diambil')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Location::query();

        $isActive = $request->query('is_active', 'true');
        if ($isActive === 'true' || $isActive === '1') {
            $query->where('is_active', true);
        } elseif ($isActive === 'false' || $isActive === '0') {
            $query->where('is_active', false);
        } // if 'all', do not apply is_active filter

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $like = $this->likeOperator();
            $query->where(function ($q) use ($search, $like) {
                $q->where('name', $like, $search)
                    ->orWhere('code', $like, $search)
                    ->orWhere('faculty', $like, $search);
            });
        }

        $locations = $query->orderBy('name', 'asc')->get();

        return $this->getResponse($locations, 'Daftar lokasi berhasil diambil', 200);
    }

    #[OA\Get(
        path: '/locations/detail',
        summary: 'Detail lokasi berdasarkan UUID',
        tags: ['Location'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detail lokasi berhasil diambil'),
            new OA\Response(response: 400, description: 'Parameter UUID wajib diisi'),
            new OA\Response(response: 404, description: 'Lokasi tidak ditemukan')
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        $uuid = (string) $request->query('uuid');
        if (empty($uuid)) {
            return $this->errorResponse('Query parameter uuid wajib diisi', 400);
        }

        $location = Location::where('uuid', $uuid)->first();
        if (!$location) {
            return $this->errorResponse('Lokasi tidak ditemukan', 404);
        }

        return $this->getResponse($location, 'Detail lokasi berhasil diambil', 200);
    }

    #[OA\Post(
        path: '/locations',
        summary: 'Tambah gedung baru (Admin Universitas)',
        tags: ['Location'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Gedung Laboratorium Terpadu'),
                    new OA\Property(property: 'code', type: 'string', example: 'GLT'),
                    new OA\Property(property: 'faculty', type: 'string', example: 'Fakultas Teknologi Industri'),
                    new OA\Property(property: 'floor_count', type: 'integer', example: 4),
                    new OA\Property(property: 'type', type: 'string', enum: ['BUILDING', 'ROOM'], default: 'BUILDING'),
                    new OA\Property(property: 'parent_uuid', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Gedung berhasil ditambahkan'),
            new OA\Response(response: 403, description: 'Hanya Admin Universitas yang berwenang')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        if ($request->user()?->role !== 'ADMIN_UNIV') {
            return $this->errorResponse('Hanya Admin Universitas yang dapat mengelola gedung', 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:30'],
            'faculty' => ['nullable', 'string', 'max:100'],
            'floor_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'type' => ['nullable', 'in:BUILDING,ROOM'],
            'parent_uuid' => ['nullable', 'string', 'exists:locations,uuid'],
        ]);

        $parentId = null;
        if (!empty($validated['parent_uuid'])) {
            $parentId = Location::where('uuid', $validated['parent_uuid'])->value('id');
        }

        $location = Location::create([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'faculty' => $validated['faculty'] ?? null,
            'floor_count' => $validated['floor_count'] ?? null,
            'type' => $validated['type'] ?? 'BUILDING',
            'parent_id' => $parentId,
            'is_active' => true,
        ]);

        return $this->postResponse($location, 'Lokasi berhasil dibuat', 201);
    }

    #[OA\Put(
        path: '/locations',
        summary: 'Perbarui gedung (Admin Universitas)',
        tags: ['Location'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Gedung Laboratorium Terpadu'),
                    new OA\Property(property: 'code', type: 'string', example: 'GLT'),
                    new OA\Property(property: 'faculty', type: 'string', example: 'Fakultas Teknologi Industri'),
                    new OA\Property(property: 'floor_count', type: 'integer', example: 4),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Lokasi berhasil diperbarui'),
            new OA\Response(response: 403, description: 'Tidak berwenang'),
            new OA\Response(response: 404, description: 'Lokasi tidak ditemukan')
        ]
    )]
    public function update(Request $request): JsonResponse
    {
        if ($request->user()?->role !== 'ADMIN_UNIV') {
            return $this->errorResponse('Hanya Admin Universitas yang dapat mengelola gedung', 403);
        }

        $uuid = (string) $request->query('uuid');
        if (empty($uuid)) {
            return $this->errorResponse('Query parameter uuid wajib diisi', 400);
        }

        $location = Location::where('uuid', $uuid)->first();
        if (!$location) {
            return $this->errorResponse('Lokasi tidak ditemukan', 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:30'],
            'faculty' => ['nullable', 'string', 'max:100'],
            'floor_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $location->update($validated);

        return $this->putResponse($location, 'Lokasi berhasil diperbarui', 200);
    }

    #[OA\Delete(
        path: '/locations',
        summary: 'Nonaktifkan gedung (Admin Universitas)',
        tags: ['Location'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Gedung dinonaktifkan'),
            new OA\Response(response: 400, description: 'Gedung memiliki laporan aktif, hanya bisa dinonaktifkan')
        ]
    )]
    public function destroy(Request $request): JsonResponse
    {
        if ($request->user()?->role !== 'ADMIN_UNIV') {
            return $this->errorResponse('Hanya Admin Universitas yang dapat mengelola gedung', 403);
        }

        $uuid = (string) $request->query('uuid');
        if (empty($uuid)) {
            return $this->errorResponse('Query parameter uuid wajib diisi', 400);
        }

        $location = Location::where('uuid', $uuid)->first();
        if (!$location) {
            return $this->errorResponse('Lokasi tidak ditemukan', 404);
        }

        // Cek laporan aktif (FR-18 / US-20: jika ada laporan aktif, hanya nonaktifkan)
        $hasActiveReports = $location->reports()->whereIn('status', ['DITERIMA', 'DIPROSES'])->exists();
        if ($hasActiveReports) {
            $location->update(['is_active' => false]);
            return $this->getResponse($location, 'Gedung memiliki laporan aktif sehingga otomatis dinonaktifkan (tidak dihapus)', 200);
        }

        $location->update(['is_active' => false]);
        return $this->deleteResponse('Gedung berhasil dinonaktifkan', 204);
    }
}
