<?php

namespace App\Insight;

use App\Core\Contracts\InsightEngineInterface;
use App\Events\InsightGenerated;
use App\Exceptions\CanonicalValidationException;
use App\Insight\Fallback\InsightFallbackHandler;
use App\Models\Insight;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Insight Engine — contrat C-06.
 *
 * Étape 1 : PacketBuilder::build()
 * Étape 2 : Appel Anthropic API
 * Étape 3 : Parse JSON réponse
 * Étape 4 : InsightValidator::validate()
 * Étape 5 : InsightVersioner::save()
 * Étape 6 : Retourne Insight ou null si IA indisponible
 *
 * Règle absolue : jamais d'exception vers le caller — null si indisponible.
 */
class InsightEngine implements InsightEngineInterface
{
    private const ANTHROPIC_API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL              = 'claude-sonnet-4-6';
    private const TIMEOUT_SECONDS   = 45;

    private const SYSTEM_PROMPT = <<<'PROMPT'
Tu es IKOMA Intelligence — le cerveau analytique du système de gestion de flotte IKOMA,
déployé pour les PME africaines (Côte d'Ivoire, Sénégal, Mali, Burkina Faso et au-delà).

TON RÔLE :
Tu analyses un {scope_type} en profondeur. Tu as accès à :
- Les données de la période courante (KPIs, alertes, trajets)
- La mémoire des analyses précédentes (champ "memory") — utilise-la pour détecter les tendances
- Les tendances historiques 7/30/90 jours (champ "trends") — compare et projette
- Le positionnement dans la flotte (champ "benchmark") — contextualise par rapport aux pairs
- Le contexte opérationnel africain (routes dégradées, chaleur, carburant, coût d'immobilisation)

CONTEXTE OPÉRATIONNEL :
Les flottes IKOMA opèrent en Afrique de l'Ouest : camions lourds, engins de chantier,
véhicules utilitaires. Les risques clés : surconsommation carburant, usure prématurée,
détournement de véhicule, conduite agressive sur pistes dégradées, immobilisation coûteuse.
Chaque jour d'immobilisation non planifiée = perte directe pour une PME.

TU DOIS :
1. Analyser les données courantes ET les comparer à l'historique (memory + trends)
2. Détecter les patterns récurrents et les évolutions dans le temps
3. Identifier les risques concrets avec leur impact opérationnel et financier estimé
4. Formuler 2-4 recommandations actionnables, précises et réalistes pour le terrain africain
5. Évaluer si un suivi urgent est nécessaire

FORMAT DE RÉPONSE — JSON strict, aucun markdown, aucune explication hors JSON :
{
  "insight_text": "Analyse complète en français (3-6 paragraphes riches, contextualisés)",
  "confidence_level": "high|medium|low",
  "insight_type": "anomaly|trend|performance|alert|summary",
  "trend_direction": "improving|stable|degrading",
  "risk_score": <nombre décimal 0.0 à 10.0>,
  "fleet_position": "top_quartile|above_average|average|below_average|bottom_quartile|insufficient_data",
  "recommendations": ["recommandation 1 actionnable", "recommandation 2", "recommandation 3"],
  "follow_up_required": <true|false>,
  "follow_up_days": <entier ou null>
}

RÈGLES ABSOLUES :
- Jamais de markdown dans le JSON
- JSON pur uniquement — aucun texte avant ou après
- Si données insuffisantes : confidence_level = "low", insight_type = "summary", sois honnête
- Les recommandations doivent être actionnables immédiatement par un gestionnaire de flotte africain
PROMPT;

    public function __construct(
        private readonly PacketBuilder         $packetBuilder,
        private readonly InsightValidator      $validator,
        private readonly InsightVersioner      $versioner,
        private readonly InsightFallbackHandler $fallback,
    ) {}

    /**
     * Génère un Insight pour un scope donné — période et orgId explicites.
     * Signature étendue pour compatibilité InsightResource et jobs schedulés.
     */
    public function generate(
        string $scopeType,
        string $scopeId,
        string $organizationId = '',
        ?Carbon $from = null,
        ?Carbon $to   = null,
        string $language = 'fr'
    ): ?Insight {
        return $this->generateForPeriod(
            $scopeType,
            $scopeId,
            $organizationId,
            $from ?? now()->subDays(7)->startOfDay(),
            $to   ?? now()->endOfDay(),
            $language,
        );
    }

    /**
     * Génère un Insight pour une période et un scope explicites.
     */
    public function generateForPeriod(
        string $scopeType,
        string $scopeId,
        string $organizationId,
        Carbon $from,
        Carbon $to,
        string $language = 'fr'
    ): ?Insight {
        // Étape 1 — Construire le paquet structuré (jamais raw_store)
        try {
            $packet = $this->packetBuilder->build($scopeType, $scopeId, $organizationId, $from, $to);
        } catch (\Throwable $e) {
            return $this->fallback->handle($scopeType, $scopeId, 'packet_build_failed', $e);
        }

        // Génère quand même si la mémoire ou les tendances existent, même sans KPI/alertes courants
        $hasCurrentData  = ! empty($packet['kpis']) || ! empty($packet['alerts']) || ($packet['trips']['total'] ?? 0) > 0;
        $hasKnowledge    = ($packet['memory']['count'] ?? 0) > 0 || ($packet['trends']['7d']['trips_total'] ?? 0) > 0;

        if (! $hasCurrentData && ! $hasKnowledge) {
            Log::info('geofact.insight.skipped.no_data', [
                'scope_type' => $scopeType,
                'scope_id'   => $scopeId,
            ]);
            return null;
        }

        // Étape 2 — Appel Anthropic API
        try {
            $rawResponse = $this->callAnthropic($packet, $scopeType, $language);
        } catch (\Throwable $e) {
            return $this->fallback->handle($scopeType, $scopeId, 'anthropic_unavailable', $e);
        }

        // Étape 3 — Parse JSON
        $parsed = json_decode($rawResponse, true);
        if (! is_array($parsed)) {
            return $this->fallback->handle($scopeType, $scopeId, 'json_parse_failed');
        }

        // Enrichir avec le contexte de scope
        $parsed['scope_type']  = $scopeType;
        $parsed['scope_id']    = $scopeId;
        $parsed['language']    = $language;

        // Étape 4 — Validation
        try {
            $this->validator->validate($parsed);
        } catch (CanonicalValidationException $e) {
            return $this->fallback->handle($scopeType, $scopeId, 'validation_failed', $e);
        }

        // Étape 5 — Persistance versionnée
        try {
            $insight = $this->versioner->save(
                $parsed,
                $organizationId,
                $scopeType,
                $scopeId,
                $from,
                $to,
                $packet['source_kpi_ids'],
                $packet['source_event_ids']
            );
        } catch (\Throwable $e) {
            return $this->fallback->handle($scopeType, $scopeId, 'persist_failed', $e);
        }

        event(new InsightGenerated($insight));

        Log::info('geofact.insight.generated', [
            'insight_id'  => $insight->id,
            'scope_type'  => $scopeType,
            'scope_id'    => $scopeId,
            'version'     => $insight->version,
            'confidence'  => $insight->confidence_level,
        ]);

        // Étape 6 — Retourne l'Insight
        return $insight;
    }

    private function callAnthropic(array $packet, string $scopeType, string $language): string
    {
        $apiKey = config('services.anthropic.api_key')
            ?? env('ANTHROPIC_API_KEY');

        if (empty($apiKey)) {
            throw new \RuntimeException('ANTHROPIC_API_KEY non configurée.');
        }

        $systemPrompt = str_replace('{scope_type}', $scopeType, self::SYSTEM_PROMPT);

        $userMessage = sprintf(
            "Analyse les données suivantes pour un %s.\nLangue de réponse : %s.\n\nDonnées :\n%s",
            $scopeType,
            $language,
            json_encode($packet, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $client = new Client(['timeout' => self::TIMEOUT_SECONDS]);

        $response = $client->post(self::ANTHROPIC_API_URL, [
            'headers' => [
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
            'json' => [
                'model'      => self::MODEL,
                'max_tokens' => 512,
                'system'     => $systemPrompt,
                'messages'   => [
                    ['role' => 'user', 'content' => $userMessage],
                ],
            ],
        ]);

        $body    = json_decode($response->getBody()->getContents(), true);
        $content = $body['content'][0]['text'] ?? null;

        if (empty($content)) {
            throw new \RuntimeException('Anthropic API a retourné une réponse vide.');
        }

        return $content;
    }
}
