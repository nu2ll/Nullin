import json
import os
import sys

from PIL import Image, ImageDraw, ImageFont, features

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
W, H = 1000, 660


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


def glyph_layer(size, sym, px, rot, alpha):
    f = font(px, True)
    tmp = Image.new("RGBA", (int(px * 2.2 * S), int(px * 2.2 * S)), (0, 0, 0, 0))
    ImageDraw.Draw(tmp).text((tmp.width // 2, tmp.height // 2), sym, font=f, fill=(255, 255, 255, alpha), anchor="mm")
    return tmp.rotate(rot, resample=Image.BICUBIC, expand=False)


def main():
    p = json.load(sys.stdin)
    pts = [float(v) for v in p.get("points", [])]
    pct = float(p.get("pct", 0))
    up = pct >= 0
    sym = str(p.get("sym", "$")) or "$"
    c1, c2 = ((30, 150, 70), (14, 100, 48)) if up else ((200, 60, 60), (140, 32, 40))
    line = (22, 163, 100) if up else (220, 70, 70)
    soft = (214, 240, 226, 255) if up else (250, 220, 220, 255)

    img = gradient(W * S, H * S, c1, c2).convert("RGBA")

    # نمادهای تزئینی کمرنگ پشت کارت
    deco = [(-10, -20, 210, -14), (W - 190, 40, 190, 16), (W - 160, H - 230, 230, 10), (-20, H - 210, 170, -12)]
    for x, y, px, rot in deco:
        g = glyph_layer(S, sym if len(sym) == 1 else sym[0], px, rot, 46)
        img.alpha_composite(g, (int(x * S), int(y * S)))

    d = ImageDraw.Draw(img)
    d.rounded_rectangle((40 * S, 40 * S, (W - 40) * S, (H - 70) * S), radius=40 * S, fill=(248, 249, 248, 255))

    ink, mute = (22, 24, 30), (120, 126, 136)

    # آیکن گرد نماد ارز کنار عنوان
    bx, by, br = W - 80 - 24, 86, 24
    d.ellipse(((bx - br) * S, (by - br) * S, (bx + br) * S, (by + br) * S), fill=line)
    d.ellipse(((bx - br + 4) * S, (by - br + 4) * S, (bx + br - 4) * S, (by + br - 4) * S), outline=(255, 255, 255, 255), width=2 * S)
    text(d, (bx, by + 1), sym, font(22 if len(sym) == 1 else 15, True), (255, 255, 255, 255), "mm")

    text(d, (W - 80 - 2 * br - 16, 86), p.get("title", ""), font(27, True), ink, "rm")

    # برچسب IRT
    tf0 = font(19, True)
    tag = p.get("tag", "IRT")
    tw0 = tlen(d, tag, tf0) + 36
    d.rounded_rectangle((80 * S, 66 * S, (80 + tw0) * S, 106 * S), radius=20 * S, fill=(236, 238, 240, 255))
    text(d, (80 + tw0 / 2, 86), tag, tf0, mute, "mm")

    unit = p.get("unit", "تومان")
    uf = font(28)
    uw = tlen(d, unit, uf)
    text(d, (W - 80, 192), unit, uf, mute, "rs")
    text(d, (W - 80 - uw - 16, 192), p.get("value", ""), font(76, True), ink, "rs")

    # درصد تغییر: کپسول درصد + کپسول فلش
    pill = f"{'+' if up else '-'}{abs(pct):.2f}%"
    pf = font(24, True)
    pw = tlen(d, pill, pf) + 44
    px1 = W - 80
    d.rounded_rectangle(((px1 - pw) * S, 215 * S, px1 * S, 258 * S), radius=22 * S, fill=soft)
    text(d, (px1 - pw / 2, 237), pill, pf, line, "mm")
    ax1 = px1 - pw - 10
    d.rounded_rectangle(((ax1 - 46) * S, 215 * S, ax1 * S, 258 * S), radius=22 * S, fill=soft)
    acx = ax1 - 23
    for dy in (-7, 5):
        y0 = 237 + dy
        if up:
            pts_a = [((acx - 9) * S, (y0 + 5) * S), (acx * S, (y0 - 4) * S), ((acx + 9) * S, (y0 + 5) * S)]
        else:
            pts_a = [((acx - 9) * S, (y0 - 5) * S), (acx * S, (y0 + 4) * S), ((acx + 9) * S, (y0 - 5) * S)]
        d.line(pts_a, fill=line, width=3 * S, joint="curve")

    cx0, cx1, cy0, cy1 = 190, W - 90, 300, H - 130
    if len(pts) < 2:
        pts = [pts[0] if pts else 0, pts[0] if pts else 0]
    lo, hi = min(pts), max(pts)
    if hi - lo < 1e-9:
        lo, hi = lo - 1, hi + 1
    pad = (hi - lo) * 0.08
    lo, hi = lo - pad, hi + pad
    xy = [(cx0 + (cx1 - cx0) * i / (len(pts) - 1), cy1 - (cy1 - cy0) * (v - lo) / (hi - lo)) for i, v in enumerate(pts)]

    lf = font(15, True)
    for i in range(5):
        v = hi - (hi - lo) * i / 4
        y = cy0 + (cy1 - cy0) * i / 4
        d.line((cx0 * S, y * S, cx1 * S, y * S), fill=(232, 235, 233, 255), width=S)
        text(d, (cx0 - 14, y), f"{v:,.0f}", lf, mute, "rm")

    layer = Image.new("RGBA", img.size, (0, 0, 0, 0))
    ld = ImageDraw.Draw(layer)
    ld.polygon([(x * S, y * S) for x, y in xy] + [(xy[-1][0] * S, cy1 * S), (xy[0][0] * S, cy1 * S)], fill=line + (70,))
    img = Image.alpha_composite(img, layer)
    d = ImageDraw.Draw(img)
    d.line([(x * S, y * S) for x, y in xy], fill=line, width=5 * S, joint="curve")
    ex, ey = xy[-1]
    d.ellipse(((ex - 8) * S, (ey - 8) * S, (ex + 8) * S, (ey + 8) * S), fill=line, outline=(255, 255, 255, 255), width=3 * S)

    foot = p.get("footer", "")
    if foot:
        ff = font(19, True)
        tw = tlen(d, foot, ff) + 50 + 34
        x0 = (W - tw) / 2
        shade = (20, 60, 38, 215) if up else (90, 24, 30, 215)
        d.rounded_rectangle((x0 * S, (H - 62) * S, (x0 + tw) * S, (H - 24) * S), radius=19 * S, fill=shade)
        lx, ly = x0 + 26, H - 43
        d.ellipse(((lx - 12) * S, (ly - 12) * S, (lx + 12) * S, (ly + 12) * S), fill=(41, 171, 238, 255))
        d.polygon([((lx - 6) * S, (ly + 0) * S), ((lx + 6) * S, (ly - 6) * S), ((lx + 3) * S, (ly + 7) * S), ((lx - 0.5) * S, (ly + 2.5) * S)], fill=(255, 255, 255, 255))
        text(d, (lx + 20, ly), foot, ff, (255, 255, 255, 255), "lm")

    img = img.convert("RGB").resize((W, H), Image.LANCZOS)
    img.save(p["out"], "PNG", optimize=True)
    print("ok")


main()
