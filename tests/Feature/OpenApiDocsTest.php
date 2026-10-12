<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * OpenAPI 规范与交互式文档页测试：
 * 规范文件有效性与关键端点、/api/openapi.json 服务、/api/docs 页面渲染、
 * 生成命令可重复执行。
 */
class OpenApiDocsTest extends TestCase
{
    use RefreshDatabase;

    private string $specPath = 'public/api/openapi.json';

    protected function tearDown(): void
    {
        // 测试可能重新生成规范，结束后恢复仓库版本
        if (File::exists(base_path($this->specPath))) {
            $this->artisan('api:generate-docs');
        }

        parent::tearDown();
    }

    private function spec(): array
    {
        return json_decode(File::get(base_path($this->specPath)), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_spec_file_is_valid_openapi_with_key_endpoints(): void
    {
        $spec = $this->spec();

        $this->assertSame('3.0.3', $spec['openapi']);
        $this->assertSame('/api/v1', $spec['servers'][0]['url']);
        $this->assertSame('bearerAuth', array_key_first($spec['security'][0]));

        // 关键端点覆盖：认证、CRUD 资源、只读发现、附件下载、Webhook 测试
        foreach ([
            '/login', '/register', '/logout', '/user', '/inbound-email/{tenantSlug}',
            '/companies', '/companies/{id}',
            '/contacts', '/leads', '/opportunities', '/activities', '/webhooks', '/webhooks/{id}/test',
            '/stages', '/users',
            '/attachments', '/attachments/{id}', '/attachments/{id}/download',
        ] as $path) {
            $this->assertArrayHasKey($path, $spec['paths'], "缺少端点: {$path}");
        }

        $this->assertArrayHasKey('bearerAuth', $spec['components']['securitySchemes']);
    }

    public function test_crud_resources_have_create_schema_requirements(): void
    {
        $spec = $this->spec();

        // 创建商机的必填字段（stage_id NOT NULL）
        $this->assertSame(['title', 'stage_id'], $spec['paths']['/opportunities']['post']['requestBody']['content']['application/json']['schema']['required']);
        // 联系人姓名必填
        $this->assertContains('first_name', $spec['paths']['/contacts']['post']['requestBody']['content']['application/json']['schema']['required']);
    }

    public function test_openapi_json_endpoint_serves_spec(): void
    {
        $this->getJson(route('api.openapi'))
            ->assertOk()
            ->assertJsonPath('openapi', '3.0.3')
            ->assertJsonPath('info.version', '1.0.0');
    }

    public function test_docs_page_renders_swagger_ui(): void
    {
        $this->get(route('api.docs'))
            ->assertOk()
            ->assertSee('swagger-ui-dist', false)
            ->assertSee(route('api.openapi'), false);
    }

    public function test_generate_command_regenerates_spec(): void
    {
        File::delete(base_path($this->specPath));
        $this->assertFileDoesNotExist(base_path($this->specPath));

        $this->artisan('api:generate-docs')->assertSuccessful();

        $this->assertFileExists(base_path($this->specPath));
        $this->assertSame('3.0.3', $this->spec()['openapi']);
    }
}
