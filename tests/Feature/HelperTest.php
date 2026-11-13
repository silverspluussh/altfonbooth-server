<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;
use Tests\TestCase;

class HelperTest extends TestCase
{
    /**
     * Test SMS helper.
     */
    public function test_send_sms_mnotify_helper(): void
    {
        Http::fake([
            'api.mnotify.com/*' => Http::response(['status' => 'success'], 200),
        ]);

        $result = send_sms_mnotify('0240000000', 'Test Message');

        Http::assertSent(function ($request) {
            return $request->url() == "https://api.mnotify.com/api/sms/quick?key=" . config('services.mnotify.key') &&
                   $request['recipient'] == ['233240000000'];
        });

        $this->assertNotFalse($result);
    }

    /**
     * The gateway must receive a canonical Ghanaian number even when the
     * stored value contains a misplaced trunk zero.
     */
    public function test_send_sms_mnotify_normalizes_a_misplaced_trunk_zero(): void
    {
        Http::fake([
            'api.mnotify.com/*' => Http::response(['status' => 'success'], 200),
        ]);

        $result = send_sms_mnotify('2330249335549', 'Test Message');

        Http::assertSent(function ($request) {
            return $request['recipient'] == ['233249335549'];
        });

        $this->assertNotFalse($result);
    }

    /**
     * Test Email helper.
     */
    public function test_send_email_otp_helper(): void
    {
        Mail::fake();

        send_email_otp('test@example.com', '123456');

        Mail::assertQueued(OtpMail::class, function ($mail) {
            return $mail->hasTo('test@example.com') && $mail->otp === '123456';
        });
    }
}
