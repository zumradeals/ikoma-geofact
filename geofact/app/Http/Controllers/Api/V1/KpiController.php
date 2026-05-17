<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Services\KpiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KpiController extends Controller
{
    public function __construct(private readonly KpiService $service) {}

    public function show(string $scopeType, string $scopeId, Request $request): JsonResponse
    {
        $data = $this->service->latest($scopeType, $scopeId, $request->tenant['organization_id']);
        return $this->apiResponse($data);
    }

    public function history(string $scopeType, string $scopeId, Request $request): JsonResponse
    {
        $kpiType = $request->query('kpi_type', '');
        if (empty($kpiType)) return $this->apiResponse(null, 'kpi_type query parameter is required', 422);
        $data = $this->service->history($scopeType, $scopeId, $request->tenant['organization_id'], $kpiType);
        return $this->apiResponse($data);
    }
}
