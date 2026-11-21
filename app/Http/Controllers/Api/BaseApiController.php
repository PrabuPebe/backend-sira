<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: 'Dokumentasi RESTful API Laravel dengan N-Tier Architecture (Controller -> Service -> Repository -> Model).',
    title: 'Laravel N-Tier API Documentation'
)]
#[OA\Server(
    url: '/api',
    description: 'Local API Server'
)]
#[OA\Schema(
    schema: 'ApiResponseList',
    type: 'object',
    properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
        new OA\Property(property: 'msg', type: 'string', example: 'Data successfully retrieved'),
        new OA\Property(property: 'status', type: 'integer', example: 200),
    ]
)]
#[OA\Schema(
    schema: 'ApiResponseItem',
    type: 'object',
    properties: [
        new OA\Property(property: 'data', type: 'object'),
        new OA\Property(property: 'msg', type: 'string', example: 'Data successfully retrieved'),
        new OA\Property(property: 'status', type: 'integer', example: 200),
    ]
)]
#[OA\Schema(
    schema: 'ApiResponsePost',
    type: 'object',
    properties: [
        new OA\Property(property: 'msg', type: 'string', example: 'Data successfully created'),
        new OA\Property(property: 'status', type: 'integer', example: 201),
        new OA\Property(property: 'data', type: 'object', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'ApiResponsePut',
    type: 'object',
    properties: [
        new OA\Property(property: 'msg', type: 'string', example: 'Data successfully updated'),
        new OA\Property(property: 'status', type: 'integer', example: 200),
        new OA\Property(property: 'data', type: 'object', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'ApiResponseDelete',
    type: 'object',
    properties: [
        new OA\Property(property: 'msg', type: 'string', example: 'Data successfully deleted'),
        new OA\Property(property: 'status', type: 'integer', example: 204),
    ]
)]
abstract class BaseApiController extends Controller
{
    use ApiResponse;

    protected function likeOperator(): string
    {
        return \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}
