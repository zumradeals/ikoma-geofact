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
    private const MODEL              = 'claude-sonnet-4-20250514';
    private const TIMEOUT_SECONDS   = 30;

    private const SYSTEM_PROMPT = <<<'PROMPT'
Tu es l'Insight Engine de GEOFACT.
Tu reçois des données structurées sur un {scope_type}.
Tu ne détectes pas — tu interprètes.
Réponds UNIQUEMENT en JSON :
insight_text (string), confidence_level (high|medium|low),
insight_type (anomaly|trend|performance|alert|summary).
Pas de markdown. JSON pur.
PROMPT;

    public function __construct(
        private readonly PacketBuilder         $packetBuilder,
        private readonly InsightValidator      $validator,
        private readonly InsightVersioner      $versioner,
        private readonly InsightFallbackHandler $fallback,
    ) {}

    /**
     * Implémentation InsightEngineInterface (C-06).
     * Génère un Insight pour le scope donné sur les 7 derniers jours par défaut.
     */
    public function generate(string $scopeType, string $scopeId): ?Insight
    {
        return $this->generateForPeriod(
            $scopeType,
            $scopeId,
            organizationId: '',   // résolu via PacketBuilder en contexte caller
            from: now()->subDays(7)->startOfDay(),
            to: now()->endOfDay(),
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

        if (empty($packet['kpis']) && empty($packet['alerts'])) {
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
