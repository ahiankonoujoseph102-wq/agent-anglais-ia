<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Journal des e-mails envoyés aux élèves (App\Listeners\RecordSentMail), résumé chaque heure au fondateur. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_journal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient', 190);
            $table->string('kind', 120);
            $table->string('subject', 200);
            $table->timestamp('reported_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_journal');
    }
};
