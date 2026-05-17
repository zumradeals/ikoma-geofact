<?php
namespace App\Services;
use App\Models\Driver;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class DriverService
{
    public function list(string $organizationId): LengthAwarePaginator
    {
        return Driver::where('organization_id', $organizationId)
            ->whereNull('deleted_at')->orderByDesc('created_at')->paginate(25);
    }

    public function create(array $data, string $organizationId): Driver
    {
        return Driver::create(array_merge($data, [
            'id'              => Str::uuid()->toString(),
            'organization_id' => $organizationId,
            'created_at'      => now(),
        ]));
    }

    public function findForOrg(string $id, string $organizationId): ?Driver
    {
        return Driver::where('id', $id)->where('organization_id', $organizationId)->whereNull('deleted_at')->first();
    }
}
