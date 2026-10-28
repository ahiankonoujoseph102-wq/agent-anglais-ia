<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Page « Devenir ambassadeur » : candidatures (une par compte, toujours avec un compte Talkami) et liste
 * d'attente « Me prévenir quand une place se libère » (une ligne par compte).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambassador_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // pending, accepted, rejected
            $table->string('status', 20)->default('pending')->index();
            $table->text('motivation');
            $table->string('channels', 300);
            $table->boolean('minor')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ambassador_waitlist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // Prévenu par l'administrateur (bouton WhatsApp) quand une place s'est libérée.
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambassador_waitlist');
        Schema::dropIfExists('ambassador_applications');
    }
};
