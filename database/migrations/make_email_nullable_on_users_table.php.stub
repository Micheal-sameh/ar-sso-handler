<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Avarewase SSO accounts aren't required to have a verified (or any)
     * email on file, but Laravel's default users table makes `email`
     * NOT NULL — so a login for such an account fails with a database
     * error when the provisioner creates the local user. Requires
     * doctrine/dbal on Laravel 10.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
