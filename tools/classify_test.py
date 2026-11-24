import re, sys
src = open(__import__('os').path.join(__import__('os').path.dirname(__file__), '..', 'src', 'Features', 'Downloader.php'), encoding='utf-8').read()

def php_re(s):  # '/.../' -> python pattern
    assert s[0] == '/' and s[-1] == '/'
    return re.compile(s[1:-1])

def parse_block(text):
    return [(k, php_re(r.replace("\\\\", "\\"))) for k, r in re.findall(r"\['(\w+)',\s*'((?:[^'\\]|\\.)*)'\]", text)]

m = re.search(r"private const ERRORS = \[(.*?)\n    \];", src, re.S)
errs = {}
for site, body in re.findall(r"'(\w+)' => \[(.*?)\n        \],", m.group(1), re.S):
    errs[site] = parse_block(body)
core = parse_block(re.search(r"private const CORE_ERRORS = \[(.*?)\n    \];", src, re.S).group(1))

def kind(err, code=1):
    if code in (124, 137): return 'timeout'
    e = err.lower(); site = ''
    for tag in re.findall(r'\[([a-z0-9]+)(?::[a-z0-9_]+)?\]', e):
        tag = 'twitter' if tag == 'x' else tag
        if tag in errs: site = tag; break
    for k, r in errs.get(site, []) + core:
        if r.search(e): return k
    return 'other'

cases = [
 ("ERROR: [twitter] 1: Twitter API says: This Post was deleted by the Post author", 'gone'),
 ("ERROR: [twitter] 1: Twitter API says: This Post is from a suspended account", 'gone'),
 ("ERROR: [twitter] 1: Twitter API says: You're unable to view this Post because this account owner limits who can view their Posts", 'private'),
 ("ERROR: [twitter] 1: Twitter API says: Age-restricted adult content. This content might not be appropriate for people under 18 years old", 'age'),
 ("ERROR: [twitter] 1: Twitter API says: Unknown error", 'other'),
 ("ERROR: [twitter] 1: Error(s) while querying API: Unknown error", 'other'),
 ("ERROR: [twitter] 1: Requested tweet is unavailable", 'gone'),
 ("ERROR: [twitter] 1: Video #2 is unavailable", 'gone'),
 ("ERROR: [twitter] 1: Media #2 is not a video", 'empty'),
 ("ERROR: [twitter] 1: Twitter Space ended and replay is disabled", 'empty'),
 ("ERROR: [twitter] 1: NSFW tweet requires authentication", 'private'),
 ("ERROR: [douyin] 1: Fresh cookies (not necessarily logged in) are needed", 'other'),
 ("ERROR: [tiktok] 1: This account does not have any videos posted", 'empty'),
 ("ERROR: [tiktok] 1: This livestream has ended", 'gone'),
 ("ERROR: [reddit] 1: Your IP address is unable to access the Reddit API", 'geo'),
 ("ERROR: [youtube] x: Your IP is likely being blocked by Youtube", 'bot'),
 ("ERROR: [youtube] x: Login details are needed to download this content", 'private'),
 ("ERROR: [youtube] x: Unable to extract initial data", 'other'),
]
bad = 0
for msg, exp in cases:
    got = kind(msg)
    flag = 'OK ' if got == exp else 'BAD'
    bad += got != exp
    print(flag, got.ljust(8), exp.ljust(8), msg)
print("sites parsed:", {k: len(v) for k, v in errs.items()}, "core:", len(core))
sys.exit(bad)
