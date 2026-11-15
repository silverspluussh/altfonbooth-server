<?php

namespace Tests;

use App\Models\Country;
use Database\Seeders\CountrySeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        try {
            if (Schema::hasTable('countries') && Country::count() === 0) {
                $this->seed(CountrySeeder::class);
            }
        } catch (\Throwable $exception) {
            // Tests that do not use a database do not need countries.
        }
    }
}
