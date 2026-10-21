{{--
    One 10 Event project page, 960 × 540 pt — the same file is printed to PDF by Chrome and shown live in the editor.
    Every position below was measured on the company profile (page 16). The fonts have ascent 1.0 em and descent 0.5 em,
    so a line of height L puts its baseline L/2 + 0.25 em below its top; the tops are worked out from the profile's baselines.

    $project  array: client, title, description, details_url, details_label, stats (list of {value, label})
    $cells    list of {id, src, x, y, w, h, img: {x, y, w, h}} in points — each picture's window outside the card, and
              where the picture sits in it (Project::windows)
    $fonts    ['medium' => url, 'bold' => url]
    $picturePage  true for a picture page: the pictures alone, no card, logo or texts
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
    .cell img { position: absolute; display: block; max-width: none; }
    .frame { position: absolute; inset: 0; width: 960pt; height: 540pt; }
    .t { position: absolute; margin: 0; white-space: nowrap; }
    /* the client and the title are centred on x = 493 pt; a long one is set smaller until it fits inside the card */
    .client-row { position: absolute; left: 293pt; width: 400pt; top: 278.16pt; height: 26pt; display: flex; justify-content: center; }
    .client { font-size: 20.57pt; line-height: 26pt; font-weight: 700; white-space: nowrap; }
    .title-row { position: absolute; left: 193pt; width: 600pt; top: 310.2pt; height: 35.3pt; display: flex; justify-content: center; align-items: center; }
    .title {
        height: 35.3pt; padding: 0 9pt 2.84pt; border-radius: 5.1pt; background: #000; color: #fff;
        font-size: 22.28pt; line-height: 32.46pt; font-weight: 700; text-align: center; white-space: nowrap;
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
        <div class="cell" data-image="{{ $c['id'] }}" style="left: {{ $c['x'] }}pt; top: {{ $c['y'] }}pt; width: {{ $c['w'] }}pt; height: {{ $c['h'] }}pt;">
            <img src="{{ $c['src'] }}" alt="" style="left: {{ $c['img']['x'] }}pt; top: {{ $c['img']['y'] }}pt; width: {{ $c['img']['w'] }}pt; height: {{ $c['img']['h'] }}pt;">
        </div>
    @endforeach

    @unless ($picturePage)
    <svg class="frame" id="frame" viewBox="0 0 960 540" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path id="card" d="{{ \App\Support\PageGeometry::BLOB }}" fill="#FFFFFF" stroke="{{ \App\Support\PageGeometry::BROWN }}" stroke-width="2" stroke-miterlimit="10"/>
        @include('portfolio.logo-event')
    </svg>

    @if (filled($project['client']))
        <div class="client-row"><span class="client" id="client">{{ $project['client'] }}</span></div>
    @endif
    @if (filled($project['title']))
        <div class="title-row"><div class="title" id="title">{{ $project['title'] }}</div></div>
    @endif
    @if (filled($project['description']))
        @php
            // the description's room: down to the statistics; with none, down to the button, or to near the card's foot
            $descBottom = count($project['stats']) ? 460.33 : (filled($project['details_url']) ? 476.0 : 522.0);
        @endphp
        <p class="desc" id="desc" data-grow="{{ count($project['stats']) ? 0 : 1 }}" style="max-height: {{ round($descBottom - 352.33, 2) }}pt;">{{ $project['description'] }}</p>
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
    @endunless
</div>
<script>
    // the client and the title stay inside the card: where the card is narrower than the line, the line is set smaller
    // (the title's black box shrinks with it, about its middle) however long it is; the editor is told how much
    (function () {
        var poly = @json(\App\Support\PageGeometry::blobPolygon());
        var CX = 493, PT = 0.75, MARGIN = 12;
        function span(y) {
            var xs = [];
            for (var i = 0, j = poly.length - 1; i < poly.length; j = i++) {
                var a = poly[i], b = poly[j];
                if ((a[1] > y) !== (b[1] > y)) xs.push(a[0] + (y - a[1]) * (b[0] - a[0]) / (b[1] - a[1]));
            }
            return xs.length ? [Math.min.apply(null, xs), Math.max.apply(null, xs)] : null;
        }
        // the widest line, centred on CX, that stays clear of the card's edge between y0 and y1
        function room(y0, y1) {
            var half = Infinity;
            for (var y = y0; y <= y1; y += 1) {
                var s = span(y);
                half = Math.min(half, s ? Math.min(CX - s[0], s[1] - CX) - MARGIN : 0);
            }
            return Math.max(0, 2 * half);
        }
        function fit(el, base, apply, band) {
            if (!el) return 1;
            var top = document.getElementById('page').getBoundingClientRect().top;
            for (var size = base; size >= 6; size = Math.round((size - 0.25) * 100) / 100) {
                apply(size);
                var r = el.getBoundingClientRect(), b = band(r, top);
                if (r.width * PT <= room(b[0], b[1])) return size / base;
            }
            return 6 / base;
        }
        function go() {
            var client = document.getElementById('client');
            var root = document.documentElement;
            root.dataset.clientScale = fit(client, 20.57, function (s) { client.style.fontSize = s + 'pt'; },
                // the letters, not the line's empty top: from a third down to the bottom
                function (r, top) { var y = (r.top - top) * PT, h = r.height * PT; return [y + h / 3, y + h]; });
            var title = document.getElementById('title');
            root.dataset.titleScale = fit(title, 22.28, function (s) {
                var k = s / 22.28;
                title.style.fontSize = s + 'pt';
                title.style.height = (35.3 * k).toFixed(2) + 'pt';
                title.style.lineHeight = (32.46 * k).toFixed(2) + 'pt';
                title.style.padding = '0 ' + (9 * k).toFixed(2) + 'pt ' + (2.84 * k).toFixed(2) + 'pt';
                title.style.borderRadius = (5.1 * k).toFixed(2) + 'pt';
            }, function (r, top) { var y = (r.top - top) * PT, h = r.height * PT; return [y + 2, y + h - 2]; }).toFixed(2);
            root.dataset.clientScale = Number(root.dataset.clientScale).toFixed(2);
            root.dataset.titled = '1';
        }
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(go); else go();
    })();

    // a description longer than its room steps its type down (never below 9.5 pt) instead of being cut off; with no
    // statistics under it, the room reaches further down and the description grows into it: larger type first (up to
    // 14.5 pt), then wider line spacing, until it fills the room to the bottom
    (function () {
        var d = document.getElementById('desc');
        if (!d) return;
        var BASE = 11.46, RATIO = 18 / 11.46, PX = 4 / 3;
        var size = BASE;
        var set = function (s, lh) { d.style.fontSize = s + 'pt'; d.style.lineHeight = (lh || s * RATIO).toFixed(2) + 'pt'; };
        var fit = function () { return d.scrollHeight <= d.clientHeight + 1; };
        var go = function () {
            if (d.dataset.grow === '1') {
                while (size < 14.5) {
                    set(size + 0.25);
                    if (!fit()) { set(size); break; }
                    size = Math.round((size + 0.25) * 100) / 100;
                }
                if (fit()) {
                    // as many lines as now, spread over the whole room (but never more than twice the type apart)
                    var room = parseFloat(d.style.maxHeight);
                    var lines = Math.max(1, Math.round(d.scrollHeight / (size * RATIO * PX)));
                    set(size, Math.max(size * RATIO, Math.min(size * 2, room / lines - 0.05)));
                    if (!fit()) set(size);
                }
            }
            while (!fit() && size > 9.5) {
                size = Math.round((size - 0.25) * 100) / 100;
                set(size);
            }
            document.documentElement.dataset.descScale = (size / BASE).toFixed(2);
            document.documentElement.dataset.overflow = fit() ? '0' : '1';
            document.documentElement.dataset.fitted = '1';
        };
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(go); else go();
    })();
</script>
</body>
</html>
