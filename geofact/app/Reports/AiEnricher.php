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
        $this->apiKey = config('services.anthropic.api_key') ?? env('ANTHROPIC_API_KEY', '');
        $this->model  = config('services.anthropic.model', 'claude-sonnet-4-6');
    }

    public function enrichFleetReport(array $data): array
    {
        if (empty($this->apiKey)) {
            Log::warning('geofact.report.ai.missing_key');
            return $this->fallback();
        }

        $prompt = $this->buildFleetPrompt($data);

        return $this->callApi($prompt);
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
            Log::warning('geofact.report.ai.json_missing', [
                'raw_prefix' => mb_substr($text, 0, 300),
            ]);
            return $this->fallback();
        }

        $parsed = json_decode($matches[0], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('geofact.report.ai.json_invalid', [
                'json_error' => json_last_error_msg(),
                'raw_prefix' => mb_substr($text, 0, 300),
            ]);
            return $this->fallback();
        }

        return [
            'summary'         => $parsed['summary'] ?? '',
            'anomalies'       => (array) ($parsed['anomalies'] ?? []),
            'recommendations' => (array) ($parsed['recommendations'] ?? []),
        ];
    }

    public function enrichVehicleReport(array $data): array
    {
        if (empty($this->apiKey)) {
            Log::warning('geofact.report.ai.missing_key', ['report_type' => 'vehicle']);
            return $this->fallback();
        }

        $ruleLines = '';
        foreach ($data['alerts_by_rule'] ?? [] as $rule => $count) {
            $ruleLines .= "  - {$rule} : {$count} alerte(s)\n";
        }

        $freshnessLabel = match ($data['last_seen_freshness'] ?? null) {
            'fresh'   => 'fraîche (< 5 min)',
            'delayed' => 'en retard (5–60 min)',
            'stale'   => '⚠ OBSOLÈTE (> 1 h)',
            default   => 'fraîcheur inconnue',
        };
        $locationLine = ($data['last_seen_latitude'] !== null && $data['last_seen_longitude'] !== null)
            ? "DERNIÈRE POSITION OFFICIELLE IKOMA : lat={$data['last_seen_latitude']}, lon={$data['last_seen_longitude']}, vitesse={$data['last_seen_speed_kmh']} km/h, moteur=" . ($data['last_seen_ignition'] ? 'allumé' : 'éteint') . " — relevé le {$data['last_seen_at']} [{$freshnessLabel}]"
            : "DERNIÈRE POSITION OFFICIELLE IKOMA : non disponible (aucun signal GPS intégré)";

        $prompt = <<<PROMPT
Tu es analyste expert en gestion de flotte pour PME africaines.
Analyse les données suivantes pour UN véhicule et génère un rapport structuré en français.

VÉHICULE : {$data['vehicle_plate']} ({$data['vehicle_brand']} {$data['vehicle_model']} {$data['vehicle_year']})
FLOTTE : {$data['fleet_name']}
{$locationLine}
PÉRIODE : {$data['period_from']} → {$data['period_to']}

ACTIVITÉ :
- Trajets effectués : {$data['total_trips']}
- Distance totale : {$data['total_km']} km
- Heures de conduite : {$data['total_hours']} h
- Jours actifs : {$data['active_days']}
- Vitesse moyenne : {$data['avg_speed_kmh']} km/h
- Vitesse maximale : {$data['max_speed_kmh']} km/h
- Trajets anomaleux : {$data['anomalous_trips']}
- Dernière localisation : {$data['last_seen_at']}

ALERTES ({$data['alerts_total']} total, {$data['alerts_unresolved']} non résolues) :
- Critiques : {$data['alerts_critical']} | Hautes : {$data['alerts_high']} | Moyennes : {$data['alerts_medium']} | Basses : {$data['alerts_low']}
Par règle :
{$ruleLines}

Génère une réponse JSON avec exactement ces 3 clés :
{
  "summary": "Résumé exécutif en 3-4 phrases sur l'utilisation et l'état du véhicule.",
  "anomalies": ["point 1", "point 2", "point 3"],
  "recommendations": ["recommandation 1", "recommandation 2", "recommandation 3"]
}

Règles : sois factuel, mets en avant les risques, recommandations actionnables immédiatement.
PROMPT;

        return $this->callApi($prompt);
    }

    public function enrichDriverReport(array $data): array
    {
        if (empty($this->apiKey)) {
            Log::warning('geofact.report.ai.missing_key', ['report_type' => 'driver']);
            return $this->fallback();
        }

        $ruleLines = '';
        foreach ($data['alerts_by_rule'] ?? [] as $rule => $count) {
            $ruleLines .= "  - {$rule} : {$count} alerte(s)\n";
        }

        $prompt = <<<PROMPT
Tu es analyste expert en gestion de flotte pour PME africaines.
Analyse les données suivantes pour UN conducteur et génère un rapport structuré en français.

CONDUCTEUR : {$data['driver_name']}
Permis : {$data['license_number']} (expire le {$data['license_expiry']})
PÉRIODE : {$data['period_from']} → {$data['period_to']}

ACTIVITÉ :
- Trajets effectués : {$data['total_trips']}
- Distance totale : {$data['total_km']} km
- Heures de conduite : {$data['total_hours']} h
- Jours actifs : {$data['active_days']}
- Vitesse moyenne : {$data['avg_speed_kmh']} km/h
- Vitesse maximale : {$data['max_speed_kmh']} km/h
- Trajets anomaleux : {$data['anomalous_trips']}
- Score de sécurité : {$data['safety_score']}/100
- Véhicules utilisés : {$data['vehicles_count']}

ALERTES ({$data['alerts_total']} total, {$data['alerts_unresolved']} non résolues) :
- Critiques : {$data['alerts_critical']} | Hautes : {$data['alerts_high']} | Moyennes : {$data['alerts_medium']} | Basses : {$data['alerts_low']}
Par règle :
{$ruleLines}

Génère une réponse JSON avec exactement ces 3 clés :
{
  "summary": "Résumé exécutif en 3-4 phrases sur le comportement de conduite.",
  "anomalies": ["point 1", "point 2", "point 3"],
  "recommendations": ["recommandation 1", "recommandation 2", "recommandation 3"]
}

Règles : sois factuel, évalue le niveau de risque du conducteur, recommandations actionnables.
PROMPT;

        return $this->callApi($prompt);
    }

    private function callApi(string $prompt): array
    {
        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model'      => $this->model,
                'max_tokens' => 1024,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]);

            if (! $response->successful()) {
                Log::error('geofact.report.ai.api_error', [
                    'status'      => $response->status(),
                    'body_prefix' => mb_substr($response->body(), 0, 500),
                ]);
                return $this->fallback();
            }

            $text = $response->json('content.0.text', '');

            if (trim((string) $text) === '') {
                Log::error('geofact.report.ai.empty_response', [
                    'status'      => $response->status(),
                    'body_prefix' => mb_substr($response->body(), 0, 500),
                ]);
                return $this->fallback();
            }

            return $this->parseAiResponse($text);
        } catch (\Throwable $e) {
            Log::error('geofact.report.ai.exception', ['error' => $e->getMessage()]);
            return $this->fallback();
        }
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
