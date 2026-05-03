<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SubscribersTempModel extends Model
{
    protected $table = 'subscribers_temp';
    protected $primaryKey = 'recid';
    protected $fillable = [
        'subscriberid',
        'fullname',
        'username',
        'password',
        'phonenumber',
        'emailaddress',
        'country',
        'otp',
        'otp_expiration',
        'verify_code',
        'status'
    ];

    public $timestamps = true;
    const CREATED_AT = 'regdatetime';
    const UPDATED_AT = 'regdatetime';

    public function getByPhone($phonenumber)
    {
        return $this->where('phonenumber', $phonenumber)->first();
    }

    public function getByOTP($phonenumber, $otp)
    {
        return $this->where('phonenumber', $phonenumber)
            ->where('otp', $otp)
            ->first();
    }
}
