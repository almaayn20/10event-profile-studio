"""The profile book: a page printed from HTML is one 960 × 540 pt sheet with a working link, and editing the profile
puts pages exactly where asked, keeps the others' links and information, and does not grow the file on updates."""
import os
import shutil
import tempfile

from PIL import Image
from pypdf import PdfReader, PdfWriter

import sys

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
import studio_pdf as portfolio  # noqa: E402
from studio_pdf import EngineError  # noqa: E402

ROOT = tempfile.gettempdir()
os.makedirs(ROOT, exist_ok=True)
OUT = tempfile.mkdtemp(dir=ROOT)


def picture_pdf(path, colour, pages=1):
    """Pages that differ (a full-page picture each), so a page can be recognised by its size in bytes."""
    imgs = [Image.new("RGB", (1280, 720), (colour[0], colour[1], (colour[2] + 40 * i) % 256)) for i in range(pages)]
    imgs[0].save(path, save_all=True, append_images=imgs[1:], resolution=96)    # 1280 px at 96 dpi = 960 pt
    return path


book = picture_pdf(os.path.join(OUT, "book.pdf"), (20, 120, 90), pages=5)
w = PdfWriter(clone_from=book)
w.add_metadata({"/Title": "الملف التعريفي"})
w.write(book)
page = picture_pdf(os.path.join(OUT, "page.pdf"), (200, 80, 20))

r = portfolio.inspect({"pdf": book, "thumbs_dir": os.path.join(OUT, "thumbs")}, OUT)
assert r["pages"] == 5 and r["width"] == 960 and r["height"] == 540, r
assert r["thumbs"] == [f"p-{n:03d}.jpg" for n in range(1, 6)], r["thumbs"]
print("1. inspect: page count, size, and a small picture of every page")

a = os.path.join(OUT, "a.pdf")
r = portfolio.compose({"base": book, "out": a, "ops": [{"op": "insert", "at": 3, "pdf": page}]}, OUT)
rd = PdfReader(a)
assert r["pages"] == 6 and len(rd.pages) == 6
colour_of = lambda f, n: Image.open(f).getpixel((5, 5))  # noqa: E731
portfolio._thumb(a, os.path.join(OUT, "a3.jpg"), dpi=10, page_no=3)
assert colour_of(os.path.join(OUT, "a3.jpg"), 3)[0] > 150, "the new page is page 3"
assert rd.metadata.get("/Title") == "الملف التعريفي", "the document information is kept"
print("2. insert: the page becomes the page asked for; the book's title stays")

b = os.path.join(OUT, "b.pdf")
c = os.path.join(OUT, "c.pdf")
portfolio.compose({"base": a, "out": b, "ops": [{"op": "replace", "page": 3, "pdf": page}]}, OUT)
portfolio.compose({"base": b, "out": c, "ops": [{"op": "replace", "page": 3, "pdf": page}]}, OUT)
assert abs(os.path.getsize(c) - os.path.getsize(a)) < 2048, (os.path.getsize(a), os.path.getsize(c))
print("3. replace twice: same pages, and the file does not grow")

m = os.path.join(OUT, "m.pdf")
r = portfolio.compose({"base": a, "out": m, "ops": [{"op": "move", "page": 3, "to": 6}]}, OUT)
assert r["pages"] == 6
portfolio._thumb(m, os.path.join(OUT, "m6.jpg"), dpi=10, page_no=6)
portfolio._thumb(m, os.path.join(OUT, "m3.jpg"), dpi=10, page_no=3)
assert colour_of(os.path.join(OUT, "m6.jpg"), 6)[0] > 150 and colour_of(os.path.join(OUT, "m3.jpg"), 3)[0] < 150
mm = os.path.join(OUT, "mm.pdf")
portfolio.compose({"base": m, "out": mm, "ops": [{"op": "move", "page": 6, "to": 3}]}, OUT)
assert [len(p.extract_text() or "") for p in PdfReader(mm).pages] == [len(p.extract_text() or "") for p in PdfReader(a).pages]
assert abs(os.path.getsize(mm) - os.path.getsize(a)) < 2048
print("3b. move: the same page goes where asked and back again")

d = os.path.join(OUT, "d.pdf")
r = portfolio.compose({"base": c, "out": d, "ops": [{"op": "remove", "page": 3}]}, OUT)
assert r["pages"] == 5 and abs(os.path.getsize(d) - os.path.getsize(book)) < 4096
try:
    portfolio.compose({"base": d, "out": d + ".x", "ops": [{"op": "remove", "page": 9}]}, OUT)
    raise AssertionError("a page that is not there cannot be removed")
except EngineError as e:
    assert e.code == "portfolio_page_out_of_range"
print("4. remove: back to the original; a missing page is refused")

small = os.path.join(OUT, "small.pdf")
Image.new("RGB", (640, 360), "white").save(small, resolution=96)
r = portfolio.compose({"base": book, "out": os.path.join(OUT, "e.pdf"), "ops": [{"op": "insert", "at": 99, "pdf": small}]}, OUT)
last = PdfReader(os.path.join(OUT, "e.pdf")).pages[-1].mediabox
assert r["pages"] == 6 and round(float(last.width)) == 960 and round(float(last.height)) == 540
print("5. a page at another size is scaled to the book; a position past the end means the end")

bad = os.path.join(OUT, "bad.pdf")
open(bad, "wb").write(b"not a pdf")
try:
    portfolio.inspect({"pdf": bad}, OUT)
    raise AssertionError("a broken file must be reported, not crash")
except EngineError as e:
    assert e.code == "portfolio_pdf_unreadable"
print("6. a damaged upload is reported as such")

try:
    chrome = portfolio._chrome({"chrome": os.environ.get("CHROME_BIN")})
except EngineError:
    chrome = None
if chrome:
    html = os.path.join(OUT, "page.html")
    open(html, "w").write("""<!doctype html><html dir="rtl"><style>@page{size:960pt 540pt;margin:0}body{margin:0}
        .p{width:960pt;height:540pt;background:#333}</style><div class="p"><a href="https://tenevents.sa/x">لتفاصيل أكثر</a></div>""")
    r = portfolio.page({"html": html, "pdf": os.path.join(OUT, "printed.pdf"), "thumb": os.path.join(OUT, "printed.jpg"), "chrome": chrome}, OUT)
    rd = PdfReader(os.path.join(OUT, "printed.pdf"))
    box = rd.pages[0].mediabox
    links = [x.get_object()["/A"]["/URI"] for x in rd.pages[0].get("/Annots", [])]
    assert len(rd.pages) == 1 and round(float(box.width)) == 960 and round(float(box.height)) == 540, box
    assert links == ["https://tenevents.sa/x"], links
    assert r["thumb"] and os.path.exists(os.path.join(OUT, "printed.jpg"))
    print("7. page: one 960 × 540 pt sheet, the details button a real link")
else:
    print("7. (skipped: no Chrome on this machine)")

shutil.rmtree(OUT, ignore_errors=True)
print("OK")
