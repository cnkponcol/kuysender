<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasUuids;

    protected $guarded = [];
    protected $casts = [
        'is_read' => 'boolean',
        'metadata' => 'array',
        'message_at' => 'datetime',
    ];
}
