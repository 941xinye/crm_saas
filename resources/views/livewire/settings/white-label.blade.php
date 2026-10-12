<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('white_label.title') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('white_label.subtitle') }}</p>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm break-all">{{ session('error') }}</div>
    @endif

    @if($tenant)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-2xl space-y-5">
            <!-- 状态 -->
            <div class="flex items-center justify-between bg-gray-50 rounded-lg p-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700">{{ __('white_label.status_label') }}</h3>
                    <p class="text-xs text-gray-500 mt-1">{{ __('white_label.status_desc') }}</p>
                </div>
                @if($enabled)
                    <span class="px-2.5 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-medium">{{ __('white_label.status_enabled') }}</span>
                @else
                    <span class="px-2.5 py-1 bg-gray-200 text-gray-600 rounded-full text-xs font-medium">{{ __('white_label.status_disabled') }}</span>
                @endif
            </div>

            <form wire:submit="save" class="space-y-5">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('white_label.smtp_host') }}</label>
                        <input wire:model="smtpHost" type="text" placeholder="smtp.example.com"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        @error('smtpHost') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('white_label.smtp_port') }}</label>
                        <input wire:model="smtpPort" type="number" min="1" max="65535"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        @error('smtpPort') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('white_label.smtp_encryption') }}</label>
                        <select wire:model="smtpEncryption"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="tls">TLS (STARTTLS)</option>
                            <option value="ssl">SSL</option>
                            <option value="none">{{ __('white_label.encryption_none') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('white_label.smtp_username') }}</label>
                        <input wire:model="smtpUsername" type="text" autocomplete="off"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        @error('smtpUsername') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('white_label.smtp_password') }}</label>
                        <input wire:model="smtpPassword" type="password" autocomplete="new-password" placeholder="@if($enabled)••••••••@endif"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        @error('smtpPassword') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        <p class="text-xs text-gray-400 mt-1">{{ __('white_label.smtp_password_hint') }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('white_label.from_address') }}</label>
                        <input wire:model="fromAddress" type="email" placeholder="crm@example.com"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        @error('fromAddress') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('white_label.from_name') }}</label>
                        <input wire:model="fromName" type="text" placeholder="{{ $tenant->name }}"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        @error('fromName') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" wire:loading.attr="disabled"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-2.5 rounded-lg">{{ __('white_label.save_btn') }}</button>
                    @if($enabled)
                        <button type="button" wire:click="sendTest" wire:loading.attr="disabled"
                                class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium px-5 py-2.5 rounded-lg transition-colors">
                            {{ __('white_label.test_mail_btn') }}
                        </button>
                        <button type="button" wire:click="disable" wire:confirm="{{ __('white_label.disable_confirm') }}" wire:loading.attr="disabled"
                                class="text-sm font-medium text-red-600 hover:text-red-700">
                            {{ __('white_label.disable_btn') }}
                        </button>
                    @endif
                </div>
                <p class="text-xs text-gray-400">{{ __('white_label.test_mail_hint') }}</p>
            </form>
        </div>
    @else
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 text-yellow-700">
            {{ __('settings_profile.no_tenant') }}
        </div>
    @endif
</div>
