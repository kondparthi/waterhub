<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    private const REDACT = ['password', 'remember_token', 'otp', 'otp_hash', 'token'];

    public static function log(string $action, ?Model $entity = null, ?array $before = null, ?array $after = null, ?int $actorId = null): void
    {
        AuditLog::create([
            'actor_user_id' => $actorId ?? auth()->id(),
            'action' => $action,
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey(),
            'before_json' => $before ? self::clean($before) : null,
            'after_json' => $after ? self::clean($after) : null,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    private static function clean(array $data): array
    {
        foreach (self::REDACT as $k) unset($data[$k]);
        return $data;
    }
}
