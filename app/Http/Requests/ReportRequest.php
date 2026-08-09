<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Enums\StockMovementType;
use App\Support\ReportCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::ReportView->value) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reportFromRoute = (string) $this->route('report', '');

        return [
            'report' => [
                $reportFromRoute === '' ? 'nullable' : 'required',
                Rule::in(ReportCatalog::keys()),
            ],
            'q' => ['nullable', 'string', 'max:255'],
            'item' => ['nullable', 'integer', 'exists:items,id'],
            'movement_type' => [
                'nullable',
                Rule::in(StockMovementType::values()),
            ],
            'status' => ['nullable', 'string', 'max:40'],
            'work_unit' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'until' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::when($this->filled('from'), 'after_or_equal:from'),
            ],
        ];
    }

    /**
     * Route parameter report must participate in FormRequest validation too.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return [
            ...$this->all(),
            'report' => $this->route('report') ?: $this->input('report'),
        ];
    }

    /**
     * @return array{
     *     report: string,
     *     search: string,
     *     itemId: int,
     *     movementType: string,
     *     status: string,
     *     workUnit: string,
     *     from: string,
     *     until: string
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();
        $report = (string) ($this->route('report')
            ?: ($validated['report'] ?? 'stock'));

        return [
            'report' => $report,
            'search' => trim((string) ($validated['q'] ?? '')),
            'itemId' => (int) ($validated['item'] ?? 0),
            'movementType' => (string) ($validated['movement_type'] ?? ''),
            'status' => (string) ($validated['status'] ?? ''),
            'workUnit' => trim((string) ($validated['work_unit'] ?? '')),
            'from' => (string) ($validated['from'] ?? ''),
            'until' => (string) ($validated['until'] ?? ''),
        ];
    }
}
