#!/bin/sh
# Pulls the exact official error text for the 3 cases the classifier can't match by itself
# (Twitter "Twitter API says:", Instagram "title: description", TikTok "Fresh cookies ...")
# straight from the yt-dlp / gallery-dl source. Needs network + git.
#   sh tools/extract_official_errors.sh > official_errors.txt
set -e
D=${1:-/tmp/dl-src}; mkdir -p "$D"; cd "$D"
[ -d yt-dlp ]     || git clone -q --depth 1 https://github.com/yt-dlp/yt-dlp
[ -d gallery-dl ] || git clone -q --depth 1 https://github.com/mikf/gallery-dl
Y=yt-dlp/yt_dlp/extractor; G=gallery-dl/gallery_dl/extractor

echo "##### TWITTER (yt-dlp): every raise + the 'Twitter API says' site"
grep -n -E "Twitter API says|raise_login_required|ExtractorError\(|raise_no_formats" $Y/twitter.py
echo; echo "##### TWITTER (gallery-dl)"
grep -n -E "raise exception\.|exception\.[A-Za-z]+Error\(" $G/twitter.py

echo; echo "##### TIKTOK: 'Fresh cookies' with surrounding condition"
grep -n -B8 -A3 "Fresh cookies" $Y/tiktok.py
echo; echo "##### TIKTOK: every status-code message"
grep -n -E "ExtractorError\(|raise_login_required|status" $Y/tiktok.py | grep -i -E "raise|error|login"

echo; echo "##### INSTAGRAM (yt-dlp): every raise + where title/description are joined"
grep -n -E "ExtractorError\(|raise_login_required|raise_no_formats|'title'.*description|description.*title" $Y/instagram.py
echo; echo "##### INSTAGRAM (gallery-dl)"
grep -n -E "raise exception\.|exception\.[A-Za-z]+Error\(|description" $G/instagram.py
