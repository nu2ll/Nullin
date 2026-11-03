#!/usr/bin/env python3
"""Nullini music identifier: python3 bin/musicid.py <audio file>  ->  one JSON line on stdout."""
import asyncio
import json
import sys


def out(obj):
    sys.stdout.write(json.dumps(obj, ensure_ascii=False) + "\n")
    sys.stdout.flush()


async def main(path):
    from shazamio import Shazam

    shazam = Shazam()
    fn = getattr(shazam, "recognize", None) or getattr(shazam, "recognize_song")
    res = await fn(path)
    track = (res or {}).get("track")
    if not track:
        out({"ok": False})
        return
    meta = {}
    for sec in track.get("sections") or []:
        for m in sec.get("metadata") or []:
            if m.get("title"):
                meta[m["title"]] = m.get("text", "")
    img = track.get("images") or {}
    out({
        "ok": True,
        "title": track.get("title", ""),
        "artist": track.get("subtitle", ""),
        "album": meta.get("Album", ""),
        "label": meta.get("Label", ""),
        "year": meta.get("Released", ""),
        "genre": (track.get("genres") or {}).get("primary", ""),
        "cover": img.get("coverarthq") or img.get("coverart") or img.get("background") or "",
        "url": track.get("url", ""),
    })


if __name__ == "__main__":
    if len(sys.argv) < 2:
        out({"ok": False, "error": "no file"})
        sys.exit(0)
    try:
        asyncio.run(main(sys.argv[1]))
    except Exception as e:  # noqa: BLE001
        out({"ok": False, "error": str(e)[:200]})
