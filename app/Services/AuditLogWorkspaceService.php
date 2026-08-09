<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditLogPresenter;
use App\Support\DisplayDateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLogWorkspaceService
{
    public function __construct(
        private readonly AuditDataSanitizer $sanitizer,
    ) {}

    /**
     * @param  array{
     *     search: string,
     *     actorId: int,
     *     module: string,
     *     event: string,
     *     method: string,
     *     requestId: string,
     *     from: string,
     *     until: string,
     *     perPage: int
     * }  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        $query = $this->filteredQuery($filters)->with([
            'actor' => static function (BelongsTo $actorQuery): void {
                $actorQuery->select([
                    'id',
                    'name',
                    'employee_number',
                    'work_unit',
                    'deleted_at',
                ]);
            },
        ]);
        $summaryQuery = clone $query;

        return [
            'auditLogs' => $query
                ->latest('created_at')
                ->latest('id')
                ->paginate($filters['perPage'])
                ->withQueryString(),
            'actors' => User::query()
                ->withTrashed()
                ->whereHas('auditLogs')
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'employee_number',
                    'deleted_at',
                ]),
            'moduleOptions' => AuditLog::query()
                ->select('module')
                ->distinct()
                ->orderBy('module')
                ->pluck('module'),
            'eventOptions' => AuditLog::query()
                ->when(
                    $filters['module'] !== '',
                    static fn (Builder $eventQuery): Builder => $eventQuery
                        ->where('module', $filters['module']),
                )
                ->select('event')
                ->distinct()
                ->orderBy('event')
                ->pluck('event'),
            'filters' => $filters,
            'summary' => [
                'activities' => (clone $summaryQuery)->count(),
                'actors' => (clone $summaryQuery)
                    ->whereNotNull('actor_id')
                    ->distinct()
                    ->count('actor_id'),
                'modules' => (clone $summaryQuery)
                    ->distinct()
                    ->count('module'),
                'system' => (clone $summaryQuery)
                    ->whereNull('actor_id')
                    ->count(),
            ],
            'displayTimezone' => (string) config(
                'simantap.display_timezone',
                'Asia/Jakarta',
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detailData(AuditLog $auditLog): array
    {
        $auditLog->load([
            'actor' => static function (BelongsTo $actorQuery): void {
                $actorQuery->select([
                    'id',
                    'name',
                    'employee_number',
                    'work_unit',
                    'position',
                    'deleted_at',
                ]);
            },
        ]);

        $oldValues = $this->sanitizer->redact($auditLog->old_values) ?? [];
        $newValues = $this->sanitizer->redact($auditLog->new_values) ?? [];
        $keys = array_values(array_unique([
            ...array_keys($oldValues),
            ...array_keys($newValues),
        ], SORT_REGULAR));
        sort($keys, SORT_NATURAL | SORT_FLAG_CASE);

        $changes = array_map(
            static fn (string|int $key): array => [
                'field' => AuditLogPresenter::fieldLabel($key),
                'hasOld' => array_key_exists($key, $oldValues),
                'hasNew' => array_key_exists($key, $newValues),
                'old' => AuditLogPresenter::formatValue($oldValues[$key] ?? null),
                'new' => AuditLogPresenter::formatValue($newValues[$key] ?? null),
            ],
            $keys,
        );

        return [
            'auditLog' => $auditLog,
            'changes' => $changes,
            'safeUrl' => $this->sanitizer->redactStoredUrl($auditLog->url),
            'displayTimezone' => (string) config(
                'simantap.display_timezone',
                'Asia/Jakarta',
            ),
        ];
    }

    /**
     * @param  array{
     *     search: string,
     *     actorId: int,
     *     module: string,
     *     event: string,
     *     method: string,
     *     requestId: string,
     *     from: string,
     *     until: string,
     *     perPage: int
     * }  $filters
     * @return Builder<AuditLog>
     */
    private function filteredQuery(array $filters): Builder
    {
        $bounds = DisplayDateRange::utcBounds(
            $filters['from'],
            $filters['until'],
        );

        return AuditLog::query()
            ->when(
                $filters['search'] !== '',
                static function (Builder $auditQuery) use ($filters): void {
                    $search = $filters['search'];
                    $auditQuery->where(function (Builder $nested) use ($search): void {
                        $nested
                            ->where('event', 'like', "%{$search}%")
                            ->orWhere('module', 'like', "%{$search}%")
                            ->orWhere('ip_address', 'like', "%{$search}%")
                            ->orWhere('url', 'like', "%{$search}%")
                            ->orWhere('user_agent', 'like', "%{$search}%")
                            ->orWhere('request_id', 'like', "%{$search}%")
                            ->orWhereHas(
                                'actor',
                                static function (Builder $actorQuery) use ($search): void {
                                    $actorQuery->where(function (Builder $actorSearch) use ($search): void {
                                        $actorSearch
                                            ->where('name', 'like', "%{$search}%")
                                            ->orWhere('employee_number', 'like', "%{$search}%")
                                            ->orWhere('work_unit', 'like', "%{$search}%");
                                    });
                                },
                            );
                    });
                },
            )
            ->when(
                $filters['actorId'] > 0,
                static fn (Builder $auditQuery): Builder => $auditQuery
                    ->where('actor_id', $filters['actorId']),
            )
            ->when(
                $filters['module'] !== '',
                static fn (Builder $auditQuery): Builder => $auditQuery
                    ->where('module', $filters['module']),
            )
            ->when(
                $filters['event'] !== '',
                static fn (Builder $auditQuery): Builder => $auditQuery
                    ->where('event', $filters['event']),
            )
            ->when(
                $filters['method'] !== '',
                static fn (Builder $auditQuery): Builder => $auditQuery
                    ->where('http_method', $filters['method']),
            )
            ->when(
                $filters['requestId'] !== '',
                static fn (Builder $auditQuery): Builder => $auditQuery
                    ->where('request_id', $filters['requestId']),
            )
            ->when(
                $bounds['from'] !== null,
                static fn (Builder $auditQuery): Builder => $auditQuery
                    ->where('created_at', '>=', $bounds['from']),
            )
            ->when(
                $bounds['until'] !== null,
                static fn (Builder $auditQuery): Builder => $auditQuery
                    ->where('created_at', '<=', $bounds['until']),
            );
    }
}
