<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UiText extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ui_texts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
