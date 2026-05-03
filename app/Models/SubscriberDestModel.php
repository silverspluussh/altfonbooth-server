<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SubscriberDestModel extends Model
{
    protected $table = 'subscriber_dest';
    protected $primaryKey = 'id';

    protected $fillable = [
        'subscriberid',
        'authusername',
        'destination',
        'status'
    ];
    public $timestamps = true;

    public function getBySubscriber($subscriberid)
    {
        return $this->where('subscriberid', $subscriberid)->get();
    }

    public function deleteDest($subscriberid, $authuser, $destination)
    {
        return $this->where('subscriberid', $subscriberid)
            ->where('authusername', $authuser)
            ->where('destination', $destination)
            ->delete();
    }
}
