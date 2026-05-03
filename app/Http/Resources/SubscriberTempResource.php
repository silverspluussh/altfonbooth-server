<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriberTempResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'recid' => $this->recid,
            'subscriberid' => $this->subscriberid,
            'fullname' => $this->fullname,
            'username' => $this->username,
            'phonenumber' => $this->phonenumber,
            'emailaddress' => $this->emailaddress,
            'country' => $this->country,
            'otp' => $this->otp,
            'otp_expiration' => $this->otp_expiration,
            'verify_code' => $this->verify_code,
            'status' => $this->status,
            'regdatetime' => $this->regdatetime,
        ];
    }
}
