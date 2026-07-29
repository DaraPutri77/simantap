<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        '_token',
        'authorization',
        'cookie',
        'current_password',
        'new_password',
        'new_password_confirmation',
        'password',
        'password_confirmation',
        'remember_token',
        'signature',
        'signature_data',
        'token',
        'token_hash',
    ];

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        string $event,
        string $module,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null,
        ?int $actorId = null,
    ): AuditLog {
        $request ??= request();

        return AuditLog::query()->create([
            'request_id' => $this->resolveRequestId($request),
            'actor_id' => $actorId
                ?? $request->user()?->getAuthIdentifier(),
            'event' => $event,
            'module' => $module,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit(
                (string) $request->userAgent(),
                2000,
                '',
            ),
            'url' => Str::limit(
                $request->fullUrl(),
                2048,
                '',
            ),
            'http_method' => Str::limit(
                Str::upper($request->method()),
                10,
                '',
            ),
        ]);
    }

    private function resolveRequestId(Request $request): string
    {
        $attributeRequestId = $request->attributes->get(
            'simantap_request_id',
        );

        $headerRequestId = $request->headers->get('X-Request-ID');

        $requestId = is_string($attributeRequestId)
            ? $attributeRequestId
            : (is_string($headerRequestId) ? $headerRequestId : '');

        if (! Str::isUuid($requestId)) {
            $requestId = (string) Str::uuid();
        }

        $request->attributes->set(
            'simantap_request_id',
            $requestId,
        );

        return $requestId;
    }

    /**
     * @param  array<array-key, mixed>|null  $values
     * @return array<array-key, mixed>|null
     */
    private function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $sanitized = [];

        foreach ($values as $key => $value) {
            if (
                is_string($key)
                && in_array(
                    Str::lower($key),
                    self::SENSITIVE_KEYS,
                    true,
                )
            ) {
                continue;
            }

            $sanitized[$key] = is_array($value)
                ? $this->sanitize($value)
                : $value;
        }

        return $sanitized;
    }
}
