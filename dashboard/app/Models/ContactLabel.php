<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactLabel extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'label_id', 'id');
    }
}
