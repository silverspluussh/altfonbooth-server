<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SubscriberAuthModel extends Model
{
    protected $table = 'subscriber_auth';
    protected $primaryKey = 'id';
    protected $fillable = [
        'subscriberid',
        'authusername',
        'authpassword',
        'status'
    ];
    public $timestamps = true;

    public function getBySubscriberId($subscriberid)
    {
        return $this->where('subscriberid', $subscriberid)->get();
    }

    public function getByAuthUsername($authusername)
    {
        return $this->where('authusername', $authusername)->first();
    }
    public function credits()
    {
        return $this->hasMany(PrepaidCredit::class, 'authusername', 'authusername');
    }

    public function getBalanceAttribute()
    {
        return $this->credits()->sum('amount');
    }
}
