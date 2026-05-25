<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\MemberStatus;
use App\Enums\MemberType;
use App\Enums\PaymentMethod;
use App\Enums\QuotaStatus;
use App\Enums\SepaMandateStatus;
use App\Enums\SepaSequenceType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    use HasFactory;

    protected $table = 'members';

    protected $attributes = [
        'gender' => Gender::NoDefinido,
        'member_type' => MemberType::Socio,
        'status' => MemberStatus::Pendiente,
    ];

    protected $fillable = [
        'member_number',
        'first_name',
        'last_name',
        'dni',
        'email',
        'phone',
        'address',
        'city',
        'province',
        'postal_code',
        'birth_date',
        'gender',
        'photo_path',
        'payment_method_id',
        'sepa_mandate_reference',
        'sepa_mandate_signed_at',
        'sepa_mandate_status',
        'sepa_sequence_type',
        'bank_account_holder',
        'bank_iban',
        'bank_bic',
        'member_type',
        'status',
        'joined_at',
        'left_at',
        'notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'joined_at' => 'date',
        'left_at' => 'date',
        'gender' => Gender::class,
        'member_type' => MemberType::class,
        'payment_method_id' => PaymentMethod::class,
        'status' => MemberStatus::class,
        'sepa_mandate_signed_at' => 'date',
        'sepa_mandate_status' => SepaMandateStatus::class,
        'sepa_sequence_type' => SepaSequenceType::class,
    ];

    public function isNoSocio(): bool
    {
        if ($this->member_type instanceof MemberType) {
            return $this->member_type === MemberType::NoSocio;
        }

        return (string) $this->member_type === MemberType::NoSocio->value;
    }

    public function isSocio(): bool
    {
        return ! $this->isNoSocio();
    }

    public function hasCurrentPaidQuota(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->quotas()
            ->where('status', QuotaStatus::Pagada)
            ->whereDate('period_start', '<=', $at->toDateString())
            ->whereDate('period_end', '>=', $at->toDateString())
            ->exists();
    }

    // Relationships
    public function quotas()
    {
        return $this->hasMany(Quota::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function sponsoredRegistrations()
    {
        return $this->hasMany(Registration::class, 'sponsor_member_id');
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getMemberNumberFormattedAttribute(): string
    {
        return (string) ($this->member_number ?? '');
    }
}
