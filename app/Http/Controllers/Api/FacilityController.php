<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Facility\StoreFacilityRequest;
use App\Http\Requests\Facility\UpdateFacilityRequest;
use App\Services\Contracts\FacilityServiceInterface;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Facility', description: 'API Endpoints for Facility')]
class FacilityController extends BaseApiController
{
    protected FacilityServiceInterface $service;

    public function __construct(FacilityServiceInterface $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/facilities',
        summary: 'Get all facilities',
        tags: ['Facility'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Facilities retrieved successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponseList')
            )
        ]
    )]
    public function index(): JsonResponse
    {
        $data = $this->service->getAll();

        return $this->getResponse($data, 'Facilities retrieved successfully', 200);
    }

    #[OA\Post(
        path: '/facilities',
        summary: 'Create a new facility',
        tags: ['Facility'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Example Name')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Facility created successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponsePost')
            )
        ]
    )]
    public function store(StoreFacilityRequest $request): JsonResponse
    {
        $this->service->create($request->validated());

        return $this->postResponse(null, 'Facility created successfully', 201);
    }

    #[OA\Get(
        path: '/facilities/{uuid}',
        summary: 'Get facility by UUID',
        tags: ['Facility'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                description: 'Facility UUID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Facility retrieved successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponseItem')
            ),
            new OA\Response(response: 404, description: 'Facility not found')
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        $data = $this->service->getByUuid($uuid);

        return $this->getResponse($data, 'Facility retrieved successfully', 200);
    }

    #[OA\Put(
        path: '/facilities/{uuid}',
        summary: 'Update facility by UUID',
        tags: ['Facility'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                description: 'Facility UUID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Updated Name')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Facility updated successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponsePut')
            ),
            new OA\Response(response: 404, description: 'Facility not found')
        ]
    )]
    public function update(UpdateFacilityRequest $request, string $uuid): JsonResponse
    {
        $this->service->update($uuid, $request->validated());

        return $this->putResponse(null, 'Facility updated successfully', 200);
    }

    #[OA\Delete(
        path: '/facilities/{uuid}',
        summary: 'Delete facility by UUID',
        tags: ['Facility'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                description: 'Facility UUID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Facility deleted successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponseDelete')
            ),
            new OA\Response(response: 404, description: 'Facility not found')
        ]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        $this->service->delete($uuid);

        return $this->deleteResponse('Facility deleted successfully', 204);
    }
}
