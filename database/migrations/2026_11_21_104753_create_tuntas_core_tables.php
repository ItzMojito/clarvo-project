<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pelajar hanya dikenali lewat kode anonim (misal S01..S05), tanpa PII
        Schema::create('learners', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('class_name', 50)->default('Class 10A');
            $table->timestamps();
        });

        // Node Konsep Kurikulum + Parameter BKT Default
        Schema::create('concepts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('phase')->default('Phase E');
            $table->decimal('default_p_l0', 5, 4)->default(0.3000);
            $table->decimal('default_p_t', 5, 4)->default(0.1500);
            $table->decimal('default_p_s', 5, 4)->default(0.1000);
            $table->decimal('default_p_g', 5, 4)->default(0.2000);
            $table->timestamps();
        });

        // Edge Graf Prasyarat (DAG)
        Schema::create('concept_prerequisites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concept_id')->constrained('concepts')->cascadeOnDelete();
            $table->foreignId('prerequisite_id')->constrained('concepts')->cascadeOnDelete();
            $table->unsignedTinyInteger('priority')->default(1);
            $table->unique(['concept_id', 'prerequisite_id']);
        });

        // 6 Langkah Stepper sesuai Figure 2 Esai
        Schema::create('step_blueprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concept_id')->constrained('concepts')->cascadeOnDelete();
            $table->unsignedTinyInteger('step_order');
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->enum('step_type', ['diagnostic', 'gate_check', 'guided', 'transfer']);
            $table->enum('required_form', ['integers_pair', 'factored_form', 'roots_set']);
            $table->boolean('is_scored_step')->default(true);
            $table->foreignId('tracked_concept_id')->constrained('concepts');
            $table->timestamps();
            $table->unique(['concept_id', 'step_order']);
        });

        // Profil Penguasaan BKT per kode pelajar
        Schema::create('student_masteries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->foreignId('concept_id')->constrained('concepts')->cascadeOnDelete();
            $table->decimal('p_l', 5, 4)->default(0.3000);
            $table->unsignedInteger('total_attempts')->default(0);
            $table->timestamp('last_practiced_at')->nullable();
            $table->timestamps();
            $table->unique(['learner_id', 'concept_id']);
        });

        // Sesi Belajar Siswa
        Schema::create('learning_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->foreignId('target_concept_id')->constrained('concepts');
            $table->string('original_problem_latex');
            $table->json('problem_params_json');
            $table->enum('status', ['active', 'remedial', 'completed'])->default('active');
            $table->string('current_step_label')->default('Prerequisite check');
            $table->unsignedTinyInteger('completed_steps_count')->default(0);
            $table->unsignedTinyInteger('hints_used')->default(0);
            $table->unsignedTinyInteger('max_hints')->default(3);
            $table->unsignedTinyInteger('hint_level_current_item')->default(0);
            $table->unsignedTinyInteger('failed_attempts_current_gate')->default(0);
            $table->boolean('final_answer_shown')->default(false);
            $table->string('needs_help_note')->nullable();
            $table->timestamps();
        });

        // Status langkah per sesi & butir aktif
        Schema::create('session_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('step_blueprint_id')->constrained();
            $table->enum('status', ['locked', 'active', 'passed'])->default('locked');
            $table->json('active_micro_item_json')->nullable();
            $table->timestamps();
        });

        // Decision Log TANPA menyimpan teks jawaban mentah siswa (sesuai klausul privasi esai)
        Schema::create('micro_check_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_step_id')->constrained()->cascadeOnDelete();
            $table->foreignId('concept_id')->constrained();
            $table->string('verifier_status');
            $table->boolean('is_scored')->default(true);
            $table->boolean('hint_used_before_attempt')->default(false);
            $table->decimal('p_l_before', 5, 4);
            $table->decimal('p_l_after', 5, 4);
            $table->enum('gate_status_after', ['locked', 'open']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('micro_check_attempts');
        Schema::dropIfExists('session_steps');
        Schema::dropIfExists('learning_sessions');
        Schema::dropIfExists('student_masteries');
        Schema::dropIfExists('step_blueprints');
        Schema::dropIfExists('concept_prerequisites');
        Schema::dropIfExists('concepts');
        Schema::dropIfExists('learners');
    }
};
