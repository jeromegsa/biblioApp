<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('emprunts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adherent_id')->constrained();
            $table->foreignId('livre_id')->constrained();
            $table->date('date_emprunt');
            $table->date('date_echeance');
            $table->date('date_retour')->nullable();
            $table->timestamps();

            $table->index(['adherent_id', 'date_retour']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emprunts');
    }
};
