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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->nullable()->index();

            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('event', 100)->index();
            $table->string('module', 100)->index();
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('http_method', 10)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['auditable_type', 'auditable_id'],
                'audit_logs_auditable_index'
            );

            $table->index(
                ['module', 'created_at'],
                'audit_logs_module_created_at_index'
            );

            $table->index(
                ['actor_id', 'created_at'],
                'audit_logs_actor_created_at_index'
            );
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 40);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(
                ['document_type', 'year', 'month'],
                'document_sequences_type_period_unique'
            );
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->string('group', 80)->index();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(
                ['notifiable_type', 'notifiable_id'],
                'notifications_notifiable_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('audit_logs');
    }
};
