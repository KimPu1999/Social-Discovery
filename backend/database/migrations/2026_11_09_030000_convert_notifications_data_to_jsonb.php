<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Convert the database notification payload to PostgreSQL's native JSON type. */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Laravel database notifications are JSON strings in the legacy text
        // column. The cast preserves every existing payload while allowing
        // indexed JSON operators and future whereJson queries.
        DB::statement("ALTER TABLE notifications ALTER COLUMN data TYPE jsonb USING data::jsonb");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text");
    }
};
