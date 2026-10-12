<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class NotificationsCenter extends Component
{
    public function markAsRead(string $notificationId): void
    {
        Auth::user()->notifications()->whereKey($notificationId)->update(['read_at' => now()]);
    }

    public function markAllAsRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
    }

    public function delete(string $notificationId): void
    {
        Auth::user()->notifications()->whereKey($notificationId)->delete();
    }

    public function clearAll(): void
    {
        Auth::user()->notifications()->delete();
    }

    public function render()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return view('livewire.notifications-center', [
            'notifications' => $user->notifications()
                ->orderByDesc('created_at')
                ->limit(100)
                ->get(),
            'unreadCount' => $user->unreadNotifications()->count(),
        ])->layout('layouts.app', ['title' => '通知中心']);
    }
}
