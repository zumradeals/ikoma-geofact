<?php

namespace App\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class TokenVersionGuard
{
    /**
     * Vérifie que la token_version du JWT correspond à celle en base.
     * Toute version inférieure est rejetée immédiatement — révocation par rotation.
     */
    public function check(string $token, User $user): bool
    {
        try {
            $payload = JWTAuth::setToken($token)->getPayload();
            $tokenVersion = $payload->get('token_version');

            if ($tokenVersion === null || (int) $tokenVersion < $user->token_version) {
                Log::warning('geofact.auth.token_version_rejected', [
                    'user_id'       => $user->id,
                    'token_version' => $tokenVersion,
                    'db_version'    => $user->token_version,
                ]);

                $this->logAuditViolation($user);
                return false;
            }

            return true;
        } catch (JWTException $e) {
            Log::warning('geofact.auth.token_version_check_failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function logAuditViolation(User $user): void
    {
        try {
            AuditLog::create([
                'id'              => Str::uuid()->toString(),
                'actor_id'        => $user->id,
                'actor_role'      => $user->role,
                'organization_id' => $user->organization_id,
                'action'          => 'token_version_rejected',
                'resource_type'   => 'auth',
                'resource_id'     => $user->id,
                'result'          => 'forbidden',
                'ip_address'      => request()->ip(),
                'user_agent'      => request()->userAgent(),
                'payload'         => ['reason' => 'token_version_mismatch'],
                'created_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('geofact.audit.write_failed', ['error' => $e->getMessage()]);
        }
    }
}
