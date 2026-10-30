<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_disposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->string('disposal_number', 30);
            $table->date('disposal_date');
            $table->enum('disposal_type', ['sale', 'scrap', 'write_off', 'donation', 'theft_loss', 'insurance_claim'])->default('sale');
            $table->string('buyer_name')->nullable();
            $table->string('buyer_gstin', 20)->nullable();
            $table->string('sale_invoice_number', 60)->nullable();
            $table->decimal('sale_value', 18, 2)->default(0);
            $table->decimal('disposal_cost', 18, 2)->default(0);
            $table->decimal('gross_block_at_disposal', 18, 2)->default(0);
            $table->decimal('accumulated_depreciation_at_disposal', 18, 2)->default(0);
            $table->decimal('depreciation_upto_disposal', 18, 2)->default(0)->comment('Pro-rata charge for the disposal period');
            $table->decimal('nbv_at_disposal', 18, 2)->default(0);
            $table->decimal('profit_loss', 18, 2)->default(0)->comment('Positive = profit');
            $table->text('reason')->nullable();
            $table->enum('status', ['draft', 'approved', 'completed', 'cancelled'])->default('completed');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'disposal_number']);
            $table->index(['company_id', 'disposal_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_disposals');
    }
};
