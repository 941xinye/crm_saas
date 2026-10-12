<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RestCrud;
use App\Models\Company;
use App\Services\CustomFieldService;
use App\Services\Tenancy;
use Illuminate\Http\Request;

class CompanyApiController extends ApiController
{
    use RestCrud;

    protected string $model = Company::class;

    protected array $relations = ['owner'];

    protected array $searchFields = ['name', 'industry'];

    protected array $sortableColumns = ['name', 'created_at'];

    protected ?string $ownerColumn = 'owner_id';

    /**
     * 自定义字段清洗：只保留租户已定义的 key（与 Web 表单 sanitizeValues 一致）。
     */
    protected function sanitizeData(Request $request, array $data): array
    {
        if (array_key_exists('custom_fields', $data)) {
            $data['custom_fields'] = app(CustomFieldService::class)->sanitizeValues('company', $data['custom_fields'] ?? []);
        }

        return $data;
    }

    protected function rulesForStore(Request $request): array
    {
        $tenantId = app(Tenancy::class)->id();

        return [
            'name' => ['required', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'owner_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            ...app(CustomFieldService::class)->validationRules('company'),
        ];
    }

    protected function rulesForUpdate(Request $request): array
    {
        $tenantId = app(Tenancy::class)->id();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'owner_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            ...app(CustomFieldService::class)->validationRules('company'),
        ];
    }
}
