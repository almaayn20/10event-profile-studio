{{--
    One 10 Event project page, 960 × 540 pt — the same file is printed to PDF by Chrome and shown live in the editor.
    Every position below was measured on the company profile (page 16). The fonts have ascent 1.0 em and descent 0.5 em,
    so a line of height L puts its baseline L/2 + 0.25 em below its top; the tops are worked out from the profile's baselines.

    $project  array: client, title, description, details_url, details_label, stats (list of {value, label})
    $cells    list of {src, x, y, w, h} in points
    $fonts    ['medium' => url, 'bold' => url]
--}}
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>{{ $project['title'] ?: 'صفحة مشروع' }}</title>
<style>
    @font-face { font-family: 'ProfileFace'; src: url('{{ $fonts['medium'] }}'); font-weight: 500; }
    @font-face { font-family: 'ProfileFace'; src: url('{{ $fonts['bold'] }}'); font-weight: 700; }
    @page { size: 960pt 540pt; margin: 0; }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; background: #fff; }
    .page {
        position: relative; width: 960pt; height: 540pt; overflow: hidden; background: #E8E4DE; color: #000;
        font-family: 'ProfileFace', sans-serif; font-weight: 500;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
        font-kerning: normal; text-rendering: geometricPrecision;
    }
    .cell { position: absolute; overflow: hidden; }
    .cell img { display: block; width: 100%; height: 100%; object-fit: cover; }
    .frame { position: absolute; inset: 0; width: 960pt; height: 540pt; }
    .t { position: absolute; margin: 0; white-space: nowrap; }
    .client { left: 293pt; width: 400pt; top: 278.16pt; font-size: 20.57pt; line-height: 26pt; font-weight: 700; text-align: center; }
    .title-row { position: absolute; left: 293pt; width: 400pt; top: 310.2pt; display: flex; justify-content: center; }
    .title {
        min-width: 198.8pt; max-width: 400pt; height: 35.3pt; padding: 0 6pt 2.84pt; border-radius: 5.1pt; background: #000; color: #fff;
        font-size: 22.28pt; line-height: 32.46pt; font-weight: 700; text-align: center; white-space: nowrap; overflow: hidden;
    }
    .desc {
        margin: 0;
        position: absolute; left: 301.8pt; width: 350.1pt; top: 352.33pt; max-height: 108pt;
        font-size: 11.46pt; line-height: 18pt; text-align: justify; text-align-last: center; white-space: pre-line; overflow: hidden;
    }
    .stat { position: absolute; width: 70pt; text-align: right; font-weight: 700; }
    .stat .v { position: absolute; right: 0; line-height: 22pt; white-space: nowrap; }
    .stat .v.num { top: 0; font-size: 17.11pt; }
    .stat .v.word { top: 0.84pt; font-size: 13.78pt; }
    .stat .l { position: absolute; right: 0; top: 20.48pt; font-size: 14.26pt; line-height: 20pt; white-space: nowrap; }
    .more {
        position: absolute; left: 308.23pt; top: 484.47pt; min-width: 51.24pt; height: 19.85pt; padding: 0 3pt; border-radius: 1.56pt;
        background: {{ \App\Support\PageGeometry::BROWN }}; color: #fff; text-decoration: none;
        font-size: 10pt; line-height: 19.85pt; font-weight: 700; text-align: center; white-space: nowrap;
    }
</style>
</head>
<body>
<div class="page" id="page">
    @foreach ($cells as $c)
        {{-- each picture reaches 0.4 pt under its neighbour, so no viewer ever draws a hairline seam between them --}}
        <div class="cell" style="left: {{ $c['x'] }}pt; top: {{ $c['y'] }}pt; width: {{ $c['w'] + 0.4 }}pt; height: {{ $c['h'] + 0.4 }}pt;">
            <img src="{{ $c['src'] }}" alt="" style="object-position: {{ $c['focus'] ?? '50% 50%' }};">
        </div>
    @endforeach

    <svg class="frame" viewBox="0 0 960 540" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="{{ \App\Support\PageGeometry::BLOB }}" fill="#FFFFFF" stroke="{{ \App\Support\PageGeometry::BROWN }}" stroke-width="2" stroke-miterlimit="10"/>
        @include('portfolio.logo-event')
    </svg>

    @if (filled($project['client']))
        <p class="t client">{{ $project['client'] }}</p>
    @endif
    @if (filled($project['title']))
        <div class="title-row"><div class="title">{{ $project['title'] }}</div></div>
    @endif
    @if (filled($project['description']))
        <p class="desc" id="desc">{{ $project['description'] }}</p>
    @endif

    @foreach (array_slice($project['stats'], 0, 3) as $i => $s)
        <div class="stat" style="left: {{ \App\Support\PageGeometry::STAT_COLUMNS[$i] - 70 }}pt; top: 473.22pt;">
            {{-- a bare number is set large and read left to right («100+»); a number with a word («4 مناطق») smaller, as in the profile --}}
            @if (preg_match('/\p{Arabic}/u', $s['value']))
                <span class="v word">{{ $s['value'] }}</span>
            @else
                <span class="v num" dir="ltr">{{ $s['value'] }}</span>
            @endif
            <span class="l">{{ $s['label'] }}</span>
        </div>
    @endforeach

    @if (filled($project['details_url']))
        <a class="more" href="{{ $project['details_url'] }}">{{ $project['details_label'] ?: 'لتفاصيل أكثر' }}</a>
    @endif
</div>
<script>
    // a description longer than the card's six lines steps its type down (never below 9.5 pt) instead of being cut off
    (function () {
        var d = document.getElementById('desc');
        if (!d) return;
        var size = 11.46;
        var fit = function () { return d.scrollHeight <= d.clientHeight + 1; };
        var go = function () {
            while (!fit() && size > 9.5) {
                size = Math.round((size - 0.25) * 100) / 100;
                d.style.fontSize = size + 'pt';
                d.style.lineHeight = (size * 18 / 11.46).toFixed(2) + 'pt';
            }
            document.documentElement.dataset.overflow = fit() ? '0' : '1';
            document.documentElement.dataset.fitted = '1';
        };
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(go); else go();
    })();
</script>
</body>
</html>
