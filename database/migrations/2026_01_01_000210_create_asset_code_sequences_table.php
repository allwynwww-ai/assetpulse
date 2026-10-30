<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_code_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('prefix', 20);
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'prefix']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_code_sequences');
    }
};
