<?php

namespace App\Http\Controllers;

use App\Helpers\Lyn;
use App\Models\Bulk;
use App\Models\Campaigns;
use App\Models\Contact;
use App\Models\ContactLabel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CampaignsController extends Controller
{
    public function index(Request $request)
    {
        $sessionId = session('main_device');
        if (($request->ajax() || $request->isMethod('post')) && !$sessionId) return response()->json(['message' => 'No main device selected'], 422);
        if ($request->ajax() || $request->isMethod('post')) {
            $table = Campaigns::where('user_id', $request->user()->id)->where('session_id', $sessionId)->latest()->get();
            return datatables()->of($table)->addColumn('responsive_id', fn () => null)
                ->editColumn('scheduled_at', fn ($row) => $row->scheduled_at ? Carbon::parse($row->scheduled_at)->format('d M Y H:i') : 'Now')
                ->editColumn('delay', fn ($row) => $row->delay.'-'.$row->delay_max.'s')
                ->addColumn('of', function ($row) {
                    $total = $row->bulk()->where('status', '!=', 'invalid')->count();
                    $sent = $row->bulk()->where('status', 'sent')->count();
                    return Lyn::thousandsCurrencyFormat($sent).'/'.Lyn::thousandsCurrencyFormat($total);
                })
                ->editColumn('status', function ($row) {
                    $map = ['waiting' => 'warning', 'processing' => 'info', 'completed' => 'success', 'paused' => 'secondary'];
                    return '<span class="badge bg-label-'.($map[$row->status] ?? 'secondary').'">'.e(ucfirst($row->status)).'</span>';
                })
                ->addColumn('action', function ($row) {
                    $btn = '<a href="'.route('campaigns.detail', $row->id).'" class="btn btn-icon btn-label-primary me-1" title="Detail"><span class="ti ti-list-details"></span></a>';
                    if ($row->status === 'paused') $btn .= '<button type="button" class="btn btn-icon btn-label-success me-1 is-change-status" data-id="'.$row->id.'" data-status="resume" title="Resume"><span class="ti ti-player-play"></span></button>';
                    elseif (in_array($row->status, ['waiting','processing'], true)) $btn .= '<button type="button" class="btn btn-icon btn-label-warning me-1 is-change-status" data-id="'.$row->id.'" data-status="pause" title="Pause"><span class="ti ti-player-pause"></span></button>';
                    return $btn;
                })->rawColumns(['action','status'])->make(true);
        }
        if (!$sessionId) return Lyn::view('nodevice');
        return Lyn::view('campaigns.index', ['phonebook' => ContactLabel::where('user_id', $request->user()->id)->where('session_id', $sessionId)->withCount('contacts')->get()]);
    }

    public function ajax_change_status(Request $request)
    {
        $sessionId = session('main_device');
        $data = $request->validate(['id' => ['required','integer'], 'status' => ['required','in:pause,resume']]);
        $campaign = Campaigns::where('id', $data['id'])->where('user_id', $request->user()->id)->where('session_id', $sessionId)->firstOrFail();
        if ($campaign->status === 'completed') return response()->json(['message' => 'Campaign already completed.'], 422);
        if ($data['status'] === 'pause') $campaign->update(['status' => 'paused']);
        else $campaign->update(['status' => 'waiting', 'stopped_reason' => null]);
        return response()->json(['message' => $data['status'] === 'pause' ? 'Campaign paused.' : 'Campaign resumed.']);
    }

    public function store(Request $request)
    {
        $sessionId = session('main_device');
        if (!$sessionId) return response()->json(['message' => 'No main device selected'], 422);
        $data = $request->validate([
            'name' => ['required','string','max:190'], 'phonebook_id' => ['required','integer'],
            'message_type' => ['required','in:text,media,button,list'],
            'delay' => ['required','integer','min:5','max:3600'], 'delay_max' => ['nullable','integer','min:5','max:3600','gte:delay'],
            'max_recipients' => ['nullable','integer','min:1','max:10000'], 'scheduled_at' => ['nullable','date'],
            'send_window_start' => ['nullable','date_format:H:i'], 'send_window_end' => ['nullable','date_format:H:i'],
            'stop_error_rate' => ['nullable','integer','min:5','max:100'],
        ]);
        $label = ContactLabel::where('id', $data['phonebook_id'])->where('user_id', $request->user()->id)->where('session_id', $sessionId)->firstOrFail();
        $query = Contact::where('user_id', $request->user()->id)->where('session_id', $sessionId)->where('label_id', $label->id)
            ->where('opt_in_status', 'opted_in')->whereNull('opted_out_at')->whereNull('blocklisted_at');
        if (!empty($data['max_recipients'])) $query->limit($data['max_recipients']);
        $contacts = $query->get();
        if ($contacts->isEmpty()) return response()->json(['message' => 'No opted-in contacts found in this phonebook.'], 422);

        $campaign = new Campaigns();
        $campaign->user_id = $request->user()->id; $campaign->session_id = $sessionId; $campaign->name = $data['name'];
        $campaign->phonebook_id = $label->id; $campaign->message_type = $data['message_type'];
        $campaign->delay = $data['delay']; $campaign->delay_max = $data['delay_max'] ?? max($data['delay'], (int) config('services.broadcast.max_delay', 45));
        $campaign->max_recipients = $data['max_recipients'] ?? $contacts->count(); $campaign->scheduled_at = $data['scheduled_at'] ?? now();
        $campaign->send_window_start = $data['send_window_start'] ?? null; $campaign->send_window_end = $data['send_window_end'] ?? null;
        $campaign->stop_error_rate = $data['stop_error_rate'] ?? 35; $campaign->opt_in_only = true; $campaign->status = 'waiting';
        Lyn::genereate_message($campaign, $request, 'save');

        $rows = [];
        foreach ($contacts as $contact) $rows[] = [
            'id' => (string) Str::uuid(), 'user_id' => $request->user()->id, 'session_id' => $sessionId,
            'campaign_id' => $campaign->id, 'receiver' => $contact->deliveryAddress(), 'message_type' => $campaign->message_type,
            'message' => $campaign->message, 'status' => 'pending', 'contact_id' => $contact->id, 'attempts' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ];
        Bulk::insert($rows);
        return response()->json(['message' => 'Campaign created for '.$contacts->count().' opted-in contacts.']);
    }

    public function detail(Request $request, int $id)
    {
        $sessionId = session('main_device');
        if (!$sessionId) return Lyn::view('nodevice');
        $campaign = Campaigns::where('id', $id)->where('user_id', $request->user()->id)->where('session_id', $sessionId)->firstOrFail();
        if ($request->ajax() || $request->isMethod('post')) {
            return datatables()->of(Bulk::where('campaign_id', $campaign->id)->get())->addColumn('responsive_id', fn () => null)
                ->editColumn('status', fn ($row) => '<span class="badge bg-label-'.(['pending'=>'warning','sent'=>'success','failed'=>'danger','invalid'=>'secondary'][$row->status] ?? 'secondary').'">'.e(ucfirst($row->status)).'</span>')
                ->addColumn('type', fn ($row) => str_contains($row->receiver, '@g.us') ? 'Group' : 'Personal')
                ->editColumn('updated_at', fn ($row) => optional($row->updated_at)->format('d M Y H:i') ?: '-')
                ->rawColumns(['status'])->make(true);
        }
        return Lyn::view('campaigns.detail', ['row' => $campaign, 'data' => json_decode($campaign->message)]);
    }

    public function delete(Request $request)
    {
        $sessionId = session('main_device');
        $data = $request->validate(['id' => ['required','array'], 'id.*' => ['integer']]);
        $campaigns = Campaigns::whereIn('id', $data['id'])->where('user_id', $request->user()->id)->where('session_id', $sessionId)->get();
        foreach ($campaigns as $campaign) { $campaign->bulk()->delete(); $campaign->delete(); }
        return response()->json(['message' => 'Campaigns deleted.']);
    }
}
