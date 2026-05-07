<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrepaidCredit extends Model
{
    protected $table = 'prepaid_credits';
    protected $fillable = [
        'authusername',
        'amount',
        'transaction_id',
        'status'
    ];
}
