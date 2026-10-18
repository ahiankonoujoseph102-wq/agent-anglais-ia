<?php

use App\Support\Countries;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pays de l'apprenant (code ISO, ou ZZ pour « Autre pays »), choisi à l'inscription ; les comptes existants
 * reçoivent le pays de l'indicatif de leur numéro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('country', 2)->nullable()->after('phone');
            $table->index('country');
        });

        DB::table('users')->select(['id', 'phone'])->orderBy('id')->each(function (object $user) {
            DB::table('users')->where('id', $user->id)->update(['country' => Countries::forPhone($user->phone)]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['country']);
            $table->dropColumn('country');
        });
    }
};
