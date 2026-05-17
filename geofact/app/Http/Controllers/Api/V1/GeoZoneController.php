<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Services\GeoZoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeoZoneController extends Controller
{
    public function __construct(private readonly GeoZoneService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->apiResponse($this->service->list($request->tenant['organization_id']));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'      => 'required|string|max:100',
            'zone_type' => 'required|in:authorized,restricted,depot,customer,alert,custom',
            'geometry'  => 'required|array',
        ]);
        $zone = $this->service->create($request->only(['name','zone_type','geometry','fleet_id','max_stay_minutes','active_from','active_to']), $request->tenant['organization_id'], $request->tenant['user_id'] ?? '');
        return $this->apiResponse($zone, 'GeoZone created', 201);
    }

    public function show(string $id, Request $request): JsonResponse
    {
        $zone = $this->service->findForOrg($id, $request->tenant['organization_id']);
        if (!$zone) return $this->apiResponse(null, 'GeoZone not found', 404);
        return $this->apiResponse($zone);
    }

    public function update(string $id, Request $request): JsonResponse
    {
        $zone = $this->service->findForOrg($id, $request->tenant['organization_id']);
        if (!$zone) return $this->apiResponse(null, 'GeoZone not found', 404);
        $zone->fill($request->only(['name','zone_type','geometry','status','max_stay_minutes','active_from','active_to']));
        $zone->save();
        return $this->apiResponse($zone->fresh(), 'GeoZone updated');
    }

    public function destroy(string $id, Request $request): JsonResponse
    {
        $zone = $this->service->findForOrg($id, $request->tenant['organization_id']);
        if (!$zone) return $this->apiResponse(null, 'GeoZone not found', 404);
        $zone->status = 'deleted';
        $zone->save();
        return $this->apiResponse(null, 'GeoZone deleted');
    }
}
