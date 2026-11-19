<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Standard GET Response
     * format:
     * {
     *   "data": [{}],
     *   "msg": "...",
     *   "status": 200
     * }
     */
    public function getResponse(mixed $data, string $msg = 'Data successfully retrieved', int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'msg' => $msg,
            'status' => $status,
        ], $status);
    }

    /**
     * Standard POST Response
     * format:
     * {
     *   "msg": "...",
     *   "status": 201
     * }
     */
    public function postResponse(mixed $data = null, string $msg = 'Data successfully created', int $status = 201): JsonResponse
    {
        $payload = [
            'msg' => $msg,
            'status' => $status,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    /**
     * Standard PUT Response
     * format:
     * {
     *   "msg": "...",
     *   "status": 200
     * }
     */
    public function putResponse(mixed $data = null, string $msg = 'Data successfully updated', int $status = 200): JsonResponse
    {
        $payload = [
            'msg' => $msg,
            'status' => $status,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    /**
     * Standard DELETE Response
     * format:
     * {
     *   "msg": "...",
     *   "status": 204
     * }
     */
    public function deleteResponse(string $msg = 'Data successfully deleted', int $status = 204, int $httpStatus = 200): JsonResponse
    {
        return response()->json([
            'msg' => $msg,
            'status' => $status,
        ], $httpStatus);
    }

    /**
     * Standard Error Response
     */
    public function errorResponse(string $msg = 'An error occurred', int $status = 400, mixed $errors = null): JsonResponse
    {
        $payload = [
            'msg' => $msg,
            'status' => $status,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
