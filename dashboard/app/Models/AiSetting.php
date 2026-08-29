<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiSetting extends Model
{
    protected $guarded = [];
    protected $hidden = ['api_key'];
    protected $casts = [
        'api_key' => 'encrypted',
        'business_hours' => 'array',
        'enabled' => 'boolean',
    ];
}
