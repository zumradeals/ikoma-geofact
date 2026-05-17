<?php

namespace App\Core\Deduplication;

use App\Models\TelemetryEvent;
use Illuminate\Support\Facades\Log;

class CanonicalDeduplicator
{
    /**
     * Vérifie si un event_id a déjà été traité par le Core.
     * Contrat C-10.5 : détecte les doublons canoniques — loggué, jamais d'exception.
     *
     * @return bool true si doublon détecté
     */
    public function isDuplicate(string $eventId): bool
    {
        $exists = TelemetryEvent::where('id', $eventId)->exists();

        if ($exists) {
            Log::info('geofact.core.dedup.duplicate_canonical', [
                'event_id' => $eventId,
                'flag'     => 'DUPLICATE_CANONICAL',
            ]);
            return true;
        }

        return false;
    }
}
