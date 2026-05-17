<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Services\FleetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetController extends Controller
{
    public function __construct(private readonly FleetService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->apiResponse($this->service->list($request->tenant['organization_id']));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:100']);
        $fleet = $this->service->create($request->only(['name','description']), $request->tenant['organization_id']);
        return $this->apiResponse($fleet, 'Fleet created', 201);
    }

    public function show(string $id, Request $request): JsonResponse
    {
        $fleet = $this->service->findForOrg($id, $request->tenant['organization_id']);
        if (!$fleet) return $this->apiResponse(null, 'Fleet not found', 404);
        return $this->apiResponse($fleet);
    }

    public function update(string $id, Request $request): JsonResponse
    {
        $fleet = $this->service->findForOrg($id, $request->tenant['organization_id']);
        if (!$fleet) return $this->apiResponse(null, 'Fleet not found', 404);
        $fleet->fill($request->only(['name','description']));
        $fleet->save();
        return $this->apiResponse($fleet->fresh(), 'Fleet updated');
    }

    public function destroy(string $id, Request $request): JsonResponse
    {
        return $this->apiResponse(null, 'Fleet archival not yet supported', 405);
    }

    public function updateStatus(string $id, Request $request): JsonResponse
    {
        $fleet = $this->service->findForOrg($id, $request->tenant['organization_id']);
        if (!$fleet) return $this->apiResponse(null, 'Fleet not found', 404);
        $updated = $this->service->updateStatus($fleet, $request->input('status'));
        return $this->apiResponse($updated, 'Status updated');
    }
}
