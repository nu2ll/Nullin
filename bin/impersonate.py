import base64
import json
import sys
from urllib.parse import urljoin


def main():
    p = json.load(sys.stdin)
    out = {"code": 0, "body": "", "url": p.get("url", ""), "redirect": "", "error": ""}
    try:
        from curl_cffi import requests

        headers = {}
        for h in p.get("headers") or []:
            if ":" in h:
                k, v = h.split(":", 1)
                headers[k.strip()] = v.strip()
        body = p.get("body")
        r = requests.request(
            p.get("method") or "GET",
            p["url"],
            headers=headers,
            data=body.encode("utf-8") if isinstance(body, str) else None,
            impersonate=p.get("impersonate") or "chrome",
            allow_redirects=bool(p.get("follow", True)),
            timeout=p.get("timeout") or 15,
        )
        content = r.content[: int(p.get("max_bytes") or 2000000)]
        out["code"] = r.status_code
        out["body"] = base64.b64encode(content).decode("ascii")
        out["url"] = str(r.url)
        loc = r.headers.get("location")
        if loc and 300 <= r.status_code < 400:
            out["redirect"] = urljoin(str(r.url), loc)
    except Exception as e:
        out["error"] = str(e)
    sys.stdout.write(json.dumps(out))


main()
