<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Contact;
use App\Models\ContactLabel;
use Illuminate\Http\Request;

class ContactController extends BaseApiController
{
    public function index(Request $request)
    {
        $data = $request->validate(['session_id' => ['required', 'uuid'], 'search' => ['nullable', 'string', 'max:120']]);
        $session = $this->session($request, $data['session_id']);
        $query = Contact::where('session_id', $session->id)->where('user_id', $session->user_id);
        if (!empty($data['search'])) {
            $search = $data['search'];
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('number', 'like', "%{$search}%"));
        }
        return response()->json($query->orderByDesc('last_chat_at')->paginate(100));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'session_id' => ['required', 'uuid'],
            'name' => ['nullable', 'string', 'max:190'],
            'number' => ['required', 'string', 'max:40'],
            'opt_in' => ['sometimes', 'boolean'],
            'opt_in_source' => ['required_if:opt_in,true', 'nullable', 'string', 'max:190'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:80'],
        ]);
        $session = $this->session($request, $data['session_id']);
        $number = preg_replace('/\D+/', '', $data['number']);
        abort_if(strlen($number) < 7, 422, 'Invalid phone number.');
        $label = ContactLabel::firstOrCreate(['user_id' => $session->user_id, 'session_id' => $session->id, 'title' => 'API Contacts']);
        $contact = Contact::updateOrCreate(
            ['user_id' => $session->user_id, 'session_id' => $session->id, 'number' => $number],
            [
                'label_id' => $label->id,
                'name' => $data['name'] ?? null,
                'tags' => $data['tags'] ?? null,
                'opt_in_status' => !empty($data['opt_in']) ? 'opted_in' : 'unknown',
                'opt_in_source' => !empty($data['opt_in']) ? $data['opt_in_source'] : null,
                'opted_in_at' => !empty($data['opt_in']) ? now() : null,
                'opted_out_at' => null,
            ]
        );
        return response()->json(['data' => $contact], 201);
    }

    public function optOut(Request $request, int $contactId)
    {
        $data = $request->validate(['session_id' => ['required', 'uuid']]);
        $session = $this->session($request, $data['session_id']);
        $contact = Contact::where('id', $contactId)->where('session_id', $session->id)->firstOrFail();
        $contact->update(['opt_in_status' => 'opted_out', 'opted_out_at' => now()]);
        return response()->json(['data' => $contact]);
    }
}
