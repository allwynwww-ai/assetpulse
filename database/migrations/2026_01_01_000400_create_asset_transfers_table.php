<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->string('transfer_number', 30);
            $table->date('transfer_date');
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('from_cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->foreignId('to_cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->string('from_custodian')->nullable();
            $table->string('to_custodian')->nullable();
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'completed', 'rejected', 'cancelled'])->default('completed');
            $table->boolean('affects_depreciation')->default(false);
            $table->unsignedSmallInteger('revised_useful_life_months')->nullable()->comment('Remaining life revision, if any');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'transfer_number']);
            $table->index(['company_id', 'asset_id', 'transfer_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_transfers');
    }
};
