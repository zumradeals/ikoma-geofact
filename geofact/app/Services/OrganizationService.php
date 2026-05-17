<?php
namespace App\Services;
use App\Models\Organization;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class OrganizationService
{
    public function list(string $organizationId): LengthAwarePaginator
    {
        // geofact_admin voit tout — autres rôles voient leur propre org uniquement
        return Organization::where('id', $organizationId)->paginate(25);
    }

    public function find(string $id, string $organizationId): ?Organization
    {
        return Organization::where('id', $id)->where('id', $organizationId)->first();
    }

    public function create(array $data, string $actorId): Organization
    {
        return Organization::create(array_merge($data, [
            'id'         => Str::uuid()->toString(),
            'created_by' => $actorId,
            'created_at' => now(),
        ]));
    }
}
