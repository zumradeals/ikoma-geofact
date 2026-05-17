<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Services\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function __construct(private readonly OrganizationService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->apiResponse($this->service->list($request->tenant['organization_id']));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:150', 'country_code' => 'required|size:2']);
        $org = $this->service->create($request->only(['name','country_code','timezone']), $request->tenant['user_id'] ?? '');
        return $this->apiResponse($org, 'Organization created', 201);
    }

    public function show(string $id, Request $request): JsonResponse
    {
        $org = $this->service->find($id, $request->tenant['organization_id']);
        if (!$org) return $this->apiResponse(null, 'Organization not found', 404);
        return $this->apiResponse($org);
    }

    public function update(string $id, Request $request): JsonResponse
    {
        $org = $this->service->find($id, $request->tenant['organization_id']);
        if (!$org) return $this->apiResponse(null, 'Organization not found', 404);
        $org->fill($request->only(['name','timezone']));
        $org->save();
        return $this->apiResponse($org->fresh(), 'Organization updated');
    }

    public function destroy(string $id, Request $request): JsonResponse
    {
        return $this->apiResponse(null, 'Organization deletion not supported', 405);
    }
}
