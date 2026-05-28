<?php

namespace App\Models;

use App\Enums\DocumentGenerationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentGeneration extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'generated_by',
        'member_id',
        'status',
        'output_path',
        'error_message',
    ];

    protected $casts = [
        'status' => DocumentGenerationStatus::class,
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
