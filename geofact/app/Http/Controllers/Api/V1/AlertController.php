<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Services\AlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function __construct(private readonly AlertService $service) {}

    public function index(Request $request): JsonResponse
    {
        $orgId = $request->tenant['organization_id'];
        $data  = $this->service->list($orgId, $request->only(['severity','status']));
        return $this->apiResponse($data);
    }

    public function show(string $id, Request $request): JsonResponse
    {
        $orgId = $request->tenant['organization_id'];
        $alert = $this->service->findForOrg($id, $orgId);
        if (!$alert) return $this->apiResponse(null, 'Alert not found', 404);
        return $this->apiResponse($alert);
    }

    public function acknowledge(string $id, Request $request): JsonResponse
    {
        $orgId  = $request->tenant['organization_id'];
        $actorId = $request->tenant['actor_id'] ?? '';
        $alert  = $this->service->findForOrg($id, $orgId);
        if (!$alert) return $this->apiResponse(null, 'Alert not found', 404);
        if ($alert->status !== 'open') return $this->apiResponse(null, 'Alert is not open', 422);
        $updated = $this->service->acknowledge($alert, $actorId);
        return $this->apiResponse($updated, 'Alert acknowledged');
    }

    public function resolve(string $id, Request $request): JsonResponse
    {
        $orgId          = $request->tenant['organization_id'];
        $resolutionNote = $request->input('resolution_note', '');
        if (empty($resolutionNote)) {
            return $this->apiResponse(null, 'resolution_note is required', 422, ['resolution_note' => ['required']]);
        }
        $alert = $this->service->findForOrg($id, $orgId);
        if (!$alert) return $this->apiResponse(null, 'Alert not found', 404);
        $updated = $this->service->resolve($alert, $resolutionNote);
        return $this->apiResponse($updated, 'Alert resolved');
    }

    public function store(Request $request): JsonResponse { return $this->apiResponse(null, 'Alerts are system-generated', 405); }
    public function update(string $id, Request $request): JsonResponse { return $this->apiResponse(null, 'Use /acknowledge or /resolve', 405); }
    public function destroy(string $id, Request $request): JsonResponse { return $this->apiResponse(null, 'Alert deletion is forbidden (DC-09)', 405); }
}
