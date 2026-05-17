<?php
namespace App\Services;
use App\Models\Vehicle;
use App\Models\VehicleTransfer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class VehicleService
{
    public function list(string $organizationId): LengthAwarePaginator
    {
        return Vehicle::where('organization_id', $organizationId)
            ->whereNull('deleted_at')->orderByDesc('created_at')->paginate(25);
    }

    public function create(array $data, string $organizationId): Vehicle
    {
        return Vehicle::create(array_merge($data, [
            'id'         => Str::uuid()->toString(),
            'created_at' => now(),
        ]));
    }

    public function findForOrg(string $id, string $organizationId): ?Vehicle
    {
        return Vehicle::where('id', $id)->where('organization_id', $organizationId)->whereNull('deleted_at')->first();
    }

    public function updateStatus(Vehicle $vehicle, string $status): Vehicle
    {
        $vehicle->status = $status;
        $vehicle->save();
        return $vehicle->fresh();
    }

    public function transfer(Vehicle $vehicle, array $data, string $actorId): VehicleTransfer
    {
        $transfer = VehicleTransfer::create([
            'id'                     => Str::uuid()->toString(),
            'vehicle_id'             => $vehicle->id,
            'from_fleet_id'          => $vehicle->fleet_id,
            'to_fleet_id'            => $data['target_fleet_id'],
            'from_organization_id'   => $vehicle->organization_id,
            'to_organization_id'     => $vehicle->organization_id,
            'effective_date'         => $data['effective_date'],
            'historical_data_policy' => $data['historical_data_policy'],
            'transfer_note'          => $data['transfer_note'] ?? null,
            'status'                 => 'pending',
            'initiated_by'           => $actorId,
            'created_at'             => now(),
        ]);
        $vehicle->fleet_id = $data['target_fleet_id'];
        $vehicle->save();
        return $transfer;
    }
}
