<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->binary('id', length: 16, fixed: true)->primary();
            $table->foreignId('shop_id')->constrained('shops')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('compared_name', 255)->collation('utf8mb4_bin');
            $table->string('identity_key', 255)->collation('utf8mb4_bin');
            $table->string('phone', 16)->nullable();
            $table->timestamps();
            $table->unique(['shop_id', 'identity_key']);
            $table->index(['shop_id', 'compared_name', 'identity_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
