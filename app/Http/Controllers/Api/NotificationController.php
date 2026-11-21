<?php

namespace App\Http\Controllers\Api;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Notifications', description: 'Notifikasi in-app untuk pengguna')]
class NotificationController extends BaseApiController
{
    #[OA\Get(
        path: '/notifications',
        summary: 'Daftar notifikasi pengguna saat ini',
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'is_read', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar notifikasi berhasil diambil'),
            new OA\Response(response: 401, description: 'Tidak terotentikasi')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        $query = Notification::where('recipient_id', $user->id)
            ->with(['report' => function ($q) {
                $q->select('id', 'uuid', 'ticket_no', 'room_name', 'description', 'status');
            }])
            ->orderBy('created_at', 'desc');

        if ($request->has('is_read')) {
            $isRead = filter_var($request->query('is_read'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_read', $isRead);
        }

        $perPage = min((int) $request->query('per_page', 15), 50);
        $paginator = $query->paginate($perPage);

        $unreadCount = Notification::where('recipient_id', $user->id)
            ->where('is_read', false)
            ->count();

        $result = $paginator->toArray();
        $result['unread_count'] = $unreadCount;

        return $this->getResponse($result, 'Daftar notifikasi berhasil diambil', 200);
    }

    #[OA\Post(
        path: '/notifications/mark-read',
        summary: 'Tandai notifikasi sebagai sudah dibaca',
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'query', required: false, description: 'UUID spesifik notifikasi'),
            new OA\Parameter(name: 'all', in: 'query', required: false, description: 'Set ke 1 untuk tandai semua notifikasi sudah dibaca'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Notifikasi berhasil diperbarui'),
            new OA\Response(response: 404, description: 'Notifikasi tidak ditemukan')
        ]
    )]
    public function markRead(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        if ($request->query('all') === '1' || $request->query('all') === 'true') {
            Notification::where('recipient_id', $user->id)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);

            return $this->putResponse(null, 'Semua notifikasi ditandai sudah dibaca');
        }

        $uuid = $request->query('uuid');
        if (!$uuid) {
            return $this->errorResponse('Parameter uuid atau all=1 wajib diisi', 422);
        }

        $notification = Notification::where('uuid', $uuid)
            ->where('recipient_id', $user->id)
            ->first();

        if (!$notification) {
            return $this->notFoundResponse('Notifikasi tidak ditemukan');
        }

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return $this->putResponse($notification, 'Notifikasi berhasil ditandai sudah dibaca');
    }
}
