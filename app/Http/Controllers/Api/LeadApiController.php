<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RestCrud;
use App\Models\Lead;
use App\Services\Tenancy;
use Illuminate\Http\Request;

class LeadApiController extends ApiController
{
    use RestCrud;

    protected string $model = Lead::class;

    protected array $relations = ['owner'];

    protected array $searchFields = ['name', 'email', 'company_name'];

    protected array $sortableColumns = ['name', 'status', 'created_at'];

    protected ?string $ownerColumn = 'owner_id';

    protected function rulesForStore(Request $request): array
    {
        $tenantId = app(Tenancy::class)->id();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:new,contacted,qualified,converted,lost'],
            'owner_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
        ];
    }

    protected function rulesForUpdate(Request $request): array
    {
        $tenantId = app(Tenancy::class)->id();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:new,contacted,qualified,converted,lost'],
            'owner_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
