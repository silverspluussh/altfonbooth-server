<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriberResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'subscriberid' => $this->subscriberid,
            'fullname' => $this->fullname,
            'username' => $this->username,
            'phonenumber' => $this->phonenumber,
            'emailaddress' => $this->emailaddress,
            'country' => $this->country,
            'authusername' => $this->authusername,
            'switch_status' => $this->switch_status,
            'billing_acc_status' => $this->billing_acc_status,
            'regdatetime' => $this->regdatetime,
        ];
    }
}
