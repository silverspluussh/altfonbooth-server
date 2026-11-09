<?php

namespace Tests\Unit;

use App\Support\Msisdn;
use PHPUnit\Framework\TestCase;

class MsisdnTest extends TestCase
{
    public function test_it_normalizes_local_numbers(): void
    {
        $this->assertSame('0249335549', Msisdn::normalize('0249335549'));
        $this->assertSame('0249335549', Msisdn::normalize('249335549'));
    }

    public function test_it_normalizes_international_numbers(): void
    {
        $this->assertSame('0249335549', Msisdn::normalize('233249335549'));
        $this->assertSame('0249335549', Msisdn::normalize('+233 24 933 5549'));
        $this->assertSame('0249335549', Msisdn::normalize('00233249335549'));
    }

    public function test_it_converts_to_international_format(): void
    {
        $this->assertSame('233249335549', Msisdn::toInternational('0249335549'));
    }

    public function test_it_rejects_invalid_numbers(): void
    {
        $this->assertNull(Msisdn::normalize(''));
        $this->assertNull(Msisdn::normalize('12345'));
        $this->assertNull(Msisdn::normalize('abc'));
    }
}
