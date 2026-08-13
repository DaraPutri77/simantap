<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'vehicle_condition_checks',
            function (Blueprint $table): void {
                $table->string('checker_name_snapshot')
                    ->nullable()
                    ->after('checked_by');

                $table->string(
                    'checker_employee_number_snapshot',
                    50,
                )
                    ->nullable()
                    ->after('checker_name_snapshot');
            },
        );

        DB::table('vehicle_condition_checks')
            ->where(function ($query): void {
                $query
                    ->whereNull('checker_name_snapshot')
                    ->orWhereNull('checker_employee_number_snapshot');
            })
            ->orderBy('id')
            ->chunkById(100, function ($checks): void {
                foreach ($checks as $check) {
                    $checker = DB::table('users')
                        ->where('id', $check->checked_by)
                        ->first([
                            'name',
                            'employee_number',
                        ]);

                    if ($checker === null) {
                        continue;
                    }

                    DB::table('vehicle_condition_checks')
                        ->where('id', $check->id)
                        ->update([
                            'checker_name_snapshot' => $check->checker_name_snapshot
                                ?? $checker->name,
                            'checker_employee_number_snapshot' => $check->checker_employee_number_snapshot
                                ?? $checker->employee_number,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table(
            'vehicle_condition_checks',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'checker_name_snapshot',
                    'checker_employee_number_snapshot',
                ]);
            },
        );
    }
};
