<?php

namespace App\Http\Controllers;

use App\Helpers\Lyn;
use App\Models\Session;

class ApiController extends Controller
{
    public function index()
    {
        $device = session('main_device') ? Session::where('id', session('main_device'))->where('user_id', auth()->id())->first() : null;
        return Lyn::view('api.apidocs', ['device' => $device]);
    }
}
