<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ConsoleCommandsTest extends TestCase
{
    /**
     * The commands live in app/Console/Commands, which used to be gitignored.
     * This guards against them silently disappearing from a deployment again.
     */
    public function test_console_commands_are_registered(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('payments:reconcile', $commands);
        $this->assertArrayHasKey('ussd:reconcile', $commands);
        $this->assertArrayHasKey('admin:create-super', $commands);
    }
}
