<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Services\DriverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function __construct(private readonly DriverService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->apiResponse($this->service->list($request->tenant['organization_id']));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'first_name'   => 'required|string|max:80',
            'last_name'    => 'required|string|max:80',
            'phone'        => 'nullable|string|max:20',
            'license_number' => 'nullable|string|max:50',
        ]);
        $driver = $this->service->create($request->only(['first_name','last_name','phone','license_number','fleet_id']), $request->tenant['organization_id']);
        return $this->apiResponse($driver, 'Driver created', 201);
    }

    public function show(string $id, Request $request): JsonResponse
    {
        $driver = $this->service->findForOrg($id, $request->tenant['organization_id']);
        if (!$driver) return $this->apiResponse(null, 'Driver not found', 404);
        return $this->apiResponse($driver);
    }

    public function update(string $id, Request $request): JsonResponse
    {
        $driver = $this->service->findForOrg($id, $request->tenant['organization_id']);
        if (!$driver) return $this->apiResponse(null, 'Driver not found', 404);
        $driver->fill($request->only(['first_name','last_name','phone','license_number']));
        $driver->save();
        return $this->apiResponse($driver->fresh(), 'Driver updated');
    }

    public function destroy(string $id, Request $request): JsonResponse
    {
        $driver = $this->service->findForOrg($id, $request->tenant['organization_id']);
        if (!$driver) return $this->apiResponse(null, 'Driver not found', 404);
        $driver->deleted_at = now();
        $driver->save();
        return $this->apiResponse(null, 'Driver archived');
    }
}
