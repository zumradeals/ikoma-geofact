<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Services\InsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsightController extends Controller
{
    public function __construct(private readonly InsightService $service) {}

    public function show(string $scopeType, string $scopeId, Request $request): JsonResponse
    {
        $data = $this->service->latest($scopeType, $scopeId, $request->tenant['organization_id']);
        return $this->apiResponse($data);
    }

    public function generate(string $scopeType, string $scopeId, Request $request): JsonResponse
    {
        $insight = $this->service->generate($scopeType, $scopeId, $request->tenant['organization_id']);
        if (!$insight) return $this->apiResponse(null, 'Insight generation unavailable — AI offline or insufficient data', 503);
        return $this->apiResponse($insight, 'Insight generated', 201);
    }
}
