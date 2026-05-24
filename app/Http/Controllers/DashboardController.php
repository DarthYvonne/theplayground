<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function feed(Request $request)
    {
        return view('dashboard.feed', ['user' => $request->user()]);
    }
}
