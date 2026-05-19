<?php

namespace App\Jobs;

use App\Models\Report;
use App\Reports\AiEnricher;
use App\Reports\DataCollectors\VehicleDataCollector;
use App\Reports\PdfRenderer;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateVehicleReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $timeout = 120;
    public int $tries   = 1;

    public function __construct(
        public string  $reportId,
        public string  $orgId,
        public string  $vehicleId,
        public Carbon  $from,
        public Carbon  $to,
        public ?string $generatedBy = null,
    ) {}

    public function handle(): void
    {
        $report = Report::findOrFail($this->reportId);
        $report->update(['status' => 'generating']);

        try {
            $data = (new VehicleDataCollector())->collect($this->orgId, $this->vehicleId, $this->from, $this->to);
            $ai   = (new AiEnricher())->enrichVehicleReport($data);
            $path = (new PdfRenderer())->renderVehicle($data, $ai, $this->reportId);

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
