<?php

namespace App\Http\Controllers;

use App\Services\Fonts;
use App\Support\StudioAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function edit()
    {
        return Inertia::render('Settings', ['fonts' => Fonts::present()]);
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current' => ['nullable', 'string', 'max:200'],
            'password' => ['required', 'string', 'min:8', 'max:200', 'confirmed'],
        ]);
        // whoever changes it proves they know the one in force
        if (StudioAccess::configured() && ! StudioAccess::check((string) ($data['current'] ?? ''))) {
            throw ValidationException::withMessages(['current' => __('ui.studio.login.wrong')]);
        }
        StudioAccess::set($data['password']);
        StudioAccess::grant($request);

        return back()->with('success', 'ui.studio.password_saved');
    }

    public function font(Request $request, string $weight)
    {
        $request->validate(['file' => ['required', 'file', 'max:10240']]);
        Fonts::store($weight, $request->file('file'));

        return back()->with('success', 'ui.studio.font_saved');
    }

    /** The profile's font, only for drawing the preview inside the studio. */
    public function show(string $weight)
    {
        return response()->file(Fonts::path($weight), [
            'Content-Type' => 'font/ttf',
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
