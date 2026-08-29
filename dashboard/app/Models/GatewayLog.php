<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GatewayLog extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['context' => 'array', 'created_at' => 'datetime'];
}
