<?php

namespace App\Http\Controllers;

use App\Helpers\ContactImport;
use App\Helpers\ContactsExport;
use App\Helpers\Lyn;
use App\Models\Contact;
use App\Models\ContactLabel;
use App\Models\Session;
use App\Services\WaService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class PhonebookController extends Controller
{
    private function session(Request $request): Session
    {
        $id = session('main_device');
        abort_unless($id, 404, 'No device selected.');
        return Session::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
    }

    private function label(Request $request, int|string $id): ContactLabel
    {
        $session = $this->session($request);
        return ContactLabel::where('id', $id)->where('user_id', $request->user()->id)->where('session_id', $session->id)->firstOrFail();
    }

    public function index(Request $request)
    {
        if (!session('main_device')) return Lyn::view('nodevice');
        $session = $this->session($request);
        return Lyn::view('phonebook.index', ['phonebook' => ContactLabel::where('user_id', $request->user()->id)->where('session_id', $session->id)]);
    }

    public function ajax_label_store(Request $request)
    {
        $session = $this->session($request);
        $data = $request->validate(['title' => ['required', 'string', 'max:120']]);
        $label = ContactLabel::create(['user_id' => $request->user()->id, 'session_id' => $session->id, 'title' => $data['title']]);
        return response()->json(['message' => 'Label created.', 'data' => ['title' => $label->title, 'url' => route('phonebook.contacts.index', $label->id)]]);
    }

    public function label_delete(Request $request, int $id)
    {
        $label = $this->label($request, $id);
        Contact::where('label_id', $label->id)->where('user_id', $request->user()->id)->delete();
        $label->delete();
        return response()->json(['message' => 'Phonebook and its contacts removed.']);
    }

    public function contacts(Request $request, int $id)
    {
        $label = $this->label($request, $id);
        if ($request->ajax() || $request->isMethod('post')) {
            $rows = Contact::where('user_id', $request->user()->id)->where('session_id', $label->session_id)->where('label_id', $label->id)->get();
            return datatables()->of($rows)->addIndexColumn()->addColumn('responsive_id', fn () => null)
                ->addColumn('type', fn ($row) => str_contains($row->number, '@g.us') ? 'Group' : 'Personal')
                ->addColumn('consent', function ($row) {
                    $map = ['opted_in' => 'success', 'opted_out' => 'danger', 'unknown' => 'secondary'];
                    $class = $map[$row->opt_in_status] ?? 'secondary';
                    return '<span class="badge bg-label-'.$class.'">'.e(str_replace('_', ' ', $row->opt_in_status)).'</span>';
                })
                ->addColumn('action', function ($row) {
                    return '<div class="d-flex gap-1">'
                        .'<button type="button" class="btn btn-sm btn-label-success set-consent" data-id="'.(int) $row->id.'" data-status="opted_in">Opt-in</button>'
                        .'<button type="button" class="btn btn-sm btn-label-danger set-consent" data-id="'.(int) $row->id.'" data-status="opted_out">Opt-out</button>'
                        .'</div>';
                })->rawColumns(['consent', 'action'])->make(true);
        }
        return Lyn::view('phonebook.contacts', ['label' => $label, 'getlabel' => ContactLabel::where('user_id', $request->user()->id)->where('session_id', $label->session_id)->get()]);
    }

    public function contacts_store(Request $request, int $id)
    {
        $label = $this->label($request, $id);
        $data = $request->validate([
            'number' => ['required', 'string', 'max:80'], 'name' => ['nullable', 'string', 'max:190'],
            'opt_in' => ['nullable', 'boolean'], 'opt_in_source' => ['required_if:opt_in,1', 'nullable', 'string', 'max:190'],
        ]);
        $number = str_contains($data['number'], '@g.us') ? trim($data['number']) : preg_replace('/\D+/', '', $data['number']);
        if (!str_contains($number, '@g.us') && strlen($number) < 7) return response()->json(['message' => 'Invalid number.'], 422);
        if (Contact::where('user_id', $request->user()->id)->where('session_id', $label->session_id)->where('label_id', $label->id)->where('number', $number)->exists()) {
            return response()->json(['message' => 'Number already exists.'], 422);
        }
        Contact::create([
            'user_id' => $request->user()->id, 'session_id' => $label->session_id, 'label_id' => $label->id,
            'name' => $data['name'] ?? null, 'number' => $number,
            'opt_in_status' => $request->boolean('opt_in') ? 'opted_in' : 'unknown',
            'opt_in_source' => $request->boolean('opt_in') ? $data['opt_in_source'] : null,
            'opted_in_at' => $request->boolean('opt_in') ? now() : null,
        ]);
        return response()->json(['message' => 'Contact created.']);
    }

    public function contacts_delete(Request $request)
    {
        $session = $this->session($request);
        $data = $request->validate(['id' => ['required', 'array'], 'id.*' => ['integer']]);
        Contact::where('user_id', $request->user()->id)->where('session_id', $session->id)->whereIn('id', $data['id'])->delete();
        return response()->json(['message' => 'Contacts deleted.']);
    }

    public function consent(Request $request, int $id)
    {
        $session = $this->session($request);
        $data = $request->validate(['status' => ['required', 'in:opted_in,opted_out,unknown'], 'source' => ['nullable', 'string', 'max:190']]);
        $contact = Contact::where('id', $id)->where('user_id', $request->user()->id)->where('session_id', $session->id)->firstOrFail();
        if ($data['status'] === 'opted_in' && empty($data['source'])) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => 'Opt-in source is required.'], 422);
            }
            return back()->withErrors(['message' => 'Opt-in source is required.']);
        }
        $contact->update([
            'opt_in_status' => $data['status'],
            'opt_in_source' => $data['status'] === 'opted_in' ? $data['source'] : $contact->opt_in_source,
            'opted_in_at' => $data['status'] === 'opted_in' ? now() : null,
            'opted_out_at' => $data['status'] === 'opted_out' ? now() : null,
        ]);
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => 'Contact consent updated.']);
        }
        return back()->with('success', 'Contact consent updated.');
    }

    public function contacts_export(Request $request, int $id)
    {
        $label = $this->label($request, $id);
        if (!Contact::where('label_id', $label->id)->exists()) return back()->withErrors(['message' => 'No contacts found.']);
        return Excel::download(new ContactsExport($label->id, $request->user()->id, $label->session_id), $label->title.' - '.date('d-m-Y').'.xlsx');
    }

    public function contacts_import(Request $request, int $id)
    {
        $label = $this->label($request, $id);
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:5120']]);
        try {
            Excel::import(new ContactImport($label->id, $request->user()->id, $label->session_id), $request->file('file'));
            return response()->json(['message' => 'Contacts imported. Imported contacts remain consent=unknown until explicitly opted in.']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Import failed. Please verify the spreadsheet format.'], 422);
        }
    }

    public function sync_whatsapp_contacts(Request $request, int $id, WaService $wa)
    {
        $label = $this->label($request, $id);
        try {
            $response = $wa->contacts($label->session_id);
            $created = 0;
            $updated = 0;
            foreach (($response['data'] ?? []) as $row) {
                $jid = trim((string) ($row['jid'] ?? ''));
                $number = preg_replace('/\D+/', '', (string) ($row['number'] ?? explode('@', $jid)[0] ?? ''));
                if ($jid === '' || $number === '' || (!str_ends_with($jid, '@s.whatsapp.net') && !str_ends_with($jid, '@lid'))) continue;
                $name = trim((string) ($row['name'] ?? $row['notify'] ?? $row['verified_name'] ?? ''));

                $contact = Contact::where('user_id', $request->user()->id)
                    ->where('session_id', $label->session_id)
                    ->where('label_id', $label->id)
                    ->where(function ($query) use ($jid, $number) {
                        $query->where('wa_jid', $jid)->orWhere('number', $number);
                    })->first();

                if (!$contact) {
                    Contact::create([
                        'user_id' => $request->user()->id,
                        'session_id' => $label->session_id,
                        'label_id' => $label->id,
                        'name' => $name !== '' ? $name : null,
                        'profile_name' => $name !== '' ? $name : null,
                        'number' => $number,
                        'wa_jid' => $jid,
                        'opt_in_status' => 'unknown',
                    ]);
                    $created++;
                    continue;
                }

                $changes = ['wa_jid' => $jid];
                if ($name !== '') {
                    $changes['profile_name'] = $name;
                    if (!$contact->name) $changes['name'] = $name;
                }
                $contact->update($changes);
                $updated++;
            }

            return response()->json([
                'message' => 'WhatsApp personal contacts synced: '.$created.' added, '.$updated.' updated. Contacts stay consent=unknown until explicitly opted in.',
                'created' => $created,
                'updated' => $updated,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    public function fetch_group(Request $request, int $id, WaService $wa)
    {
        $label = $this->label($request, $id);
        try {
            $response = $wa->groups($label->session_id);
            foreach (($response['data'] ?? []) as $group) {
                if (empty($group['id'])) continue;
                Contact::firstOrCreate(
                    ['user_id' => $request->user()->id, 'session_id' => $label->session_id, 'label_id' => $label->id, 'number' => $group['id']],
                    ['name' => $group['subject'] ?? $group['id'], 'opt_in_status' => 'unknown']
                );
            }
            return response()->json(['message' => 'WhatsApp groups synced.']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }
}
