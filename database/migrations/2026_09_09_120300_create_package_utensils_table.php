<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_utensils', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('catering_packages')->onDelete('cascade');
            $table->foreignId('utensil_id')->constrained('utensils')->onDelete('cascade');
            $table->integer('quantity_needed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_utensils');
    }
};
