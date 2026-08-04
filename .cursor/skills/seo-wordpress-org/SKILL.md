---
name: seo-wordpress-org
description: Develop, test, secure, version, and publish the Praison SEO / AISEO WordPress plugin (repo seo-wordpress, aiseo.php, slug seo-wordpress) to WordPress.org SVN and GitHub. Use when changing this plugin's PHP/JS, aiseo/v1 REST API, AJAX handlers, admin screens or post editor metabox; when testing changes against local WordPress; when bumping the version, editing readme.txt, cutting a release, deploying to SVN, or answering plugins@wordpress.org re-review requests.
---

# Praison SEO WordPress.org Workflow

End-to-end workflow for code changes, security fixes, and publishing to the WordPress.org Plugin Directory.

## Repository layout (critical)

| Branch | Version | Main file | REST API | Publish target |
|--------|---------|-----------|----------|----------------|
| `master` | 4.x | `seo-wordpress.php` | None | **A different plugin** (legacy "Praison SEO WordPress"). Has `release.sh` + `.github/workflows/deploy.yml` |
| `origin/main` / feature branches | 5.x | `aiseo.php` (git) | `aiseo/v1` | **Current WordPress.org trunk** |
| WordPress.org SVN | live | `seo-wordpress.php` | `aiseo/v1` | `https://plugins.svn.wordpress.org/seo-wordpress` |

**`master` is not an ancestor of `main`.** They are separate codebases that share a repo —
`master` has no `aiseo.php` and no `AISEO_Admin`. Never merge 5.x work into `master`, and
never use `release.sh` for a 5.x release: it greps `seo-wordpress.php`, tags from `master`,
and its workflow deploys the **4.x** plugin. `main` has no `.github/`, so pushing a `v5.x.y`
tag triggers no GitHub Action — the SVN publish is manual.

**Before editing:** confirm which branch matches production. Security issues in 5.x live on
`origin/main`, not on `master`.

**Naming mismatch:** Git uses `aiseo.php`; SVN trunk uses `seo-wordpress.php` (same plugin,
different filename). The two files are byte-identical **except the `Plugin Name:` header**
(`AISEO` in git, `Praison AI SEO` on SVN). When publishing, copy `aiseo.php` over
`trunk/seo-wordpress.php` and restore that one line.

**WordPress.org slug is `seo-wordpress`, not `aiseo`.** Looking the plugin up as `aiseo`
returns "Plugin not found" and will mislead you into thinking it is unpublished.

---

## Standard change workflow

```
1. Identify branch (5.x for current plugin)
2. Check what is ALREADY published (see below) before choosing a version
3. Implement fix/feature
4. Functional test on local WordPress -> testing-reference.md
5. Security + Plugin Check review
6. Bump version (plugin file + readme.txt Stable tag + changelog + upgrade notice)
7. Commit, push, fast-forward main, tag vX.Y.Z
8. Copy changed files to ~/seo-wordpress-svn/trunk/ (+ bump seo-wordpress.php there)
9. Install the staged trunk locally and re-run the tests
10. svn ci trunk → svn cp trunk tags/X.Y.Z → svn ci
11. Optional: gh release create with a .distignore-built ZIP
12. Reply to plugins@wordpress.org if re-review required
```

---

## Check what is published BEFORE bumping

The git version is frequently **already live**. Committing a change under an
already-published version ships different code under the same number.

```bash
curl -s "https://api.wordpress.org/plugins/info/1.0/seo-wordpress.json" \
  | python3 -c "import sys,json;d=json.load(sys.stdin);print(d['version'],d['tested'])"
svn ls https://plugins.svn.wordpress.org/seo-wordpress/tags/ | sort -V | tail -3
```

The next version is `live + 1` patch — **not** whatever `aiseo.php` currently says.

---

## Version management

**Single source of truth (5.x git):**
- `aiseo.php` line `Version:` and `define('AISEO_VERSION', ...)`

**SVN trunk:**
- `seo-wordpress.php` line `Version:` and `define('AISEO_VERSION', ...)`

**readme.txt (both git and SVN):**
- `Stable tag:` must match released version
- `Tested up to:` keep within 3 major WP versions of latest release
- Add `= X.Y.Z =` entry under `== Changelog ==`
- Add `== Upgrade Notice ==` for security releases

**Semver:**
- Patch/security: increment the patch from the **published** version (e.g. live `5.0.8` → `5.0.9`)
- Tag folder on SVN must match Stable tag exactly: Stable tag `5.0.9` → `tags/5.0.9`
- The git tag is `vX.Y.Z`; the SVN tag folder is `X.Y.Z` (no `v`)

---

## REST API security (aiseo/v1)

All routes register in `includes/class-aiseo-rest.php`.

**Never use:**
```php
'permission_callback' => '__return_true',
```

**Use the existing callbacks:**

| Callback | When |
|----------|------|
| `check_admin_permission` | Site settings, analytics, redirects, 404 monitor, webmaster verification, competitor/backlink summaries |
| `check_edit_post_permission` | Post-specific routes (`id` or `post_id` param), including `POST /permalink/optimize` |
| `check_edit_term_permission` | Taxonomy SEO routes with `taxonomy` + `term_id` |
| `check_permission` | General editor tools (requires login + `edit_posts`) |

**After changing permissions**, verify zero open callbacks:
```bash
grep -r "__return_true" includes/class-aiseo-rest.php
# Must return no matches
```

**Smoke test (anonymous must get 401/403):**
```bash
curl -s -o /dev/null -w "%{http_code}\n" "$SITE/wp-json/aiseo/v1/analytics"
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$SITE/wp-json/aiseo/v1/permalink/optimize" \
  -H "Content-Type: application/json" --data '{"post_id":1,"apply":1}'
```

---

## Repo-specific hazards

Each of these has already shipped a bug to WordPress.org. Check them when touching AJAX,
the metabox, or admin JavaScript.

### 1. Duplicate `wp_ajax_*` registrations across classes

`AISEO_Admin` and `AISEO_Metabox` are separate classes that have both registered the same
action names. WordPress runs every callback for an action, but the first one to call
`wp_send_json_*` / `wp_die()` terminates the request — so **the class that loads first
always wins and the other becomes dead code.** `AISEO_Admin` loads first (early AJAX
bootstrap in `aiseo.php`, before `init`).

When the two handlers validate different nonces, the losing entry point gets a hard 403.
This is what broke the metabox Focus Keyword and Analyze Content buttons.

**Rule:** one action name → one handler. Namespace per entry point
(`aiseo_metabox_*` for metabox actions) so each owns its own nonce.

```bash
# every client-side action must resolve to exactly one registered handler
grep -rn "add_action('wp_ajax_" --include='*.php' admin/ includes/ | sed "s/.*wp_ajax_//" | sort | uniq -d
```

### 2. `check_ajax_referer` is an ACTION, not a filter

```php
add_filter('check_ajax_referer', $cb, 10, 2);   // ← does NOTHING; return value discarded
```

Core calls `do_action('check_ajax_referer', $action, $result)`. A callback added as a
filter runs but cannot change the outcome. Code written to "bypass" nonces this way is
inert — the real bypass will be elsewhere (typically a manual `wp_verify_nonce()` whose
result is ignored). Do not trust the comment; trace the actual control flow.

### 3. Never bypass a nonce to fix a nonce failure

A handler that calls `wp_verify_nonce()` and then continues regardless is a CSRF hole.
If nonces fail, the cause is almost always (1) a handler collision as above, or (2) the
wrong nonce action localised into the script. Fix the cause. Use `aiseo_refresh_nonce`
for genuine expiry.

The admin JS depends on `check_ajax_referer`'s exact failure response — body `-1` with
HTTP 403 — to trigger its refresh-and-retry. Custom nonce failure responses break it.

### 4. Two copies of the metabox JavaScript

`includes/class-aiseo-metabox.php` once held a `get_metabox_script()` string that was
**never called**, while the file actually served was `js/aiseo-metabox.js`. Editing the
wrong one silently ships nothing. Before editing plugin JS, confirm what is enqueued:

```bash
grep -rn "wp_enqueue_script\|wp_add_inline_script" --include='*.php' includes/ admin/
curl -sk "https://wordpress.test/wp-content/plugins/aiseo-dev/js/<file>.js" | grep "action:"
```

### 5. Debug scaffolding reaching production

5.0.7 shipped with `error_log()` on the normal request path (noise on every front-end hit
and WP-CLI command), a logger that wrote nonces and full POST payloads to the error log,
and a `console.log` printing the admin nonce. Before release:

```bash
grep -rn "error_log" --include='*.php' aiseo.php admin/ includes/   # only AISEO_Helpers::log + export handlers
grep -rn "console\.log" --include='*.php' --include='*.js' admin/ js/
grep -rniE "bypass|TEMPORARY|DEBUG" --include='*.php' admin/ includes/
```

Gate any intentional logging behind `WP_DEBUG && AISEO_DEBUG`; never log on a normal path.

---

## Testing

Static checks do not catch broken action names or nonce regressions. Full harness —
local WP setup, authenticated AJAX testing, before/after baselines, Plugin Check
diffing — is in **[testing-reference.md](testing-reference.md)**.

Minimum before any release:

```bash
for f in $(find . -name '*.php' -not -path './.git/*'); do php -l "$f" >/dev/null || echo "LINT $f"; done
# activate working tree locally, then:
#  - wp plugin list + front-end request leave 0 AISEO lines in wp-content/debug.log
#  - AJAX handlers reject missing/invalid nonces with -1 + 403
#  - each entry point's nonce reaches its own handler
#  - wp-admin settings page and post editor load with no fatals
#  - repeat against the staged SVN trunk (different bootstrap filename)
```

---

## Pre-publish checklist

### Code
- [ ] No `__return_true` on REST routes
- [ ] `ABSPATH` check is first in every PHP file (before any `require`)
- [ ] Input sanitised, output escaped, nonces on forms/AJAX
- [ ] `$wpdb->prepare()` for SQL
- [ ] Scripts/styles enqueued (no inline JS/CSS)
- [ ] `php -l` passes on changed files

### readme.txt
- [ ] Max 5 tags
- [ ] `Stable tag` matches version **and** is ahead of the published version
- [ ] `Tested up to` matches the WP version you actually tested on
- [ ] Changelog entry added (`Security:` prefix for security fixes)
- [ ] `== Upgrade Notice ==` entry for security releases
- [ ] `== External Services ==` documents **every** outbound call, not just OpenAI

**Never let a section heading appear twice.** WordPress.org parses the **first** occurrence
and silently ignores everything under any later duplicate. readme.txt carried two
`== Changelog ==` headings for several releases; the first held only a stale `= 1.0.0 =`
entry, so every 5.x changelog entry was invisible on the plugin page. Fixed in 5.0.8 —
verify it stays fixed:

```bash
grep -c '^== Changelog ==' readme.txt      # must be 1
grep -n '^==' readme.txt | sort -k2 | uniq -df1   # any duplicated heading
```

Confirm what the directory actually renders after publishing:

```bash
curl -s "https://api.wordpress.org/plugins/info/1.0/seo-wordpress.json" \
  | python3 -c "import sys,json,re;print(re.sub('<[^>]+>','',json.load(sys.stdin)['sections']['changelog'])[:400])"
```

To enumerate what must be disclosed under External Services:

```bash
grep -rhoE "https?://[a-zA-Z0-9./_-]+" --include='*.php' includes/ admin/ aiseo.php | sort -u
grep -rn "wp_remote_post\|wp_remote_get" --include='*.php' includes/ admin/
```

Currently in scope: **OpenAI** (`api.openai.com`, required for AI features) and **Google
Analytics** (`googletagmanager.com/gtag/js`, opt-in, only when a GA4 ID is configured).
Competitor/backlink features fetch user-entered URLs. `ping_search_engines()` references
Google/Bing ping endpoints but is **dead code** — verify a call site exists before
documenting a service.

### Build exclusions (`.distignore`)
Exclude from SVN: `.git`, `.github`, `tests/`, `node_modules/`, `*.md` (except `readme.txt`), `.env`, `*.example`, dev docs.

---

## Git workflow

```bash
# Work on 5.x
git checkout -B fix/my-change origin/main

# After edits
git add <files>
git commit -m "Security: describe fix (X.Y.Z)."
git push -u origin fix/my-change
```

Releasing from the branch (5.x lands on `main`, never `master`):

```bash
git checkout main
git merge --ff-only fix/my-change     # should fast-forward; if not, rebase the branch
git push origin main
git tag -a vX.Y.Z -m "Release vX.Y.Z - short description"
git push origin vX.Y.Z                # no Action fires: main has no .github/
```

Optional GitHub release with a distributable ZIP (honours `.distignore`):

```bash
rsync -a --exclude-from=<(grep -vE '^\s*#|^\s*$|^!' .distignore) \
      --exclude='.git' --exclude='.svn' --exclude='.DS_Store' --exclude='.cursor' \
      ./ /tmp/dist/aiseo/
(cd /tmp/dist && zip -qr aiseo-X.Y.Z.zip aiseo)
gh release create vX.Y.Z /tmp/dist/aiseo-X.Y.Z.zip --title "Praison AI SEO vX.Y.Z" --latest --notes "..."
```

Verify the ZIP excludes `.git/`, `tests/`, `node_modules`, `.DS_Store`, `.env`, `*.md`.
The repo contains `.DS_Store` files — they must not reach the ZIP or SVN.

**Do not commit unless the user asks.** Do not push unless asked.

---

## SVN publish workflow

**Prerequisites:**
- SVN password (not WP login password): https://profiles.wordpress.org/me/profile/edit/group/3/?screen=svn-password
- Local checkout: `~/seo-wordpress-svn` (`svn co https://plugins.svn.wordpress.org/seo-wordpress`)

**Update local SVN:**
```bash
svn up ~/seo-wordpress-svn
```

**Copy fix to trunk** (adjust paths to changed files only when possible):
```bash
cp includes/class-aiseo-rest.php ~/seo-wordpress-svn/trunk/includes/
# Bump seo-wordpress.php version + readme.txt on SVN trunk
```

**Verify before commit:**
```bash
grep "Version:" ~/seo-wordpress-svn/trunk/seo-wordpress.php
grep "Stable tag:" ~/seo-wordpress-svn/trunk/readme.txt
grep -c "__return_true" ~/seo-wordpress-svn/trunk/includes/class-aiseo-rest.php  # must be 0
cd ~/seo-wordpress-svn && svn status trunk/
```

**Publish:**
```bash
cd ~/seo-wordpress-svn
svn cleanup
svn ci trunk/ -m "Release X.Y.Z: short description."
svn cp trunk tags/X.Y.Z
svn ci -m "Tagging version X.Y.Z"
```

**Verify remote:**
```bash
svn ls https://plugins.svn.wordpress.org/seo-wordpress/tags/ | grep X.Y.Z
```

For SVN locks, broken tags, and auth issues, see [svn-reference.md](svn-reference.md).

---

## Automated deploy (master / 4.x only — NOT for this plugin)

`release.sh` and `.github/workflows/deploy.yml` exist only on `master` and belong to the
legacy 4.x "Praison SEO WordPress" plugin. They read the version from `seo-wordpress.php`
and deploy that codebase. **Do not use them for a 5.x release.**

On `master` only:
```bash
# Bump Version in seo-wordpress.php, then:
./release.sh
```
This syncs readme, tags `vX.Y.Z`, pushes GitHub, and triggers `.github/workflows/deploy.yml` via `10up/action-wordpress-plugin-deploy`.

Requires GitHub secrets: `SVN_USERNAME`, `SVN_PASSWORD`.

For 5.x, publishing is the manual SVN flow above. SVN credentials are already cached in
`~/.subversion/auth/` for user `mervinpraison`, so `svn ci --non-interactive` works without
prompting (a prompt would hang a non-interactive session).

---

## WordPress.org re-review (security removal)

When plugins@wordpress.org removes a plugin for security:

1. Fix all reported issues + run Plugin Check
2. Increment version + update `Tested up to`
3. Commit to SVN trunk + create matching tag
4. Reply to the email (do not open a new thread):

```
Subject: Re: seo-wordpress — security patch submitted (vX.Y.Z)

Hello WordPress Plugins Team,

We have addressed [issue summary] in version X.Y.Z.
Changes: [bullet list]
SVN: committed to trunk and tagged tags/X.Y.Z.

Please re-review when convenient.

Thank you,
Mervin Praison
```

They perform a **full re-scan** — fix all Plugin Check findings before replying.

---

## Plugin Check

Install: https://wordpress.org/plugins/plugin-check/

```bash
wp plugin check seo-wordpress
# or from WP admin: Tools → Plugin Check
```

Run before every WordPress.org submission.

---

## Common mistakes

| Mistake | Fix |
|---------|-----|
| Editing `master` for 5.x REST bugs | Use `origin/main` or 5.x branch |
| Only updating `aiseo.php` in git | Also update `seo-wordpress.php` + readme on SVN |
| `svn cp` after failed partial tag | `svn cleanup && svn revert -R tags/X.Y.Z` then re-copy |
| Sharing SVN password in chat | Regenerate at profiles.wordpress.org |
| Leaving `__return_true` on GET routes | Match POST routes: admin or edit_post checks |
| Reusing the version already in `aiseo.php` | Query api.wordpress.org first; it is often already live |
| Looking the plugin up as slug `aiseo` | The slug is `seo-wordpress` |
| Running `release.sh` for a 5.x release | That publishes the 4.x plugin from `master` |
| Merging 5.x into `master` | `master` is a different plugin; 5.x lands on `main` |
| Checking WP-CLI stderr for `error_log` noise | `WP_DEBUG_LOG` sends it to `wp-content/debug.log` |
| Editing `get_metabox_script()` / inline JS strings | Confirm which asset is actually enqueued |
| Trusting Plugin Check absolute counts | Large pre-existing backlog; diff before vs after |
| Testing only the git tree | Also test the staged SVN trunk (different bootstrap filename) |
| Leaving the local WP on `aiseo-dev` | Restore the original active plugin set afterwards |

---

## Key files

| File | Purpose |
|------|---------|
| `includes/class-aiseo-rest.php` | All `aiseo/v1` REST routes + permission callbacks |
| `seo-wordpress.php` / `aiseo.php` | Version header + bootstrap (+ early AJAX bootstrap of `AISEO_Admin`) |
| `admin/class-aiseo-admin.php` | Admin screens + most `wp_ajax_aiseo_*` handlers (`aiseo_admin_nonce`) |
| `includes/class-aiseo-metabox.php` | Post editor metabox + `wp_ajax_aiseo_metabox_*` handlers (`aiseo_metabox_nonce`) |
| `js/aiseo-metabox.js` | The metabox JS actually enqueued — edit this, not any inline copy |
| `admin/views/*.php` | Admin tab markup **with inline jQuery** that calls the AJAX actions |
| `readme.txt` | WordPress.org listing metadata |
| `.distignore` | Files excluded from deploy archive |
| `release.sh` | Git tag + GitHub release (**master / 4.x only**) |
| `.github/workflows/deploy.yml` | SVN deploy on tag push (**master / 4.x only**) |

---

## Additional resources

- Functional testing harness: [testing-reference.md](testing-reference.md)
- SVN troubleshooting: [svn-reference.md](svn-reference.md)
- Security patterns + coding standards: [security-reference.md](security-reference.md)
- Agent release notes: [AGENTS.md](../../AGENTS.md)
