# Security Reference — seo-wordpress / aiseo

## REST API permission model

File: `includes/class-aiseo-rest.php`

### Permission callback implementations

```php
// Site admin settings — manage_options
public function check_admin_permission($request) {
    if (!is_user_logged_in()) {
        return new WP_Error('rest_forbidden', __('You must be logged in...', 'aiseo'), ['status' => 401]);
    }
    if (!current_user_can('manage_options')) {
        return new WP_Error('rest_forbidden', __('You do not have permission...', 'aiseo'), ['status' => 403]);
    }
    return true;
}

// Editor — edit_posts (minimum)
public function check_permission($request) { /* login + edit_posts */ }

// Post object — edit_post for id/post_id param
public function check_edit_post_permission($request) { /* check_permission + edit_post */ }

// Term object — edit_term for taxonomy + term_id
public function check_edit_term_permission($request) { /* check_permission + edit_term */ }
```

### Route → callback mapping

| Route pattern | Callback |
|---------------|----------|
| `/analytics`, `/webmaster-verification`, `/redirects/*`, `/404/*` | `check_admin_permission` |
| `/permalink/optimize`, `/schema/{id}`, `/meta-tags/{id}`, `/report/unified/{id}` | `check_edit_post_permission` |
| `/taxonomy-seo/{taxonomy}/{term_id}` GET | `check_edit_term_permission` |
| `/generate/title`, `/bulk/update`, AI editor tools | `check_permission` |

When adding a new route, never default to `__return_true`. Match sensitivity of similar existing routes.

---

## WordPress coding standards (required patterns)

### Direct access
```php
if (!defined('ABSPATH')) {
    exit;
}
```
Must be **before** any `require` or `include`.

### Sanitisation
```php
$text = sanitize_text_field(wp_unslash($_POST['field']));
$email = sanitize_email($_POST['email']);
$url = esc_url_raw($_POST['url']);
$id = absint($_POST['id']);
```

### Escaping output
```php
echo esc_html($value);
echo esc_attr($value);
echo esc_url($url);
```

### Nonces
```php
check_admin_referer('action_nonce');
check_ajax_referer('ajax_nonce', 'nonce');
wp_nonce_field('action', 'nonce_field');
```

### Capabilities
```php
if (!current_user_can('manage_options')) {
    wp_die(esc_html__('Unauthorized', 'aiseo'));
}
```

### SQL
```php
$wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}table WHERE id = %d",
    absint($id)
));
```

---

## AJAX pattern

```php
add_action('wp_ajax_my_action', 'handler');
// Do NOT add wp_ajax_nopriv_ unless public access is intentional and secured

function handler() {
    check_ajax_referer('aiseo_nonce', 'nonce');
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }
    wp_send_json_success(['data' => $result]);
}
```

Localise nonce in enqueued scripts via `wp_localize_script`.

### Nonce actions in use

| Nonce action | Issued by | Consumed by |
|--------------|-----------|-------------|
| `aiseo_admin_nonce` | `wp_localize_script` (`aiseoAdmin.nonce`, `aiseoSEOImprover`, `aiseGutenbergData`) | all `AISEO_Admin` AJAX handlers |
| `aiseo_metabox_nonce` | `wp_nonce_field()` in the post editor metabox | `AISEO_Metabox` `aiseo_metabox_*` handlers + `save_post` |
| `aiseo_image_seo` | `wp_localize_script` in `AISEO_Image_SEO` | image SEO handlers |

One handler must accept exactly one nonce action. If a handler needs to serve two entry
points, that is a signal the action names should be split instead.

### Anti-patterns that have shipped here

```php
// 1. Registered as a filter, but check_ajax_referer is an ACTION -> return value ignored.
add_filter('check_ajax_referer', [$this, 'bypass_nonce_check'], 10, 2);

// 2. Verifies, logs, then proceeds anyway -> CSRF hole.
$ok = wp_verify_nonce($_POST['nonce'], 'aiseo_admin_nonce');
error_log('Nonce result: ' . var_export($ok, true));
// "TEMPORARY: skip nonce check" ... continues regardless

// 3. Logs secrets.
error_log('Nonce in POST: ' . $_POST['nonce']);
console.log('Nonce being sent:', aiseoAdmin.nonce);
```

Correct form:

```php
check_ajax_referer('aiseo_admin_nonce', 'nonce');   // dies with -1 + HTTP 403
if (!current_user_can('edit_posts')) {
    wp_send_json_error('Permission denied');
    return;
}
```

Endpoints that cannot verify a nonce (e.g. a nonce-refresh endpoint) must still gate on the
capability every consumer requires — `is_user_logged_in()` alone lets a subscriber mint one.

---

## Pre-submission security checklist

- [ ] No `permission_callback => '__return_true'` on REST routes
- [ ] No `wp_ajax_nopriv_*` without capability + nonce checks
- [ ] No inline `<script>` or `<style>` in PHP output
- [ ] No `eval()`, `base64_decode()` on user input, remote file inclusion
- [ ] API keys stored via `update_option` with sanitisation (consider encryption for OpenAI key)
- [ ] Plugin Check passes with no errors
- [ ] Anonymous curl tests return 401/403 on admin REST endpoints

---

## External services (readme.txt requirement)

Document OpenAI in `== External Services ==`:
- Service URL
- What data is sent and when
- User control (API key required, explicit action)
- Links to OpenAI privacy/terms

---

## Security incident response

1. Read WordPress.org email + vulnerability report fully
2. Fix root cause (not only the PoC endpoint)
3. Grep entire codebase for same pattern (`__return_true`, missing nonces, etc.)
4. Bump patch version
5. Update changelog with "Security:" prefix
6. Publish to SVN trunk + tag
7. Reply to plugins@wordpress.org requesting re-review
8. Rotate any credentials exposed during debugging

---

## Useful tools

| Tool | URL / command |
|------|---------------|
| Plugin Check | https://wordpress.org/plugins/plugin-check/ |
| WP-CLI check | `wp plugin check seo-wordpress` |
| PHPCS WPCS | `phpcs --standard=WordPress includes/` |
| Query Monitor | https://wordpress.org/plugins/query-monitor/ |
| Plugin guidelines | https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/ |
