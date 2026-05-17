<?php
namespace App\Services;
use App\Insight\InsightEngine;
use App\Models\Insight;
use Illuminate\Database\Eloquent\Collection;

class InsightService
{
    public function __construct(private readonly InsightEngine $engine) {}

    public function latest(string $scopeType, string $scopeId, string $organizationId): Collection
    {
        return Insight::where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->where('organization_id', $organizationId)
            ->orderByDesc('generated_at')
            ->limit(10)
            ->get();
    }

    public function generate(string $scopeType, string $scopeId, string $organizationId): ?Insight
    {
        return $this->engine->generateForPeriod(
            $scopeType, $scopeId, $organizationId,
            now()->subDays(7)->startOfDay(),
            now()->endOfDay()
        );
    }
}
