<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class NotificationWebController extends Controller
{
    public function index(Request $request): View
    {
        $selectedType = $request->string('type')->toString() ?: null;

        $notifications = Notification::query()
            ->where('user_id', $request->user()->id)
            ->when($selectedType !== null, fn ($q) => $q->where('type', $selectedType))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('notifications.index', compact('notifications', 'selectedType'));
    }
}
