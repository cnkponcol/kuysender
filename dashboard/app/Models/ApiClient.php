<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ApiClient extends Model
{
    use HasUuids;

    protected $guarded = [];
    protected $hidden = ['secret_hash', 'secret_value', 'webhook_secret'];
    protected $casts = [
        'scopes' => 'array',
        'secret_value' => 'encrypted',
        'webhook_secret' => 'encrypted',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function sessions()
    {
        return $this->belongsToMany(Session::class, 'api_client_sessions', 'api_client_id', 'session_id')->withTimestamps();
    }

    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];
        return in_array('*', $scopes, true) || in_array($scope, $scopes, true);
    }
}
