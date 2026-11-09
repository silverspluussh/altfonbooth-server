<?php

use App\Support\Msisdn;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeTable('subscribers');
        $this->normalizeTable('subscribers_temp');
    }

    public function down(): void
    {
        // The original formatting cannot be reconstructed safely; leave as is.
    }

    private function normalizeTable(string $table): void
    {
        if (!DB::getSchemaBuilder()->hasTable($table)) {
            return;
        }

        DB::table($table)
            ->select(['recid', 'phonenumber'])
            ->chunkById(200, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    if ($row->phonenumber === null || trim((string) $row->phonenumber) === '') {
                        continue;
                    }

                    $normalized = Msisdn::toStorage($row->phonenumber);

                    if ($normalized === null || $normalized === $row->phonenumber) {
                        continue;
                    }

                    DB::table($table)
                        ->where('recid', $row->recid)
                        ->update(['phonenumber' => $normalized]);
                }
            }, 'recid');
    }
};
