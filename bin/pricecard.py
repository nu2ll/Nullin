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

FONTS = [
    "/usr/share/fonts/truetype/vazirmatn/Vazirmatn-Regular.ttf",
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
        if os.path.isfile(p):
            if SHAPER and "Vazirmatn" in p:
                continue
            return ImageFont.truetype(p, int(size * S))
    return ImageFont.load_default()


def has_rtl(t):
    return any("\u0600" <= c <= "\u06ff" for c in t)


def shape(t):
    if SHAPER and has_rtl(t):
        return get_display(arabic_reshaper.reshape(t))
    return t


def text(d, xy, t, f, fill, anchor="la"):
    x, y = xy
    kw = {}
    if not SHAPER and RAQM and has_rtl(t):
        kw = {"direction": "rtl", "language": "fa"}
    d.text((x * S, y * S), shape(t), font=f, fill=fill, anchor=anchor, **kw)


def gradient(w, h, c1, c2):
    m = Image.linear_gradient("L").resize((w, h))
    return Image.composite(Image.new("RGB", (w, h), c2), Image.new("RGB", (w, h), c1), m)


def main():
    p = json.load(sys.stdin)
    pts = [float(v) for v in p.get("points", [])]
    pct = float(p.get("pct", 0))
    up = pct >= 0
    c1, c2 = ((30, 150, 70), (14, 100, 48)) if up else ((200, 60, 60), (140, 32, 40))
    line = (22, 163, 100) if up else (220, 70, 70)

    img = gradient(W * S, H * S, c1, c2).convert("RGBA")
    d = ImageDraw.Draw(img)
    d.rounded_rectangle((40 * S, 40 * S, (W - 40) * S, (H - 70) * S), radius=40 * S, fill=(248, 249, 248, 255))

    ink, mute = (22, 24, 30), (120, 126, 136)
    text(d, (W - 80, 72), p.get("title", ""), font(27, True), ink, "ra")
    text(d, (80, 76), p.get("tag", "IRT"), font(20, True), mute, "la")

    text(d, (W - 80, 192), p.get("unit", "تومان"), font(28), mute, "rs")
    big = font(76, True)
    uw = d.textlength(shape(p.get("unit", "تومان")), font=font(28)) / S
    text(d, (W - 80 - uw - 16, 192), p.get("value", ""), big, ink, "rs")

    pill = f"{'+' if up else '-'}{abs(pct):.2f}%"
    pf = font(24, True)
    pw = d.textlength(pill, font=pf) / S + 60
    px1 = W - 80
    d.rounded_rectangle(((px1 - pw) * S, 215 * S, px1 * S, 258 * S), radius=22 * S, fill=(214, 240, 226, 255) if up else (250, 220, 220, 255))
    text(d, (px1 - 18, 236), pill, pf, line, "rm")
    d.polygon([((px1 - pw + 22) * S, (244 if up else 228) * S), ((px1 - pw + 34) * S, (230 if up else 244) * S), ((px1 - pw + 46) * S, (244 if up else 228) * S)], fill=line)

    cx0, cx1, cy0, cy1 = 190, W - 90, 300, H - 130
    if len(pts) < 2:
        pts = [pts[0] if pts else 0, pts[0] if pts else 0]
    lo, hi = min(pts), max(pts)
    if hi - lo < 1e-9:
        lo, hi = lo - 1, hi + 1
    pad = (hi - lo) * 0.08
    lo, hi = lo - pad, hi + pad
    xy = [(cx0 + (cx1 - cx0) * i / (len(pts) - 1), cy1 - (cy1 - cy0) * (v - lo) / (hi - lo)) for i, v in enumerate(pts)]

    lf = font(15)
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

    tag = p.get("footer", "")
    if tag:
        tf = font(19, True)
        tw = d.textlength(tag, font=tf) / S + 50
        x0 = (W - tw) / 2
        d.rounded_rectangle((x0 * S, (H - 62) * S, (x0 + tw) * S, (H - 24) * S), radius=19 * S, fill=(20, 80, 45, 200))
        text(d, (W / 2, H - 43), tag, tf, (255, 255, 255, 255), "mm")

    img = img.convert("RGB").resize((W, H), Image.LANCZOS)
    img.save(p["out"], "PNG", optimize=True)
    print("ok")


main()
