<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depreciation_run_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('frequency', ['monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->enum('book', ['companies_act', 'income_tax'])->default('companies_act');
            $table->enum('proration_basis', ['days', 'months'])->default('days')->comment('Pro-rata from put-to-use date');
            $table->boolean('depreciate_in_month_of_capitalization')->default(true);
            $table->boolean('depreciate_in_month_of_disposal')->default(true);
            $table->decimal('low_value_threshold', 18, 2)->default(5000)->comment('Assets at/below this are fully depreciated in year 1');
            $table->enum('shift_basis', ['single', 'double', 'triple'])->default('single')->comment('Schedule II extra-shift: +50% double, +100% triple');
            $table->unsignedTinyInteger('rounding_precision')->default(2);
            $table->boolean('auto_lock_on_finalize')->default(true);
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_run_parameters');
    }
};
