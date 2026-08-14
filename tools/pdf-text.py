#!/usr/bin/env python3
"""
Pull the text out of a PDF, page by page.

Written because this container has no poppler and no working pypdf, and the
utility reports arrive as PDFs. It decompresses the page content streams and
reads the text-showing operators, which is enough for the table-of-figures
layout these reports use.

    python3 tools/pdf-text.py report.pdf              # whole document
    python3 tools/pdf-text.py report.pdf hardness     # pages matching a word

It will not read a scanned PDF: there is no text in one, only pictures of it.
If the output is empty, that is what happened, and the figures have to be typed
in by hand or the page screenshotted.
"""

import re
import sys
import zlib


def pages(path):
    data = open(path, 'rb').read()
    out = []

    for match in re.finditer(rb'stream\r?\n', data):
        start = match.end()
        end = data.find(b'endstream', start)

        if end < 0:
            continue

        try:
            body = zlib.decompress(data[start:end])
        except zlib.error:
            continue

        # A page's content stream shows text. A font or an image does not.
        if b'TJ' in body or b'Tj' in body:
            out.append(body)

    return out


def text(stream):
    parts = []

    for shown in re.finditer(rb'\[(.*?)\]\s*TJ|\((?:\\.|[^\\()])*\)\s*Tj', stream, re.S):
        for literal in re.finditer(rb'\((?:\\.|[^\\()])*\)', shown.group(0)):
            parts.append(literal.group(0)[1:-1])

    body = b''.join(parts).decode('latin-1')

    for old, new in ((r'\(', '('), (r'\)', ')'), (r'\\', '\\')):
        body = body.replace(old, new)

    return body


def main():
    if len(sys.argv) < 2:
        print(__doc__)
        return 1

    path = sys.argv[1]
    needle = sys.argv[2].lower() if len(sys.argv) > 2 else ''
    found = 0

    for number, stream in enumerate(pages(path), start=1):
        body = text(stream)

        # Compared with the spacing stripped out, because these reports kern
        # letter by letter and "hardness" arrives as "har dnes s".
        if needle and needle not in re.sub(r'[^a-z0-9]', '', body.lower()):
            continue

        found += 1
        print(f"\n=== PAGE {number} ===")
        print(body)

    if needle:
        print(f"\n{found} of {len(pages(path))} pages matched {needle!r}", file=sys.stderr)

    return 0


if __name__ == '__main__':
    sys.exit(main())
