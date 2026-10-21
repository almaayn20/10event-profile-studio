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
        font-size: 11.46pt; line-height: 18pt; text-align: right; white-space: pre-line; overflow: hidden;
    }
    /* the statistics: a row from the card's right column inwards, each as wide as its own words, evenly apart; when
       they outgrow the room the gaps close first, then the type steps down (--s) — see the script */
    .stats { position: absolute; right: 314pt; top: 473.22pt; display: flex; gap: 34pt; align-items: flex-start; --s: 1; }
    .stat { display: flex; flex-direction: column; align-items: flex-start; text-align: right; font-weight: 700; white-space: nowrap; }
    .stat .v { line-height: calc(22pt * var(--s)); }
    .stat .v.num { font-size: calc(17.11pt * var(--s)); }
    .stat .v.word { font-size: calc(13.78pt * var(--s)); padding-top: calc(0.84pt * var(--s)); margin-bottom: calc(-0.84pt * var(--s)); }
    .stat .l { margin-top: calc(-1.52pt * var(--s)); font-size: calc(14.26pt * var(--s)); line-height: calc(20pt * var(--s)); }
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
        @php
            // the profile's own: justified with the last line centred; «justify»: the last line from the right; «center».
            // Justifying is done by the script below, between words only (Chrome's own justification opens gaps inside
            // words in SF Mada); the CSS only centres
            $alignMode = in_array($project['desc_align'] ?? null, ['justify', 'center'], true) ? $project['desc_align'] : 'profile';
            $align = $alignMode === 'center' ? 'text-align: center;' : '';
        @endphp
        <p class="desc" id="desc" data-align="{{ $alignMode }}" data-grow="{{ count($project['stats']) ? 0 : 1 }}" @if (filled($project['desc_size'] ?? null)) data-size="{{ (float) $project['desc_size'] }}" @endif
           style="max-height: {{ round($descBottom - 352.33, 2) }}pt; {{ $align }}">{{ $project['description'] }}</p>
    @endif

    @if (count($project['stats']))
    <div class="stats" id="stats">
    @foreach (array_slice($project['stats'], 0, 3) as $i => $s)
        <div class="stat">
            {{-- a bare number is set large and read left to right («100+»); a number with a word («4 مناطق») smaller, as in the profile --}}
            @if (preg_match('/\p{Arabic}/u', $s['value']))
                <span class="v word">{{ $s['value'] }}</span>
            @else
                <span class="v num" dir="ltr">{{ $s['value'] }}</span>
            @endif
            <span class="l">{{ $s['label'] }}</span>
        </div>
    @endforeach
    </div>
    @endif

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

    // the statistics keep to their room: from the right column (646 pt) to the button (or near the card's edge without
    // one). Gaps first close from 34 pt down to 14 pt, then the type steps down — never below 55 %
    (function () {
        var row = document.getElementById('stats');
        if (!row) { document.documentElement.dataset.statsScale = '1'; return; }
        var PT = 0.75, GAP = 34, MIN_GAP = 14, RIGHT = 646;
        var go = function () {
            var page = document.getElementById('page').getBoundingClientRect();
            var more = document.querySelector('.more');
            var left = more ? (more.getBoundingClientRect().right - page.left) * PT + 16 : 324;
            var room = RIGHT - left;
            var items = Array.prototype.slice.call(row.children);
            var gaps = Math.max(0, items.length - 1);
            var words = function () { return items.reduce(function (w, it) { return w + it.getBoundingClientRect().width * PT; }, 0); };
            var s = 1;
            row.style.setProperty('--s', '1');
            var w = words();
            if (w + gaps * GAP > room) {
                if (w + gaps * MIN_GAP <= room) {
                    row.style.gap = ((room - w) / gaps).toFixed(2) + 'pt';
                } else {
                    row.style.gap = MIN_GAP + 'pt';
                    while (s > 0.55 && words() + gaps * MIN_GAP > room) {
                        s = Math.round((s - 0.02) * 100) / 100;
                        row.style.setProperty('--s', s);
                    }
                }
            }
            document.documentElement.dataset.statsScale = s.toFixed(2);
        };
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(go); else go();
    })();

    // a description longer than its room steps its type down (never below 9.5 pt) instead of being cut off; with no
    // statistics under it, the room reaches further down and the description grows into it: larger type first (up to
    // 14.5 pt), then wider line spacing, until it fills the room to the bottom. A size chosen by hand (data-size) is
    // kept as it is: only the line spacing still fills the room, and what does not fit is reported, not shrunk
    (function () {
        var d = document.getElementById('desc');
        if (!d) return;
        var BASE = 11.46, RATIO = 18 / 11.46, PX = 4 / 3;
        var size = BASE;
        var set = function (s, lh) { d.style.fontSize = s + 'pt'; d.style.lineHeight = (lh || s * RATIO).toFixed(2) + 'pt'; };
        var fit = function () { return d.scrollHeight <= d.clientHeight + 1; };
        var manual = parseFloat(d.dataset.size || '');
        var spread = function () {
            // as many lines as now, spread over the whole room (but never more than twice the type apart)
            if (!fit()) return;
            var room = parseFloat(d.style.maxHeight);
            var lines = Math.max(1, Math.round(d.scrollHeight / (size * RATIO * PX)));
            set(size, Math.max(size * RATIO, Math.min(size * 2, room / lines - 0.05)));
            if (!fit()) set(size);
        };
        var go = function () {
            if (manual > 0) {
                size = manual;
                set(size);
                if (d.dataset.grow === '1') spread();
                document.documentElement.dataset.descSize = size.toFixed(2);
                document.documentElement.dataset.descScale = '1';
                document.documentElement.dataset.overflow = fit() ? '0' : '1';
                document.documentElement.dataset.fitted = '1';
                return;
            }
            if (d.dataset.grow === '1') {
                while (size < 14.5) {
                    set(size + 0.25);
                    if (!fit()) { set(size); break; }
                    size = Math.round((size + 0.25) * 100) / 100;
                }
                spread();
            }
            while (!fit() && size > 9.5) {
                size = Math.round((size - 0.25) * 100) / 100;
                set(size);
            }
            document.documentElement.dataset.descScale = (size / BASE).toFixed(2);
            document.documentElement.dataset.descSize = size.toFixed(2);
            document.documentElement.dataset.overflow = fit() ? '0' : '1';
            justify();
            document.documentElement.dataset.fitted = '1';
        };
        // Justify between words only: the text is laid out ragged (the lines break where they would anyway), each line is
        // then held on its own and its spare width shared out between its words. A paragraph's last line is centred (the
        // profile's way) or set from the right («justify»)
        var justify = function () {
            var mode = d.dataset.align;
            if (mode === 'center') return;
            var paras = d.textContent.split('\n');
            d.textContent = '';
            d.style.whiteSpace = 'normal';
            var blocks = paras.map(function (p) {
                var block = document.createElement('span');
                block.style.display = 'block';
                p.trim().split(/\s+/).filter(Boolean).forEach(function (w, i) {
                    if (i) block.appendChild(document.createTextNode(' '));
                    var s = document.createElement('span');
                    s.textContent = w;
                    block.appendChild(s);
                });
                if (!block.childNodes.length) block.appendChild(document.createElement('br'));
                d.appendChild(block);
                return block;
            });
            var width = d.clientWidth;
            blocks.forEach(function (block) {
                var words = Array.prototype.filter.call(block.childNodes, function (n) { return n.nodeType === 1 && n.tagName === 'SPAN'; });
                if (!words.length) return;
                var lines = [];
                words.forEach(function (w) {
                    var top = Math.round(w.getBoundingClientRect().top);
                    var last = lines[lines.length - 1];
                    if (last && Math.abs(last.top - top) < 3) last.words.push(w.textContent); else lines.push({ top: top, words: [w.textContent] });
                });
                block.textContent = '';
                lines.forEach(function (line, i) {
                    var el = document.createElement('span');
                    el.style.display = 'block';
                    el.style.whiteSpace = 'nowrap';
                    el.textContent = line.words.join(' ');
                    block.appendChild(el);
                    if (i === lines.length - 1) {
                        el.style.textAlign = mode === 'justify' ? 'right' : 'center';
                        return;
                    }
                    if (line.words.length < 2) return;
                    // the line's own width, measured unstretched
                    el.style.display = 'inline-block';
                    var natural = el.getBoundingClientRect().width;
                    el.style.display = 'block';
                    el.style.wordSpacing = Math.max(0, (width - natural) / (line.words.length - 1)) + 'px';
                });
            });
        };
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(go); else go();
    })();
</script>
</body>
</html>
