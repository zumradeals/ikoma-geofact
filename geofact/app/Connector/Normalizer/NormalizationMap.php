<?php

namespace App\Connector\Normalizer;

class NormalizationMap
{
    /**
     * Dictionnaire de mapping par provider_id.
     * Contrat C-10 : le Connector traduit les champs fournisseur → modèle canonique.
     * Le Core ne connaît jamais les noms de champs propriétaires.
     *
     * Format : provider_id => [champ_fournisseur => champ_canonique]
     */
    private const MAPS = [
        // Mapping Wialon — les messages sont aplatis par WialonApiClient::flattenMessage()
        // avant d'arriver ici (pos.y → latitude, pos.x → longitude, etc.)
        'wialon' => [
            'latitude'            => 'latitude',           // pos.y aplati par flattenMessage
            'longitude'           => 'longitude',          // pos.x aplati par flattenMessage
            'speed_kmh'           => 'speed_kmh',          // pos.s aplati par flattenMessage
            'heading'             => 'heading',            // pos.c aplati par flattenMessage
            'altitude_m'         => 'altitude_m',          // pos.z aplati par flattenMessage
            'timestamp'           => 'timestamp',          // t aplati par flattenMessage
            'fuel_level_pct'      => 'fuel_level_pct',     // p.can_fuel_litres aplati (si présent)
            'temperature_celsius' => 'temperature_celsius',// p.engine_temp aplati (si présent)
            'ignition'            => 'ignition',           // i aplati (bitfield — 1 = on)
            'device_id'           => 'device_id',          // deviceId injecté par flattenMessage
            'event_type'          => 'event_type',          // injecté par WialonSyncJob
            'vehicle_id'          => 'vehicle_id',          // injecté depuis WialonUnitMapping
        ],
        'traccar' => [
            'speed'      => 'speed_kmh',
            'deviceTime' => 'timestamp',
            'latitude'   => 'latitude',
            'longitude'  => 'longitude',
            'altitude'   => 'altitude_m',
            'course'     => 'heading',
            'fuel'       => 'fuel_level_pct',
        ],
        'teltonika' => [
            'spd'     => 'speed_kmh',
            'lat'     => 'latitude',
            'lng'     => 'longitude',
            'imei'    => 'device_id',
            'ts'      => 'timestamp',
            'alt'     => 'altitude_m',
            'ang'     => 'heading',
            'ign'     => 'ignition',
            'fuel'    => 'fuel_level_pct',
            'temp'    => 'temperature_celsius',
        ],
        'custom' => [
            // Mapping identité — les champs arrivent déjà normalisés
            'speed_kmh'           => 'speed_kmh',
            'latitude'            => 'latitude',
            'longitude'           => 'longitude',
            'timestamp'           => 'timestamp',
            'altitude_m'          => 'altitude_m',
            'heading'             => 'heading',
            'fuel_level_pct'      => 'fuel_level_pct',
            'temperature_celsius' => 'temperature_celsius',
            'ignition'            => 'ignition',
            'device_id'           => 'device_id',
            'event_type'          => 'event_type',
        ],
    ];

    /**
     * Retourne la map de normalisation pour un provider donné.
     * Retourne le mapping 'custom' (identité) si le provider est inconnu.
     */
    public function getMap(string $providerId): array
    {
        return self::MAPS[$providerId] ?? self::MAPS['custom'];
    }

    /**
     * Vérifie si un provider est supporté nativement.
     */
    public function isSupported(string $providerId): bool
    {
        return array_key_exists($providerId, self::MAPS);
    }
}
