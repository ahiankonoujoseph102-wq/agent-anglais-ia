<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alertes de paiement pour l'administrateur : appareils abonnés aux notifications (Web Push), journal des
 * alertes envoyées (canal et résultat), clics « Écrire sur WhatsApp » des apprenants d'un pays sans paiement en
 * ligne, et marque de l'alerte « l'accès provisoire expire bientôt » (une seule par paiement).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // L'adresse du service de notifications du navigateur est longue : on garde son empreinte (unique).
            $table->string('endpoint_hash', 64)->unique('push_subscriptions_endpoint_hash_unique');
            $table->text('endpoint');
            $table->string('public_key', 255);
            $table->string('auth_token', 255);
            $table->string('content_encoding', 20)->default('aes128gcm');
            $table->string('device', 120)->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_alerts', function (Blueprint $table) {
            $table->id();
            // payment_captured, payment_expiring, whatsapp_click, test
            $table->string('kind', 30);
            // push ou mail
            $table->string('channel', 10);
            $table->string('status', 10);
            $table->string('title', 160);
            $table->string('reason', 200)->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->timestamp('created_at')->nullable()->index('admin_alerts_created_at_index');
        });

        Schema::create('whatsapp_payment_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 40);
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'created_at'], 'whatsapp_clicks_user_created_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('expiry_alert_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('expiry_alert_sent_at');
        });
        Schema::dropIfExists('whatsapp_payment_clicks');
        Schema::dropIfExists('admin_alerts');
        Schema::dropIfExists('push_subscriptions');
    }
};
