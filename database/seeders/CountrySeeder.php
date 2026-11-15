<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['name' => 'Ghana', 'iso_code' => 'GH', 'dial_code' => '233', 'domain' => 'ghana.altfon.net', 'sort_order' => 1],
            ['name' => 'Nigeria', 'iso_code' => 'NG', 'dial_code' => '234', 'domain' => 'nigeria.altfon.net', 'sort_order' => 2],
            ['name' => 'United States', 'iso_code' => 'US', 'dial_code' => '1', 'domain' => 'unitedstates.altfon.net', 'sort_order' => 3],
            ['name' => 'Canada', 'iso_code' => 'CA', 'dial_code' => '1', 'domain' => 'canada.altfon.net', 'sort_order' => 4],
            ['name' => 'United Kingdom', 'iso_code' => 'GB', 'dial_code' => '44', 'domain' => 'unitedkingdom.altfon.net', 'sort_order' => 5],
        ];

        foreach ($countries as $country) {
            Country::updateOrCreate(
                ['name' => $country['name']],
                $country + ['is_active' => true],
            );
        }
    }
}
