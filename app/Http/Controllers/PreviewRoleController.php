<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PreviewRoleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->role === 'owner', 403);

        $data = $request->validate([
            'role' => ['required', 'in:owner,trainer,assistant,user'],
        ]);

        if ($data['role'] === 'owner') {
            $request->session()->forget('preview_role');
        } else {
            $request->session()->put('preview_role', $data['role']);
        }

        return redirect('/feed');
    }
}
