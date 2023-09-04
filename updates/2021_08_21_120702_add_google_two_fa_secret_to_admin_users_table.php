<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGoogleTwoFaSecretToAdminUsersTable extends Migration
{
    public function config($key)
    {
        return config('admin.' . $key);
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table($this->config('database.users_table'), function (Blueprint $table) {
            if (Schema::hasColumns($this->config('database.users_table'), ['remember_token'])) {
                $table->string('google_two_fa_secret', 32)->nullable()->after('remember_token');
            } else {
                $table->string('google_two_fa_secret', 32)->nullable();
            }
            $table->boolean('google_two_fa_enable')->default(0)->index()->after('google_two_fa_secret');
            $table->boolean('status')->default(1)->index()->after('google_two_fa_enable');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table($this->config('database.users_table'), function (Blueprint $table) {
            $table->dropColumn('google_two_fa_secret');
            $table->dropColumn('google_two_fa_enable');
            $table->dropColumn('status');
        });
    }
}
