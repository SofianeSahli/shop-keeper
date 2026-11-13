<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_products', function (Blueprint $table) {
            $table->id();
            $table->string('translation_key', 100)->unique();
            $table->string('status', 10);
            $table->string('barcode', 13)->nullable()->unique();
            $table->string('pending_name', 120)->nullable();
            $table->char('pending_language', 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_products');
    }
};
