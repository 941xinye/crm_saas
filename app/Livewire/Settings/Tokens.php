<?php

namespace App\Livewire\Settings;

use Livewire\Component;

class Tokens extends Component
{
    public string $name = '';

    /** Token 权限范围（abilities），按需勾选 */
    public array $scopes = ['read', 'write'];

    /** 新创建 Token 的明文，仅创建后一次性展示 */
    public ?string $newToken = null;

    /** 可勾选的 scope 清单 */
    public const SCOPES = [
        'read' => '读取（GET 列表/详情/下载）',
        'write' => '写入（创建/更新/删除/上传）',
    ];

    public function create(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            // scope 必须在白名单内，至少勾选一项
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['in:read,write'],
        ]);

        $token = auth()->user()->createToken($this->name, array_values($this->scopes));
        $this->newToken = $token->plainTextToken;
        $this->name = '';
    }

    public function revoke(int $tokenId): void
    {
        // 只能吊销自己的 Token
        auth()->user()->tokens()->where('id', $tokenId)->delete();
    }

    public function render()
    {
        return view('livewire.settings.tokens', [
            'scopeOptions' => collect(self::SCOPES)->mapWithKeys(
                fn ($label, $scope) => [$scope => __("settings_tokens.scope_{$scope}")]
            )->all(),
            'tokens' => auth()->user()->tokens()
                ->orderByDesc('created_at')
                ->get(['id', 'name', 'abilities', 'last_used_at', 'created_at']),
        ])->layout('layouts.app', ['title' => 'API Tokens']);
    }
}
