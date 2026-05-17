<?php
namespace App\Services;
use App\Exceptions\ContractViolationException;
use App\Models\Alert;
use Illuminate\Pagination\LengthAwarePaginator;

class AlertService
{
    public function list(string $organizationId, array $filters = []): LengthAwarePaginator
    {
        $query = Alert::where('organization_id', $organizationId)
            ->orderByDesc('triggered_at');
        if (!empty($filters['severity'])) $query->where('severity', $filters['severity']);
        if (!empty($filters['status']))   $query->where('status', $filters['status']);
        return $query->paginate(25);
    }

    public function findForOrg(string $id, string $organizationId): ?Alert
    {
        return Alert::where('id', $id)->where('organization_id', $organizationId)->first();
    }

    public function acknowledge(Alert $alert, string $actorId): Alert
    {
        $alert->status           = 'acknowledged';
        $alert->acknowledged_at  = now();
        $alert->save();
        return $alert->fresh();
    }

    public function resolve(Alert $alert, string $resolutionNote): Alert
    {
        $alert->status           = 'resolved';
        $alert->resolved_at      = now();
        $alert->resolution_note  = $resolutionNote;
        $alert->save();
        return $alert->fresh();
    }
}
