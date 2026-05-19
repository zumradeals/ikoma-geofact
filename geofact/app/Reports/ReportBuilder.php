<?php

namespace App\Reports;

use App\Models\Report;
use App\Reports\DataCollectors\FleetDataCollector;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReportBuilder
{
    public function __construct(
        private FleetDataCollector $collector,
        private AiEnricher         $enricher,
        private PdfRenderer        $renderer,
    ) {}

    public function buildFleetReport(string $orgId, Carbon $from, Carbon $to, ?string $generatedBy = null): Report
    {
        $report = Report::create([
            'id'            => Str::uuid()->toString(),
            'organization_id' => $orgId,
            'report_type'   => 'fleet',
            'status'        => 'generating',
            'period_from'   => $from,
            'period_to'     => $to,
            'title'         => 'Rapport Flotte — ' . $from->format('d/m/Y') . ' au ' . $to->format('d/m/Y'),
            'generated_by'  => $generatedBy,
        ]);

        try {
            Log::info('geofact.report.fleet.started', ['report_id' => $report->id]);

            $data = $this->collector->collect($orgId, $from, $to);
            $ai   = $this->enricher->enrichFleetReport($data);
            $path = $this->renderer->renderFleet($data, $ai, $report->id);

            $report->update([
                'status'       => 'ready',
                'file_path'    => $path,
                'ai_summary'   => $ai['summary'],
                'generated_at' => now(),
            ]);

            Log::info('geofact.report.fleet.done', ['report_id' => $report->id]);

        } catch (\Throwable $e) {
            $report->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            Log::error('geofact.report.fleet.failed', [
                'report_id' => $report->id,
                'error'     => $e->getMessage(),
            ]);
        }

        return $report->fresh();
    }
}
