<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::AuditLogView->value) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'actor' => ['nullable', 'integer', 'exists:users,id'],
            'module' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', 'string', 'max:100'],
            'method' => [
                'nullable',
                Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']),
            ],
            'request_id' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'until' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::when($this->filled('from'), 'after_or_equal:from'),
            ],
            'per_page' => ['nullable', Rule::in([15, 30, 50])],
        ];
    }

    /**
     * @return array{
     *     search: string,
     *     actorId: int,
     *     module: string,
     *     event: string,
     *     method: string,
     *     requestId: string,
     *     from: string,
     *     until: string,
     *     perPage: int
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'search' => trim((string) ($validated['q'] ?? '')),
            'actorId' => (int) ($validated['actor'] ?? 0),
            'module' => trim((string) ($validated['module'] ?? '')),
            'event' => trim((string) ($validated['event'] ?? '')),
            'method' => (string) ($validated['method'] ?? ''),
            'requestId' => (string) ($validated['request_id'] ?? ''),
            'from' => (string) ($validated['from'] ?? ''),
            'until' => (string) ($validated['until'] ?? ''),
            'perPage' => (int) ($validated['per_page'] ?? 15),
        ];
    }
}
