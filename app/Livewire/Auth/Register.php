<?php

namespace App\Livewire\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class Register extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $tenant_name = '';

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'tenant_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function register(): void
    {
        $this->validate();

        $tenantSlug = \Illuminate\Support\Str::slug($this->tenant_name) . '-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6));
        $tenant = Tenant::create([
            'name' => $this->tenant_name,
            'slug' => $tenantSlug,
            'plan' => 'free',
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'tenant_id' => $tenant->id,
            'role' => 'admin',
            'is_platform_admin' => false,
        ]);

        Auth::login($user);
        request()->session()->regenerate();

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('auth.register')->layout('layouts.guest');
    }
}
