<?php

namespace App\Http\Controllers;

use App\Support\StudioAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AccessController extends Controller
{
    public function show(Request $request)
    {
        if (StudioAccess::granted($request)) {
            return redirect()->route('studio.index');
        }

        return Inertia::render('Login', ['configured' => StudioAccess::configured()]);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['password' => ['required', 'string', 'max:200']]);
        if (! StudioAccess::check($data['password'])) {
            throw ValidationException::withMessages(['password' => __('ui.studio.login.wrong')]);
        }
        StudioAccess::grant($request);

        return redirect()->intended(route('studio.index'));
    }

    public function logout(Request $request)
    {
        StudioAccess::revoke($request);

        return redirect()->route('studio.login');
    }
}
