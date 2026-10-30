<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('asset_categories')->nullOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->string('asset_code_prefix', 10)->comment('Used for auto asset code, e.g. IMM');
            $table->text('description')->nullable();
            $table->foreignId('depreciation_method_id')->nullable()->constrained('depreciation_methods')->nullOnDelete();
            $table->unsignedSmallInteger('useful_life_years')->default(0);
            $table->unsignedTinyInteger('useful_life_months')->default(0)->comment('Additional months beyond years');
            $table->decimal('residual_value_percent', 5, 2)->default(5.00);
            $table->decimal('wdv_rate', 7, 4)->nullable()->comment('Derived WDV rate % (Schedule II)');
            $table->string('schedule_ii_reference')->nullable()->comment('Part C entry reference');
            $table->decimal('capitalization_threshold', 18, 2)->default(0)->comment('Below this, write off in year of purchase');
            $table->string('gl_asset_account', 30)->nullable();
            $table->string('gl_accumulated_depreciation_account', 30)->nullable();
            $table->string('gl_depreciation_expense_account', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'asset_code_prefix']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_categories');
    }
};
