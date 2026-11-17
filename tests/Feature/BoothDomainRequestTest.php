<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\SubscriberAuthModel;
use App\Models\SubscriberChannelModel;
use App\Models\SubscribersModel;
use App\Services\BoothApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BoothDomainRequestTest extends TestCase
{
    use RefreshDatabase;

    private function accountWithBothChannels(): SubscribersModel
    {
        $ghana = Country::where('name', 'Ghana')->firstOrFail();

        $subscriber = SubscribersModel::create([
            'subscriberid' => 'SUB_DOMAIN1',
            'fullname' => 'Domain Account',
            'username' => 'domainaccount',
            'password' => bcrypt('secret123'),
            'phonenumber' => '0249335549',
            'emailaddress' => 'domainaccount@example.com',
            'country' => 'Ghana',
            'country_id' => $ghana->id,
            'domain' => 'ghana.altfon.net',
            'has_mobile_account' => true,
            'has_booth_account' => true,
            'switch_status' => 'active',
            'billing_acc_status' => 'active',
        ]);

        $mobile = SubscriberAuthModel::create([
            'subscriberid' => $subscriber->subscriberid,
            'channel' => 'mobile',
            'authusername' => '111111',
            'authpassword' => 'secret',
            'status' => 'active',
        ]);

        $booth = SubscriberAuthModel::create([
            'subscriberid' => $subscriber->subscriberid,
            'channel' => 'booth',
            'authusername' => '222222',
            'authpassword' => 'secret',
            'status' => 'active',
        ]);

        SubscriberChannelModel::create([
            'subscriberid' => $subscriber->subscriberid,
            'channel' => 'mobile',
            'country_id' => $ghana->id,
            'domain' => 'ghana.altfon.net',
            'default_auth_id' => $mobile->id,
            'status' => 'active',
        ]);

        SubscriberChannelModel::create([
            'subscriberid' => $subscriber->subscriberid,
            'channel' => 'booth',
            'country_id' => null,
            'domain' => 'booth.altfon.net',
            'default_auth_id' => $booth->id,
            'status' => 'active',
        ]);

        return $subscriber;
    }

    private function fakeBooth(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'booth_getbal.php')) {
                return Http::response(['status' => 'success', 'balance' => '10.00'], 200);
            }

            return Http::response(['status' => 'success'], 200);
        });
    }

    public function test_balance_requests_send_the_channel_domain_as_post_params(): void
    {
        $this->accountWithBothChannels();
        $this->fakeBooth();

        $booth = app(BoothApiService::class);

        $booth->getBalance('111111');
        $booth->getBalance('222222');

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), 'booth_getbal.php')
                && $request['accesscode'] === '111111'
                && $request['domain'] === 'ghana.altfon.net';
        });

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), 'booth_getbal.php')
                && $request['accesscode'] === '222222'
                && $request['domain'] === 'booth.altfon.net';
        });
    }

    public function test_topup_requests_send_the_channel_domain_with_amount(): void
    {
        $this->accountWithBothChannels();
        $this->fakeBooth();

        $booth = app(BoothApiService::class);

        $booth->topup('111111', 10);
        $booth->topup('222222', 5);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), 'booth_topup.php')
                && $request['accesscode'] === '111111'
                && (float) $request['amount'] === 10.0
                && $request['domain'] === 'ghana.altfon.net';
        });

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), 'booth_topup.php')
                && $request['accesscode'] === '222222'
                && (float) $request['amount'] === 5.0
                && $request['domain'] === 'booth.altfon.net';
        });
    }

    public function test_batch_balance_requests_use_each_accounts_domain(): void
    {
        $this->accountWithBothChannels();
        $this->fakeBooth();

        $balances = app(BoothApiService::class)->getBalances(['111111', '222222']);

        $this->assertSame(10.0, $balances['111111']);
        $this->assertSame(10.0, $balances['222222']);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request['accesscode'] === '111111'
                && $request['domain'] === 'ghana.altfon.net';
        });

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request['accesscode'] === '222222'
                && $request['domain'] === 'booth.altfon.net';
        });
    }
}
