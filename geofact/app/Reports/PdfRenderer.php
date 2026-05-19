<?php

namespace App\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PdfRenderer
{
    public function renderFleet(array $data, array $ai, string $reportId): string
    {
        $pdf = Pdf::loadView('reports.fleet', [
            'data' => $data,
            'ai'   => $ai,
        ])->setPaper('a4', 'portrait');

        $path = 'reports/' . $reportId . '.pdf';
        Storage::disk('local')->makeDirectory('reports');
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }
}
