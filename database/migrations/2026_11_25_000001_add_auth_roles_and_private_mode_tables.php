<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->after('class_name');
            $table->string('pin_hash')->nullable()->after('is_private');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('individual')->after('password');
            $table->string('class_name', 50)->nullable()->after('role');
            $table->foreignId('learner_id')->nullable()->after('class_name')->constrained('learners')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('learner_id');
            $table->dropColumn(['role', 'class_name']);
        });

        Schema::table('learners', function (Blueprint $table) {
            $table->dropColumn(['is_private', 'pin_hash']);
        });
    }
};
