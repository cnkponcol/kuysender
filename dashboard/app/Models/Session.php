<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $hidden = ['api_key', 'qr_code'];
    protected $casts = [
        'qr_expires_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function messages()
    {
        return $this->hasMany(Message::class, 'session_id');
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'session_id');
    }

    public function apiClients()
    {
        return $this->belongsToMany(ApiClient::class, 'api_client_sessions', 'session_id', 'api_client_id')->withTimestamps();
    }

    public function aiSetting()
    {
        return $this->hasOne(AiSetting::class, 'session_id');
    }
}
