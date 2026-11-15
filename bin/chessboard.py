import json
import sys

from PIL import Image, ImageDraw, ImageFont

SQ = 100
M = 44
SIZE = SQ * 8 + M * 2

LIGHT = (240, 217, 181)
DARK = (181, 136, 99)
FRAME = (38, 36, 34)
FRAME_TEXT = (200, 196, 190)
LAST = (246, 235, 90, 120)
SEL = (60, 140, 255, 150)
DOT = (30, 30, 30, 70)
CAP = (214, 48, 49, 200)
CHECK = (255, 40, 40, 190)

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
            return ImageFont.truetype(p, size)
        except Exception:
            continue
    return ImageFont.load_default()


def cell(sq, flip):
    r, c = divmod(sq, 8)
    if flip:
        r, c = 7 - r, 7 - c
    return M + c * SQ, M + r * SQ


def main():
    d = json.load(sys.stdin)
    board = d["b"]
    flip = bool(d.get("flip"))
    last = d.get("last") or []
    sel = int(d.get("sel", -1))
    targets = set(d.get("targets") or [])
    caps = set(d.get("caps") or [])
    check = int(d.get("check", -1))

    img = Image.new("RGB", (SIZE, SIZE), FRAME)
    dr = ImageDraw.Draw(img)

    for sq in range(64):
        x, y = cell(sq, flip)
        r, c = divmod(sq, 8)
        dr.rectangle([x, y, x + SQ, y + SQ], fill=LIGHT if (r + c) % 2 == 0 else DARK)

    over = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    od = ImageDraw.Draw(over)
    for sq in last:
        x, y = cell(int(sq), flip)
        od.rectangle([x, y, x + SQ, y + SQ], fill=LAST)
    if sel >= 0:
        x, y = cell(sel, flip)
        od.rectangle([x, y, x + SQ, y + SQ], fill=SEL)
    if check >= 0:
        x, y = cell(check, flip)
        for i in range(5):
            pad = i * 9
            a = 40 + i * 35
            od.ellipse([x + pad, y + pad, x + SQ - pad, y + SQ - pad], fill=(255, 40, 40, a))
    for sq in targets:
        x, y = cell(int(sq), flip)
        if sq in caps:
            od.ellipse([x + 4, y + 4, x + SQ - 4, y + SQ - 4], outline=CAP, width=9)
        else:
            cx, cy = x + SQ // 2, y + SQ // 2
            od.ellipse([cx - 15, cy - 15, cx + 15, cy + 15], fill=DOT)
    img = Image.alpha_composite(img.convert("RGBA"), over)
    dr = ImageDraw.Draw(img)

    pf = load(FONTS, 82)
    for sq in range(64):
        p = board[sq]
        if p == ".":
            continue
        x, y = cell(sq, flip)
        white = p.isupper()
        g = GLYPH.get(p.lower())
        if g is None:
            continue
        fill = (252, 252, 250) if white else (24, 24, 26)
        edge = (20, 20, 20) if white else (230, 230, 226)
        dr.text(
            (x + SQ // 2, y + SQ // 2 + 4),
            g,
            font=pf,
            fill=fill,
            anchor="mm",
            stroke_width=3,
            stroke_fill=edge,
        )

    cf = load(BOLDS, 26)
    files = "abcdefgh"
    for i in range(8):
        col = 7 - i if flip else i
        rank = i + 1 if flip else 8 - i
        x = M + i * SQ + SQ // 2
        dr.text((x, M // 2 + 1), files[col], font=cf, fill=FRAME_TEXT, anchor="mm")
        dr.text((x, SIZE - M // 2 - 1), files[col], font=cf, fill=FRAME_TEXT, anchor="mm")
        y = M + i * SQ + SQ // 2
        dr.text((M // 2, y), str(rank), font=cf, fill=FRAME_TEXT, anchor="mm")
        dr.text((SIZE - M // 2, y), str(rank), font=cf, fill=FRAME_TEXT, anchor="mm")

    img.convert("RGB").save(d["out"], "PNG", optimize=True)


if __name__ == "__main__":
    main()
