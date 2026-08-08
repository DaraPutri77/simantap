<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Throwable;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $filter = (string) $request->query('filter', 'all');

        if (! in_array($filter, ['all', 'unread', 'read'], true)) {
            $filter = 'all';
        }

        $query = $request->user()
            ->notifications()
            ->latest();

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        } elseif ($filter === 'read') {
            $query->whereNotNull('read_at');
        }

        return view('notifications.index', [
            'notifications' => $query->paginate(20)->withQueryString(),
            'filter' => $filter,
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $databaseNotification = $this->ownedNotification($request, $notification);
        $databaseNotification->markAsRead();

        $routeName = data_get($databaseNotification->data, 'route_name');
        $routeParams = data_get($databaseNotification->data, 'route_params', []);

        if (! is_string($routeName) || ! Route::has($routeName)) {
            return redirect()->route('notifications.index');
        }

        if (! is_array($routeParams)) {
            $routeParams = [];
        }

        try {
            return redirect()->route($routeName, $routeParams);
        } catch (Throwable) {
            return redirect()->route('notifications.index');
        }
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $this->ownedNotification($request, $notification)->markAsRead();

        return back()->with('status', 'Notifikasi ditandai sudah dibaca.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return back()->with('status', 'Semua notifikasi telah ditandai sudah dibaca.');
    }

    private function ownedNotification(
        Request $request,
        string $notification,
    ): DatabaseNotification {
        return $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();
    }
}
