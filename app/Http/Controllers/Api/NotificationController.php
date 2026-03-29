<?php

namespace App\Http\Controllers\Api;

use App\Models\Notification;
use App\Services\Api\UserNotificationApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function __construct(private UserNotificationApiService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $paginator = $this->service->unreadForUser((int) $request->user()->id, $perPage);

        return $this->respondPaginated($paginator);
    }

    public function read(Request $request, Notification $notification): JsonResponse
    {
        $updated = $this->service->markRead((int) $request->user()->id, (int) $notification->id);

        return $this->respond($updated, ['status' => 'updated']);
    }

    public function dismiss(Request $request, Notification $notification): JsonResponse
    {
        $updated = $this->service->dismiss((int) $request->user()->id, (int) $notification->id);

        return $this->respond($updated, ['status' => 'updated']);
    }
}
