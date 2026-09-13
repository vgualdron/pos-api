<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'document')) {
                $table->dropUnique(['document']);
            }

            $columns = array_filter(
                ['lastname', 'document', 'position', 'contract'],
                fn (string $column) => Schema::hasColumn('users', $column)
            );

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'lastname')) {
                $table->string('lastname')->after('name');
            }

            if (!Schema::hasColumn('users', 'document')) {
                $table->string('document')->unique()->after('lastname');
            }

            if (!Schema::hasColumn('users', 'position')) {
                $table->string('position')->after('document');
            }

            if (!Schema::hasColumn('users', 'contract')) {
                $table->string('contract')->after('position');
            }
        });
    }
};
