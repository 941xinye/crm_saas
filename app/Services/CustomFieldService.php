<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Tenant;

/**
 * 自定义字段服务（Phase 6 / P1）：
 * - 字段定义存租户（tenants.custom_fields），按实体分组，TenantScope 天然隔离
 * - 字段值存业务表 custom_fields JSON 列
 * - 统一产出验证规则（Livewire Form 与 REST API 共用）
 *
 * 定义结构：{entity: [{key, label, type, required, options}]}
 * - entity ∈ company / contact / opportunity
 * - type ∈ text / textarea / number / date / select / multiselect
 * - options 仅 select / multiselect 使用（字符串数组）
 */
class CustomFieldService
{
    /** 支持的实体（key → 模型） */
    public const ENTITIES = [
        'company' => Company::class,
        'contact' => Contact::class,
        'opportunity' => Opportunity::class,
    ];

    /** 实体中文名 */
    public const ENTITY_LABELS = [
        'company' => '公司',
        'contact' => '联系人',
        'opportunity' => '商机',
    ];

    /** 字段类型（key → 中文名） */
    public const TYPES = [
        'text' => '单行文本',
        'textarea' => '多行文本',
        'number' => '数字',
        'date' => '日期',
        'select' => '单选下拉',
        'multiselect' => '多选',
    ];

    public function __construct(private readonly Tenancy $tenancy)
    {
    }

    /** 实体显示名（随 locale 翻译；常量 ENTITY_LABELS 保留供既有外部调用方使用） */
    private function entityLabel(string $entity): string
    {
        return __('services.custom_field_entity_' . $entity);
    }

    /**
     * 某实体的字段定义列表。
     */
    public function definitions(string $entity): array
    {
        if (! isset(self::ENTITIES[$entity])) {
            return [];
        }

        $tenant = $this->tenancy->current();

        return $tenant->custom_fields[$entity] ?? [];
    }

    /**
     * 某实体某 key 的定义（不存在返回 null）。
     */
    public function definition(string $entity, string $key): ?array
    {
        foreach ($this->definitions($entity) as $field) {
            if ($field['key'] === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * 某实体自定义字段的验证规则（custom_fields.key => rules），
     * 直接与表单/API 的 rules 合并。
     */
    public function validationRules(string $entity): array
    {
        $rules = ['custom_fields' => ['nullable', 'array']];

        foreach ($this->definitions($entity) as $field) {
            $key = "custom_fields.{$field['key']}";
            $type = [
                'number' => ['nullable', 'numeric'],
                'date' => ['nullable', 'date'],
                'multiselect' => ['nullable', 'array'],
                'select' => ['nullable', 'string', 'max:255'],
            ][$field['type']] ?? ['nullable', 'string', 'max:1000'];

            if ($field['type'] === 'select' && ! empty($field['options'])) {
                $type[] = \Illuminate\Validation\Rule::in($field['options']);
            }

            $rules[$key] = $field['required'] ? ['required', ...$type] : $type;
        }

        return $rules;
    }

    /**
     * 规范化保存值（number 转数值、date 保持字符串、multiselect 转字符串数组），
     * 丢弃未定义的 key，防止脏数据入库。
     */
    public function sanitizeValues(string $entity, array $values): array
    {
        $clean = [];

        foreach ($this->definitions($entity) as $field) {
            $key = $field['key'];
            if (! array_key_exists($key, $values) || $values[$key] === null || $values[$key] === '') {
                continue;
            }

            $clean[$key] = match ($field['type']) {
                'number' => $values[$key] + 0,
                'multiselect' => array_values(array_map('strval', (array) $values[$key])),
                default => (string) $values[$key],
            };
        }

        return $clean;
    }

    /**
     * 保存字段定义（设置页），返回错误消息（null 表示成功）。
     * 规范化：key 小写下划线、去重、类型合法、options 拆分、required 布尔化。
     */
    public function saveDefinitions(array $rows): ?string
    {
        $grouped = [];
        $seen = [];

        foreach ($rows as $row) {
            $entity = $row['entity'] ?? '';
            $key = trim(strtolower(preg_replace('/[^A-Za-z0-9_]+/', '_', (string) ($row['key'] ?? ''))), '_');
            $label = trim((string) ($row['label'] ?? ''));
            $type = $row['type'] ?? 'text';

            if ($label === '') {
                continue; // 空行跳过
            }
            if ($key === '') {
                return __('services.custom_field_key_required', ['label' => $label]);
            }
            if (! isset(self::ENTITIES[$entity]) || ! isset(self::TYPES[$type])) {
                return __('services.custom_field_invalid_entity_type', ['label' => $label]);
            }

            $seenKey = "{$entity}.{$key}";
            if (isset($seen[$seenKey])) {
                return __('services.custom_field_duplicate_key', ['entity' => $this->entityLabel($entity), 'key' => $key]);
            }
            $seen[$seenKey] = true;

            $options = [];
            if (in_array($type, ['select', 'multiselect'], true)) {
                $options = array_values(array_filter(array_map('trim', explode(',', (string) ($row['options'] ?? '')))));
                if ($options === []) {
                    return __('services.custom_field_options_required', ['entity' => $this->entityLabel($entity), 'label' => $label]);
                }
            }

            $grouped[$entity][] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'required' => (bool) ($row['required'] ?? false),
                'options' => $options,
            ];
        }

        $tenant = $this->tenancy->current();
        $tenant->update(['custom_fields' => $grouped]);

        return null;
    }

    /**
     * 列表筛选 key 白名单校验（防 JSON path 注入：仅允许已定义或合法标识符）。
     */
    public function isFilterableKey(string $entity, string $key): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_]{1,64}$/', $key)
            && $this->definition($entity, $key) !== null;
    }

    /**
     * 展示值（multiselect 数组转顿号拼接，其余原样字符串）。
     */
    public function displayValue(array $definition, mixed $value): string
    {
        if (is_array($value)) {
            return implode(__('services.custom_field_multiselect_separator'), $value);
        }

        return (string) $value;
    }

    /**
     * 当前租户的空定义行（设置页新增行用）。
     */
    public static function blankRow(string $entity = 'company'): array
    {
        return ['entity' => $entity, 'key' => '', 'label' => '', 'type' => 'text', 'options' => '', 'required' => false];
    }

    /**
     * 展开租户已保存定义为可编辑行（设置页挂载用）。
     */
    public static function expandRows(Tenant $tenant): array
    {
        $rows = [];

        foreach (self::ENTITY_LABELS as $entity => $label) {
            foreach ($tenant->custom_fields[$entity] ?? [] as $field) {
                $rows[] = [
                    'entity' => $entity,
                    'key' => $field['key'],
                    'label' => $field['label'],
                    'type' => $field['type'],
                    'options' => implode(',', $field['options'] ?? []),
                    'required' => $field['required'],
                ];
            }
        }

        return $rows;
    }
}
