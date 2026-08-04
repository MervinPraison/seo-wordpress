# Testing Reference — seo-wordpress / aiseo

How to functionally test this plugin before publishing. `php -l` and Plugin Check are
static only — they will not catch a broken AJAX action name or a nonce regression.

## Local test environment

| Item | Value |
|------|-------|
| WordPress root | `~/Sites/localhost/wordpress` |
| Version | 6.9 (Laravel Valet, https://wordpress.test) |
| Admin user | `praison` (ID 1) |
| Installed copies | `aiseo` (active), plus `aiseo-dist` / `aiseo-test` (stale, ignore) |
| Debug log | `wp-content/debug.log` |

The installed `aiseo` copy is **not** a symlink to the git repo and is usually an older
build. To test your working tree, symlink it in as a separate plugin:

```bash
PLUGINS=~/Sites/localhost/wordpress/wp-content/plugins
ln -sfn /Users/praison/seo-wordpress "$PLUGINS/aiseo-dev"
cd ~/Sites/localhost/wordpress
wp plugin deactivate aiseo && wp plugin activate aiseo-dev
```

**Only one copy may be active at a time** — two active copies fatal on class
redeclaration. Save and restore state around testing:

```bash
wp plugin list --status=active --field=name > /tmp/active-before.txt
# ... test ...
wp plugin deactivate aiseo-dev && wp plugin activate aiseo
rm -f "$PLUGINS/aiseo-dev"
wp plugin list --status=active --field=name | sort | diff /tmp/active-before.txt -
```

The activation hook is idempotent (`add_option` only when missing, `dbDelta`), so
activating/deactivating repeatedly is safe.

## error_log goes to debug.log, not stderr

`wp-config.php` sets `WP_DEBUG_LOG = true`, so WordPress redirects `error_log()` to
`wp-content/debug.log`. Checking WP-CLI stderr for log noise gives a **false negative**.

```bash
cd ~/Sites/localhost/wordpress
: > wp-content/debug.log            # truncate to isolate the run
wp plugin list >/dev/null 2>&1
curl -sk -o /dev/null https://wordpress.test/
grep -c AISEO wp-content/debug.log  # must be 0
```

On production (mer.vin) the same calls land in the Apache/PHP-FPM error log instead.

## Before/after baseline — prove the test discriminates

A test that passes on broken code proves nothing. Stage the pre-fix tree as a second
plugin and run the same suite against both:

```bash
PLUGINS=~/Sites/localhost/wordpress/wp-content/plugins
mkdir -p "$PLUGINS/aiseo-before"
git archive HEAD | tar -x -C "$PLUGINS/aiseo-before"   # pre-fix, without touching your tree
```

Expect the suite to fail on `aiseo-before` and pass on `aiseo-dev`.

## Authenticated session for AJAX / wp-admin testing

Nonces are bound to the session token in the logged-in cookie, so the cookie and the
nonce must be generated together. `wp-admin` over HTTPS additionally requires the
**secure_auth** cookie — the logged-in cookie alone returns a 302 to the login page.

```php
<?php // gen-auth.php — run with: wp eval-file gen-auth.php --url=https://wordpress.test
$user_id = get_users(['role' => 'administrator', 'number' => 1])[0]->ID;
$expiration = time() + 3600;
$token = WP_Session_Tokens::get_instance($user_id)->create($expiration);

$logged_in = wp_generate_auth_cookie($user_id, $expiration, 'logged_in', $token);
$auth_name = is_ssl() ? SECURE_AUTH_COOKIE : AUTH_COOKIE;
$auth      = wp_generate_auth_cookie($user_id, $expiration, is_ssl() ? 'secure_auth' : 'auth', $token);

$_COOKIE[LOGGED_IN_COOKIE] = $logged_in;   // wp_create_nonce() reads the token from here
wp_set_current_user($user_id);

echo json_encode([
    'cookie_header' => LOGGED_IN_COOKIE . "=$logged_in; $auth_name=$auth",
    'admin_nonce'   => wp_create_nonce('aiseo_admin_nonce'),
    'metabox_nonce' => wp_create_nonce('aiseo_metabox_nonce'),
]), "\n";
```

Then drive `admin-ajax.php` directly:

```bash
curl -sk -b "$COOKIE_HEADER" -w '|HTTP:%{http_code}' \
  --data-urlencode "action=aiseo_generate_title" \
  --data-urlencode "nonce=$ADMIN_NONCE" \
  --data-urlencode "post_id=0" \
  https://wordpress.test/wp-admin/admin-ajax.php
```

## Assertions that matter

| Case | Expected |
|------|----------|
| Handler with **no** nonce | `-1` + HTTP 403 |
| Handler with **invalid** nonce | `-1` + HTTP 403 |
| Handler with valid nonce, `post_id` omitted | JSON error, **no** API call |
| Metabox action + metabox nonce | reaches metabox handler |
| Metabox action + admin nonce | `-1` + HTTP 403 |
| Admin action + metabox nonce | `-1` + HTTP 403 |
| No cookie | `0` (no `wp_ajax_nopriv_` handler) |

**Omit `post_id` to stay free.** Handlers validate the post ID before calling OpenAI, so
`post_id` omitted exercises nonce + capability without spending API credit. With a real
`post_id` and no configured key you get `"OpenAI API key not configured"` — which still
proves the full path was traversed.

**Identify *which* handler answered by its error string.** `AISEO_Admin` returns
`"Post ID is required"`; `AISEO_Metabox` returns `"Invalid post ID"`. That difference is
how you detect a handler collision (see SKILL.md → Repo-specific hazards).

## Admin screen smoke test

```bash
curl -sk -b "$COOKIE_HEADER" "https://wordpress.test/wp-admin/admin.php?page=aiseo" -o /tmp/settings.html
curl -sk -b "$COOKIE_HEADER" "https://wordpress.test/wp-admin/post-new.php"        -o /tmp/editor.html
grep -ciE "fatal error|critical error" /tmp/settings.html /tmp/editor.html   # must be 0
```

Write the body to a **file** before grepping — capturing a multi-hundred-KB page into a
shell variable truncates it and produces false "not found" results.

Note the block editor loads classic metaboxes in a separate request, so the metabox's own
inline markup may not appear in `post-new.php` output. Verify the enqueued JS instead:

```bash
curl -sk "https://wordpress.test/wp-content/plugins/aiseo-dev/js/aiseo-metabox.js" | grep "action:"
```

## Plugin Check

```bash
wp plugin check aiseo-dev --format=csv > /tmp/check-after.txt
```

**Text-domain artifact:** Plugin Check expects the text domain to equal the directory
name, so testing from `aiseo-dev` produces hundreds of bogus
`WordPress.WP.I18n.TextDomainMismatch` errors ("Expected 'aiseo-dev' but got 'aiseo'").
Ignore those, or run the check against a directory named `aiseo`.

The codebase carries a large pre-existing backlog (~370 findings). Absolute counts are
not a useful gate — **diff before vs after** and require zero *new* `(file, rule)` pairs:

```bash
wp plugin check aiseo-before --format=csv > /tmp/check-before.txt
# compare (file, rule-code) pairs, excluding TextDomainMismatch
```

## Test the release artifact, not just the working tree

The SVN build renames the bootstrap file (`aiseo.php` → `seo-wordpress.php`). Install the
staged trunk and re-run the suite before `svn ci`:

```bash
rsync -a --exclude='.svn' ~/seo-wordpress-svn/trunk/ "$PLUGINS/aiseo-trunk/"
cd ~/Sites/localhost/wordpress
wp plugin deactivate aiseo-dev && wp plugin activate aiseo-trunk
wp plugin list --name=aiseo-trunk --fields=name,status,version   # must show the new version
```
