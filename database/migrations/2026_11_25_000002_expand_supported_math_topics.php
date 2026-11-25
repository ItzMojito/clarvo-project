<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('step_blueprints', function (Blueprint $table) {
            $table->string('required_form', 40)->change();
        });
    }

    public function down(): void
    {
        // Keep string width backward-compatible to avoid truncating expanded blueprints.
    }
};
