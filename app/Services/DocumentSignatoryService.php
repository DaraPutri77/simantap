<?php

namespace App\Services;

use App\Models\User;

class DocumentSignatoryService
{
    /**
     * @return array<string, array{
     *     role_label: string,
     *     name: string|null,
     *     employee_number: string|null
     * }>
     */
    public function for(string $document): array
    {
        $definitions = config(
            "document-signatories.{$document}",
            [],
        );

        if (! is_array($definitions) || $definitions === []) {
            return [];
        }

        $employeeNumbers = collect($definitions)
            ->pluck('employee_number')
            ->filter(
                static fn (mixed $value): bool => is_string($value)
                    && trim($value) !== '',
            )
            ->unique()
            ->values();

        $users = User::query()
            ->active()
            ->whereIn('employee_number', $employeeNumbers)
            ->get([
                'employee_number',
                'name',
            ])
            ->keyBy('employee_number');

        $resolved = [];

        foreach ($definitions as $key => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $employeeNumber = isset($definition['employee_number'])
                ? trim((string) $definition['employee_number'])
                : '';

            $user = $employeeNumber !== ''
                ? $users->get($employeeNumber)
                : null;

            $resolved[(string) $key] = [
                'role_label' => trim(
                    (string) ($definition['role_label'] ?? ''),
                ),
                'name' => $user?->name,
                'employee_number' => $user?->employee_number,
            ];
        }

        return $resolved;
    }
}
