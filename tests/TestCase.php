<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 测试断言依赖中文文案（zh_CN 与原文逐字一致，见 memory i18n 约定）。
        // 在 app 启动后强制 translator locale，防止 .env / CI 环境的 APP_LOCALE
        // 漂移造成大面积断言失败（phpunit.xml 的 env force 对 Laravel 不生效）。
        $this->app->setLocale('zh_CN');
    }
}
