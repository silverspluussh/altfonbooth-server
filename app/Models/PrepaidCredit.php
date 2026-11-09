<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrepaidCredit extends Model
{
    public const STATUS_PENDING_TOPUP = 'pending_topup';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $table = 'prepaid_credits';

    protected $fillable = [
        'authusername',
        'amount',
        'transaction_id',
        'status',
        'source',
        'topup_attempts',
        'failure_reason',
        'paid_at',
        'last_attempt_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'topup_attempts' => 'integer',
            'paid_at' => 'datetime',
            'last_attempt_at' => 'datetime',
        ];
    }
}
