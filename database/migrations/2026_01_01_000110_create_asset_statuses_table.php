<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30)->comment('ACTIVE, UNDER_MAINTENANCE, DISPOSED, SCRAPPED, TRANSFERRED ...');
            $table->string('name');
            $table->string('color', 20)->default('slate')->comment('UI badge colour');
            $table->boolean('is_depreciable')->default(true)->comment('Include in depreciation runs');
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_statuses');
    }
};
