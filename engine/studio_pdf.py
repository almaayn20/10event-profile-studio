"""The company profile book: one project page printed from HTML, and the profile PDF edited page by page.

page     HTML → a single 960 × 540 pt PDF page (Chrome), plus a JPEG preview of it.
compose  the profile with pages inserted, replaced or removed; everything else in it (links, fonts, images)
         is carried over untouched.
inspect  page count and size of a profile, and a small picture of every page so a person can pick where a new
         project goes.
"""
from __future__ import annotations

import argparse
import glob
import json
import os
import shutil
import subprocess
import sys
import time
import traceback



class EngineError(Exception):
    def __init__(self, code: str, detail: str = ""):
        super().__init__(f"{code}: {detail}")
        self.code = code
        self.detail = detail


def _chrome(job: dict) -> str:
    candidates = [job.get("chrome"), os.environ.get("CHROME_BIN"), "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome",
                  shutil.which("chromium"), shutil.which("chromium-browser"), shutil.which("google-chrome")]
    exe = next((c for c in candidates if c and (os.path.exists(c) or shutil.which(c))), None)
    if not exe:
        raise EngineError("engine_crash", "Chrome/Chromium not found for PDF export")
    return shutil.which(exe) or exe


def _reader(path: str):
    from pypdf import PdfReader
    try:
        return PdfReader(path)
    except Exception as e:  # noqa: BLE001 — a damaged or protected upload is the person's file, not a crash
        raise EngineError("portfolio_pdf_unreadable", str(e)[:300]) from e


def page(job: dict, work: str) -> dict:
    """Print one project page. The page runs a little script that steps a long description down to fit, so Chrome is
    given virtual time to let it finish before printing."""
    from pypdf import PdfReader, PdfWriter
    os.makedirs(work, exist_ok=True)
    html, pdf = job["html"], job["pdf"]
    raw = pdf + ".raw.pdf"
    for p in (raw, pdf):
        if os.path.exists(p):
            os.remove(p)
    subprocess.run([_chrome(job), "--headless", "--disable-gpu", "--no-sandbox", "--no-pdf-header-footer",
                    "--allow-file-access-from-files", "--virtual-time-budget=4000", "--run-all-compositor-stages-before-draw",
                    f"--print-to-pdf={raw}", "file://" + os.path.abspath(html)],
                   capture_output=True, timeout=int(job.get("timeout") or 90))
    if not os.path.exists(raw):
        raise EngineError("portfolio_render_failed", "Chrome produced no PDF")
    # the page is exactly one sheet; anything Chrome spilled onto a second one is dropped
    w = PdfWriter()
    w.add_page(PdfReader(raw).pages[0])
    with open(pdf, "wb") as fh:
        w.write(fh)
    os.remove(raw)

    result = {"pdf": os.path.basename(pdf)}
    if job.get("thumb"):
        result["thumb"] = _thumb(pdf, job["thumb"], dpi=int(job.get("thumb_dpi") or 60))
    return result


def _thumb(pdf: str, out_jpg: str, dpi: int, page_no: int = 1) -> str | None:
    stem = out_jpg[:-4] if out_jpg.lower().endswith(".jpg") else out_jpg
    subprocess.run(["pdftoppm", "-f", str(page_no), "-l", str(page_no), "-r", str(dpi), "-jpeg", "-jpegopt", "quality=82",
                    "-singlefile", pdf, stem], capture_output=True, timeout=120)
    return os.path.basename(stem + ".jpg") if os.path.exists(stem + ".jpg") else None


def compose(job: dict, work: str) -> dict:
    """Apply operations to a copy of the profile. Each op names pages by their number in the book as it stands when
    the op runs (1-based):  {"op": "insert", "at": 12, "pdf": path}  puts the page so it BECOMES page 12;
    {"op": "replace", "page": 12, "pdf": path};  {"op": "remove", "page": 12}.
    A page made at another size than the book's is scaled to the book's first page.

    The new file is built from the final list of pages (not by deleting from a copy), so a replaced or removed page
    takes its pictures and fonts with it and the file does not grow with every update. Pages keep their links; the
    document's information (title, author) is carried over."""
    from pypdf import PdfWriter
    base = _reader(job["base"])
    pages = list(base.pages)
    first = pages[0].mediabox if pages else None

    def sheet(path: str):
        p = _reader(path).pages[0]
        if first is not None and (abs(float(p.mediabox.width) - float(first.width)) > 0.5 or abs(float(p.mediabox.height) - float(first.height)) > 0.5):
            p.scale_to(float(first.width), float(first.height))
        return p

    for op in job.get("ops") or []:
        kind = op.get("op")
        if kind == "insert":
            at = max(1, min(int(op["at"]), len(pages) + 1))
            pages.insert(at - 1, sheet(op["pdf"]))
        elif kind in ("replace", "remove"):
            n = int(op["page"])
            if not 1 <= n <= len(pages):
                raise EngineError("portfolio_page_out_of_range", f"page {n} of {len(pages)}")
            if kind == "replace":
                pages[n - 1] = sheet(op["pdf"])
            else:
                del pages[n - 1]
        else:
            raise EngineError("unknown_command", f"compose op {kind}")

    writer = PdfWriter()
    for p in pages:
        writer.add_page(p)
    if base.metadata:
        writer.add_metadata({k: v for k, v in base.metadata.items() if isinstance(v, str)})
    writer.compress_identical_objects(remove_identicals=True, remove_orphans=True)
    out = job["out"]
    with open(out, "wb") as fh:
        writer.write(fh)
    return {"pdf": os.path.basename(out), "pages": len(writer.pages), "bytes": os.path.getsize(out)}


def inspect(job: dict, work: str) -> dict:
    """Page count, page size, and (if asked) a small JPEG of every page, named p-01.jpg, p-02.jpg, …"""
    reader = _reader(job["pdf"])
    pages = len(reader.pages)
    box = reader.pages[0].mediabox if pages else None
    result = {"pages": pages, "width": round(float(box.width), 2) if box else None, "height": round(float(box.height), 2) if box else None}
    out_dir = job.get("thumbs_dir")
    if out_dir and pages:
        os.makedirs(out_dir, exist_ok=True)
        for old in glob.glob(os.path.join(out_dir, "p-*.jpg")):
            os.remove(old)
        subprocess.run(["pdftoppm", "-r", str(int(job.get("thumb_dpi") or 18)), "-jpeg", "-jpegopt", "quality=78",
                        job["pdf"], os.path.join(out_dir, "p")], capture_output=True, timeout=600)
        made = sorted(glob.glob(os.path.join(out_dir, "p-*.jpg")))
        # pdftoppm pads the number to the page count's width (p-1 … p-9, or p-01 … p-45); store them predictably
        thumbs = []
        for path in made:
            n = int(os.path.basename(path)[2:-4])
            name = f"p-{n:03d}.jpg"
            if os.path.basename(path) != name:
                os.replace(path, os.path.join(out_dir, name))
            thumbs.append(name)
        result["thumbs"] = sorted(thumbs)
    return result


def main(argv=None) -> int:
    ap = argparse.ArgumentParser(prog="studio_pdf")
    ap.add_argument("command", choices=["page", "compose", "inspect"])
    ap.add_argument("--in", dest="inp", required=True)
    ap.add_argument("--out", dest="out", required=True)
    args = ap.parse_args(argv)
    with open(args.inp) as fh:
        job = json.load(fh)
    work = os.path.dirname(os.path.abspath(args.out))
    t0 = time.time()
    try:
        payload = {"ok": True, **{"page": page, "compose": compose, "inspect": inspect}[args.command](job, work)}
        code = 0
    except EngineError as e:
        payload, code = {"ok": False, "error": {"code": e.code, "detail": e.detail}}, 2
    except Exception as e:  # noqa: BLE001
        payload, code = {"ok": False, "error": {"code": "engine_crash", "detail": f"{type(e).__name__}: {e}", "trace": traceback.format_exc()[-2000:]}}, 3
    payload["seconds"] = round(time.time() - t0, 2)
    with open(args.out, "w") as fh:
        json.dump(payload, fh, ensure_ascii=False)
    return code


if __name__ == "__main__":
    sys.exit(main())
