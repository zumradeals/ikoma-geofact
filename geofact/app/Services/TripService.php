<?php
namespace App\Services;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class TripService
{
    public function list(string $organizationId, array $filters = []): LengthAwarePaginator
    {
        $query = Trip::where('organization_id', $organizationId)->orderByDesc('created_at');
        if (!empty($filters['vehicle_id'])) $query->where('vehicle_id', $filters['vehicle_id']);
        if (!empty($filters['status']))     $query->where('status', $filters['status']);
        return $query->paginate(25);
    }

    public function findForOrg(string $id, string $organizationId): ?Trip
    {
        return Trip::where('id', $id)->where('organization_id', $organizationId)->first();
    }

    public function activeTrip(string $vehicleId, string $organizationId): ?Trip
    {
        return Trip::where('vehicle_id', $vehicleId)
            ->where('organization_id', $organizationId)
            ->whereIn('status', ['active', 'paused'])
            ->first();
    }

    public function create(array $data, string $organizationId): Trip
    {
        return Trip::create(array_merge($data, [
            'id'              => Str::uuid()->toString(),
            'organization_id' => $organizationId,
            'status'          => 'pending',
            'created_at'      => now(),
        ]));
    }
}
