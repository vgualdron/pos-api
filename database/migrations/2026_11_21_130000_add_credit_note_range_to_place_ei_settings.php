<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('place_electronic_invoice_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('credit_note_numbering_range_id')->nullable()->after('numbering_range_label');
            $table->string('credit_note_numbering_range_label')->nullable()->after('credit_note_numbering_range_id');
        });
    }

    public function down(): void
    {
        Schema::table('place_electronic_invoice_settings', function (Blueprint $table) {
            $table->dropColumn(['credit_note_numbering_range_id', 'credit_note_numbering_range_label']);
        });
    }
};
