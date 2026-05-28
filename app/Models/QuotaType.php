<?php

namespace App\Models;

use App\Enums\PaymentPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuotaType extends Model
{
    use HasFactory;

    protected $table = 'quota_types';

    protected $fillable = [
        'name',
        'description',
        'period_type',
        'period_reference_date',
        'default_amount',
        'align_period_start',
        'grace_days',
        'refund_days',
        'sort_order',
    ];

    protected $casts = [
        'period_type' => PaymentPeriod::class,
        'period_reference_date' => 'date',
        'default_amount' => 'decimal:2',
        'align_period_start' => 'boolean',
        'grace_days' => 'integer',
        'refund_days' => 'integer',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function quotas()
    {
        return $this->hasMany(Quota::class, 'quota_type_id');
    }
}
