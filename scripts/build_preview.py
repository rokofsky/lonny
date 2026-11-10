#!/usr/bin/env python3
"""Export public-facing PHP routes to a *static, nonfunctional* GitHub Pages demo.

Requires Python 3, PHP 8.1+, and beautifulsoup4 (pip install beautifulsoup4).
Run from the repository root: python scripts/build_preview.py

The script renders against isolated default data in a temporary directory.
It does not open production visitor submissions, photos, or administrator details.
No PHP source, private config, or persistent data is copied into docs/.
"""
from __future__ import annotations

import shutil
import subprocess
import tempfile
from pathlib import Path
from urllib.parse import parse_qs, urlparse

from bs4 import BeautifulSoup

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / 'docs'
PAGES = ('home', 'about', 'workshops', 'watch', 'experiment', 'ask', 'playground', 'contact', 'privacy')


def output_name(page: str) -> str:
    return 'index.html' if page == 'home' else f'{page}.html'


def render(page: str, index: Path) -> str:
    # No incoming HTTP requests, user data, sessions or real installation secrets.
    # JSON-escaped strings are safe to embed as PHP string literals.
    import json
    code = '$_SERVER["REQUEST_METHOD"]="GET";$_SERVER["REMOTE_ADDR"]="127.0.0.1";$_GET["page"]=' + json.dumps(page) + ';require ' + json.dumps(str(index)) + ';'
    proc = subprocess.run(['php', '-r', code], text=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=False, timeout=20)
    if proc.returncode or '<!doctype html>' not in proc.stdout.lower():
        raise RuntimeError(f'Failed to export {page}: {proc.stderr[:500]}')
    return proc.stdout


def build() -> None:
    if OUT.exists():
        shutil.rmtree(OUT)
    OUT.mkdir()
    (OUT / 'assets').mkdir()
    with tempfile.TemporaryDirectory(prefix='lonny-preview-') as temp:
        tmp = Path(temp)
        (tmp / 'app' / 'storage').mkdir(parents=True)
        (tmp / 'public').mkdir()
        for name in ('core.php', 'admin_view.php'):
            shutil.copy2(ROOT / 'app' / name, tmp / 'app' / name)
        shutil.copy2(ROOT / 'public' / 'index.php', tmp / 'public' / 'index.php')
        (tmp / 'app' / 'private.php').write_text('<?php return ["setup_key"=>"static-preview-not-a-secret"];', encoding='utf-8')
        index = tmp / 'public' / 'index.php'
        for page in PAGES:
            soup = BeautifulSoup(render(page, index), 'html.parser')
            if soup.head:
                meta = soup.new_tag('meta', attrs={'name': 'robots', 'content': 'noindex, nofollow, noarchive'})
                soup.head.append(meta)
                # The static public page is a visual demo only, not the production service.
                description = soup.find('meta', attrs={'name': 'description'})
                if description:
                    description['content'] = 'A nonfunctional visual preview of Lonny Gold’s redesigned learning website.'
                extra_css = soup.new_tag('link', rel='stylesheet', href='assets/preview.css')
                soup.head.append(extra_css)
            bar = soup.new_tag('div', attrs={'class': 'preview-bar', 'id': 'preview-banner', 'role': 'status'})
            strong = soup.new_tag('strong'); strong.string = 'DESIGN PREVIEW'
            span = soup.new_tag('span'); span.string = 'Explore the layout and the learning activity. Forms and booking are inactive.'
            bar.extend([strong, span])
            soup.body.insert(0, bar)
            for link in list(soup.find_all('a', href=True)):
                url = link.get('href', '')
                if not url.startswith('?'):
                    continue
                query = parse_qs(urlparse(url).query)
                destination = query.get('page', ['home'])[0]
                if destination in PAGES:
                    link['href'] = output_name(destination)
                else:
                    # Remove admin/setup/private portal links from public snapshot.
                    link.decompose()
            for form in list(soup.find_all('form')):
                form.attrs.pop('action', None)
                form.attrs.pop('method', None)
                form['data-preview-only'] = 'true'
                for node in form.find_all(('input', 'textarea', 'select', 'button')):
                    if node.name == 'input' and node.get('type') == 'hidden':
                        node.decompose()
                        continue
                    node['disabled'] = ''
                    node.attrs.pop('name', None)
                    node.attrs.pop('required', None)
                note = soup.new_tag('p', attrs={'class': 'preview-form-note'})
                note.string = 'Preview only — submissions are disabled. No personal information is collected.'
                form.insert(0, note)
            for script in soup.find_all('script', src=True):
                if script['src'].startswith('assets/'):
                    script['src'] = script['src']
            # No PHP source or inline setup/admin data is exported.
            markup = '<!doctype html>\n' + str(soup)
            outfile = OUT / output_name(page)
            outfile.write_text(markup, encoding='utf-8')
            print(f'Wrote {outfile.relative_to(ROOT)} ({len(markup):,} chars)')
    for fname in ('style.css', 'editorial.css'):
        shutil.copy2(ROOT / 'public' / 'assets' / fname, OUT / 'assets' / fname)
    js = (ROOT / 'public' / 'assets' / 'site.js').read_text(encoding='utf-8')
    js = js.replace('?page=playground', 'playground.html')
    (OUT / 'assets' / 'site.js').write_text(js, encoding='utf-8')
    (OUT / 'assets' / 'preview.css').write_text('''
.preview-bar { background: #244D67; color: #fff; display: flex; gap: 1rem; justify-content: center; align-items: center; flex-wrap: wrap; padding: .7rem 1rem; font: 500 .82rem/1.5 Inter, sans-serif; text-align: center; }
.preview-bar strong { color: #D3E5DA; font-size: .72rem; letter-spacing: .15em; }
.preview-form-note { color: #285648; border-left: 3px solid #5F9192; background: #EDF4F0; padding: .85rem 1rem; font-size: .88rem; border-radius: .3rem; }
form[data-preview-only] button[disabled] { opacity: .58; cursor: not-allowed; }
form[data-preview-only] :is(input, textarea, select)[disabled] { cursor: not-allowed; }
@media(max-width:600px) { .preview-bar { gap: .25rem .75rem; font-size: .72rem; } }
''', encoding='utf-8')
    (OUT / '.nojekyll').write_text('', encoding='utf-8')
    (OUT / 'README.md').write_text('''# Visual preview only\n\nThese files are a **static screenshot-like export** of the V2 PHP website. They are intended for GitHub Pages and do not provide a working CMS, visitor forms, moderation, or bookings. The Monthly Experiment remains interactive in the browser.\n\nTo publish: go to repository **Settings → Pages → Build and deployment → Deploy from a branch → main → /docs → Save**.\n\nPreview URL, once enabled: https://rokofsky.github.io/lonny/\n\nTo regenerate after changes to PHP/CSS: `pip install beautifulsoup4`, then `python scripts/build_preview.py`. Always review the generated HTML before making it public.\n\n**Never use this static preview as Lonny's production website.** Deploy the PHP application separately with a properly protected CMS and complete privacy notice.\n''', encoding='utf-8')
    # Security guardrails. Never put any PHP or private data into GitHub Pages.
    exported = [p.relative_to(OUT).as_posix() for p in OUT.rglob('*') if p.is_file()]
    forbidden = [p for p in exported if p.endswith(('.php', '.json', '.lock', '.env')) or 'private' in p or 'storage' in p or 'SETUP_KEY' in p]
    if forbidden:
        raise RuntimeError(f'Forbidden file(s) exported: {forbidden}')
    for page in PAGES:
        contents = (OUT / output_name(page)).read_text(encoding='utf-8')
        if 'csrf' in contents or '?page=' in contents or '<form method=' in contents:
            raise RuntimeError('Found live-form token/route in ' + page)
    print('Static export validated:', len(exported), 'files; no PHP or private data')


if __name__ == '__main__':
    build()