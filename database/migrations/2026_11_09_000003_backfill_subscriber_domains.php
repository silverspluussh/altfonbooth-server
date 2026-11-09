<?php

use App\Support\SubscriberDomain;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('subscribers')
            ->select(['recid', 'country'])
            ->where(function ($query) {
                $query->whereNull('domain')->orWhere('domain', '');
            })
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $domain = SubscriberDomain::fromCountry($row->country);

                    SubscriberDomain::ensureRelay($domain);

                    DB::table('subscribers')
                        ->where('recid', $row->recid)
                        ->update(['domain' => $domain]);
                }
            }, 'recid');
    }

    public function down(): void
    {
        // Domains are derived data; leave them in place on rollback.
    }
};
