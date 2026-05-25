<?php

namespace App\Models;

use App\Enums\DocumentGenerationStatus;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'file_path',
        'detected_variables',
        'preview_hash',
        'preview_html',
        'preview_generated_at',
    ];

    protected $casts = [
        'type'                  => DocumentType::class,
        'detected_variables'    => 'array',
        'preview_generated_at'  => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $template) {
            if ($template->isDirty('file_path')) {
                $template->preview_hash = null;
                $template->preview_html = null;
                $template->preview_generated_at = null;
            }
        });
    }

    public function generations(): HasMany
    {
        return $this->hasMany(DocumentGeneration::class, 'template_id');
    }
}
