<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $casts = [
        'tags' => 'array',
        'opted_in_at' => 'datetime',
        'opted_out_at' => 'datetime',
        'first_chat_at' => 'datetime',
        'last_chat_at' => 'datetime',
        'human_takeover' => 'boolean',
        'ai_paused_until' => 'datetime',
        'blocklisted_at' => 'datetime',
    ];

    public function isBroadcastEligible(): bool
    {
        return $this->opt_in_status === 'opted_in' && $this->blocklisted_at === null && $this->opted_out_at === null;
    }

    public function deliveryAddress(): string
    {
        $jid = trim((string) $this->wa_jid);
        $number = preg_replace('/\D+/', '', (string) $this->number);

        if ($jid !== '' && !str_ends_with($jid, '@lid')) return $jid;
        if ($jid !== '' && str_ends_with($jid, '@lid')) {
            $lidNumber = preg_replace('/\D+/', '', explode('@', $jid)[0]);
            if ($number !== '' && $number !== $lidNumber) return $number.'@s.whatsapp.net';
            return $jid;
        }
        return $number;
    }
}
