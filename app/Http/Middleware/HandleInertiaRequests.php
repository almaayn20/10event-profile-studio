<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            ...parent::share($request),
            'app' => [
                'name' => __('ui.studio.name'),
                'locale' => $locale,
                'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
                'uploadMaxMb' => (int) config('studio.image_max_mb'),
            ],
            // the dictionary is sent once and kept by the browser; a deploy with new wording sends it again
            't' => Inertia::once(fn () => ['studio' => __('ui.studio'), 'common' => __('ui.common'), 'errors' => __('errors')])
                ->as('t.'.$locale.'.'.self::dictionaryVersion($locale)),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    private static function dictionaryVersion(string $locale): string
    {
        $files = glob(lang_path($locale.'/*.php')) ?: [];

        return substr(md5(implode('|', array_map(fn ($f) => basename($f).':'.filemtime($f), $files))), 0, 10);
    }
}
