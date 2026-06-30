---
name: seo-wordpress-org
description: Develop, secure, version, and publish the Praison SEO / AISEO WordPress plugin to WordPress.org SVN. Use when changing seo-wordpress, aiseo/v1 REST API, WordPress.org submission, SVN deploy, security fixes, readme.txt, plugin version bumps, or plugins@wordpress.org re-review requests.
---

# Praison SEO WordPress.org Workflow

End-to-end workflow for code changes, security fixes, and publishing to the WordPress.org Plugin Directory.

## Repository layout (critical)

| Branch | Version | Main file | REST API | Publish target |
|--------|---------|-----------|----------|----------------|
| `master` | 4.x | `seo-wordpress.php` | None | Legacy; has `release.sh` + `.github/workflows/deploy.yml` |
| `origin/main` / feature branches | 5.x | `aiseo.php` (git) | `aiseo/v1` | **Current WordPress.org trunk** |
| WordPress.org SVN | live | `seo-wordpress.php` | `aiseo/v1` | `https://plugins.svn.wordpress.org/seo-wordpress` |

**Before editing:** confirm which branch matches production. Security issues in 5.x live in `includes/class-aiseo-rest.php` on `origin/main`, not on `master`.

**Naming mismatch:** Git uses `aiseo.php`; SVN trunk uses `seo-wordpress.php` (same plugin, different filename). When publishing to SVN, bump version in `seo-wordpress.php`, not only `aiseo.php`.

---

## Standard change workflow

```
1. Identify branch (5.x for current plugin)
2. Implement fix/feature
3. Security + Plugin Check review
4. Bump version (plugin file + readme.txt Stable tag + changelog)
5. Commit + push to GitHub
6. Copy changed files to ~/seo-wordpress-svn/trunk/
7. svn ci trunk → svn cp trunk tags/X.Y.Z → svn ci
8. Reply to plugins@wordpress.org if re-review required
```

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
- Patch/security: `5.0.6 → 5.0.7`
- Tag folder on SVN must match Stable tag exactly: `tags/5.0.7`

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
- [ ] `Stable tag` matches version
- [ ] `Tested up to` current
- [ ] Changelog entry added
- [ ] `== External Services ==` documents OpenAI (required for AI features)

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

## Automated deploy (master / 4.x only)

On `master`:
```bash
# Bump Version in seo-wordpress.php, then:
./release.sh
```
This syncs readme, tags `vX.Y.Z`, pushes GitHub, and triggers `.github/workflows/deploy.yml` via `10up/action-wordpress-plugin-deploy`.

Requires GitHub secrets: `SVN_USERNAME`, `SVN_PASSWORD`.

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

---

## Key files

| File | Purpose |
|------|---------|
| `includes/class-aiseo-rest.php` | All `aiseo/v1` REST routes + permission callbacks |
| `seo-wordpress.php` / `aiseo.php` | Version header + bootstrap |
| `readme.txt` | WordPress.org listing metadata |
| `.distignore` | Files excluded from deploy archive |
| `release.sh` | Git tag + GitHub release (master only) |
| `.github/workflows/deploy.yml` | SVN deploy on tag push (master only) |

---

## Additional resources

- SVN troubleshooting: [svn-reference.md](svn-reference.md)
- Security patterns + coding standards: [security-reference.md](security-reference.md)
- Agent release notes: [AGENTS.md](../../AGENTS.md)
