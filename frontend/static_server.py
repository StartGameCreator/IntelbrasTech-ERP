from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
import os
import urllib.parse


ROOT = Path(__file__).resolve().parent / "dist"
PORT = 5173


class SpaHandler(SimpleHTTPRequestHandler):
    def translate_path(self, path):
        parsed = urllib.parse.urlparse(path)
        clean = urllib.parse.unquote(parsed.path).lstrip("/")
        target = (ROOT / clean).resolve()
        try:
            target.relative_to(ROOT.resolve())
        except ValueError:
            return str(ROOT / "index.html")
        if target.is_file():
            return str(target)
        return str(ROOT / "index.html")

    def log_message(self, fmt, *args):
        return


if __name__ == "__main__":
    os.chdir(ROOT)
    server = ThreadingHTTPServer(("127.0.0.1", PORT), SpaHandler)
    print(f"Frontend ativo em http://127.0.0.1:{PORT}", flush=True)
    server.serve_forever()
