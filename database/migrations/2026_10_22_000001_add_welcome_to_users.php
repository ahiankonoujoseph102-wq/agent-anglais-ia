<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Message de bienvenue de Teacher Joe, montré automatiquement une seule fois, à la première ouverture
 * d'un accès (welcomed_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('welcomed_at')->nullable()->after('must_change_password');
        });

        // Les comptes qui ont déjà eu un accès n'ont pas à revoir la page (ils peuvent la réécouter) ;
        // les autres la verront à l'ouverture de leur premier accès.
        DB::table('users')
            ->whereExists(fn ($query) => $query->select(DB::raw(1))->from('subscriptions')->whereColumn('subscriptions.user_id', 'users.id'))
            ->update(['welcomed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('welcomed_at');
        });
    }
};
