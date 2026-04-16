<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class ChatbotTokenService
{
    public function issueToken(Tenant $tenant, string $sessionId): array
    {
        $now = now();
        $expiresAt = $now->copy()->addMinutes(60);
        $nonce = Str::random(32);

        Cache::put($this->nonceKey($tenant->id, $nonce), true, $expiresAt);

        $payload = [
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'session_id' => $sessionId,
            'iat' => $now->timestamp,
            'exp' => $expiresAt->timestamp,
            'nonce' => $nonce,
        ];

        return [
            'token' => Crypt::encryptString(json_encode($payload)),
            'expires_at' => $expiresAt->toIso8601String(),
            'session_id' => $sessionId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function parseToken(string $token): array
    {
        $payload = json_decode(Crypt::decryptString($token), true);

        if (! is_array($payload)) {
            throw new \InvalidArgumentException('Invalid token payload.');
        }

        foreach (['tenant_id', 'tenant_slug', 'session_id', 'iat', 'exp', 'nonce'] as $field) {
            if (! array_key_exists($field, $payload)) {
                throw new \InvalidArgumentException('Invalid token payload.');
            }
        }

        if ($payload['exp'] < now()->timestamp) {
            Cache::forget($this->nonceKey((int) $payload['tenant_id'], (string) $payload['nonce']));
            throw new \InvalidArgumentException('Token expired.');
        }

        if (! Cache::has($this->nonceKey((int) $payload['tenant_id'], (string) $payload['nonce']))) {
            throw new \InvalidArgumentException('Token revoked.');
        }

        return $payload;
    }

    private function nonceKey(int $tenantId, string $nonce): string
    {
        return "chatbot_nonce.{$tenantId}.{$nonce}";
    }
}
