<?php

namespace App\RawStore;

use App\Exceptions\ContractViolationException;
use App\Models\RawStore;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RawStoreWriter
{
    /**
     * Écrit une entrée dans le Raw Store — INSERT uniquement, jamais UPDATE.
     * Contrat C-01.3 : toute donnée reçue est d'abord écrite ici avant tout traitement.
     *
     * @param  string $connectorId   Identifiant du Connector source
     * @param  string $organizationId Tenant du Connector
     * @param  string $payload        Données brutes exactes reçues — jamais modifiées
     * @param  string $format         json|xml|csv|binary|unknown
     * @param  string $flag           OK|INCOMPLETE|REJECTED|CORRUPTED|DUPLICATE_TRANSPORT
     * @return string                 UUID du raw_store créé (raw_ref)
     *
     * @throws \RuntimeException si l'écriture échoue (loggé + propagé)
     */
    public function write(
        string $connectorId,
        string $organizationId,
        string $payload,
        string $format = 'json',
        string $flag = 'OK'
    ): string {
        $id = Str::uuid()->toString();

        try {
            RawStore::create([
                'id'              => $id,
                'connector_id'    => $connectorId,
                'organization_id' => $organizationId,
                'received_at'     => now(),
                'payload_raw'     => $payload,  // stocké tel quel — jamais normalisé
                'payload_format'  => $format,
                'flag'            => $flag,
                'canonical_ref'   => null,
                'processed_at'    => null,
            ]);

            return $id;

        } catch (\Throwable $e) {
            // Échec Raw Store = événement système critique (C-01 : aucune donnée ignorée silencieusement)
            Log::critical('geofact.rawstore.write_failed', [
                'connector_id'    => $connectorId,
                'organization_id' => $organizationId,
                'flag'            => $flag,
                'error'           => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                "system.rawstore.write.failed : [{$connectorId}] {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Met à jour le flag d'un enregistrement Raw Store existant.
     *
     * NOTE : raw_store est immuable — seul le flag et canonical_ref peuvent être
     * mis à jour par le pipeline Connector (cas DUPLICATE_TRANSPORT, REJECTED).
     * Cette opération est effectuée via DB::statement pour contourner le boot()
     * du modèle Eloquent (qui bloque tout UPDATE) — exception contractuelle explicite.
     */
    public function updateFlag(string $rawStoreId, string $flag, ?string $canonicalRef = null): void
    {
        try {
            \Illuminate\Support\Facades\DB::table('raw_store')
                ->where('id', $rawStoreId)
                ->update(array_filter([
                    'flag'          => $flag,
                    'canonical_ref' => $canonicalRef,
                    'processed_at'  => now(),
                ], fn($v) => $v !== null));

        } catch (\Throwable $e) {
            Log::error('geofact.rawstore.flag_update_failed', [
                'raw_store_id' => $rawStoreId,
                'flag'         => $flag,
                'error'        => $e->getMessage(),
            ]);
        }
    }
}
