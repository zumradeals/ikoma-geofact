<?php
namespace App\Services;
use App\Models\Fleet;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class FleetService
{
    public function list(string $organizationId): LengthAwarePaginator
    {
        return Fleet::where('organization_id', $organizationId)->orderByDesc('created_at')->paginate(25);
    }

    public function create(array $data, string $organizationId): Fleet
    {
        return Fleet::create(array_merge($data, [
            'id'              => Str::uuid()->toString(),
            'organization_id' => $organizationId,
            'created_at'      => now(),
        ]));
    }

    public function findForOrg(string $id, string $organizationId): ?Fleet
    {
        return Fleet::where('id', $id)->where('organization_id', $organizationId)->first();
    }

    public function updateStatus(Fleet $fleet, string $status): Fleet
    {
        $fleet->status = $status;
        $fleet->save();
        return $fleet->fresh();
    }
}
