<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'avarewase_membership_code')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('avarewase_membership_code')->nullable()->after('avarewase_avatar');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avarewase_membership_code');
        });
    }
};
