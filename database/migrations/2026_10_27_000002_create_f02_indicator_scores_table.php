<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penilaian F-02 per indikator: satu nilai 0-5 dan satu catatan.
     */
    public function up(): void
    {
        Schema::create('f02_indicator_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $table->foreignId('indicator_id')->constrained('indicators')->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['evaluation_id', 'indicator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('f02_indicator_scores');
    }
};