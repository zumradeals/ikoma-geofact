<?php

namespace App\Filament\Org\Pages;

use App\Jobs\GenerateFleetReportJob;
use App\Models\Report;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsPage extends Page
{
    protected static ?string $title          = 'Rapports';
    protected static ?int    $navigationSort = 2;
    protected string         $view           = 'filament.org.pages.reports';

    public static function getNavigationIcon(): string   { return 'heroicon-o-document-chart-bar'; }
    public static function getNavigationGroup(): ?string { return 'Analytique'; }

    public function getViewData(): array
    {
        $orgId   = Auth::user()?->organization_id;
        $reports = Report::where('organization_id', $orgId)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return ['reports' => $reports];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_fleet')
                ->label('Générer rapport flotte')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->form([
                    Select::make('period')
                        ->label('Période')
                        ->options([
                            'last_month'  => 'Mois dernier',
                            'this_month'  => 'Ce mois-ci',
                            'last_7days'  => '7 derniers jours',
                            'last_30days' => '30 derniers jours',
                            'custom'      => 'Personnalisée',
                        ])
                        ->default('last_month')
                        ->required()
                        ->live(),

                    DatePicker::make('date_from')
                        ->label('Du')
                        ->visible(fn ($get) => $get('period') === 'custom')
                        ->required(fn ($get) => $get('period') === 'custom'),

                    DatePicker::make('date_to')
                        ->label('Au')
                        ->visible(fn ($get) => $get('period') === 'custom')
                        ->required(fn ($get) => $get('period') === 'custom'),
                ])
                ->action(function (array $data): void {
                    [$from, $to] = $this->resolvePeriod($data);

                    $orgId    = Auth::user()?->organization_id;
                    $userId   = Auth::id();
                    $reportId = Str::uuid()->toString();

                    Report::create([
                        'id'              => $reportId,
                        'organization_id' => $orgId,
                        'report_type'     => 'fleet',
                        'status'          => 'pending',
                        'period_from'     => $from,
                        'period_to'       => $to,
                        'title'           => 'Rapport Flotte — ' . $from->format('d/m/Y') . ' au ' . $to->format('d/m/Y'),
                        'generated_by'    => $userId,
                    ]);

                    GenerateFleetReportJob::dispatch($reportId, $orgId, $from, $to, $userId);

                    Notification::make()
                        ->title('Rapport en cours de génération')
                        ->body('Il sera disponible dans quelques secondes.')
                        ->success()
                        ->send();
                }),
        ];
    }

    // Téléchargement via Livewire — évite les problèmes de middleware sur cPanel
    public function download(string $reportId): StreamedResponse
    {
        $report = Report::where('id', $reportId)
            ->where('organization_id', Auth::user()?->organization_id)
            ->where('status', 'ready')
            ->firstOrFail();

        abort_unless(Storage::disk('local')->exists($report->file_path), 404, 'Fichier introuvable');

        $filename = Str::slug($report->title) . '.pdf';

        return response()->streamDownload(
            fn () => print(Storage::disk('local')->get($report->file_path)),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    private function resolvePeriod(array $data): array
    {
        return match ($data['period']) {
            'last_month'  => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()],
            'this_month'  => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            'last_7days'  => [Carbon::now()->subDays(7)->startOfDay(), Carbon::now()->endOfDay()],
            'last_30days' => [Carbon::now()->subDays(30)->startOfDay(), Carbon::now()->endOfDay()],
            'custom'      => [Carbon::parse($data['date_from'])->startOfDay(), Carbon::parse($data['date_to'])->endOfDay()],
            default       => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()],
        };
    }
}
