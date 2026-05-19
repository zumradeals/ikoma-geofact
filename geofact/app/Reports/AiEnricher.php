<?php

namespace App\Reports;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiEnricher
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key', '');
        $this->model  = config('services.anthropic.model', 'claude-sonnet-4-6');
    }

    public function enrichFleetReport(array $data): array
    {
        if (empty($this->apiKey)) {
            Log::warning('geofact.report.ai.missing_key');
            return $this->fallback();
        }

        $prompt = $this->buildFleetPrompt($data);

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model'      => $this->model,
                'max_tokens' => 1024,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if (! $response->successful()) {
                Log::error('geofact.report.ai.api_error', ['status' => $response->status()]);
                return $this->fallback();
            }

            $text = $response->json('content.0.text', '');
            return $this->parseAiResponse($text);

        } catch (\Throwable $e) {
            Log::error('geofact.report.ai.exception', ['error' => $e->getMessage()]);
            return $this->fallback();
        }
    }

    private function buildFleetPrompt(array $d): string
    {
        $fleetLines = '';
        foreach ($d['by_fleet'] ?? [] as $name => $f) {
            $fleetLines .= "  - {$name} : {$f['count']} véhicules, {$f['localized']} localisés\n";
        }

        $kpiLines = '';
        foreach ($d['kpis'] ?? [] as $type => $value) {
            if ($value !== null) {
                $kpiLines .= "  - {$type} : {$value}\n";
            }
        }

        return <<<PROMPT
Tu es analyste expert en gestion de flotte pour PME africaines.
Analyse les données suivantes et génère un rapport structuré en français.

DONNÉES DE LA PÉRIODE ({$d['period_from']} → {$d['period_to']}) :
- Véhicules actifs : {$d['total_vehicles']}
- Véhicules localisés GPS : {$d['localized_count']} ({$d['coverage_pct']}%)
- Trajets effectués : {$d['total_trips']}
- Distance totale : {$d['total_km']} km
- Heures de conduite : {$d['total_hours']} h
- Vitesse moyenne : {$d['avg_speed_kmh']} km/h
- Trajets anomaleux : {$d['anomalous_trips']}
- Véhicule le plus actif : {$d['top_vehicle']} ({$d['top_vehicle_km']} km)

ALERTES :
- Total : {$d['alerts_total']} (dont {$d['alerts_unresolved']} non résolues)
- Critiques : {$d['alerts_critical']} | Hautes : {$d['alerts_high']} | Moyennes : {$d['alerts_medium']} | Basses : {$d['alerts_low']}

RÉPARTITION PAR FLOTTE :
{$fleetLines}

KPIs CALCULÉS :
{$kpiLines}

Génère une réponse JSON avec exactement ces 3 clés :
{
  "summary": "Résumé exécutif en 3-4 phrases percutantes pour un dirigeant.",
  "anomalies": ["point 1", "point 2", "point 3"],
  "recommendations": ["recommandation 1", "recommandation 2", "recommandation 3"]
}

Règles :
- Sois factuel et concis
- Mets en avant les risques prioritaires
- Les recommandations doivent être actionnables immédiatement
- Si les données sont insuffisantes pour une analyse (0 trajets, 0 alertes), adapte le commentaire
PROMPT;
    }

    private function parseAiResponse(string $text): array
    {
        // Extraire le JSON de la réponse
        preg_match('/\{.*\}/s', $text, $matches);
        if (empty($matches[0])) {
            return $this->fallback();
        }

        $parsed = json_decode($matches[0], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->fallback();
        }

        return [
            'summary'         => $parsed['summary'] ?? '',
            'anomalies'       => (array) ($parsed['anomalies'] ?? []),
            'recommendations' => (array) ($parsed['recommendations'] ?? []),
        ];
    }

    private function fallback(): array
    {
        return [
            'summary'         => 'Analyse IA non disponible pour ce rapport.',
            'anomalies'       => [],
            'recommendations' => [],
        ];
    }
}
