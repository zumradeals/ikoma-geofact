<?php

namespace App\Jobs;

use App\Models\Report;
use App\Reports\AiEnricher;
use App\Reports\DataCollectors\DriverDataCollector;
use App\Reports\PdfRenderer;
use Carbon\Carbon;
use Illuminate\Foundation\Bus\Dispatchable;

class GenerateDriverReportJob
{
    use Dispatchable;

    public int $timeout = 120;
    public int $tries   = 1;

    public function __construct(
        public string  $reportId,
        public string  $orgId,
        public string  $driverId,
        public Carbon  $from,
        public Carbon  $to,
        public ?string $generatedBy = null,
    ) {}

    public function handle(): void
    {
        $report = Report::findOrFail($this->reportId);
        $report->update(['status' => 'generating']);

        try {
            $data = (new DriverDataCollector())->collect($this->orgId, $this->driverId, $this->from, $this->to);
            $ai   = (new AiEnricher())->enrichDriverReport($data);
            $path = (new PdfRenderer())->renderDriver($data, $ai, $this->reportId);

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
