<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('membership_number', 191)->unique();
            $table->string('name', 191);
            $table->string('email', 191)->nullable()->unique();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->date('joined_at')->nullable();
            $table->string('status', 50)->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
