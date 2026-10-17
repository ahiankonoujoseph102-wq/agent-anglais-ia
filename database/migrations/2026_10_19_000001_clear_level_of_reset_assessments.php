<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Comptes dont l'évaluation a été réinitialisée avant ce correctif : le niveau était
 * gardé sans évaluation, et « Mon espace » ne proposait plus l'évaluation. Un niveau
 * vient toujours d'une évaluation : sans évaluation, il est effacé.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('level')
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('assessments')->whereColumn('assessments.user_id', 'users.id'))
            ->update(['level' => null]);
    }

    public function down(): void
    {
        // Rien à rétablir : l'ancien niveau n'est pas conservé.
    }
};
