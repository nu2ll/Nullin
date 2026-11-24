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
 # (message, expected)  -- the ones listed in the report
 ("ERROR: [tiktok] 1: This account does not have any videos posted", 'empty'),
 ("ERROR: [tiktok] 1: This livestream has ended", 'gone'),
 ("ERROR: [twitter] 1: Twitter Space not found", 'gone'),
 ("ERROR: [twitter:spaces] 1: Space ended", 'empty'),
 ("ERROR: [twitter:spaces] 1: Space not started yet", 'empty'),
 ("ERROR: [twitter] 1: Media #2 is not a video", 'empty'),
 ("ERROR: [reddit] 1: Your IP address is unable to access the Reddit API", 'geo'),
 ("ERROR: [reddit] 1: This video is processing", 'empty'),
 ("ERROR: [youtube] x: Login details are needed to download this content", 'private'),
 ("ERROR: [youtube] x: Your IP is likely being blocked by Youtube", 'bot'),
 ("ERROR: [youtube] x: Sign in to confirm you’re not a bot", 'bot'),
 ("ERROR: [youtube] x: Private video. Sign in if you've been granted access", 'private'),
 # should stay generic
 ("ERROR: [youtube] x: Unable to extract initial data", 'other'),
 ("ERROR: [generic] Invalid URL", 'other'),
 ("ERROR: [youtube] x: Failed to extract any player response", 'other'),
 ("ERROR: [tiktok] 1: Fresh cookies (not necessarily logged in) are needed", 'other'),
 # the three limitations
 ("ERROR: [twitter] 1: Twitter API says: Sorry, you are not authorized to see this status.", 'private'),
 ("ERROR: [twitter] 1: Twitter API says: Rate limit exceeded", 'rate'),
 ("ERROR: [twitter] 1: Twitter API says: No status found with that ID.", 'gone'),
 ("ERROR: [twitter] 1: Twitter API says: User has been suspended.", 'gone'),
 ("ERROR: [twitter] 1: Twitter API says: Sorry, that page does not exist.", 'gone'),
 ("ERROR: [twitter] 1: Twitter API says: something nobody has seen before", 'other'),
 ("ERROR: [twitter] 1: Requested tweet is unavailable", 'gone'),
 ("ERROR: [twitter] 1: Video #2 is unavailable", 'gone'),
]
bad = 0
for msg, exp in cases:
    got = kind(msg)
    flag = 'OK ' if got == exp else 'BAD'
    bad += got != exp
    print(flag, got.ljust(8), exp.ljust(8), msg)
print("sites parsed:", {k: len(v) for k, v in errs.items()}, "core:", len(core))
sys.exit(bad)
