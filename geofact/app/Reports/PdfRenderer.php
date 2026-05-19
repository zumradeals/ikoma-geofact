<?php

namespace App\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PdfRenderer
{
    public function renderFleet(array $data, array $ai, string $reportId): string
    {
        return $this->render('reports.fleet', $data, $ai, $reportId);
    }

    public function renderVehicle(array $data, array $ai, string $reportId): string
    {
        return $this->render('reports.vehicle', $data, $ai, $reportId);
    }

    public function renderDriver(array $data, array $ai, string $reportId): string
    {
        return $this->render('reports.driver', $data, $ai, $reportId);
    }

    private function render(string $view, array $data, array $ai, string $reportId): string
    {
        $pdf = Pdf::loadView($view, [
            'data' => $data,
            'ai'   => $ai,
        ])->setPaper('a4', 'portrait');

        $path = 'reports/' . $reportId . '.pdf';
        Storage::disk('local')->makeDirectory('reports');
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }
}
