<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'document_verifications',
            function (Blueprint $table): void {
                $table->id();

                $table->string('public_token', 64)
                    ->unique();

                $table->string('document_type', 60);

                $table->string('verifiable_type', 100);
                $table->unsignedBigInteger('verifiable_id');

                $table->string('document_reference', 150);

                $table->unsignedInteger('version');

                $table->unsignedSmallInteger(
                    'payload_schema_version',
                );

                $table->string('hash_algorithm', 20);
                $table->char('payload_hash', 64)->index();

                $table->json('public_metadata')->nullable();

                $table->foreignId('issued_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('issued_at');

                $table->foreignId('revoked_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('revoked_at')->nullable();
                $table->text('revocation_reason')->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'document_type',
                        'verifiable_type',
                        'verifiable_id',
                    ],
                    'document_verifications_context_index',
                );

                $table->unique(
                    [
                        'document_type',
                        'verifiable_type',
                        'verifiable_id',
                        'version',
                    ],
                    'document_verifications_context_version_unique',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('document_verifications');
    }
};
