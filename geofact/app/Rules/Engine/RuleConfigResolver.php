<?php

namespace App\Rules\Engine;

use App\Models\RuleConfig;
use Illuminate\Support\Facades\Cache;

/**
 * Résout la configuration d'une règle avec cascade :
 *   1. Config spécifique à l'organisation  (organization_id = $orgId)
 *   2. Config par défaut système            (organization_id IS NULL)
 *   3. Constantes PHP de secours            (défauts absolus)
 *
 * Cache 5 minutes pour éviter les requêtes SQL sur chaque event.
 */
class RuleConfigResolver
{
    private const FALLBACKS = [
        'RS01' => ['threshold_kmh' => 90,    'severity_medium_pct' => 20, 'severity_high_pct' => 40, 'is_enabled' => true],
        'RS02' => ['threshold_hours' => 4,                                                             'is_enabled' => true],
        'RS03' => ['deceleration_ms2' => 7.0,                                                          'is_enabled' => true],
        'RS04' => ['alert_on_entry' => true,  'alert_on_exit' => false,                                'is_enabled' => true],
        'RS05' => ['threshold_km' => 10000,                                                            'is_enabled' => true],
    ];

    public function resolve(string $ruleId, ?string $orgId): array
    {
        $cacheKey = "rule_config.{$ruleId}." . ($orgId ?? 'system');

        return Cache::remember($cacheKey, 300, function () use ($ruleId, $orgId) {
            // 1. Config org-spécifique
            if ($orgId) {
                $orgConfig = RuleConfig::where('rule_id', $ruleId)
                    ->where('organization_id', $orgId)
                    ->first();

                if ($orgConfig) {
                    return array_merge(
                        self::FALLBACKS[$ruleId] ?? [],
                        $orgConfig->params,
                        ['is_enabled' => $orgConfig->is_enabled]
                    );
                }
            }

            // 2. Config système par défaut
            $systemConfig = RuleConfig::where('rule_id', $ruleId)
                ->whereNull('organization_id')
                ->first();

            if ($systemConfig) {
                return array_merge(
                    self::FALLBACKS[$ruleId] ?? [],
                    $systemConfig->params,
                    ['is_enabled' => $systemConfig->is_enabled]
                );
            }

            // 3. Fallback PHP absolu
            return self::FALLBACKS[$ruleId] ?? ['is_enabled' => true];
        });
    }

    public function isEnabled(string $ruleId, ?string $orgId): bool
    {
        return (bool) ($this->resolve($ruleId, $orgId)['is_enabled'] ?? true);
    }

    public static function clearCache(string $ruleId, ?string $orgId = null): void
    {
        if ($orgId) {
            Cache::forget("rule_config.{$ruleId}.{$orgId}");
        }
        Cache::forget("rule_config.{$ruleId}.system");
    }
}
