<?php

namespace App\Http\Controllers\Api;

use App\Models\DamageCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'DamageCategory', description: 'Master data kategori kerusakan')]
class DamageCategoryController extends BaseApiController
{
    #[OA\Get(
        path: '/damage-categories',
        summary: 'Daftar kategori kerusakan aktif',
        tags: ['DamageCategory'],
        responses: [
            new OA\Response(response: 200, description: 'Daftar kategori berhasil diambil')
        ]
    )]
    public function index(): JsonResponse
    {
        $categories = DamageCategory::where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();

        return $this->getResponse($categories, 'Daftar kategori kerusakan berhasil diambil', 200);
    }

    #[OA\Get(
        path: '/damage-categories/detail',
        summary: 'Detail kategori kerusakan berdasarkan UUID',
        tags: ['DamageCategory'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detail kategori berhasil diambil'),
            new OA\Response(response: 404, description: 'Kategori tidak ditemukan')
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        $uuid = (string) $request->query('uuid');
        if (empty($uuid)) {
            return $this->errorResponse('Query parameter uuid wajib diisi', 400);
        }

        $category = DamageCategory::where('uuid', $uuid)->first();
        if (!$category) {
            return $this->errorResponse('Kategori kerusakan tidak ditemukan', 404);
        }

        return $this->getResponse($category, 'Detail kategori kerusakan berhasil diambil', 200);
    }
}
