<?php

namespace App\Models;

use App\Enums\TransactionSourceType;
use App\Enums\TransactionType;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'amount',
        'date',
        'description',
        'parent_transaction_id',
        'category_id',
        'source_type',
        'payment_method',
        'reference_type',
        'reference_id',
        'receipt_number',
        'notes',
    ];

    protected $casts = [
        'type' => TransactionType::class,
        'amount' => 'decimal:2',
        'date' => 'date',
        'source_type' => TransactionSourceType::class,
    ];

    public function parentTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_transaction_id');
    }

    public function childTransactions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_transaction_id');
    }

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_transaction_id');
    }

    public function scopeChildrenFirst(Builder $query): Builder
    {
        return $query->orderByRaw('coalesce(parent_transaction_id, id) asc')
            ->orderByRaw('case when parent_transaction_id is null then 0 else 1 end asc')
            ->orderBy('id');
    }

    public function scopeBasePrice(Builder $query): Builder
    {
        return $query->whereIn('source_type', array_map(
            fn (TransactionSourceType $sourceType): string => $sourceType->value,
            TransactionSourceType::basePriceCases(),
        ));
    }

    public function scopeSurcharge(Builder $query): Builder
    {
        return $query->whereIn('source_type', array_map(
            fn (TransactionSourceType $sourceType): string => $sourceType->value,
            TransactionSourceType::surchargeCases(),
        ));
    }

    public function isBasePrice(): bool
    {
        return $this->source_type?->isBasePrice() ?? false;
    }

    public function isSurcharge(): bool
    {
        return $this->source_type?->isSurcharge() ?? false;
    }

    public function sourceLabel(): string
    {
        return $this->source_type?->getLabel()
            ?? $this->category?->name
            ?? 'Sin origen';
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
