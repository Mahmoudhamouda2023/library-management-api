<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('books', 'review_status')) {
            Schema::table('books', function (Blueprint $table) {
                $table->string('review_status', 50)
                    ->default('approved')
                    ->after('status');
            });
        }

        if (!Schema::hasColumn('books', 'review_note')) {
            Schema::table('books', function (Blueprint $table) {
                $table->text('review_note')
                    ->nullable()
                    ->after('review_status');
            });
        }

        if (!Schema::hasColumn('books', 'reviewed_by')) {
            Schema::table('books', function (Blueprint $table) {
                $table->unsignedBigInteger('reviewed_by')
                    ->nullable()
                    ->after('review_note');
            });
        }

        if (!Schema::hasColumn('books', 'reviewed_at')) {
            Schema::table('books', function (Blueprint $table) {
                $table->timestamp('reviewed_at')
                    ->nullable()
                    ->after('reviewed_by');
            });
        }
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            if (Schema::hasColumn('books', 'review_note')) {
                $table->dropColumn('review_note');
            }

            if (Schema::hasColumn('books', 'reviewed_by')) {
                $table->dropColumn('reviewed_by');
            }

            if (Schema::hasColumn('books', 'reviewed_at')) {
                $table->dropColumn('reviewed_at');
            }

            if (Schema::hasColumn('books', 'review_status')) {
                $table->dropColumn('review_status');
            }
        });
    }
};
