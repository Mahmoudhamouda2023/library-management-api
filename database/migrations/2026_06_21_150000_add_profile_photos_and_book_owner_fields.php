<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 50)->nullable()->after('email');
            }

            if (!Schema::hasColumn('users', 'country')) {
                $table->string('country', 191)->nullable()->after('phone');
            }

            if (!Schema::hasColumn('users', 'bio')) {
                $table->text('bio')->nullable()->after('country');
            }

            if (!Schema::hasColumn('users', 'photo')) {
                $table->string('photo')->nullable()->after('bio');
            }
        });

        Schema::table('authors', function (Blueprint $table) {
            if (!Schema::hasColumn('authors', 'photo')) {
                $table->string('photo')->nullable()->after('bio');
            }
        });

        Schema::table('publisher_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('publisher_requests', 'photo')) {
                $table->string('photo')->nullable()->after('bio');
            }
        });

        Schema::table('books', function (Blueprint $table) {
            if (!Schema::hasColumn('books', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('users')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            if (Schema::hasColumn('books', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });

        Schema::table('publisher_requests', function (Blueprint $table) {
            if (Schema::hasColumn('publisher_requests', 'photo')) {
                $table->dropColumn('photo');
            }
        });

        Schema::table('authors', function (Blueprint $table) {
            if (Schema::hasColumn('authors', 'photo')) {
                $table->dropColumn('photo');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach (['photo', 'bio', 'country', 'phone'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
