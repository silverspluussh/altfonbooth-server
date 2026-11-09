<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UssdPaymentIntent extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PENDING_TOPUP = 'pending_topup';

    protected $table = 'ussd_payment_intents';

    protected $fillable = [
        'reference',
        'subscriberid',
        'authusername',
        'msisdn',
        'amount',
        'network',
        'provider',
        'external_ref',
        'status',
        'failure_reason',
        'topup_attempts',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'topup_attempts' => 'integer',
            'paid_at' => 'datetime',
        ];
    }
}
