<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RestCrud;
use App\Models\Contact;
use App\Services\CustomFieldService;
use App\Services\Tenancy;
use Illuminate\Http\Request;

class ContactApiController extends ApiController
{
    use RestCrud;

    protected string $model = Contact::class;

    protected array $relations = ['company'];

    protected array $searchFields = ['first_name', 'last_name', 'email'];

    protected array $sortableColumns = ['first_name', 'last_name', 'email', 'created_at'];

    // Contact 无 owner_id，不做 sales 数据权限限制

    /**
     * 自定义字段清洗：只保留租户已定义的 key（与 Web 表单 sanitizeValues 一致）。
     */
    protected function sanitizeData(Request $request, array $data): array
    {
        if (array_key_exists('custom_fields', $data)) {
            $data['custom_fields'] = app(CustomFieldService::class)->sanitizeValues('contact', $data['custom_fields'] ?? []);
        }

        return $data;
    }

    protected function rulesForStore(Request $request): array
    {
        $tenantId = app(Tenancy::class)->id();

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255', \Illuminate\Validation\Rule::unique('contacts', 'email')->where('tenant_id', $tenantId)],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('companies', 'id')->where('tenant_id', $tenantId)],
            ...app(CustomFieldService::class)->validationRules('contact'),
        ];
    }

    protected function rulesForUpdate(Request $request): array
    {
        $tenantId = app(Tenancy::class)->id();

        return [
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'], // unique 需 ignore:contact,id 但 RestCrud 暂未传 id
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('companies', 'id')->where('tenant_id', $tenantId)],
            ...app(CustomFieldService::class)->validationRules('contact'),
        ];
    }
}
