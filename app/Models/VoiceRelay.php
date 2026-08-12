<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoiceRelay extends Model
{
    protected $table = 'voice_relay';

    protected $primaryKey = 'recid';

    public $timestamps = true;

    protected $fillable = [
        'domain',
        'port',
        'protocol',
        'outboundproxy',
    ];
}