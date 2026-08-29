<?php

namespace App\Http\Controllers;

use App\Helpers\Lyn;
use App\Models\AutoResponder;
use App\Models\Bulk;
use App\Models\Campaigns;
use App\Models\Contact;
use App\Models\ContactLabel;
use App\Models\Session;
use App\Services\WaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DashController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax() || $request->isMethod('post')) {
            $rows = Session::where('user_id', $request->user()->id)->get();
            return datatables()->of($rows)->addIndexColumn()->addColumn('responsive_id', fn () => null)
                ->editColumn('status', function ($row) {
                    $connected = $row->connection_state === 'connected';
                    $class = $connected ? 'success' : ($row->connection_state === 'qr' ? 'warning' : 'danger');
                    return '<span class="badge rounded-pill bg-label-'.$class.'">'.e(strtoupper($row->connection_state ?: 'disconnected')).'</span>';
                })
                ->editColumn('whatsapp_number', fn ($row) => $row->whatsapp_number ?: '<span class="text-muted">Not connected</span>')
                ->addColumn('action', function ($row) {
                    return '<a href="'.route('device.detail', $row->id).'" class="btn btn-icon btn-label-primary me-1" title="Device"><span class="ti ti-qrcode"></span></a>'.
                        '<button type="button" class="btn btn-icon btn-label-danger is-delete-device" title="Delete" data-id="'.e($row->id).'"><span class="ti ti-trash-x"></span></button>';
                })->rawColumns(['action', 'status', 'whatsapp_number'])->make(true);
        }

        return Lyn::view('dash.dash', [
            'count_device_online' => Session::where('user_id', $request->user()->id)->where('connection_state', 'connected')->count(),
            'count_device' => Session::where('user_id', $request->user()->id)->count(),
        ]);
    }

    public function device_store(Request $request)
    {
        $data = $request->validate(['session_name' => ['required', 'string', 'max:120']]);
        $limit = $request->user()->limit_device;
        if ($limit !== null && Session::where('user_id', $request->user()->id)->count() >= $limit) {
            return response()->json(['message' => 'Device limit reached.'], 422);
        }
        Session::create([
            'session_name' => $data['session_name'], 'user_id' => $request->user()->id,
            'whatsapp_number' => null, 'webhook' => null, 'api_key' => Lyn::unique_apikey(32),
            'status' => 'STOPPED', 'connection_state' => 'disconnected',
        ]);
        return response()->json(['message' => 'Device created.']);
    }

    public function device_delete(Request $request, WaService $wa)
    {
        $data = $request->validate(['id' => ['required', 'uuid']]);
        $device = Session::where('id', $data['id'])->where('user_id', $request->user()->id)->firstOrFail();
        try { $wa->deleteDevice($device->id); } catch (\Throwable) {}
        AutoResponder::where('session_id', $device->id)->delete();
        Contact::where('session_id', $device->id)->delete();
        ContactLabel::where('session_id', $device->id)->delete();
        Bulk::where('session_id', $device->id)->delete();
        Campaigns::where('session_id', $device->id)->delete();
        $device->delete();
        if (session('main_device') === $data['id']) session()->forget('main_device');
        return response()->json(['message' => 'Device deleted.']);
    }

    public function ajax_change_device(Request $request)
    {
        $data = $request->validate(['device' => ['required', 'string']]);
        if ($data['device'] === 'forgot') {
            $request->session()->forget('main_device');
        } else {
            $device = Session::where('id', $data['device'])->where('user_id', $request->user()->id)->firstOrFail();
            $request->session()->put('main_device', $device->id);
        }
        return response()->json(['message' => 'Main device changed.']);
    }

    public function ajax_main_device(Request $request)
    {
        $current = session('main_device');
        $options = '<option value="forgot">-- Select Device --</option>';
        foreach (Session::where('user_id', $request->user()->id)->orderBy('session_name')->get() as $device) {
            $selected = $current === $device->id ? ' selected' : '';
            $options .= '<option'.$selected.' value="'.e($device->id).'">'.e($device->session_name).'</option>';
        }
        return response($options)->header('Content-Type', 'text/html');
    }

    public function files() { return Lyn::view('dash.files'); }

    public function storage(Request $request)
    {
        $data = $request->validate(['url' => ['required', 'string', 'max:2048']]);
        $path = ltrim(str_replace('\\', '/', urldecode($data['url'])), '/');
        $prefix = $request->user()->id.'/';
        abort_if(str_contains($path, '../') || (!str_starts_with($path, $prefix) && $path !== (string) $request->user()->id), 403);
        abort_unless(Storage::exists($path), 404);
        return Storage::response($path);
    }
}
