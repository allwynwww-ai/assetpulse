<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depreciation_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depreciation_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete()->comment('Snapshot at run time');
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete()->comment('Snapshot at run time');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('method_code', 10);
            $table->decimal('opening_gross_block', 18, 2)->default(0);
            $table->decimal('opening_accumulated_depreciation', 18, 2)->default(0);
            $table->decimal('opening_wdv', 18, 2)->default(0);
            $table->decimal('rate', 9, 4)->nullable();
            $table->unsignedSmallInteger('days')->default(0);
            $table->decimal('shift_factor', 5, 2)->default(1);
            $table->decimal('depreciation_amount', 18, 2)->default(0);
            $table->decimal('closing_accumulated_depreciation', 18, 2)->default(0);
            $table->decimal('closing_wdv', 18, 2)->default(0);
            $table->json('calculation_details')->nullable()->comment('Formula inputs for audit');
            $table->boolean('is_reversal')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'asset_id', 'period_end']);
            $table->index(['company_id', 'financial_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_entries');
    }
};
