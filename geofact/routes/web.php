<?php

use App\Filament\Org\Pages\ReportsPage;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Téléchargement sécurisé des rapports PDF (auth Filament requise)
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/app/{tenant}/reports/download/{reportId}', [ReportsPage::class, 'downloadReport'])
        ->name('filament.org.pages.reports.download');
});
