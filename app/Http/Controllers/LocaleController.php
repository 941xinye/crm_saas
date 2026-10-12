<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

// 界面语言切换（i18n）：保存用户级偏好到 users.locale
class LocaleController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            // 仅允许已提供翻译的 locale
            'locale' => ['required', 'in:zh_CN,en'],
        ]);

        $request->user()->update(['locale' => $validated['locale']]);

        App::setLocale($validated['locale']);

        return back()->with('success', $validated['locale'] === 'en'
            ? 'Language switched to English.'
            : '界面语言已切换为中文。');
    }
}
