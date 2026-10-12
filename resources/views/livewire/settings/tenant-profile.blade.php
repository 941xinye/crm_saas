<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('settings_profile.title') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('settings_profile.subtitle') }}</p>
    </div>

    @if($tenant)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-2xl space-y-5">
            <!-- 只读信息 -->
            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('settings_profile.subscription_info') }}</h3>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">{{ __('settings_profile.plan_label') }}</span>
                        <span class="font-medium text-gray-900">
                            @if($tenant->plan === 'free') <span class="px-2 py-0.5 bg-gray-200 text-gray-700 rounded">{{ __('settings_profile.plan_free') }}</span>
                            @elseif($tenant->plan === 'pro') <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded">{{ __('settings_profile.plan_pro') }}</span>
                            @elseif($tenant->plan === 'enterprise') <span class="px-2 py-0.5 bg-purple-100 text-purple-700 rounded">{{ __('settings_profile.plan_enterprise') }}</span>
                            @else {{ $tenant->plan }} @endif
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-500">{{ __('settings_profile.status_label') }}</span>
                        @if($tenant->is_active) <span class="font-medium text-green-600">{{ __('settings_profile.active') }}</span>
                        @else <span class="font-medium text-red-600">{{ __('settings_profile.frozen') }}</span> @endif
                    </div>
                </div>
            </div>

            <form wire:submit="save" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('settings_profile.tenant_name') }}</label>
                    <input wire:model="name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('settings_profile.language_pref') }}</label>
                    <select wire:model="language" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="zh-CN">简体中文</option>
                        <option value="en-US">English</option>
                        <option value="ja-JP">日本語</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('settings_profile.default_currency') }}</label>
                    <select wire:model="currency" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="CNY">{{ __('settings_profile.currency_cny') }}</option>
                        <option value="USD">{{ __('settings_profile.currency_usd') }}</option>
                        <option value="EUR">{{ __('settings_profile.currency_eur') }}</option>
                        <option value="GBP">{{ __('settings_profile.currency_gbp') }}</option>
                        <option value="JPY">{{ __('settings_profile.currency_jpy') }}</option>
                    </select>
                </div>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-2.5 rounded-lg">{{ __('settings_profile.save_btn') }}</button>
            </form>

            <!-- 公共线索表单（web-to-lead） -->
            <div class="border-t border-gray-100 pt-5 mt-5">
                <h3 class="text-sm font-semibold text-gray-700">{{ __('settings_profile.lead_form_title') }}</h3>
                <p class="text-xs text-gray-500 mt-1 mb-3">{{ __('settings_profile.lead_form_desc') }}</p>
                @if($tenant->lead_form_key)
                    <div class="flex flex-wrap items-center gap-2">
                        <code class="text-xs bg-gray-50 border border-gray-200 rounded px-2 py-1 text-gray-700 break-all">{{ route('web-form.lead', ['key' => $tenant->lead_form_key]) }}</code>
                        <a href="{{ route('web-form.lead', ['key' => $tenant->lead_form_key]) }}" target="_blank" rel="noopener"
                           class="text-xs font-medium text-indigo-600 hover:text-indigo-700">{{ __('settings_profile.lead_form_preview') }}</a>
                        <button wire:click="revokeLeadFormKey" wire:confirm="{{ __('settings_profile.lead_form_revoke_confirm') }}" wire:loading.attr="disabled"
                                class="text-xs font-medium text-red-600 hover:text-red-700">{{ __('settings_profile.lead_form_revoke') }}</button>
                    </div>
                @else
                    <button wire:click="generateLeadFormKey" wire:loading.attr="disabled"
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                        {{ __('settings_profile.lead_form_generate') }}
                    </button>
                @endif
            </div>
        </div>
    @else
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 text-yellow-700">
            {{ __('settings_profile.no_tenant') }}
        </div>
    @endif
</div>
