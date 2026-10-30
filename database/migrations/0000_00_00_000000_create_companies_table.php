<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('cin', 30)->nullable()->comment('Corporate Identification Number');
            $table->string('gstin', 20)->nullable();
            $table->string('pan', 15)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->default('India');
            $table->string('pincode', 12)->nullable();
            $table->string('currency', 3)->default('INR');
            $table->unsignedTinyInteger('fy_start_month')->default(4)->comment('1-12; India = April');
            $table->enum('depreciation_basis', ['companies_act', 'income_tax', 'ind_as'])->default('companies_act');
            $table->string('asset_code_separator', 3)->default('-');
            $table->unsignedTinyInteger('asset_code_padding')->default(4);
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
