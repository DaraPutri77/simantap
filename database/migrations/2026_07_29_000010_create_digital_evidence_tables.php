<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->string('file_category', 80)->index();
            $table->string('disk', 50)->default('local');
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('file_path');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size');
            $table->char('checksum', 64);
            $table->json('metadata')->nullable();

            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['attachable_type', 'attachable_id'],
                'attachments_attachable_index'
            );
        });

        Schema::create('digital_signatures', function (Blueprint $table) {
            $table->id();
            $table->string('signable_type');
            $table->unsignedBigInteger('signable_id');

            $table->foreignId('signer_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('signer_name_snapshot');
            $table->string('employee_number_snapshot', 50)->nullable();
            $table->string('purpose', 100);
            $table->string('image_path');
            $table->char('transaction_hash', 64)->index();
            $table->char('image_checksum', 64);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('signed_at');
            $table->timestamps();

            $table->index(
                ['signable_type', 'signable_id'],
                'digital_signatures_signable_index'
            );

            $table->unique(
                [
                    'signable_type',
                    'signable_id',
                    'signer_id',
                    'purpose',
                ],
                'digital_signatures_context_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('digital_signatures');
        Schema::dropIfExists('attachments');
    }
};
