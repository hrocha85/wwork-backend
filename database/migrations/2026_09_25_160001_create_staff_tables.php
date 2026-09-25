<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('staff_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->timestamps();
        });

        Schema::create('staff_profile_permission', function (Blueprint $table) {
            $table->foreignId('staff_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['staff_profile_id', 'staff_permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profile_permission');
        Schema::dropIfExists('staff_permissions');
        Schema::dropIfExists('staff_profiles');
    }
};
