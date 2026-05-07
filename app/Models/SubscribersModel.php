<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class SubscribersModel extends Authenticatable
{
    use HasApiTokens;
    protected $table = 'subscribers';
    protected $primaryKey = 'recid';
    protected $fillable = [
        'subscriberid',
        'fullname',
        'username',
        'password',
        'phonenumber',
        'emailaddress',
        'country',
        'authusername',
        'switch_status',
        'billing_acc_status',
        'password_reset_token',
        'password_reset_expiration'
    ];

    protected $hidden = [
        'password',
        'password_reset_token',
        'password_reset_expiration',
    ];

    public $timestamps = true;
    const CREATED_AT = 'regdatetime';
    const UPDATED_AT = 'regdatetime';

    public function getByUsernameOrEmail($identifier)
    {
        return $this->where('username', $identifier)
            ->orWhere('emailaddress', $identifier)
            ->first();
    }

    public function getBySubscriberId($subscriberid)
    {
        return $this->where('subscriberid', $subscriberid)->first();
    }

    public function auth()
    {
        return $this->hasOne(SubscriberAuthModel::class, 'subscriberid', 'subscriberid');
    }
}
