<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Clé de la formule dans config/platform.php (essentiel, intensif, semaine…).
            $table->string('plan', 50);
            // Copie des conditions au moment de l'achat : un changement de
            // configuration ne modifie pas les abonnements déjà vendus.
            $table->string('plan_name');
            $table->unsignedSmallInteger('daily_minutes');
            $table->unsignedInteger('price');
            $table->string('currency', 10);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            // pending | active | expired | cancelled. Voir App\Enums\SubscriptionStatus.
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->index(['user_id', 'status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
