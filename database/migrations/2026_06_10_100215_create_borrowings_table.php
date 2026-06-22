<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrowings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('book_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->date('borrowed_at');
            $table->date('due_date');
            $table->date('returned_at')->nullable();

            $table->string('status', 50)->default('borrowed');
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrowings');
    }
};
