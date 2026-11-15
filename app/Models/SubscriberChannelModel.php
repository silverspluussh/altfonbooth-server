<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriberChannelModel extends Model
{
    public const MOBILE = 'mobile';
    public const BOOTH = 'booth';

    protected $table = 'subscriber_channels';

    protected $fillable = [
        'subscriberid',
        'channel',
        'country_id',
        'domain',
        'default_auth_id',
        'status',
    ];

    protected $casts = [
        'country_id' => 'integer',
        'default_auth_id' => 'integer',
    ];

    public function subscriber()
    {
        return $this->belongsTo(SubscribersModel::class, 'subscriberid', 'subscriberid');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function defaultAuth()
    {
        return $this->belongsTo(SubscriberAuthModel::class, 'default_auth_id');
    }
}
