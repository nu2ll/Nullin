import json
import sys

from PIL import Image, ImageDraw, ImageFilter, ImageFont

S = 2
W = 1024
H = 1088
CARD = (40, 40, 984, 984)
CARD_R = 48
BOARD_X = 80
BOARD_Y = 80
SQ = 108

BG1 = (30, 41, 59)
BG2 = (8, 12, 24)
CARD_FILL = (14, 17, 22, 255)
CARD_LINE = (40, 46, 56, 255)
SLATE = (148, 158, 176)
INK = (245, 247, 250)

LIGHT = (66, 78, 98)
DARK = (34, 41, 54)

LAST = (18, 184, 126, 100)
SEL = (37, 99, 235, 190)
DOT = (245, 247, 250, 120)
CAP = (232, 72, 78, 235)
CHECK = (232, 72, 78)

FONTS = [
    "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
    "/usr/share/fonts/dejavu/DejaVuSans.ttf",
]
BOLDS = [
    "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
    "/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf",
]

GLYPH = {"k": "♚", "q": "♛", "r": "♜", "b": "♝", "n": "♞", "p": "♟"}


def load(paths, size):
    for p in paths:
        try:
            return ImageFont.truetype(p, int(size * S))
        except Exception:
            continue
    return ImageFont.load_default()


def gradient(w, h, c1, c2):
    m = Image.linear_gradient("L").resize((w, h))
    return Image.composite(Image.new("RGB", (w, h), c2), Image.new("RGB", (w, h), c1), m)


def cell(sq, flip):
    r, c = divmod(sq, 8)
    if flip:
        r, c = 7 - r, 7 - c
    return c * SQ * S, r * SQ * S


def board_layer(d):
    board = d["b"]
    flip = bool(d.get("flip"))
    last = d.get("last") or []
    sel = int(d.get("sel", -1))
    targets = set(int(t) for t in (d.get("targets") or []))
    caps = set(int(t) for t in (d.get("caps") or []))
    check = int(d.get("check", -1))
    q = SQ * S

    layer = Image.new("RGBA", (8 * q, 8 * q), (0, 0, 0, 255))
    dr = ImageDraw.Draw(layer)
    for sq in range(64):
        x, y = cell(sq, flip)
        r, c = divmod(sq, 8)
        dr.rectangle([x, y, x + q, y + q], fill=LIGHT if (r + c) % 2 == 0 else DARK)

    over = Image.new("RGBA", layer.size, (0, 0, 0, 0))
    od = ImageDraw.Draw(over)
    for sq in last:
        x, y = cell(int(sq), flip)
        od.rectangle([x, y, x + q, y + q], fill=LAST)
    if sel >= 0:
        x, y = cell(sel, flip)
        od.rectangle([x, y, x + q, y + q], fill=SEL)
    if check >= 0:
        x, y = cell(check, flip)
        for i in range(6):
            pad = int(i * 8 * S)
            od.ellipse([x + pad, y + pad, x + q - pad, y + q - pad], fill=CHECK + (34 + i * 30,))
    for sq in targets:
        x, y = cell(sq, flip)
        if sq in caps:
            od.ellipse([x + 5 * S, y + 5 * S, x + q - 5 * S, y + q - 5 * S], outline=CAP, width=int(9 * S))
        else:
            cx, cy = x + q // 2, y + q // 2
            rr = 16 * S
            od.ellipse([cx - rr, cy - rr, cx + rr, cy + rr], fill=DOT)
    layer = Image.alpha_composite(layer, over)
    dr = ImageDraw.Draw(layer)

    pf = load(FONTS, 88)
    for sq in range(64):
        p = board[sq]
        if p == ".":
            continue
        g = GLYPH.get(p.lower())
        if g is None:
            continue
        x, y = cell(sq, flip)
        white = p.isupper()
        dr.text(
            (x + q // 2, y + q // 2 + 4 * S),
            g,
            font=pf,
            fill=(248, 249, 251) if white else (12, 14, 18),
            anchor="mm",
            stroke_width=int(3 * S),
            stroke_fill=(8, 10, 14) if white else (196, 204, 220),
        )
    return layer


def main():
    d = json.load(sys.stdin)
    flip = bool(d.get("flip"))
    img = gradient(W * S, H * S, BG1, BG2).convert("RGBA")
    shadow = Image.new("RGBA", img.size, (0, 0, 0, 0))
    ImageDraw.Draw(shadow).rounded_rectangle(
        (CARD[0] * S, (CARD[1] + 10) * S, CARD[2] * S, (CARD[3] + 10) * S),
        radius=CARD_R * S,
        fill=(0, 0, 0, 120),
    )
    img = Image.alpha_composite(img, shadow.filter(ImageFilter.GaussianBlur(14 * S)))
    dr = ImageDraw.Draw(img)
    dr.rounded_rectangle([v * S for v in CARD], radius=CARD_R * S, fill=CARD_FILL, outline=CARD_LINE, width=2 * S)

    layer = board_layer(d)
    mask = Image.new("L", layer.size, 0)
    ImageDraw.Draw(mask).rounded_rectangle((0, 0, layer.width, layer.height), radius=18 * S, fill=255)
    img.paste(layer, (BOARD_X * S, BOARD_Y * S), mask)
    dr = ImageDraw.Draw(img)
    dr.rounded_rectangle(
        (BOARD_X * S - S, BOARD_Y * S - S, (BOARD_X + 8 * SQ) * S + S, (BOARD_Y + 8 * SQ) * S + S),
        radius=19 * S,
        outline=CARD_LINE,
        width=2 * S,
    )

    cf = load(BOLDS, 24)
    files = "abcdefgh"
    for i in range(8):
        col = 7 - i if flip else i
        rank = i + 1 if flip else 8 - i
        x = (BOARD_X + i * SQ + SQ // 2) * S
        dr.text((x, (BOARD_Y - 22) * S), files[col], font=cf, fill=SLATE, anchor="mm")
        dr.text((x, (BOARD_Y + 8 * SQ + 22) * S), files[col], font=cf, fill=SLATE, anchor="mm")
        y = (BOARD_Y + i * SQ + SQ // 2) * S
        dr.text(((BOARD_X - 22) * S, y), str(rank), font=cf, fill=SLATE, anchor="mm")
        dr.text(((BOARD_X + 8 * SQ + 22) * S, y), str(rank), font=cf, fill=SLATE, anchor="mm")

    foot = str(d.get("foot") or "")
    if foot:
        ff = load(BOLDS, 19)
        tw = dr.textlength(foot, font=ff) / S + 52 + 36
        x0 = (W - tw) / 2
        cy = (CARD[3] + 46)
        fl = Image.new("RGBA", img.size, (0, 0, 0, 0))
        ImageDraw.Draw(fl).rounded_rectangle(
            (x0 * S, (cy - 18) * S, (x0 + tw) * S, (cy + 18) * S),
            radius=18 * S,
            fill=(0, 0, 0, 90),
            outline=(255, 255, 255, 70),
            width=S,
        )
        img = Image.alpha_composite(img, fl)
        dr = ImageDraw.Draw(img)
        lx, ly = x0 + 28, cy
        dr.ellipse(((lx - 13) * S, (ly - 13) * S, (lx + 13) * S, (ly + 13) * S), fill=(41, 171, 238, 255))
        dr.polygon(
            [((lx - 7) * S, ly * S), ((lx + 7) * S, (ly - 6.5) * S), ((lx + 3.5) * S, (ly + 7.5) * S), ((lx - 0.5) * S, (ly + 3) * S)],
            fill=(255, 255, 255, 255),
        )
        dr.text(((lx + 22) * S, ly * S), foot, font=ff, fill=(255, 255, 255, 255), anchor="lm")

    img = img.convert("RGB").resize((W, H), Image.LANCZOS)
    img.save(d["out"], "PNG", optimize=True)


if __name__ == "__main__":
    main()
