<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'label',
        'description',
    ];

    /**
     * Get setting value casted to its designated type.
     */
    public function getValueAttribute(mixed $value): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'double' => (float) $value,
            'json' => json_decode((string) $value, true),
            default => $value,
        };
    }

    /**
     * Set setting value.
     */
    public function setValueAttribute(mixed $value): void
    {
        $this->attributes['value'] = is_array($value) || is_object($value)
            ? json_encode($value)
            : (string) $value;
    }
}
