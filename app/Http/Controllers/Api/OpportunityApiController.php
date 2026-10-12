<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RestCrud;
use App\Models\Opportunity;
use App\Services\CustomFieldService;
use App\Services\Tenancy;
use Illuminate\Http\Request;

class OpportunityApiController extends ApiController
{
    use RestCrud;

    protected string $model = Opportunity::class;

    protected array $relations = ['company', 'contact', 'stage', 'owner'];

    protected array $searchFields = ['title'];

    protected array $sortableColumns = ['title', 'value', 'expected_close_date', 'created_at'];

    protected ?string $ownerColumn = 'owner_id';

    /**
     * 自定义字段清洗：只保留租户已定义的 key（与 Web 表单 sanitizeValues 一致）。
     */
    protected function sanitizeData(Request $request, array $data): array
    {
        if (array_key_exists('custom_fields', $data)) {
            $data['custom_fields'] = app(CustomFieldService::class)->sanitizeValues('opportunity', $data['custom_fields'] ?? []);
        }

        return $data;
    }

    protected function rulesForStore(Request $request): array
    {
        $tenantId = app(Tenancy::class)->id();

        return [
            'title' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'expected_close_date' => ['nullable', 'date'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'company_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('companies', 'id')->where('tenant_id', $tenantId)],
            'contact_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('contacts', 'id')->where('tenant_id', $tenantId)],
            'stage_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('stages', 'id')->where('tenant_id', $tenantId)],
            'owner_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            ...app(CustomFieldService::class)->validationRules('opportunity'),
        ];
    }

    protected function rulesForUpdate(Request $request): array
    {
        $tenantId = app(Tenancy::class)->id();

        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'expected_close_date' => ['nullable', 'date'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'company_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('companies', 'id')->where('tenant_id', $tenantId)],
            'contact_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('contacts', 'id')->where('tenant_id', $tenantId)],
            'stage_id' => ['sometimes', 'integer', \Illuminate\Validation\Rule::exists('stages', 'id')->where('tenant_id', $tenantId)],
            'owner_id' => ['sometimes', 'integer', \Illuminate\Validation\Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            ...app(CustomFieldService::class)->validationRules('opportunity'),
        ];
    }
}
