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
                'name' => self::studioWords()['name'],
                'brand' => self::studioWords()['brand'],
                'locale' => $locale,
                'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
                'uploadMaxMb' => (int) config('studio.image_max_mb'),
                'statsShown' => \App\Support\Settings::statsShown(),
                'edition' => \App\Support\Edition::name(),
                'page' => \App\Support\Edition::page(),
            ],
            // the dictionary is sent once and kept by the browser; a deploy with new wording sends it again
            't' => Inertia::once(fn () => ['studio' => self::studioWords(), 'common' => __('ui.common'), 'errors' => __('errors')])
                ->as('t.'.$locale.'.'.\App\Support\Edition::name().'.'.self::dictionaryVersion($locale)),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /** The studio's words; the gift studio reads its own where they differ. */
    public static function studioWords(): array
    {
        $words = __('ui.studio');

        return \App\Support\Edition::gift() ? array_replace_recursive($words, (array) __('ui.gift')) : $words;
    }

    private static function dictionaryVersion(string $locale): string
    {
        $files = glob(lang_path($locale.'/*.php')) ?: [];

        return substr(md5(implode('|', array_map(fn ($f) => basename($f).':'.filemtime($f), $files))), 0, 10);
    }
}
