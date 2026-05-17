<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\VehicleTransferRequest;
use App\Services\VehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function __construct(private readonly VehicleService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->apiResponse($this->service->list($request->tenant['organization_id']));
    }

    public function store(StoreVehicleRequest $request): JsonResponse
    {
        $vehicle = $this->service->create($request->validated(), $request->tenant['organization_id']);
        return $this->apiResponse($vehicle, 'Vehicle created', 201);
    }

    public function show(string $id, Request $request): JsonResponse
    {
        $vehicle = $this->service->findForOrg($id, $request->tenant['organization_id']);
        if (!$vehicle) return $this->apiResponse(null, 'Vehicle not found', 404);
        return $this->apiResponse($vehicle);
    }

    public function update(string $id, Request $request): JsonResponse
    {
        $vehicle = $this->service->findForOrg($id, $request->tenant['organization_id']);
        if (!$vehicle) return $this->apiResponse(null, 'Vehicle not found', 404);
        $vehicle->fill($request->only(['name','brand','model','year']));
        $vehicle->save();
        return $this->apiResponse($vehicle->fresh(), 'Vehicle updated');
    }

    public function destroy(string $id, Request $request): JsonResponse
    {
        $vehicle = $this->service->findForOrg($id, $request->tenant['organization_id']);
        if (!$vehicle) return $this->apiResponse(null, 'Vehicle not found', 404);
        $vehicle->deleted_at = now();
        $vehicle->save();
        return $this->apiResponse(null, 'Vehicle archived');
    }

    public function updateStatus(string $id, Request $request): JsonResponse
    {
        $vehicle = $this->service->findForOrg($id, $request->tenant['organization_id']);
        if (!$vehicle) return $this->apiResponse(null, 'Vehicle not found', 404);
        $updated = $this->service->updateStatus($vehicle, $request->input('status'));
        return $this->apiResponse($updated, 'Status updated');
    }

    public function transfer(string $id, VehicleTransferRequest $request): JsonResponse
    {
        $vehicle = $this->service->findForOrg($id, $request->tenant['organization_id']);
        if (!$vehicle) return $this->apiResponse(null, 'Vehicle not found', 404);
        $actorId = $request->tenant['user_id'] ?? '';
        $transfer = $this->service->transfer($vehicle, $request->validated(), $actorId);
        return $this->apiResponse($transfer, 'Transfer initiated', 201);
    }
}
