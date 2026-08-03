<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'model',
        'temperature',
        'max_tokens',
        'top_p',
        'frequency_penalty',
        'presence_penalty',
        'enabled_tools',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'temperature' => 'float',
            'max_tokens' => 'integer',
            'top_p' => 'float',
            'frequency_penalty' => 'float',
            'presence_penalty' => 'float',
            'enabled_tools' => 'array',
            'is_default' => 'boolean',
        ];
    }
}
