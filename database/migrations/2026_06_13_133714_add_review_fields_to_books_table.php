<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('review_status', 50)
                ->default('approved')
                ->after('status');

            $table->text('rejection_reason')
                ->nullable()
                ->after('review_status');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['review_status', 'rejection_reason']);
        });
    }
};
