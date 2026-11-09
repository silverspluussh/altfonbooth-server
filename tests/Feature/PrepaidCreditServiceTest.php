<?php

namespace Tests\Feature;

use App\Jobs\SendOtpSmsJob;
use App\Models\PrepaidCredit;
use App\Models\SubscriberAuthModel;
use App\Models\SubscribersModel;
use App\Models\UssdPaymentIntent;
use App\Services\PrepaidCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PrepaidCreditServiceTest extends TestCase
{
    use RefreshDatabase;

    private function subscriber(string $subscriberid = 'SUB_CRED1', string $authusername = '7022875'): SubscribersModel
    {
        $subscriber = SubscribersModel::create([
            'subscriberid' => $subscriberid,
            'fullname' => 'Credit Test',
            'username' => strtolower($subscriberid),
            'password' => bcrypt('secret123'),
            'phonenumber' => '0249335549',
            'emailaddress' => strtolower($subscriberid) . '@example.com',
            'country' => 'Ghana',
            'authusername' => $authusername,
            'switch_status' => 'active',
            'billing_acc_status' => 'active',
        ]);

        SubscriberAuthModel::create([
            'subscriberid' => $subscriberid,
            'authusername' => $authusername,
            'authpassword' => 'secret',
            'status' => 'active',
        ]);

        return $subscriber;
    }

    private function fakeBooth(int $failures = 0, ?int &$topups = null): void
    {
        $topups = 0;

        Http::fake(function ($request) use ($failures, &$topups) {
            if (str_contains($request->url(), 'booth_topup.php')) {
                $topups++;

                return $topups <= $failures
                    ? Http::response('', 500)
                    : Http::response(['status' => 'success'], 200);
            }

            if (str_contains($request->url(), 'booth_getbal.php')) {
                return Http::response(['balance' => '17.50'], 200);
            }

            return Http::response([], 200);
        });
    }

    public function test_credit_is_idempotent_and_tops_up_once(): void
    {
        $this->subscriber();
        $this->fakeBooth(0, $topups);

        $service = app(PrepaidCreditService::class);
        $first = $service->credit('7022875', 10, 'PAY-1', 'web');
        $second = $service->credit('7022875', 10, 'PAY-1', 'web');

        $this->assertSame(PrepaidCredit::STATUS_COMPLETED, $first->status);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $topups);
        $this->assertDatabaseCount('prepaid_credits', 1);
        $this->assertDatabaseHas('prepaid_credits', [
            'transaction_id' => 'PAY-1',
            'status' => PrepaidCredit::STATUS_COMPLETED,
            'source' => 'web',
        ]);
    }

    public function test_failed_topup_stays_pending_until_retry_succeeds(): void
    {
        $this->subscriber();
        $this->fakeBooth(1, $topups);

        $service = app(PrepaidCreditService::class);
        $credit = $service->credit('7022875', 10, 'PAY-2', 'mobile');

        $this->assertSame(PrepaidCredit::STATUS_PENDING_TOPUP, $credit->status);
        $this->assertSame(1, $credit->topup_attempts);

        $this->assertTrue($service->retry($credit->fresh()));

        $credit->refresh();
        $this->assertSame(PrepaidCredit::STATUS_COMPLETED, $credit->status);
        $this->assertSame(2, $credit->topup_attempts);
        $this->assertSame(2, $topups);
    }

    public function test_credit_fails_after_five_attempts(): void
    {
        $this->subscriber();
        $this->fakeBooth(99, $topups);

        $service = app(PrepaidCreditService::class);
        $credit = $service->credit('7022875', 10, 'PAY-3', 'web');

        for ($i = 0; $i < 4; $i++) {
            $service->retry($credit->fresh());
        }

        $credit->refresh();
        $this->assertSame(PrepaidCredit::STATUS_FAILED, $credit->status);
        $this->assertSame(5, $credit->topup_attempts);
        $this->assertSame(5, $topups);
        $this->assertFalse($service->retry($credit));
    }

    public function test_completed_credit_syncs_the_ussd_intent(): void
    {
        $this->subscriber();
        $this->fakeBooth();

        $intent = UssdPaymentIntent::create([
            'reference' => 'ALT-SYNC-1',
            'subscriberid' => 'SUB_CRED1',
            'authusername' => '7022875',
            'msisdn' => '233249335549',
            'amount' => 10,
            'network' => 'MTN',
            'provider' => 'fake',
            'status' => UssdPaymentIntent::STATUS_PROCESSING,
        ]);

        app(PrepaidCreditService::class)->credit('7022875', 10, $intent->reference, 'ussd');

        $this->assertDatabaseHas('ussd_payment_intents', [
            'reference' => $intent->reference,
            'status' => UssdPaymentIntent::STATUS_COMPLETED,
        ]);
    }

    public function test_success_sms_is_queued_for_customer_sources(): void
    {
        $this->subscriber();
        $this->fakeBooth();
        Queue::fake();

        app(PrepaidCreditService::class)->credit('7022875', 10, 'PAY-SMS-1', 'mobile');

        Queue::assertPushed(SendOtpSmsJob::class);
    }

    public function test_success_sms_is_not_queued_for_admin_credits(): void
    {
        $this->subscriber();
        $this->fakeBooth();
        Queue::fake();

        app(PrepaidCreditService::class)->credit('7022875', 10, 'ADM-SMS-1', 'admin');

        Queue::assertNotPushed(SendOtpSmsJob::class);
    }
}
