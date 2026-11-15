<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class SubscribersModel extends Authenticatable implements JWTSubject
{
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
        'country_id',
        'has_mobile_account',
        'has_booth_account',
        'domain',
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

    protected $casts = [
        'country_id' => 'integer',
        'has_mobile_account' => 'boolean',
        'has_booth_account' => 'boolean',
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

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function auth()
    {
        return $this->hasOne(SubscriberAuthModel::class, 'subscriberid', 'subscriberid');
    }

    public function auths()
    {
        return $this->hasMany(SubscriberAuthModel::class, 'subscriberid', 'subscriberid');
    }

    public function channels()
    {
        return $this->hasMany(SubscriberChannelModel::class, 'subscriberid', 'subscriberid');
    }

    public function mobileChannel()
    {
        return $this->hasOne(SubscriberChannelModel::class, 'subscriberid', 'subscriberid')
            ->where('channel', SubscriberChannelModel::MOBILE);
    }

    public function boothChannel()
    {
        return $this->hasOne(SubscriberChannelModel::class, 'subscriberid', 'subscriberid')
            ->where('channel', SubscriberChannelModel::BOOTH);
    }

    public function channelProfile(string $channel): ?SubscriberChannelModel
    {
        return $this->channels()->where('channel', $channel)->first();
    }

    public function hasChannel(string $channel): bool
    {
        if (!$this->channels()->exists()) {
            // Legacy/malformed rows without channel profiles keep working until
            // the database is rebuilt with explicit channels.
            return true;
        }

        return match ($channel) {
            SubscriberChannelModel::MOBILE => (bool) $this->has_mobile_account,
            SubscriberChannelModel::BOOTH => (bool) $this->has_booth_account,
            default => false,
        };
    }
}
