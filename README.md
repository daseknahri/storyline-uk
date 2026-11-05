# Storyline UK — site repo

Self-seeding Docker WordPress for **Storyline UK**, a UK entertainment news site
(MovieWeb-pattern): movie news, TV news, streaming, celebrity news, features & lists,
trailers & videos. Style A per the plgin `docs/sites/NEW-SITE-PLAYBOOK.md` (the
Dales & Cobbles build is the template). Planned domain: `storylineuk.getemoji.site`
(free subdomain — not yet registered/deployed; launch steps are owner-gated, see
SITE-CARD-DRAFT.md).

## Stack

- WordPress (php8.3-apache) + MariaDB 11, self-seeding on first start
  (`docker/site-init.sh` syncs the vendored code, installs WP once with en_GB,
  runs `seed/seed.php`, regenerates card image sizes once).
- Plugin: **build-v9 9.48.0** ("Automation Hamri", vendored from plgin `main`,
  unedited) in `wp-content/plugins/automation-hamri`.
- Theme: **viral-reader 1.9.37** (vendored from plgin `main`, unedited) +
  **storyline-uk 1.0.0** child theme: cinematic dark skin, Storyline UK tokens,
  a multi-rail `front-page.php` (hero → Popular → Trending → Hot on Streaming →
  Features → topics → Latest), two-axis taxonomy support.
- Mu-plugin `uk-page-cache.php`: full-page disk cache (30-min TTL).
- Brand: `brand/` (BRAND.md + generated mark/hero/OG + `brand_compose.py`).
  The ONE config place for brand/site facts is `content/site-profile.json`
  (the seed reads it; `seed/seed.php` holds matching fallbacks).

## Content structure (seeded, no posts)

- 6 pillar categories — publish by **numeric ID**: Movie News **2**, TV News **3**,
  Streaming **4**, Celebrity News **5**, Features & Lists **6**, Trailers & Videos **7**
  (1 = General). Names with `&` duplicate if published by name — always use IDs.
- 18 tag hubs seeded empty (platforms, franchises, + `popular`/`trending` curation
  tags the homepage rails read).
- 7 trust pages: About, Contact, Privacy Policy, Terms, Cookie Policy,
  Editorial Policy, Corrections.
- en_GB, Europe/London, permalinks `/%postname%/`, comments open, ads.txt = the
  getemoji-family publisher line, AdSense stage 1 (`ADSENSE_ENABLE=0`), GA off.

## Local run

```powershell
copy .env.example .env   # then fill fresh random values (never reuse a sibling's)
docker compose -f docker-compose.yml -f docker-compose.local.yml up -d --build
# site: http://localhost:8097  (wp-admin: /wp-admin, user from .env)
```

Local QA passed 2026-11-06 (see SITE-CARD-DRAFT.md). Content arrives later as
Bulk-ZIP bundles in `data/bundles/` (imported once by the seed) or via wp-admin.

## Production (owner-gated, not yet done)

GitHub repo + push, Hostinger DNS, Coolify project/app, first deploy — the exact
remaining steps are listed in SITE-CARD-DRAFT.md. A Coolify redeploy applies CODE
only; content/DB changes need a reseed (bump `UK_SEED_VERSION`).
