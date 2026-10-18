<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Messages de bienvenue de Teacher Joe, montrés automatiquement une seule fois :
 * après l'inscription (welcomed_at), puis à la première ouverture d'un accès (access_welcomed_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('welcomed_at')->nullable()->after('must_change_password');
            $table->timestamp('access_welcomed_at')->nullable()->after('welcomed_at');
        });

        // Les comptes déjà inscrits n'ont pas à revoir ces pages d'accueil (ils peuvent les réécouter).
        DB::table('users')->update(['welcomed_at' => now(), 'access_welcomed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['welcomed_at', 'access_welcomed_at']);
        });
    }
};
