# Site card (DRAFT) — Storyline UK

Status: **scaffold built + locally QA-passed 2026-11-06. NOT launched.** No GitHub repo,
no DNS, no Coolify project, no deploy — those steps are owner-gated (list at the bottom).
This draft lives in the site repo; the plgin-side filing (a `docs/sites/storyline-uk.md`
card + REGISTRY row in the plgin repo, per the NEW-SITE-PLAYBOOK) is still to be done as
its own claimed backlog item in the launch phase — it was deliberately NOT created from
this scaffold task.

## Identity

- **Brand:** Storyline UK — tagline "Movies · TV · Streaming · Celebrity"
- **What:** UK entertainment news (MovieWeb-pattern, per the REA spec): movie news, TV
  news, streaming charts/launches, celebrity news, features & lists, trailers & videos.
  UK-first: en_GB, Europe/London, UK release dates / UK streaming availability /
  UK broadcast times, British English.
- **Planned domain:** `storylineuk.getemoji.site` (free getemoji.site subdomain, Dales
  pattern). Nothing registered or configured yet.
- **Byline identity:** "Storyline UK Team" (editorial team identity, no invented persona).
- **Contact email:** daseknahri@gmail.com (admin + site email).
- **Brand pack:** `brand/` in this repo — BRAND.md, original generated clapper/play mark,
  cinema hero, OG card, `brand_compose.py`. Palette: cinema noir `#0B0D12`, carpet red
  `#D7263D` (bright `#FF5A6E`), premiere gold `#E8A33D`, cream ink `#F4F1EA`. Anton
  headlines. All art original generated imagery (no studio stills/frames/likenesses).

## Repo & stack

- **Repo:** `C:\Users\Yassi\Desktop\plgin\uk-story-site` — own git repo, branch `main`,
  NO remote. Commits: scaffold / vendor / child theme / brand+profile / theme fix / docs.
- **Pattern:** Style A self-seeding Docker (germ pattern, cloned from dales-cobbles):
  `docker/site-init.sh` waits for DB → syncs vendored code → `wp core install` once
  (en_GB, title "Storyline UK") → activates theme + plugin + Google Site Kit → runs
  `seed/seed.php` → one-time card-size regeneration. A redeploy applies CODE only;
  content changes need a reseed (bump `UK_SEED_VERSION`, currently 1).
- **Versions (vendored unedited from plgin `main` @ build time):** build-v9 **9.48.0**
  (`wp-content/plugins/automation-hamri`), viral-reader **1.9.37** (parent),
  storyline-uk child theme **1.0.0**, mu-plugin `uk-page-cache.php` (full-page disk
  cache, `cache/uk-page`, 30-min TTL).
- **Config:** brand/site facts live in ONE place — `content/site-profile.json`
  (seed reads it; `seed/seed.php` fallbacks mirror it). Secrets in gitignored `.env`
  (fresh random values generated locally at build; production values will be
  Coolify-generated and will differ — the Dales lesson).
- **Compose:** production `docker-compose.yml` (`SITE_URL` default
  `https://storylineuk.getemoji.site`); local override `docker-compose.local.yml`
  on port **8097**.

## Content structure (seeded 2026-11-06, local)

- **Categories (publish by numeric ID — names with `&` duplicate via `&amp;`):**
  1 General · **2 Movie News** (default) · **3 TV News** · **4 Streaming** ·
  **5 Celebrity News** · **6 Features & Lists** · **7 Trailers & Videos**.
- **Tag hubs (axis 2, seeded empty — they earn archives by being filed on stories):**
  platforms — netflix, prime-video, disney-plus, apple-tv-plus, paramount-plus, now-tv,
  bbc-iplayer, itvx; franchises — marvel, dc, star-wars, star-trek, doctor-who,
  harry-potter, lord-of-the-rings, james-bond; curation — **popular**, **trending**
  (the homepage Popular/Trending rails read these two).
- **Homepage (child `front-page.php`):** hero (newest story; brand hero while zero
  posts) → Popular rail (tag) → Trending rail (tag, numbered rows) → Hot on Streaming
  (category) → Features rail (category) → pillar topic band → Latest grid + pagination.
  Rails render only when they have posts.
- **Trust pages (7):** about, contact, privacy-policy (registered as the WP privacy
  page), terms, cookie-policy, editorial-policy, corrections. Footer menu = trust
  pages; Primary menu = the 6 pillars.
- **Settings:** en_GB, Europe/London, date `j F Y`, time `H:i`, week starts Monday,
  12 posts/page, comments open / pings closed, permalinks `/%postname%/`,
  theme mods `vr_byline_author=true`, `vr_noun=article`, skin = cinematic (child filter).
- **Posts: none.** Nothing invented; content arrives via Bulk-ZIP bundles
  (`data/bundles/`, imported once by the seed) or wp-admin, following the network
  content rules (own words from official sources + YouTube embeds; own generated
  images only — see the entertainment IMAGE-PLAYBOOK / FB-STUDIO docs).

## Monetisation

- AdSense getemoji-family publisher `pub-6869205417923902`, **stage 1**: ads.txt served
  exactly (`google.com, pub-6869205417923902, DIRECT, f08c47fec0942fa0`),
  `ADSENSE_ENABLE=0`, GA off, Histats off. Stage 2 (auto-ads) only after the site is
  added + approved in AdSense and Privacy & Messaging (CMP) is set — owner actions.

## Local QA (2026-11-06, docker compose on port 8097) — PASS

- Home, all 6 category archives, all 7 trust pages: **200**.
- `/ads.txt` byte-exact publisher line. Homepage JSON-LD present and parses.
  `lang="en-GB"`, `vr-skin-cinematic` body class, brand hero + intro strip render,
  `#explore` anchor resolves.
- `wp theme list`: storyline-uk 1.0.0 **active**, viral-reader 1.9.37 parent.
- Category IDs and 18 hub tags as listed above; timezone Europe/London; WPLANG en_GB;
  0 posts; 7 pages with the seeded slugs.
- `php -l` clean on seed.php, child functions/header/front-page, mu-plugin.
  Seed log clean ("[seed] site seeded", "[site-init] seed done"); the only warnings on
  reboot are wp-cli "already active" idempotency notices.
- Build note: `.env` salts must not contain a literal `$` (Docker Compose interpolates
  and mangles them) — the generated `.env` uses a `$`-free charset.

## Remaining launch steps (ALL owner-gated — none executed)

1. Owner review of this scaffold + brand pack.
2. GitHub: create `daseknahri/storyline-uk` (name TBC), add remote, push (desktop-session
   scheduled-task method — git credentials are not reachable from SSH).
3. Hostinger DNS: A record `storylineuk` → `85.31.239.111` in the getemoji.site zone.
4. Coolify: project + compose app from the repo, domain
   `https://storylineuk.getemoji.site`, env per `.env.example` (Coolify generates its
   own SERVICE_* secrets — afterwards sync the local `.env` note: local values serve
   the local stack only).
5. Deploy (owner click) + live verification (the Dales checklist: 200s, en-GB, JSON-LD,
   menus, ads.txt, HTTP→HTTPS, seed log; allow ~60s after "Running" for the seed).
6. AdSense: Add site + request review (needs content first — owner's go for the
   submission itself), then stage 2 + Privacy & Messaging CMP.
7. Google Search Console property + sitemap submission.
8. SMTP for WordPress mail (the Dales lesson: no transport = no password resets).
9. plgin-side filing: site card in plgin `docs/sites/` + REGISTRY row (own backlog item).
10. Decide: Facebook Page for Storyline UK (distribution follows the Dales model —
    FB composite cards generated per story, FB-STUDIO pattern).
