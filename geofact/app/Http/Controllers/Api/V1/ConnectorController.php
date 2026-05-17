<?php
namespace App\Http\Controllers\Api\V1;
use App\Connector\ConnectorPipeline;
use App\Exceptions\ConnectorAuthException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConnectorIngestRequest;
use App\Models\Connector;
use App\Models\RawStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConnectorController extends Controller
{
    public function __construct(private readonly ConnectorPipeline $pipeline) {}

    public function ingest(ConnectorIngestRequest $request): JsonResponse
    {
        try {
            $reached = $this->pipeline->process($request);
            return $this->apiResponse(['reached_core' => $reached], $reached ? 'Event processed' : 'Event stopped before Core');
        } catch (ConnectorAuthException $e) {
            return $this->apiResponse(null, 'Authentication failed', 401);
        } catch (\Throwable $e) {
            Log::error('geofact.connector.ingest.error', ['error' => $e->getMessage()]);
            return $this->apiResponse(null, 'Internal error', 500);
        }
    }

    public function pull(string $id, Request $request): JsonResponse
    {
        $rawStore = RawStore::find($id);
        if (!$rawStore) return $this->apiResponse(null, 'Raw entry not found', 404);

        $connector = Connector::find($rawStore->connector_id);
        if (!$connector) return $this->apiResponse(null, 'Connector not found', 404);

        try {
            $result = $this->pipeline->processReplay($rawStore, $connector);
            return $this->apiResponse(['replayed' => $result]);
        } catch (\Throwable $e) {
            Log::error('geofact.connector.pull.error', ['raw_id' => $id, 'error' => $e->getMessage()]);
            return $this->apiResponse(null, 'Internal error', 500);
        }
    }
}
