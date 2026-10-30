<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('asset_code', 40);
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('asset_category_id')->constrained('asset_categories')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('depreciation_method_id')->nullable()->constrained('depreciation_methods')->nullOnDelete();
            $table->foreignId('asset_status_id')->nullable()->constrained('asset_statuses')->nullOnDelete();
            $table->foreignId('parent_asset_id')->nullable()->constrained('assets')->nullOnDelete()->comment('Component accounting (Ind AS 16)');
            // Acquisition
            $table->date('purchase_date')->nullable();
            $table->date('capitalization_date')->nullable()->comment('Put-to-use / ready-for-use date; depreciation starts here');
            $table->string('invoice_number', 60)->nullable();
            $table->date('invoice_date')->nullable();
            $table->string('purchase_order_number', 60)->nullable();
            // Values (INR)
            $table->decimal('original_cost', 18, 2)->default(0);
            $table->decimal('residual_value', 18, 2)->default(0);
            $table->unsignedSmallInteger('useful_life_years')->default(0);
            $table->unsignedTinyInteger('useful_life_months')->default(0);
            $table->decimal('wdv_rate', 7, 4)->nullable();
            $table->decimal('total_units_capacity', 18, 2)->nullable()->comment('Units of production: lifetime units');
            $table->decimal('units_consumed', 18, 2)->default(0);
            $table->date('opening_balance_date')->nullable()->comment('Takeover date for migrated assets');
            $table->decimal('opening_accumulated_depreciation', 18, 2)->default(0);
            $table->decimal('accumulated_depreciation', 18, 2)->default(0)->comment('Opening + posted depreciation');
            $table->decimal('current_book_value', 18, 2)->default(0);
            $table->date('last_depreciation_date')->nullable();
            // Identification
            $table->string('serial_number', 100)->nullable();
            $table->string('asset_tag', 60)->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('unit_of_measure', 20)->default('Nos');
            $table->string('custodian')->nullable();
            // Warranty / insurance
            $table->date('warranty_expiry_date')->nullable();
            $table->string('insurance_policy_number', 60)->nullable();
            $table->date('insurance_expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'asset_code']);
            $table->index(['company_id', 'asset_category_id']);
            $table->index(['company_id', 'location_id']);
            $table->index(['company_id', 'asset_status_id']);
            $table->index(['company_id', 'capitalization_date']);
            $table->index(['company_id', 'serial_number']);
            $table->index(['company_id', 'asset_tag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
