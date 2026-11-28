import sys
import urllib.request

import cairosvg

name, dst = sys.argv[1], sys.argv[2]
url = "https://cdn.jsdelivr.net/npm/@tabler/icons@3.19.0/icons/outline/" + name + ".svg"
req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
svg = urllib.request.urlopen(req, timeout=15).read().decode("utf-8")
svg = svg.replace("currentColor", "#FFFFFF").replace('stroke-width="2"', 'stroke-width="1.7"')
cairosvg.svg2png(bytestring=svg.encode("utf-8"), write_to=dst, output_width=128, output_height=128)
