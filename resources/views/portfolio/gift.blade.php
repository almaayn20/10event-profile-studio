{{--
    One 10 Gift page, 566.93 × 311.81 pt — printed to PDF by Chrome and shown live in the editor. Every position was
    measured on «10Gift Profile V1.pdf» (pages 7–33; the trio on 34–35). Single lines (the labels, the client, the
    figures, the category) are SVG text set on the profile's own baselines; the description is HTML, its first line's
    baseline held at 216.6 pt (FF Shamel: ascent 1.1 em, descent 0.8 em, so a line of height L puts its baseline
    (L − 1.9 em) / 2 + 1.1 em below its top).

    $project  array: kind, client, title (the category), description, desc_size, desc_align, stats, color
    $cells    list of {id, src, slot (hero · thumb · panel), x, y, w, h, img: {x, y, w, h}} in points
    $logo     null or {src, box: {x, y, w, h}}
    $tab      where the logo tab ends, in points
    $fonts    ['medium' => url (Book), 'bold' => url]
    $picturePage  true for a picture page: the picture alone
--}}
@php
    use App\Support\GiftGeometry as G;
    $color = $project['color'] ?? G::DEFAULT_COLOR;
    $trio = ($project['kind'] ?? 'project') === 'trio';
    $stats = array_slice($project['stats'] ?? [], 0, 3);
    $thumbs = array_values(array_filter($cells, fn ($c) => $c['slot'] === 'thumb'));
    $Y0 = 204.13;                                   // the baseline of «العميل» and of the client's name
    $alignMode = in_array($project['desc_align'] ?? null, ['center', 'right'], true) ? $project['desc_align'] : 'profile';
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>{{ ($trio ? $project['title'] : $project['client']) ?: 'صفحة هدية' }}</title>
<style>
    @font-face { font-family: 'ProfileFace'; src: url('{{ $fonts['medium'] }}'); font-weight: 500; }
    @font-face { font-family: 'ProfileFace'; src: url('{{ $fonts['bold'] }}'); font-weight: 700; }
    @page { size: {{ G::W }}pt {{ G::H }}pt; margin: 0; }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; background: #fff; }
    .page {
        position: relative; width: {{ G::W }}pt; height: {{ G::H }}pt; overflow: hidden; background: #1C1C1C; color: #fff;
        font-family: 'ProfileFace', sans-serif; font-weight: 500;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
        font-kerning: normal; text-rendering: geometricPrecision;
    }
    .cell { position: absolute; overflow: hidden; }
    .cell.thumb { border-radius: {{ G::SLOT_RADIUS }}pt; }
    .cell img { position: absolute; display: block; max-width: none; }
    .frame { position: absolute; inset: 0; width: {{ G::W }}pt; height: {{ G::H }}pt; overflow: visible; }
    .logo { position: absolute; display: block; max-width: none; }
    .frame text { font-family: 'ProfileFace', sans-serif; fill: #fff; }
    .desc {
        position: absolute; margin: 0; left: 395.1pt; width: 92.2pt; top: 211.85pt;
        font-size: 5pt; line-height: 8pt; text-align: right; white-space: pre-line; overflow: hidden; color: #fff;
    }
</style>
</head>
<body>
<div class="page" id="page" data-color="{{ $color }}">
    @foreach ($cells as $c)
        <div class="cell {{ $c['slot'] }}" data-image="{{ $c['id'] }}" style="left: {{ $c['x'] }}pt; top: {{ $c['y'] }}pt; width: {{ $c['w'] }}pt; height: {{ $c['h'] }}pt;">
            <img src="{{ $c['src'] }}" alt="" style="left: {{ $c['img']['x'] }}pt; top: {{ $c['img']['y'] }}pt; width: {{ $c['img']['w'] }}pt; height: {{ $c['img']['h'] }}pt;">
        </div>
    @endforeach

    @unless ($picturePage)
    <svg class="frame" id="frame" viewBox="0 0 {{ G::W }} {{ G::H }}" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        @unless ($trio)
            {{-- the logo tab and the card, in the page's colour at 85 % --}}
            <path id="tab" d="{{ G::roundedRect(G::TAB['x'], G::TAB['y'], $tab - G::TAB['x'], G::TAB['h'], G::TAB['r']) }}" fill="{{ $color }}" fill-opacity="0.85"/>
            <path id="card" d="{{ G::CARD }}" fill="{{ $color }}" fill-opacity="0.85"/>
            @foreach ($thumbs as $t)
                <path d="{{ G::roundedRect($t['x'], $t['y'], $t['w'], $t['h'], G::SLOT_RADIUS) }}" fill="none" stroke="#FFFFFF" stroke-width="0.3"/>
            @endforeach
        @endunless

        {{-- the category tag: solid, from before its word to off the page --}}
        @if (filled($project['title']))
            <path id="tag" d="" fill="{{ $color }}"/>
            <text id="tagText" x="{{ G::TAG['text_right'] }}" y="{{ G::TAG['baseline'] }}" font-size="{{ G::TAG['size'] }}" font-weight="700" direction="rtl" text-anchor="start">{{ $project['title'] }}</text>
        @endif

        @unless ($trio)
            <text x="537.7" y="{{ $Y0 }}" font-size="6" font-weight="700" direction="rtl" text-anchor="start">العميل</text>
            <text x="537.7" y="{{ $Y0 + 15 }}" font-size="6" font-weight="700" direction="rtl" text-anchor="start">وصف العمل</text>
            @if (filled($project['client']))
                <text id="client" x="487.1" y="{{ $Y0 }}" font-size="5" font-weight="700" direction="rtl" text-anchor="start">{{ $project['client'] }}</text>
            @endif
            @if (count($stats))
                <line x1="395.45" y1="265.96" x2="556.81" y2="265.96" stroke="#FFF9F1" stroke-width="0.5"/>
                <g id="stats">
                @foreach ($stats as $i => $s)
                    <g class="stat" data-right="{{ [543.7, 502.8, 448.5][$i] }}">
                        <text class="v" x="{{ [543.7, 502.8, 448.5][$i] }}" y="283.46" font-size="16" font-weight="700" direction="ltr" text-anchor="end">{{ $s['value'] }}</text>
                        <text class="l" x="{{ [543.7, 502.8, 448.5][$i] }}" y="291.81" font-size="5.02" font-weight="700" direction="rtl" text-anchor="start">{{ $s['label'] }}</text>
                    </g>
                @endforeach
                </g>
            @endif
        @endunless
    </svg>

    @if ($logo && ! $trio)
        <img class="logo" id="logo" src="{{ $logo['src'] }}" alt="" style="left: {{ $logo['box']['x'] }}pt; top: {{ $logo['box']['y'] }}pt; width: {{ $logo['box']['w'] }}pt; height: {{ $logo['box']['h'] }}pt;">
    @endif

    @if (! $trio && filled($project['description']))
        <p class="desc" id="desc" data-align="{{ $alignMode }}" data-bottom="{{ count($stats) ? 262.0 : 278.0 }}"
           @if (filled($project['desc_size'] ?? null)) data-size="{{ (float) $project['desc_size'] }}" @endif
           style="{{ $alignMode === 'center' ? 'text-align: center;' : '' }}">{{ $project['description'] }}</p>
    @endif
    @endunless
</div>
<script>
    var PT = 0.75;
    var root = document.documentElement;
    function ready(fn) { if (document.fonts && document.fonts.ready) document.fonts.ready.then(fn); else fn(); }

    // the category tag reaches from 11.4 pt before its word to off the page; the client's name keeps to its room
    // (92 pt), set smaller when longer; the figures keep their columns unless a wide one pushes the next along
    ready(function () {
        var t = document.getElementById('tagText'), tag = document.getElementById('tag');
        if (t && tag) {
            var w = t.getComputedTextLength(), right = {{ G::TAG['text_right'] }};
            var left = Math.min(right - w - {{ G::TAG['pad'] }}, {{ G::TAG['max_left'] }});
            var y = {{ G::TAG['y'] }}, h = {{ G::TAG['h'] }}, r = {{ G::TAG['r'] }}, x1 = 668.5, k = 0.5523 * r;
            tag.setAttribute('d', 'M' + (left + r) + ' ' + y + ' L' + (x1 - r) + ' ' + y + ' L' + x1 + ' ' + (y + r) + ' L' + x1 + ' ' + (y + h - r) + ' L' + (x1 - r) + ' ' + (y + h)
                + ' L' + (left + r) + ' ' + (y + h) + ' C' + (left + r - k) + ' ' + (y + h) + ' ' + left + ' ' + (y + h - r + k) + ' ' + left + ' ' + (y + h - r)
                + ' L' + left + ' ' + (y + r) + ' C' + left + ' ' + (y + r - k) + ' ' + (left + r - k) + ' ' + y + ' ' + (left + r) + ' ' + y + ' Z');
        }
        var c = document.getElementById('client'), scale = 1;
        if (c) {
            var room = 92;
            for (var s = 5; s >= 2.5 && c.getComputedTextLength() > room; s = Math.round((s - 0.1) * 100) / 100) {
                c.setAttribute('font-size', s);
                scale = s / 5;
            }
        }
        root.dataset.clientScale = scale.toFixed(2);
        root.dataset.titleScale = '1';

        var stats = document.querySelectorAll('#stats .stat'), prevLeft = null, sScale = 1, GAP = 8, MIN_LEFT = 395;
        var place = function (k) {
            prevLeft = null;
            Array.prototype.forEach.call(stats, function (g) {
                var v = g.querySelector('.v'), l = g.querySelector('.l');
                v.setAttribute('font-size', 16 * k); l.setAttribute('font-size', 5.02 * k);
                var right = parseFloat(g.dataset.right);
                if (prevLeft !== null) right = Math.min(right, prevLeft - GAP);
                v.setAttribute('x', right); l.setAttribute('x', right);
                prevLeft = right - Math.max(v.getComputedTextLength(), l.getComputedTextLength());
            });
            return prevLeft === null || prevLeft >= MIN_LEFT;
        };
        while (!place(sScale) && sScale > 0.55) sScale = Math.round((sScale - 0.03) * 100) / 100;
        root.dataset.statsScale = sScale.toFixed(2);
        root.dataset.titled = '1';
    });

    // the description: 5 pt on 8 pt lines, its first baseline at 216.6 pt; longer than its room it steps down (never
    // below 3.6 pt), a size chosen by hand is kept and what does not fit is reported. Justified between words only
    // (the script, not CSS), its last line from the right — the profile's way
    (function () {
        var d = document.getElementById('desc');
        if (!d) { root.dataset.fitted = '1'; root.dataset.descSize = '5'; root.dataset.descScale = '1'; root.dataset.overflow = '0'; return; }
        var BASE = 5, RATIO = 1.6, BASELINE = 216.6, bottom = parseFloat(d.dataset.bottom);
        var size = BASE;
        var set = function (s) {
            d.style.fontSize = s + 'pt';
            d.style.lineHeight = (s * RATIO).toFixed(3) + 'pt';
            var top = BASELINE - 0.95 * s;
            d.style.top = top.toFixed(3) + 'pt';
            d.style.maxHeight = (bottom - top).toFixed(3) + 'pt';
        };
        var fit = function () { return d.scrollHeight <= d.clientHeight + 1; };
        var manual = parseFloat(d.dataset.size || '');
        var justify = function () {
            var mode = d.dataset.align;
            if (mode === 'center' || mode === 'right') return;
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
                    if (last && Math.abs(last.top - top) < 2) last.words.push(w.textContent); else lines.push({ top: top, words: [w.textContent] });
                });
                block.textContent = '';
                lines.forEach(function (line, i) {
                    var el = document.createElement('span');
                    el.style.display = 'block';
                    el.style.whiteSpace = 'nowrap';
                    el.textContent = line.words.join(' ');
                    block.appendChild(el);
                    if (i === lines.length - 1 || line.words.length < 2) { el.style.textAlign = 'right'; return; }
                    el.style.display = 'inline-block';
                    var natural = el.getBoundingClientRect().width;
                    el.style.display = 'block';
                    el.style.wordSpacing = Math.max(0, (width - natural) / (line.words.length - 1)) + 'px';
                });
            });
        };
        ready(function () {
            if (manual > 0) {
                size = manual;
                set(size);
            } else {
                set(size);
                while (!fit() && size > 3.6) {
                    size = Math.round((size - 0.1) * 100) / 100;
                    set(size);
                }
            }
            root.dataset.descSize = size.toFixed(2);
            root.dataset.descScale = manual > 0 ? '1' : (size / BASE).toFixed(2);
            root.dataset.overflow = fit() ? '0' : '1';
            justify();
            root.dataset.fitted = '1';
        });
    })();
</script>
</body>
</html>
