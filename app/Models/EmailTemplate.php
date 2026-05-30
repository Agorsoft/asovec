<?php

namespace App\Models;

use App\Enums\EmailTemplateType;
use Database\Factories\EmailTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    /** @use HasFactory<EmailTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'subject',
        'body_html',
        'description',
        'is_active',
    ];

    protected $casts = [
        'type' => EmailTemplateType::class,
        'is_active' => 'boolean',
    ];

    /**
     * Activa esta plantilla y desactiva las demás del mismo tipo.
     */
    public function activate(): void
    {
        static::where('type', $this->type)
            ->where('id', '!=', $this->id)
            ->update(['is_active' => false]);

        $this->update(['is_active' => true]);
    }

    /**
     * Devuelve la plantilla activa para un tipo dado, o null si no hay ninguna.
     */
    public static function activeForType(EmailTemplateType $type): ?self
    {
        return static::where('type', $type->value)
            ->where('is_active', true)
            ->first();
    }
}
