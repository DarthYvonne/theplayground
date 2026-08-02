<?php

use App\Support\Contact;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phones are stored exactly as typed, so "+45 12 34 56 78" and "12345678" never
 * match each other. This is the comparable form — used to find a member by
 * phone when assigning a personlig træning. Deliberately not unique: a shared
 * household number is legitimate.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_normalized', 20)->nullable()->after('phone')->index();
        });

        DB::table('users')->whereNotNull('phone')->orderBy('id')
            ->select('id', 'phone')->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('users')->where('id', $row->id)
                        ->update(['phone_normalized' => Contact::normalizePhone($row->phone)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone_normalized']);
            $table->dropColumn('phone_normalized');
        });
    }
};
