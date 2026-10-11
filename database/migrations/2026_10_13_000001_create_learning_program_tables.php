<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Programme personnel, bilans de séance, erreurs à revoir, bilans de niveau et attestations.
     */
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            // Niveau travaillé (programme officiel resources/curriculum) et réponses au questionnaire.
            $table->string('level', 2)->nullable()->after('user_id');
            // active | level_test_due | completed | archived. Voir App\Enums\ProgramStatus.
            $table->string('status', 20)->default('active')->after('level');
            $table->string('goal', 20)->nullable();
            $table->string('occupation', 150)->nullable();
            $table->string('interests', 255)->nullable();
            // Jours de travail (1 = lundi … 7 = dimanche) et heure préférée.
            $table->json('study_days')->nullable();
            $table->string('study_time', 5)->nullable();
            $table->json('difficulties')->nullable();
        });

        // Une ligne par leçon du programme, dans l'ordre, avec sa date prévue.
        Schema::create('program_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unsignedTinyInteger('unit_number');
            $table->unsignedTinyInteger('lesson_number');
            // Situation de conversation de l'unité travaillée (index dans le fichier du niveau).
            $table->unsignedTinyInteger('situation_index');
            // Situation liée à l'objectif ou au métier de l'apprenant.
            $table->boolean('highlighted')->default(false);
            $table->date('scheduled_on')->nullable();
            // pending | completed. Voir App\Enums\ProgramLessonStatus.
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['program_id', 'position']);
            $table->index(['program_id', 'status']);
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->foreignId('program_lesson_id')->nullable()->constrained()->nullOnDelete();
            // completed | repeat : leçon terminée ou à reprendre (bilan du professeur).
            $table->string('outcome', 20)->nullable();
            // Notes de 1 à 5. Voir App\Enums\Skill.
            foreach (['fluency', 'pronunciation', 'grammar', 'vocabulary', 'comprehension'] as $skill) {
                $table->unsignedTinyInteger('score_'.$skill)->nullable();
            }
            $table->dateTime('reported_at')->nullable();
        });

        // Erreurs relevées par le professeur, revues jusqu'à deux corrections de suite.
        Schema::create('learner_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('wrong', 255);
            $table->string('correct', 255);
            $table->string('explanation', 500)->nullable();
            // open | resolved. Voir App\Enums\ErrorStatus.
            $table->string('status', 20)->default('open');
            // Nombre de séances consécutives où l'apprenant a dit la forme correcte.
            $table->unsignedTinyInteger('correct_streak')->default(0);
            $table->unsignedSmallInteger('times_seen')->default(1);
            $table->dateTime('last_reviewed_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // Bilan oral de fin de niveau (conversation vocale, comme l'évaluation).
        Schema::create('level_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->string('level', 2);
            // in_progress | passed | failed | abandoned. Voir App\Enums\LevelTestStatus.
            $table->string('status', 20)->default('in_progress');
            $table->boolean('passed')->nullable();
            $table->text('feedback')->nullable();
            $table->json('units_to_review')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('allowed_seconds')->default(0);
            $table->unsignedInteger('used_seconds')->default(0);
            $table->dateTime('last_seen_at')->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $table->unsignedSmallInteger('connections')->default(0);
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // Attestations de niveau délivrées après un bilan réussi.
        Schema::create('attestations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_test_id')->nullable()->constrained()->nullOnDelete();
            $table->string('level', 2);
            // Code de vérification imprimé sur l'attestation.
            $table->string('code', 20)->unique();
            $table->dateTime('issued_at');
            $table->timestamps();

            $table->unique(['user_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attestations');
        Schema::dropIfExists('level_tests');
        Schema::dropIfExists('learner_errors');

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_lesson_id');
            $table->dropColumn([
                'outcome', 'score_fluency', 'score_pronunciation', 'score_grammar',
                'score_vocabulary', 'score_comprehension', 'reported_at',
            ]);
        });

        Schema::dropIfExists('program_lessons');

        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn(['level', 'status', 'goal', 'occupation', 'interests', 'study_days', 'study_time', 'difficulties']);
        });
    }
};
