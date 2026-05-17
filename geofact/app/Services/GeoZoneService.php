<?php
namespace App\Services;
use App\Models\GeoZone;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class GeoZoneService
{
    public function list(string $organizationId): LengthAwarePaginator
    {
        return GeoZone::where('organization_id', $organizationId)
            ->whereIn('status', ['active','draft'])->orderByDesc('created_at')->paginate(25);
    }

    public function create(array $data, string $organizationId, string $actorId): GeoZone
    {
        return GeoZone::create(array_merge($data, [
            'id'              => Str::uuid()->toString(),
            'organization_id' => $organizationId,
            'created_by'      => $actorId,
            'created_at'      => now(),
        ]));
    }

    public function findForOrg(string $id, string $organizationId): ?GeoZone
    {
        return GeoZone::where('id', $id)->where('organization_id', $organizationId)->first();
    }
}
