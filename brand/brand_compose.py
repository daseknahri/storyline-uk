#!/usr/bin/env python3
"""Storyline UK brand pack: icon set, logo lockups, OG card + theme assets from generated art.

Type is composited here (Anton), never baked by an image model. Run from the repo root:
    python3 brand/brand_compose.py
Reads the two raw generations in this folder and writes the pack here, the child theme's
assets/brand/ set, and content/images/."""
from PIL import Image, ImageDraw, ImageFont, ImageOps
import os

BASE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.dirname(BASE)
FONT = os.path.expanduser('~/.fonts/Anton-Regular.ttf')
MARK = f'{BASE}/media-generation-storyline-uk-mark-0-d1dde022-6809-48fa-9fd1-7d833ea8ba24.webp'
HERO = f'{BASE}/media-generation-storyline-uk-hero-0-4f8f6fcd-8775-454a-ad07-4e164dea4f7a.webp'
THEME_BRAND = f'{REPO}/wp-content/themes/storyline-uk/assets/brand'
CONTENT_IMG = f'{REPO}/content/images'
os.makedirs(THEME_BRAND, exist_ok=True)
os.makedirs(CONTENT_IMG, exist_ok=True)

CREAM = (244, 241, 234)
INK = (16, 18, 24)
RED = (215, 38, 61)
RED_BRIGHT = (240, 86, 107)
NOIR = (11, 13, 18)

# --- icon: cut the circular badge out of the square art ---
art = Image.open(MARK).convert('RGB')
S = min(art.size)
art = art.crop(((art.width - S) // 2, (art.height - S) // 2, (art.width + S) // 2, (art.height + S) // 2)).resize((1024, 1024), Image.LANCZOS)
mask = Image.new('L', (1024, 1024), 0)
md = ImageDraw.Draw(mask)
md.ellipse([6, 6, 1018, 1018], fill=255)
icon = Image.new('RGBA', (1024, 1024), (0, 0, 0, 0))
icon.paste(art, (0, 0), mask)
icon.resize((512, 512), Image.LANCZOS).save(f'{BASE}/icon-512.png')
icon.resize((180, 180), Image.LANCZOS).save(f'{BASE}/apple-touch-180.png')
icon.resize((32, 32), Image.LANCZOS).save(f'{BASE}/favicon-32.png')
print('icons done')

# --- logo lockups (horizontal: badge + wordmark) ---
def lockup(text_main, uk_colour, path):
    H = 300
    emb = icon.resize((H, H), Image.LANCZOS)
    f_title = ImageFont.truetype(FONT, 118)
    f_tag = ImageFont.truetype(FONT, 40)
    tmp = ImageDraw.Draw(Image.new('RGBA', (10, 10)))
    parts = [('STORYLINE ', text_main), ('UK', uk_colour)]
    tw = sum(tmp.textlength(t, font=f_title) for t, _c in parts)
    tag = 'M O V I E S   ·   T V   ·   S T R E A M I N G   ·   C E L E B R I T Y'
    gw = tmp.textlength(tag, font=f_tag)
    W = H + 40 + int(max(tw, gw)) + 20
    img = Image.new('RGBA', (W, H), (0, 0, 0, 0))
    img.paste(emb, (0, 0), emb)
    d = ImageDraw.Draw(img)
    x = H + 40
    y = 58
    for t, c in parts:
        d.text((x, y), t, font=f_title, fill=c)
        x += tmp.textlength(t, font=f_title)
    d.text((H + 44, y + 148), tag, font=f_tag, fill=text_main)
    img.save(path)
    print('lockup', path)

lockup(INK, RED, f'{BASE}/logo.png')                # for light backgrounds
lockup(CREAM, RED_BRIGHT, f'{BASE}/logo-light.png')  # for dark backgrounds

# --- OG / social card 1200x630 from the hero ---
hero = Image.open(HERO).convert('RGB')
og = ImageOps.fit(hero, (1200, 630), Image.LANCZOS)
grad = Image.new('L', (1, 630))
grad.putdata([int(170 * max(0.0, (y / 630 - 0.30) / 0.70)) for y in range(630)])
og = Image.composite(Image.new('RGB', (1200, 630), NOIR), og, grad.resize((1200, 630)))
d = ImageDraw.Draw(og)
emb = icon.resize((150, 150), Image.LANCZOS)
og.paste(emb, (48, 630 - 150 - 42), emb)
f = ImageFont.truetype(FONT, 92)
x = 224
d.text((x, 630 - 178), 'STORYLINE ', font=f, fill=CREAM)
x += d.textlength('STORYLINE ', font=f)
d.text((x, 630 - 178), 'UK', font=f, fill=RED_BRIGHT)
f2 = ImageFont.truetype(FONT, 34)
d.text((228, 630 - 72), 'M O V I E S   ·   T V   ·   S T R E A M I N G   ·   C E L E B R I T Y', font=f2, fill=CREAM)
og.save(f'{BASE}/og.jpg', quality=90)
print('og done')

# hero master for the site (1920w)
ImageOps.fit(hero, (1920, 1080), Image.LANCZOS).save(f'{BASE}/hero-1920.jpg', quality=88)
print('hero done')

# --- child theme assets/brand/ set (names the seed + theme read) ---
icon.resize((512, 512), Image.LANCZOS).save(f'{THEME_BRAND}/storyline-uk-icon-512.png')
for src, dst in [('logo.png', 'storyline-uk-logo.png'), ('logo-light.png', 'storyline-uk-logo-light.png')]:
    im = Image.open(f'{BASE}/{src}')
    im.save(f'{THEME_BRAND}/{dst}')
    im.save(f'{THEME_BRAND}/{dst.replace(".png", ".webp")}', quality=88)
Image.open(f'{BASE}/hero-1920.jpg').save(f'{THEME_BRAND}/hero-1920.jpg', quality=88)
for w in (640, 768, 960, 1280):
    ImageOps.fit(hero, (w, int(w * 9 / 16)), Image.LANCZOS).save(f'{THEME_BRAND}/hero-{w}.webp', quality=82)
Image.open(f'{BASE}/og.jpg').save(f'{THEME_BRAND}/og.jpg', quality=90)
icon.resize((32, 32), Image.LANCZOS).save(f'{THEME_BRAND}/favicon-32.png')
icon.resize((180, 180), Image.LANCZOS).save(f'{THEME_BRAND}/apple-touch-180.png')
print('theme brand assets done')

# --- content/images/ set (site-profile.json assets) ---
icon.resize((512, 512), Image.LANCZOS).save(f'{CONTENT_IMG}/storyline-uk-icon.png')
Image.open(f'{BASE}/logo.png').save(f'{CONTENT_IMG}/storyline-uk-logo.png')
Image.open(f'{BASE}/og.jpg').save(f'{CONTENT_IMG}/storyline-uk-og.jpg', quality=90)
Image.open(f'{BASE}/hero-1920.jpg').save(f'{CONTENT_IMG}/storyline-uk-hero.jpg', quality=88)
icon.resize((32, 32), Image.LANCZOS).save(f'{CONTENT_IMG}/favicon-32.png')
icon.resize((180, 180), Image.LANCZOS).save(f'{CONTENT_IMG}/apple-touch-180.png')
print('content images done')

# --- theme screenshot (1200x900) ---
ImageOps.fit(hero, (1200, 900), Image.LANCZOS).save(f'{REPO}/wp-content/themes/storyline-uk/screenshot.jpg', quality=85)
print('screenshot done')
