<?php

namespace App\Helpers;

use App\Models\Contact;
use Maatwebsite\Excel\Concerns\FromCollection;

class ContactsExport implements FromCollection
{
    public function __construct(protected $label_id, protected $user_id, protected $session_id) {}

    public function collection()
    {
        return Contact::where(['user_id' => $this->user_id, 'label_id' => $this->label_id, 'session_id' => $this->session_id])
            ->get(['name', 'number', 'opt_in_status', 'opt_in_source', 'opted_in_at', 'opted_out_at']);
    }
}
