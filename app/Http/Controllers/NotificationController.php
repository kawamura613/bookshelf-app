<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * 通知一覧（PG14）— 認証必須。
     *
     * Blade は $notifications を参照し、各通知の
     * data['timing'] / data['title'] / data['body'] と read_at を表示する。
     * 未読件数はナビゲーション側で unreadNotifications から算出される。
     */
    public function index(): View
    {
        return view('notifications.index', [
            'notifications' => Auth::user()->notifications()->latest()->get(),
        ]);
    }

    /**
     * 通知を既読にする。
     *
     * 他ユーザーの通知は findOrFail により 404 となる。
     */
    public function markAsRead(string $id): RedirectResponse
    {
        Auth::user()->notifications()->findOrFail($id)->markAsRead();

        return redirect()
            ->route('notifications.index')
            ->with('success', '通知を既読にしました。');
    }
}
