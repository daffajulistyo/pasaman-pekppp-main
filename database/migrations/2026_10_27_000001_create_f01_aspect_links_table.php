<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tautan Google Drive bukti dukung per aspek (bukan per butir pertanyaan).
     */
    public function up(): void
    {
        Schema::create('f01_aspect_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $table->foreignId('aspect_id')->constrained('aspects')->cascadeOnDelete();
            $table->text('drive_link')->nullable();
            $table->timestamps();

            $table->unique(['evaluation_id', 'aspect_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('f01_aspect_links');
    }
};