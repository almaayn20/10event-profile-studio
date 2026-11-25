<?php

namespace App\Http\Controllers;

use App\Support\PortalSso;
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

    /** Signed in from the platforms portal: the portal decides who may open the studio. */
    public function sso(Request $request)
    {
        try {
            PortalSso::verify((string) $request->query('t', ''));
        } catch (\RuntimeException $e) {
            return response()->view('portal-error', ['message' => $e->getMessage(), 'portal' => config('studio.portal_url')], 400);
        }
        if (! StudioAccess::configured()) {
            return response()->view('portal-error', ['message' => 'الاستوديو ما له كلمة مرور بعد — افتحه مرة بكلمة المرور', 'portal' => config('studio.portal_url')], 403);
        }
        StudioAccess::grant($request);

        return redirect(PortalSso::safeNext($request->query('next')));
    }

    public function logout(Request $request)
    {
        StudioAccess::revoke($request);

        return redirect()->route('studio.login');
    }
}
