<?php

namespace App\Http\Controllers\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * API 认证：Sanctum Token 签发/注销。
 */
class AuthController extends ApiController
{
    /**
     * 登录：校验邮箱密码 → 签发 Sanctum Token。
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        if (! Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']])) {
            throw ValidationException::withMessages([
                'email' => [__('api.auth_invalid_credentials')],
            ]);
        }

        $user = Auth::user();

        if (! $user->hasVerifiedEmail()) {
            return $this->error(__('api.auth_email_unverified'), 403);
        }

        if ($user->is_platform_admin) {
            return $this->error(__('api.auth_platform_admin_forbidden'), 403);
        }

        if (! $user->tenant) {
            return $this->error(__('api.auth_no_tenant'), 403);
        }

        // 登录签发的 Token 默认含 read/write 完整权限（与旧版兼容），细粒度 scope 请在设置页创建
        $token = $user->createToken($credentials['device_name'] ?? 'api', ['read', 'write'])->plainTextToken;

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'tenant_id' => $user->tenant_id,
            ],
        ]);
    }

    /**
     * 注册：创建租户 + 管理员用户 → 签发 Sanctum Token。
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'tenant_name' => ['required', 'string', 'max:255', 'unique:tenants,name'],
            'tenant_slug' => ['required', 'string', 'max:100', 'unique:tenants,slug', 'alpha_dash'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        [$tenant, $user] = DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name' => $data['tenant_name'],
                'slug' => $data['tenant_slug'],
                'plan' => 'free',
                'trial_ends_at' => now()->addDays(14),
            ]);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'tenant_id' => $tenant->id,
                'role' => 'admin',
                'is_platform_admin' => false,
                'email_verified_at' => now(), // API 注册视为自动验证（后续可改为强制邮箱验证）
            ]);

            return [$tenant, $user];
        });

        // 注册签发的 Token 默认含 read/write 完整权限
        $token = $user->createToken('api', ['read', 'write'])->plainTextToken;

        return $this->created([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'tenant_id' => $tenant->id,
            ],
        ]);
    }

    /**
     * 注销：删除当前 Token。
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(['message' => __('api.auth_logged_out')]);
    }

    /**
     * 获取当前用户信息。
     */
    public function me(Request $request)
    {
        $user = $request->user()->load('tenant');

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'tenant' => [
                'id' => $user->tenant_id,
                'name' => $user->tenant?->name,
                'plan' => $user->tenant?->plan,
            ],
        ]);
    }
}
