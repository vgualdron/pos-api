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
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('icon')->nullable()->after('guard_name');
            $table->unsignedInteger('order')->nullable()->after('icon');
            $table->string('label')->nullable();
            $table->string('subgroup')->nullable();
            $table->string('route')->nullable()->after('subgroup');
            $table->unsignedBigInteger('group_id')->nullable()->after('subgroup');

            $table->foreign('group_id')->references('id')->on('groups')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn(['icon', 'order', 'label', 'subgroup', 'route', 'group_id']);
        });
    }
};
