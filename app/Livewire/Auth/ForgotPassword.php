<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Livewire\Component;

class ForgotPassword extends Component
{
    public string $email = '';

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
        ];
    }

    public function sendResetLink(): void
    {
        $this->validate();

        $status = Password::sendResetLink(['email' => $this->email]);

        if ($status === Password::RESET_LINK_SENT) {
            session()->flash('success', __($status));
            $this->email = '';
        } else {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => __($status),
            ]);
        }
    }

    public function render()
    {
        return view('auth.forgot-password')->layout('layouts.guest');
    }
}
