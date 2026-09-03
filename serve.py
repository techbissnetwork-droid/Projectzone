#!/usr/bin/env python3
"""
Local preview server for the TECHBISS site concepts.

Plain static HTML/CSS/JS — nothing to install, no dependencies, no build
step. This script just serves the current folder over HTTP and opens it
in your browser.

Why use this instead of double-clicking index.html?
Concept 04 (Soft UI Control Panel) includes an interactive admin/client
demo that stores its data in the browser's localStorage. Some browsers
(Firefox in particular) treat every file:// page as its own separate
origin, so localStorage doesn't carry over between pages when you open
them directly from disk. Serving the folder over http://localhost avoids
that entirely, and behaves like the site will once it's actually hosted.

Usage:
    python3 serve.py            (then open the printed URL, or it opens
                                  automatically in your default browser)
"""
import http.server
import socketserver
import webbrowser
import os

PORT = 8000
DIRECTORY = os.path.dirname(os.path.abspath(__file__))


class Handler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=DIRECTORY, **kwargs)

    def log_message(self, format, *args):
        pass  # keep the console quiet


def main():
    port = PORT
    while True:
        try:
            with socketserver.TCPServer(("", port), Handler) as httpd:
                url = f"http://localhost:{port}/"
                print(f"Serving TECHBISS site at {url}")
                print("Press Ctrl+C to stop.")
                try:
                    webbrowser.open(url)
                except Exception:
                    pass
                httpd.serve_forever()
            break
        except OSError:
            port += 1
            if port > PORT + 20:
                print("Could not find a free port. Please close other local servers and try again.")
                break
        except KeyboardInterrupt:
            print("\nStopped.")
            break


if __name__ == "__main__":
    main()
