<?php

namespace App\Jobs;

use App\Models\Report;
use App\Reports\AiEnricher;
use App\Reports\DataCollectors\FleetDataCollector;
use App\Reports\PdfRenderer;
use App\Reports\ReportBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Bus\Dispatchable;

class GenerateFleetReportJob
{
    use Dispatchable;

    public int $timeout = 120;
    public int $tries   = 1;

    public function __construct(
        public string  $reportId,
        public string  $orgId,
        public Carbon  $from,
        public Carbon  $to,
        public ?string $generatedBy = null,
    ) {}

    public function handle(): void
    {
        $builder = new ReportBuilder(
            new FleetDataCollector(),
            new AiEnricher(),
            new PdfRenderer(),
        );

        // Reuse existing report record created by the page before dispatch
        $report = Report::findOrFail($this->reportId);
        $report->update(['status' => 'generating']);

        try {
            $data = (new FleetDataCollector())->collect($this->orgId, $this->from, $this->to);
            $ai   = (new AiEnricher())->enrichFleetReport($data);
            $path = (new PdfRenderer())->renderFleet($data, $ai, $this->reportId);

            $report->update([
                'status'       => 'ready',
                'file_path'    => $path,
                'ai_summary'   => $ai['summary'],
                'generated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $report->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
