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

    public function test_it_recovers_numbers_with_a_misplaced_trunk_zero(): void
    {
        $this->assertSame('0249335549', Msisdn::normalize('+2330249335549'));
        $this->assertSame('0249335549', Msisdn::normalize('2330249335549'));
        $this->assertSame('0249335549', Msisdn::normalize('+233 024 933 5549'));
        $this->assertSame('0249335549', Msisdn::normalize('002330249335549'));
        $this->assertSame('0249335549', Msisdn::normalize('0233249335549'));
    }

    public function test_it_converts_to_international_format(): void
    {
        $this->assertSame('233249335549', Msisdn::toInternational('0249335549'));
        $this->assertSame('233249335549', Msisdn::toInternational('2330249335549'));
    }

    public function test_it_rejects_invalid_numbers(): void
    {
        $this->assertNull(Msisdn::normalize(''));
        $this->assertNull(Msisdn::normalize('12345'));
        $this->assertNull(Msisdn::normalize('abc'));
    }

    public function test_it_normalizes_ghana_numbers_for_storage(): void
    {
        $this->assertSame('0249335549', Msisdn::toStorage('0249335549'));
        $this->assertSame('0249335549', Msisdn::toStorage('+233249335549'));
        $this->assertSame('0249335549', Msisdn::toStorage('233 24 933 5549'));
        $this->assertSame('0249335549', Msisdn::toStorage('00233249335549'));
        $this->assertSame('0249335549', Msisdn::toStorage('249335549'));
        $this->assertSame('0249335549', Msisdn::toStorage('+2330249335549'));
    }

    public function test_it_reduces_other_numbers_to_digits(): void
    {
        $this->assertSame('2348012345678', Msisdn::toStorage('+234 801 234 5678'));
        $this->assertSame('12025550123', Msisdn::toStorage('+1 (202) 555-0123'));
    }

    public function test_storage_handles_blank_values(): void
    {
        $this->assertNull(Msisdn::toStorage(null));
        $this->assertNull(Msisdn::toStorage('   '));
        $this->assertSame([], Msisdn::storageCandidates(null));
    }

    public function test_storage_candidates_include_raw_and_normalized_values(): void
    {
        $this->assertSame(
            ['+233249335549', '0249335549', '233249335549'],
            Msisdn::storageCandidates('+233249335549')
        );

        $this->assertSame(['0249335549'], Msisdn::storageCandidates('0249335549'));

        // A legacy row stored as 2330XXXXXXXXX must still resolve.
        $this->assertSame(
            ['+2330249335549', '0249335549', '2330249335549'],
            Msisdn::storageCandidates('+2330249335549')
        );
    }
}
