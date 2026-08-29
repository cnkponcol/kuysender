<?php

namespace App\Http\Controllers;

use App\Helpers\Lyn;
use App\Models\AiKnowledgeItem;
use App\Models\AiSetting;
use App\Models\ApiClient;
use App\Models\ApiRequestLog;
use App\Models\AutoResponder;
use App\Models\Bulk;
use App\Models\Campaigns;
use App\Models\Contact;
use App\Models\ContactLabel;
use App\Models\GatewayLog;
use App\Models\Message;
use App\Models\Session;
use App\Models\User;
use App\Models\WebhookDelivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function users(Request $request)
    {
        if ($request->ajax() || $request->isMethod('POST')) {
            $table = User::all();
            return datatables()->of($table)->addIndexColumn()
                ->addColumn('responsive_id', function () {
                    return '';
                })
                ->addColumn('action', function ($row) {
                    $btn = '<a href="javascript:void(0)"  class="btn btn-icon btn-label-primary me-1 is-btn-user-edit" data-id="' . $row->id . '"><span class="ti ti-edit"></span></a>';
                    $btn .= '<a href="javascript:void(0)" class="btn btn-icon btn-label-danger is-btn-user-delete" data-id="' . $row->id . '"><span class="ti ti-trash-x"></span></a>';
                    return $btn;
                })
                ->addColumn('limit', function ($row) {
                    return ($row->limit_device == null) ? 'Unlimited' : $row->limit_device;
                })
                ->rawColumns(['action'])
                ->make(true);
        }
        return Lyn::view('admin.users');
    }

    public function users_store(Request $request)
    {
        if (!$request->ajax()) return response()->json(['status' => 'error', 'message' => 'Invalid request'], 400);
        $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'username' => ['required', 'string', 'max:190', 'alpha_dash', 'unique:users,username'],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'limit_device' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'password' => ['required', 'string', 'min:12', 'max:255'],
        ]);

        $user = new User();
        $user->name = $request->name;
        $user->username = $request->username;
        $user->role = $request->role;
        $user->limit_device = $request->limit_device == 0 ? null : $request->limit_device;
        $user->password = bcrypt($request->password);
        $user->save();

        return response()->json(['status' => 'success', 'message' => 'User created successfully'], 200);
    }

    public function users_update(Request $request)
    {
        if (!$request->ajax()) return response()->json(['status' => 'error', 'message' => 'Invalid request'], 400);

        $request->validate([
            'id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:190'],
            'username' => ['required', 'string', 'max:190', 'alpha_dash', 'unique:users,username,' . $request->id],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'limit_device' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'password' => ['nullable', 'string', 'min:12', 'max:255'],
        ]);

        $user = User::find($request->id);
        if (!$user) return response()->json(['status' => 'error', 'message' => 'User not found'], 404);
        $user->name = $request->name;
        $user->username = $request->username;
        $user->role = $request->role;
        $user->limit_device = $request->limit_device == 0 ? null : $request->limit_device;
        if ($request->password) {
            $user->password = bcrypt($request->password);
        }
        $user->save();

        return response()->json(['status' => 'success', 'message' => 'User updated successfully'], 200);
    }

    public function users_edit(Request $request, $id)
    {
        if (!$request->ajax()) return response()->json(['status' => 'error', 'message' => 'Invalid request'], 400);
        $user = User::find($id);
        if (!$user) return response()->json(['status' => 'error', 'message' => 'User not found'], 404);
        return response()->json([
            'status' => 'success',
            'data' => $user
        ], 200);
    }

    public function users_delete(Request $request, $id)
    {
        if (!$request->ajax()) return response()->json(['status' => 'error', 'message' => 'Invalid request'], 400);
        $user = User::find($id);
        if (!$user) return response()->json(['status' => 'error', 'message' => 'User not found'], 404);
        if ((int) $request->user()->id === (int) $user->id) {
            return response()->json(['status' => 'error', 'message' => 'You cannot delete your own signed-in account.'], 422);
        }

        $sessionIds = Session::where('user_id', $user->id)->pluck('id');
        $clientIds = ApiClient::where('user_id', $user->id)->pluck('id');

        DB::transaction(function () use ($user, $sessionIds, $clientIds) {
            WebhookDelivery::whereIn('api_client_id', $clientIds)->delete();
            ApiRequestLog::whereIn('api_client_id', $clientIds)->delete();
            DB::table('api_client_sessions')->whereIn('api_client_id', $clientIds)->delete();
            ApiClient::whereIn('id', $clientIds)->delete();
            AiKnowledgeItem::where('user_id', $user->id)->delete();
            AiSetting::where('user_id', $user->id)->delete();
            Message::where('user_id', $user->id)->delete();
            GatewayLog::where('user_id', $user->id)->delete();
            AutoResponder::where('user_id', $user->id)->delete();
            Bulk::where('user_id', $user->id)->delete();
            Campaigns::where('user_id', $user->id)->delete();
            Contact::where('user_id', $user->id)->delete();
            ContactLabel::where('user_id', $user->id)->delete();
            Session::whereIn('id', $sessionIds)->delete();
            $user->delete();
        });

        if (Storage::exists((string) $user->id)) {
            Storage::deleteDirectory((string) $user->id);
        }

        return response()->json(['status' => 'success', 'message' => 'User deleted successfully'], 200);
    }
}
