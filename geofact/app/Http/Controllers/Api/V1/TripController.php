<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTripRequest;
use App\Services\TripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function __construct(private readonly TripService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->apiResponse($this->service->list($request->tenant['organization_id'], $request->only(['vehicle_id','status'])));
    }

    public function store(StoreTripRequest $request): JsonResponse
    {
        $trip = $this->service->create($request->validated(), $request->tenant['organization_id']);
        return $this->apiResponse($trip, 'Trip created', 201);
    }

    public function show(string $id, Request $request): JsonResponse
    {
        $trip = $this->service->findForOrg($id, $request->tenant['organization_id']);
        if (!$trip) return $this->apiResponse(null, 'Trip not found', 404);
        return $this->apiResponse($trip);
    }

    public function update(string $id, Request $request): JsonResponse
    {
        $trip = $this->service->findForOrg($id, $request->tenant['organization_id']);
        if (!$trip) return $this->apiResponse(null, 'Trip not found', 404);
        $trip->fill($request->only(['status','anomaly_note']));
        $trip->save();
        return $this->apiResponse($trip->fresh(), 'Trip updated');
    }

    public function destroy(string $id, Request $request): JsonResponse
    {
        return $this->apiResponse(null, 'Trip deletion not supported (C-11)', 405);
    }

    public function activeTrip(string $id, Request $request): JsonResponse
    {
        $trip = $this->service->activeTrip($id, $request->tenant['organization_id']);
        if (!$trip) return $this->apiResponse(null, 'No active trip for this vehicle', 404);
        return $this->apiResponse($trip);
    }
}
