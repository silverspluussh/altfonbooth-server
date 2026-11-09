<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ussd:reconcile {reference?}', function ($reference = null) {
    return $this->call('payments:reconcile', ['reference' => $reference]);
})->purpose('Alias for payments:reconcile');

Schedule::command('payments:reconcile')
    ->everyMinute()
    ->withoutOverlapping();
