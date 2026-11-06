import json
import os
import sys

import math

from PIL import Image, ImageChops, ImageDraw, ImageFilter, ImageFont, features

try:
    import arabic_reshaper
    from bidi.algorithm import get_display
    SHAPER = True
except Exception:
    SHAPER = False

RAQM = features.check("raqm")

# چیدمان حروف فارسی:
#  - اگه Raqm هست خودش اتصال حروف و راست‌به‌چپ رو درست می‌کنه (متن خام می‌دیم)
#  - اگه Raqm نیست، با arabic_reshaper + bidi خودمون آماده می‌کنیم و فونت رو BASIC می‌گیریم
# قبلاً هر دو با هم اعمال می‌شد و متن دوباره برعکس می‌شد.
MODE = "raqm" if RAQM else ("shaper" if SHAPER else "plain")

FONTS = [
    "/usr/share/fonts/truetype/vazirmatn/Vazirmatn-Regular.ttf",
    "/usr/share/fonts/truetype/vazirmatn/Vazirmatn[wght].ttf",
    "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
    "/usr/share/fonts/dejavu/DejaVuSans.ttf",
]
BOLDS = [
    "/usr/share/fonts/truetype/vazirmatn/Vazirmatn-Bold.ttf",
    "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
    "/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf",
]
S = 2
W, H = 1024, 576


def font(size, bold=False):
    for p in (BOLDS if bold else FONTS):
        if not os.path.isfile(p):
            continue
        is_vazir = "azirmatn" in p
        if is_vazir and MODE != "raqm":
            continue
        try:
            if MODE == "shaper":
                return ImageFont.truetype(p, int(size * S), layout_engine=ImageFont.Layout.BASIC)
            return ImageFont.truetype(p, int(size * S))
        except Exception:
            continue
    return ImageFont.load_default()


def has_rtl(t):
    return any("\u0600" <= c <= "\u06ff" for c in t)


def prep(t):
    if MODE == "shaper" and has_rtl(t):
        return get_display(arabic_reshaper.reshape(t))
    return t


def kw_for(t):
    if MODE == "raqm" and has_rtl(t):
        return {"direction": "rtl", "language": "fa"}
    return {}


def text(d, xy, t, f, fill, anchor="la"):
    x, y = xy
    d.text((x * S, y * S), prep(t), font=f, fill=fill, anchor=anchor, **kw_for(t))


def tlen(d, t, f):
    return d.textlength(prep(t), font=f, **kw_for(t)) / S


def gradient(w, h, c1, c2):
    m = Image.linear_gradient("L").resize((w, h))
    return Image.composite(Image.new("RGB", (w, h), c2), Image.new("RGB", (w, h), c1), m)


def gradient(w, h, c1, c2):
    m = Image.linear_gradient("L").resize((w, h))
    return Image.composite(Image.new("RGB", (w, h), c2), Image.new("RGB", (w, h), c1), m)


def smooth(pts, per=10):
    # منحنی نرم (Catmull-Rom) از همون نقطه‌های واقعی؛ داده‌ی جدید نمی‌سازه
    if len(pts) < 4:
        return pts
    lo, hi = min(pts), max(pts)
    out = []
    n = len(pts)
    for i in range(n - 1):
        p0, p1, p2, p3 = pts[max(i - 1, 0)], pts[i], pts[i + 1], pts[min(i + 2, n - 1)]
        for k in range(per):
            u = k / per
            v = 0.5 * ((2 * p1) + (-p0 + p2) * u + (2 * p0 - 5 * p1 + 4 * p2 - p3) * u * u + (-p0 + 3 * p1 - 3 * p2 + p3) * u ** 3)
            out.append(min(max(v, lo), hi))
    out.append(pts[-1])
    return out


def main():
    p = json.load(sys.stdin)
    pts = [float(v) for v in p.get("points", [])]
    pct = float(p.get("pct", 0))
    up = pct >= 0
    sym = str(p.get("sym", "$")) or "$"
    dec = int(p.get("dec", 0))
    gl = sym if len(sym) == 1 else sym[0]

    if up:
        c1, c2 = (30, 142, 70), (8, 78, 38)
        line, ptxt, soft = (18, 184, 126), (14, 165, 112), (228, 238, 240, 255)
        dark_g = (10, 80, 40, 120)
    else:
        c1, c2 = (206, 58, 62), (112, 24, 32)
        line, ptxt, soft = (232, 72, 78), (222, 62, 72), (240, 232, 236, 255)
        dark_g = (120, 20, 28, 120)

    img = gradient(W * S, H * S, c1, c2).convert("RGBA")

    # دایره‌های تزئینی با نماد ارز
    deco = [(28, 48, 112, False), (994, 112, 100, True), (64, 548, 104, False), (962, 528, 98, True)]
    layer = Image.new("RGBA", img.size, (0, 0, 0, 0))
    ld = ImageDraw.Draw(layer)
    gf = font(150, True)
    for cx, cy, r, dark in deco:
        ld.ellipse(((cx - r) * S, (cy - r) * S, (cx + r) * S, (cy + r) * S), fill=(255, 255, 255, 40))
        ld.text((cx * S, cy * S), gl, font=gf, fill=dark_g if dark else (255, 255, 255, 95), anchor="mm")
    img = Image.alpha_composite(img, layer)

    # سایه‌ی کارت
    card = (68, 54, 956, 506)
    sh = Image.new("RGBA", img.size, (0, 0, 0, 0))
    ImageDraw.Draw(sh).rounded_rectangle((card[0] * S, (card[1] + 10) * S, card[2] * S, (card[3] + 10) * S), radius=52 * S, fill=(0, 0, 0, 90))
    sh = sh.filter(ImageFilter.GaussianBlur(14 * S))
    img = Image.alpha_composite(img, sh)

    d = ImageDraw.Draw(img)
    d.rounded_rectangle([v * S for v in card], radius=52 * S, fill=(241, 245, 244, 244), outline=(255, 255, 255, 255), width=2 * S)

    ink, slate = (10, 20, 42), (98, 110, 132)

    # عنوان + آیکن گرد
    bx, by, br = 880, 122, 27
    d.ellipse(((bx - br) * S, (by - br) * S, (bx + br) * S, (by + br) * S), fill=(20, 120, 60) if up else (190, 40, 48))
    for a in range(0, 360, 24):
        d.arc(((bx - br + 4) * S, (by - br + 4) * S, (bx + br - 4) * S, (by + br - 4) * S), a, a + 14, fill=(255, 255, 255, 255), width=2 * S)
    text(d, (bx, by + 1), sym, font({1: 25, 2: 18, 3: 14}.get(len(sym), 11), True), (255, 255, 255, 255), "mm")
    text(d, (bx - br - 14, 122), p.get("title", ""), font(28, True), ink, "rm")

    # برچسب IRT / USD
    tf0 = font(21, True)
    tag = p.get("tag", "IRT")
    tw0 = tlen(d, tag, tf0) + 54
    d.rounded_rectangle((106 * S, 96 * S, (106 + tw0) * S, 142 * S), radius=23 * S, fill=(226, 231, 240, 255))
    text(d, (106 + tw0 / 2, 119), tag, tf0, slate, "mm")

    # مبلغ و واحد
    unit = p.get("unit", "تومان")
    uf = font(36, True)
    uw = tlen(d, unit, uf)
    text(d, (905, 220), unit, uf, slate, "rs")
    text(d, (905 - uw - 14, 218), p.get("value", ""), font(72, True), ink, "rs")

    # درصد تغییر
    pill = f"{'+' if up else '-'}{abs(pct):.2f}%"
    pf = font(25, True)
    pw = tlen(d, pill, pf) + 40
    d.rounded_rectangle(((905 - pw) * S, 238 * S, 905 * S, 277 * S), radius=20 * S, fill=soft)
    text(d, (905 - pw / 2, 258), pill, pf, ptxt, "mm")
    ax = 905 - pw - 14
    d.ellipse(((ax - 38) * S, 238 * S, ax * S, 277 * S), fill=soft)
    acx = ax - 19
    for dy in (-6, 5):
        y0 = 258 + dy
        if up:
            pa = [((acx - 8) * S, (y0 + 4) * S), (acx * S, (y0 - 4) * S), ((acx + 8) * S, (y0 + 4) * S)]
        else:
            pa = [((acx - 8) * S, (y0 - 4) * S), (acx * S, (y0 + 4) * S), ((acx + 8) * S, (y0 - 4) * S)]
        d.line(pa, fill=ptxt, width=3 * S, joint="curve")

    # نمودار
    cx0, cx1, cy0, cy1 = 185, 900, 298, 470
    if len(pts) < 2:
        pts = [pts[0] if pts else 0, pts[0] if pts else 0]
    raw = pts
    pts = smooth(raw)
    lo, hi = min(raw), max(raw)
    if hi - lo < 1e-9:
        lo, hi = lo - 1, hi + 1
    pad = (hi - lo) * 0.06
    lo, hi = lo - pad, hi + pad
    xy = [(cx0 + (cx1 - cx0) * i / (len(pts) - 1), cy1 - (cy1 - cy0) * (v - lo) / (hi - lo)) for i, v in enumerate(pts)]

    lf = font(15, True)
    for i in range(5):
        v = hi - (hi - lo) * i / 4
        y = cy0 + (cy1 - cy0) * i / 4
        d.line((cx0 * S, y * S, (cx1 + 6) * S, y * S), fill=(224, 230, 230, 255), width=S)
        text(d, (cx0 - 14, y), f"{v:,.{dec}f}", lf, slate, "rm")

    mask = Image.new("L", img.size, 0)
    ImageDraw.Draw(mask).polygon([(x * S, y * S) for x, y in xy] + [(xy[-1][0] * S, cy1 * S), (xy[0][0] * S, cy1 * S)], fill=255)
    fade = Image.linear_gradient("L").rotate(180).resize((img.width, int((cy1 - cy0 + 40) * S)))
    fade_full = Image.new("L", img.size, 0)
    fade_full.paste(fade.point(lambda v: int(v * 0.5)), (0, int((cy0 - 20) * S)))
    alpha = ImageChops.multiply(mask, fade_full)
    fill_layer = Image.new("RGBA", img.size, line + (0,))
    fill_layer.putalpha(alpha)
    img = Image.alpha_composite(img, fill_layer)

    d = ImageDraw.Draw(img)
    d.line([(x * S, y * S) for x, y in xy], fill=line, width=5 * S, joint="curve")
    ex, ey = xy[-1]
    d.ellipse(((ex - 10) * S, (ey - 10) * S, (ex + 10) * S, (ey + 10) * S), fill=line, outline=(255, 255, 255, 255), width=3 * S)

    # فوتر
    foot = p.get("footer", "")
    if foot:
        ff = font(19, True)
        tw = tlen(d, foot, ff) + 52 + 36
        x0 = (W - tw) / 2
        fl = Image.new("RGBA", img.size, (0, 0, 0, 0))
        ImageDraw.Draw(fl).rounded_rectangle((x0 * S, 522 * S, (x0 + tw) * S, 558 * S), radius=18 * S, fill=(0, 0, 0, 90), outline=(255, 255, 255, 70), width=S)
        img = Image.alpha_composite(img, fl)
        d = ImageDraw.Draw(img)
        lx, ly = x0 + 28, 540
        d.ellipse(((lx - 13) * S, (ly - 13) * S, (lx + 13) * S, (ly + 13) * S), fill=(41, 171, 238, 255))
        d.polygon([((lx - 7) * S, ly * S), ((lx + 7) * S, (ly - 6.5) * S), ((lx + 3.5) * S, (ly + 7.5) * S), ((lx - 0.5) * S, (ly + 3) * S)], fill=(255, 255, 255, 255))
        text(d, (lx + 22, ly), foot, ff, (255, 255, 255, 255), "lm")

    img = img.convert("RGB").resize((W, H), Image.LANCZOS)
    img.save(p["out"], "PNG", optimize=True)
    print("ok")


main()
