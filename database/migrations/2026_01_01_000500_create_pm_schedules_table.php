<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 30)->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('frequency_unit', ['days', 'weeks', 'months', 'years', 'running_hours', 'shots'])->default('months');
            $table->unsignedInteger('frequency_interval')->default(1);
            $table->date('start_date')->nullable();
            $table->date('last_done_date')->nullable();
            $table->date('next_due_date')->nullable();
            $table->decimal('meter_last_service_reading', 14, 2)->nullable()->comment('Hours / shots at last service');
            $table->decimal('meter_current_reading', 14, 2)->nullable();
            $table->decimal('meter_daily_average', 12, 2)->nullable()->comment('Average hours or shots per day, used to forecast the due date');
            $table->unsignedSmallInteger('alert_days_before')->default(7);
            $table->decimal('estimated_cost', 18, 2)->default(0);
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->string('assigned_to')->nullable();
            $table->text('checklist')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'next_due_date']);
            $table->index(['company_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_schedules');
    }
};
