<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * 生成 OpenAPI 3.0 规范（public/api/openapi.json），供交互式文档页
 * （/api/docs，Swagger UI）与外部集成方使用。重新生成：php artisan api:generate-docs。
 *
 * 端点与 routes/api.php 保持同步；认证方式为 Sanctum Bearer Token
 * （登录端点签发，读操作需 read ability、写操作需 write ability）。
 */
class GenerateOpenApiSpec extends Command
{
    protected $signature = 'api:generate-docs {--output=public/api/openapi.json}';

    protected $description = '生成 REST API v1 的 OpenAPI 3.0 规范文件';

    /**
     * 资源 → 字段类型映射（与模型 fillable/casts 同步）。
     */
    private const RESOURCES = [
        'companies' => [
            'tag' => '公司',
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
                'industry' => ['type' => 'string'],
                'website' => ['type' => 'string'],
                'phone' => ['type' => 'string'],
                'address' => ['type' => 'string'],
                'owner_id' => ['type' => 'integer'],
                'custom_fields' => ['type' => 'object'],
            ],
        ],
        'contacts' => [
            'tag' => '联系人',
            'fields' => [
                'company_id' => ['type' => 'integer'],
                'first_name' => ['type' => 'string', 'required' => true],
                'last_name' => ['type' => 'string', 'required' => true],
                'email' => ['type' => 'string', 'format' => 'email'],
                'phone' => ['type' => 'string'],
                'position' => ['type' => 'string'],
                'custom_fields' => ['type' => 'object'],
            ],
        ],
        'leads' => [
            'tag' => '线索',
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
                'company_name' => ['type' => 'string'],
                'email' => ['type' => 'string', 'format' => 'email'],
                'phone' => ['type' => 'string'],
                'source' => ['type' => 'string'],
                'status' => ['type' => 'string', 'enum' => ['new', 'contacted', 'qualified', 'unqualified', 'converted']],
                'owner_id' => ['type' => 'integer'],
            ],
        ],
        'opportunities' => [
            'tag' => '商机',
            'fields' => [
                'title' => ['type' => 'string', 'required' => true],
                'contact_id' => ['type' => 'integer'],
                'company_id' => ['type' => 'integer'],
                'stage_id' => ['type' => 'integer', 'required' => true],
                'owner_id' => ['type' => 'integer'],
                'value' => ['type' => 'number', 'format' => 'decimal'],
                'expected_close_date' => ['type' => 'string', 'format' => 'date'],
                'probability' => ['type' => 'integer'],
                'custom_fields' => ['type' => 'object'],
            ],
        ],
        'activities' => [
            'tag' => '活动',
            'fields' => [
                'activitable_type' => ['type' => 'string', 'required' => true, 'description' => '多态类型（如 company / contact / opportunity 的完整类名或别名）'],
                'activitable_id' => ['type' => 'integer', 'required' => true],
                'type' => ['type' => 'string', 'required' => true],
                'subject' => ['type' => 'string', 'required' => true],
                'scheduled_at' => ['type' => 'string', 'format' => 'date-time'],
                'completed_at' => ['type' => 'string', 'format' => 'date-time'],
                'notes' => ['type' => 'string'],
            ],
        ],
        'webhooks' => [
            'tag' => 'Webhook 配置',
            'enterprise' => true,
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
                'url' => ['type' => 'string', 'format' => 'uri', 'required' => true],
                'events' => ['type' => 'array', 'items' => ['type' => 'string'], 'required' => true],
                'is_active' => ['type' => 'boolean'],
            ],
        ],
    ];

    public function handle(): int
    {
        $spec = $this->build();
        $output = base_path($this->option('output'));

        File::ensureDirectoryExists(dirname($output));
        File::put($output, json_encode($spec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");

        $this->info("OpenAPI spec generated: {$output}");

        return self::SUCCESS;
    }

    private function build(): array
    {
        $paths = [];
        $schemas = [];

        // 认证端点（无需 Bearer）
        $paths['/login'] = $this->authEndpoint('login');
        $paths['/register'] = $this->authEndpoint('register');
        $paths['/logout'] = $this->simplePost('退出登录并撤销当前 Token', '认证');
        $paths['/user'] = $this->meEndpoint();
        $paths['/inbound-email/{tenantSlug}'] = $this->inboundEmailEndpoint();

        // 标准 CRUD 资源
        foreach (self::RESOURCES as $resource => $definition) {
            $schemas[$resource] = $this->resourceSchema($definition);
            $tag = $definition['tag'];

            $paths["/{$resource}"] = [
                'get' => $this->listOperation($resource, $tag, $definition),
                'post' => $this->createOperation($resource, $tag, $definition),
            ];
            $paths["/{$resource}/{id}"] = [
                'get' => $this->showOperation($resource, $tag),
                'put' => $this->updateOperation($resource, $tag, $definition),
                'delete' => $this->destroyOperation($resource, $tag, $definition['enterprise'] ?? false),
            ];
        }

        // Webhook 测试端点
        $paths['/webhooks/{id}/test'] = [
            'post' => array_merge($this->operation('触发一次测试推送（旁路执行，不影响失败计数）', 'Webhook 配置'), [
                'parameters' => [$this->idParameter()],
                'responses' => [
                    '200' => ['$ref' => '#/components/responses/Success'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ]),
        ];

        // 只读发现端点
        $schemas['stage'] = $this->resourceSchema([
            'fields' => [
                'name' => ['type' => 'string'],
                'order' => ['type' => 'integer'],
                'probability' => ['type' => 'integer'],
                'is_default' => ['type' => 'boolean'],
                'is_won' => ['type' => 'boolean'],
                'is_lost' => ['type' => 'boolean'],
            ],
        ]);
        $paths['/stages'] = ['get' => $this->listOperation('stages', '阶段', ['fields' => []])];

        $schemas['user'] = $this->resourceSchema([
            'fields' => [
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string', 'format' => 'email'],
                'role' => ['type' => 'string', 'enum' => ['admin', 'manager', 'sales']],
            ],
        ]);
        $paths['/users'] = ['get' => $this->listOperation('users', '成员', ['fields' => []])];

        // 附件
        $schemas['attachment'] = $this->resourceSchema([
            'fields' => [
                'attachable_type' => ['type' => 'string', 'required' => true],
                'attachable_id' => ['type' => 'integer', 'required' => true],
                'original_name' => ['type' => 'string'],
                'mime_type' => ['type' => 'string'],
                'size' => ['type' => 'integer'],
            ],
        ]);
        $paths['/attachments'] = [
            'get' => $this->listOperation('attachments', '附件', ['fields' => []]),
            'post' => $this->attachmentUploadOperation(),
        ];
        $paths['/attachments/{id}'] = ['get' => $this->showOperation('attachments', '附件')];
        $paths['/attachments/{id}/download'] = [
            'get' => array_merge($this->operation('下载附件文件流（私有磁盘，经认证与租户归属校验）', '附件'), [
                'parameters' => [$this->idParameter()],
                'responses' => [
                    '200' => ['description' => '文件二进制流', 'content' => ['application/octet-stream' => ['schema' => ['type' => 'string', 'format' => 'binary']]]],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ]),
        ];

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => config('app.name') . ' REST API',
                'description' => '多租户 CRM 开放 API（v1）。'
                    . '认证使用 Sanctum Bearer Token（`/login` 或 `/register` 签发）；'
                    . 'Token 需具备能力：读操作 `read`、写操作 `write`。'
                    . '所有数据自动限定在 Token 所属租户内，跨租户资源返回 404。'
                    . '规范由 `php artisan api:generate-docs` 生成，交互式文档见 `/api/docs`。',
                'version' => '1.0.0',
            ],
            'servers' => [['url' => '/api/v1']],
            'security' => [['bearerAuth' => []]],
            'tags' => [
                ['name' => '认证'],
                ['name' => '公司'],
                ['name' => '联系人'],
                ['name' => '线索'],
                ['name' => '商机'],
                ['name' => '活动'],
                ['name' => '阶段'],
                ['name' => '成员'],
                ['name' => '附件'],
                ['name' => 'Webhook 配置'],
                ['name' => '邮件转线索'],
            ],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'Sanctum Token'],
                ],
                'schemas' => [
                    ...$schemas,
                    'PaginationMeta' => [
                        'type' => 'object',
                        'properties' => [
                            'total' => ['type' => 'integer'],
                            'per_page' => ['type' => 'integer'],
                            'current_page' => ['type' => 'integer'],
                            'last_page' => ['type' => 'integer'],
                        ],
                    ],
                    'Error' => [
                        'type' => 'object',
                        'properties' => [
                            'status' => ['type' => 'string', 'enum' => ['error']],
                            'message' => ['type' => 'string'],
                            'errors' => ['type' => 'object', 'description' => '字段级校验错误（仅 422）'],
                        ],
                    ],
                ],
                'responses' => [
                    'Success' => [
                        'description' => '成功',
                        'content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'properties' => [
                                'status' => ['type' => 'string', 'enum' => ['ok']],
                                'data' => ['type' => 'object'],
                            ],
                        ]]],
                    ],
                    'NotFound' => [
                        'description' => '资源不存在或不属于当前租户',
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
                    ],
                    'ValidationError' => [
                        'description' => '字段校验失败',
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
                    ],
                    'Unauthorized' => [
                        'description' => '未认证 / Token 能力不足（read/write）',
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
                    ],
                    'Forbidden' => [
                        'description' => '无权限（或套餐不具备该功能）',
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
                    ],
                ],
            ],
        ];
    }

    // ------------------------------------------------------------------
    // 操作构造器
    // ------------------------------------------------------------------

    private function operation(string $summary, string $tag): array
    {
        return [
            'tags' => [$tag],
            'summary' => $summary,
            'responses' => ['200' => ['$ref' => '#/components/responses/Success']],
        ];
    }

    private function authEndpoint(string $action): array
    {
        $isLogin = $action === 'login';

        return [
            'tags' => ['认证'],
            'summary' => $isLogin ? '登录并签发 API Token' : '注册租户并签发 API Token',
            'security' => [],
            'requestBody' => [
                'required' => true,
                'content' => ['application/json' => ['schema' => [
                    'type' => 'object',
                    'required' => $isLogin ? ['email', 'password'] : ['name', 'email', 'password'],
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'password' => ['type' => 'string', 'format' => 'password'],
                    ],
                ]]],
            ],
            'responses' => [
                '200' => ['description' => '返回 token 与用户信息', 'content' => ['application/json' => ['schema' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string'],
                        'token' => ['type' => 'string', 'description' => 'Sanctum Bearer Token'],
                    ],
                ]]]],
                '422' => ['$ref' => '#/components/responses/ValidationError'],
                '429' => ['$ref' => '#/components/responses/Forbidden'],
            ],
        ];
    }

    private function simplePost(string $summary, string $tag): array
    {
        return array_merge($this->operation($summary, $tag), [
            'responses' => [
                '200' => ['$ref' => '#/components/responses/Success'],
                '401' => ['$ref' => '#/components/responses/Unauthorized'],
            ],
        ]);
    }

    private function meEndpoint(): array
    {
        return [
            'tags' => ['认证'],
            'summary' => '当前登录用户',
            'responses' => [
                '200' => ['$ref' => '#/components/responses/Success'],
                '401' => ['$ref' => '#/components/responses/Unauthorized'],
            ],
        ];
    }

    private function inboundEmailEndpoint(): array
    {
        return [
            'tags' => ['邮件转线索'],
            'summary' => '邮件 Inbound Parse 回调：按租户 slug 创建线索',
            'description' => '接收 Mailgun / SendGrid Inbound Parse JSON 载荷，解析发件人并创建 Lead（source=邮件）。按 tenantSlug 路由租户，不依赖 Token 所属租户。',
            'parameters' => [
                ['name' => 'tenantSlug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
            ],
            'requestBody' => [
                'required' => true,
                'content' => ['application/json' => ['schema' => [
                    'type' => 'object',
                    'properties' => [
                        'from' => ['type' => 'string', 'description' => '发件人（可含姓名）'],
                        'subject' => ['type' => 'string'],
                        'body-plain' => ['type' => 'string'],
                    ],
                ]]],
            ],
            'responses' => [
                '200' => ['$ref' => '#/components/responses/Success'],
                '401' => ['$ref' => '#/components/responses/Unauthorized'],
            ],
        ];
    }

    private function listOperation(string $resource, string $tag, array $definition): array
    {
        $schemaKey = $resource === 'stages' || $resource === 'users' ? ($resource === 'stages' ? 'stage' : 'user') : $resource;

        return array_merge($this->operation("列表（分页）", $tag), [
            'parameters' => [
                ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1]],
                ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 15, 'maximum' => 100]],
                ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => '关键字过滤（按资源主字段模糊匹配）'],
                ['name' => 'sort', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => '排序字段'],
                ['name' => 'direction', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['asc', 'desc']]],
            ],
            'responses' => [
                '200' => [
                    'description' => '分页列表',
                    'content' => ['application/json' => ['schema' => [
                        'type' => 'object',
                        'properties' => [
                            'status' => ['type' => 'string'],
                            'data' => ['type' => 'array', 'items' => ['$ref' => "#/components/schemas/{$schemaKey}"]],
                            'meta' => ['$ref' => '#/components/schemas/PaginationMeta'],
                        ],
                    ]]],
                ],
                '401' => ['$ref' => '#/components/responses/Unauthorized'],
            ],
        ]);
    }

    private function createOperation(string $resource, string $tag, array $definition): array
    {
        return array_merge($this->operation('创建', $tag), [
            'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => $this->requestSchema($definition)]]],
            'responses' => [
                '201' => ['$ref' => '#/components/responses/Success'],
                '401' => ['$ref' => '#/components/responses/Unauthorized'],
                '422' => ['$ref' => '#/components/responses/ValidationError'],
            ],
        ]);
    }

    private function showOperation(string $resource, string $tag): array
    {
        return array_merge($this->operation('详情', $tag), [
            'parameters' => [$this->idParameter()],
            'responses' => [
                '200' => ['$ref' => '#/components/responses/Success'],
                '401' => ['$ref' => '#/components/responses/Unauthorized'],
                '404' => ['$ref' => '#/components/responses/NotFound'],
            ],
        ]);
    }

    private function updateOperation(string $resource, string $tag, array $definition): array
    {
        return array_merge($this->operation('更新', $tag), [
            'parameters' => [$this->idParameter()],
            'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => $this->requestSchema($definition, false)]]],
            'responses' => [
                '200' => ['$ref' => '#/components/responses/Success'],
                '401' => ['$ref' => '#/components/responses/Unauthorized'],
                '404' => ['$ref' => '#/components/responses/NotFound'],
                '422' => ['$ref' => '#/components/responses/ValidationError'],
            ],
        ]);
    }

    private function destroyOperation(string $resource, string $tag, bool $enterprise): array
    {
        return array_merge($this->operation('删除' . ($enterprise ? '（企业版功能）' : ''), $tag), [
            'parameters' => [$this->idParameter()],
            'responses' => [
                '204' => ['description' => '删除成功（无内容）'],
                '401' => ['$ref' => '#/components/responses/Unauthorized'],
                '403' => ['$ref' => '#/components/responses/Forbidden'],
                '404' => ['$ref' => '#/components/responses/NotFound'],
            ],
        ]);
    }

    private function attachmentUploadOperation(): array
    {
        return array_merge($this->operation('上传附件（multipart/form-data，私有磁盘存储）', '附件'), [
            'requestBody' => [
                'required' => true,
                'content' => ['multipart/form-data' => ['schema' => [
                    'type' => 'object',
                    'required' => ['file', 'attachable_type', 'attachable_id'],
                    'properties' => [
                        'file' => ['type' => 'string', 'format' => 'binary'],
                        'attachable_type' => ['type' => 'string'],
                        'attachable_id' => ['type' => 'integer'],
                    ],
                ]]],
            ],
            'responses' => [
                '201' => ['$ref' => '#/components/responses/Success'],
                '401' => ['$ref' => '#/components/responses/Unauthorized'],
                '422' => ['$ref' => '#/components/responses/ValidationError'],
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // Schema 构造器
    // ------------------------------------------------------------------

    private function idParameter(): array
    {
        return ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']];
    }

    private function resourceSchema(array $definition): array
    {
        $properties = ['id' => ['type' => 'integer', 'readOnly' => true]];

        foreach ($definition['fields'] as $field => $meta) {
            $properties[$field] = array_filter([
                'type' => $meta['type'],
                'format' => $meta['format'] ?? null,
                'enum' => $meta['enum'] ?? null,
                'items' => $meta['items'] ?? null,
                'description' => $meta['description'] ?? null,
            ]);
        }

        $properties['created_at'] = ['type' => 'string', 'format' => 'date-time', 'readOnly' => true];
        $properties['updated_at'] = ['type' => 'string', 'format' => 'date-time', 'readOnly' => true];

        return ['type' => 'object', 'properties' => $properties];
    }

    private function requestSchema(array $definition, bool $withRequired = true): array
    {
        $properties = [];
        $required = [];

        foreach ($definition['fields'] as $field => $meta) {
            $properties[$field] = array_filter([
                'type' => $meta['type'],
                'format' => $meta['format'] ?? null,
                'enum' => $meta['enum'] ?? null,
                'items' => $meta['items'] ?? null,
                'description' => $meta['description'] ?? null,
            ]);

            if (($meta['required'] ?? false) && $withRequired) {
                $required[] = $field;
            }
        }

        return array_filter([
            'type' => 'object',
            'required' => $required ?: null,
            'properties' => $properties,
        ]);
    }
}
