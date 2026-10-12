<?php

namespace App\Livewire\Auth;

use Livewire\Component;

/**
 * 邮箱验证提示页：告知用户查收验证邮件，并提供"重新发送"按钮。
 */
class VerifyEmail extends Component
{
    /**
     * 重新发送邮箱验证通知。
     */
    public function resend(): void
    {
        $user = request()->user();

        if ($user && $user->hasVerifiedEmail()) {
            $this->redirect(route('dashboard'), navigate: true);
            return;
        }

        if ($user) {
            $user->sendEmailVerificationNotification();
        }

        session()->flash('success', __('auth.verification_resent'));
    }

    public function render()
    {
        return view('auth.verify-email')->layout('layouts.guest');
    }
}
