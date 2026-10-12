<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ResetPassword extends Component
{
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->token = request()->route('token') ?? '';
        $this->email = request()->input('email', '');
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function resetPassword(): void
    {
        $this->validate();

        $status = Password::reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(\Illuminate\Support\Str::random(60));

                $user->save();
                Auth::login($user);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            session()->flash('success', __($status));
            $this->redirect(route('dashboard'), navigate: true);
        } else {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }

    public function render()
    {
        return view('auth.reset-password')->layout('layouts.guest');
    }
}
