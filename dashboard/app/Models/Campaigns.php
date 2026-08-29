<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campaigns extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $casts = [
        'scheduled_at' => 'datetime',
        'opt_in_only' => 'boolean',
    ];

    public function bulk()
    {
        return $this->hasMany(Bulk::class, 'campaign_id', 'id');
    }

    public function contact_label()
    {
        return $this->belongsTo(ContactLabel::class, 'phonebook_id', 'id');
    }
}
