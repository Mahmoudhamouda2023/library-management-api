<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('borrowing_id')
                ->unique()
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('member_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->integer('days_late')->default(0);
            $table->decimal('amount', 8, 2)->default(0);
            $table->string('status', 50)->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fines');
    }
};
