<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    public function __construct(
        private readonly AuditDataSanitizer $sanitizer,
    ) {}

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
            'old_values' => $this->sanitizer->sanitize($oldValues),
            'new_values' => $this->sanitizer->sanitize($newValues),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit(
                (string) $request->userAgent(),
                2000,
                '',
            ),
            'url' => $this->sanitizer->sanitizeUrl($request),
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
}
