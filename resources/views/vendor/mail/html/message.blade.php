{{-- 邮件模板品牌化（Phase 10）：覆盖 Laravel 默认 message 视图，
     品牌名优先读 mail.from.name（白标 SMTP 激活时由 TenantMailer 切换为租户品牌），
     未配置时回落平台 app.name。其余结构与框架原版保持一致。 --}}
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('mail.from.name') ?: config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('mail.from.name') ?: config('app.name') }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
