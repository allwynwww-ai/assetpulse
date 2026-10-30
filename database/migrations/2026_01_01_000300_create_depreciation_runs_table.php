<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depreciation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('depreciation_run_parameter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('run_number', 30);
            $table->enum('frequency', ['monthly', 'quarterly', 'yearly']);
            $table->enum('book', ['companies_act', 'income_tax'])->default('companies_act');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('period_label', 40)->comment('e.g. Apr-2025 or FY 2025-26');
            $table->enum('status', ['draft', 'posted', 'finalized', 'reversed'])->default('draft');
            $table->unsignedInteger('total_assets')->default(0);
            $table->decimal('total_depreciation', 18, 2)->default(0);
            $table->foreignId('run_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('run_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'run_number']);
            $table->index(['company_id', 'period_start', 'period_end']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_runs');
    }
};
