<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom role & class_name ke tabel users bawaan Laravel
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['student', 'teacher', 'admin'])->default('student')->after('email');
            $table->string('class_name')->nullable()->after('role');
        });

        // 2. Node Konsep Kurikulum + Parameter BKT Default
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

        // 3. Edge Graf Prasyarat (DAG: prerequisite_id -> concept_id)
        Schema::create('concept_prerequisites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concept_id')->constrained('concepts')->cascadeOnDelete();
            $table->foreignId('prerequisite_id')->constrained('concepts')->cascadeOnDelete();
            $table->unsignedTinyInteger('priority')->default(1);
            $table->unique(['concept_id', 'prerequisite_id']);
        });

        // 4. Urutan Langkah Standar (Stepper) per Konsep
        Schema::create('step_blueprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concept_id')->constrained('concepts')->cascadeOnDelete();
            $table->unsignedTinyInteger('step_order');
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->enum('step_type', ['diagnostic', 'guided', 'gate_check', 'transfer']);
            $table->enum('required_form', ['integers_pair', 'factored_form', 'roots_set', 'equivalent']);
            $table->foreignId('tracked_concept_id')->constrained('concepts');
            $table->timestamps();
        });

        // 5. Profil Penguasaan Siswa (Mastery BKT P(L))
        Schema::create('student_masteries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('concept_id')->constrained()->cascadeOnDelete();
            $table->decimal('p_l', 5, 4)->default(0.3000);
            $table->unsignedInteger('total_attempts')->default(0);
            $table->timestamp('last_practiced_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'concept_id']);
        });

        // 6. Sesi Belajar Siswa
        Schema::create('learning_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('target_concept_id')->constrained('concepts');
            $table->string('original_problem_latex');
            $table->json('problem_params_json');
            $table->enum('status', ['active', 'remedial', 'completed'])->default('active');
            $table->unsignedTinyInteger('hints_used')->default(0);
            $table->unsignedTinyInteger('max_hints')->default(5);
            $table->unsignedTinyInteger('failed_attempts_current_gate')->default(0);
            $table->boolean('final_answer_shown')->default(false);
            $table->boolean('shared_with_teacher')->default(true);
            $table->timestamps();
        });

        // 7. Status Langkah per Sesi & Item Isomorfik Aktif
        Schema::create('session_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('step_blueprint_id')->constrained();
            $table->enum('status', ['locked', 'active', 'passed'])->default('locked');
            $table->json('active_micro_item_json')->nullable();
            $table->timestamps();
        });

        // 8. Decision Log Percobaan Micro-Check (Audit Trail & Data Guru)
        Schema::create('micro_check_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_step_id')->constrained()->cascadeOnDelete();
            $table->foreignId('concept_id')->constrained();
            $table->string('item_prompt');
            $table->string('student_input');
            $table->string('verifier_status');
            $table->boolean('is_scored')->default(true);
            $table->decimal('p_l_before', 5, 4);
            $table->decimal('p_l_after', 5, 4);
            $table->enum('gate_status_after', ['locked', 'open']);
            $table->timestamps();
        });

        // 9. Riwayat Dialog Sokratik
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_session_id')->constrained()->cascadeOnDelete();
            $table->enum('sender', ['student', 'tutor', 'system']);
            $table->text('content');
            $table->unsignedTinyInteger('hint_level')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('micro_check_attempts');
        Schema::dropIfExists('session_steps');
        Schema::dropIfExists('learning_sessions');
        Schema::dropIfExists('student_masteries');
        Schema::dropIfExists('step_blueprints');
        Schema::dropIfExists('concept_prerequisites');
        Schema::dropIfExists('concepts');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'class_name']);
        });
    }
};