# Praison SEO WordPress - Agent Instructions

Instructions for AI agents working on this project.

---

## 1. Project Structure

```
seo-wordpress/
├── seo-wordpress.php        # Main plugin file (version here)
├── readme.txt               # WordPress.org readme
├── admin/                   # Admin dashboard pages
├── css/                     # Stylesheets
├── js/                      # JavaScript files
├── docs/                    # MkDocs documentation
└── .github/workflows/       # CI/CD
```

---

## 2. Version Management

**Single Source of Truth:** `seo-wordpress.php` line 12

```php
Version: 4.0.18
```

---

## 3. Key Files

| File | Purpose |
|------|---------|
| `seo-wordpress.php` | Main plugin loader |
| `seo-metabox-class.php` | Post/page meta box |
| `seo-sitemaps.php` | XML sitemap generation |
| `seo-breadcrumbs.php` | Breadcrumb functionality |
| `seo-taxonomy.php` | Category/tag SEO |
| `seo-rewritetitle-class.php` | Title rewriting |

---

## 4. WordPress Coding Standards

### Required Patterns

```php
// ✅ Nonce verification
check_admin_referer('seo_settings_nonce');

// ✅ Sanitization
$input = sanitize_text_field($_POST['field']);

// ✅ Escaping output  
echo esc_html($value);

// ✅ Capability check
if (!current_user_can('manage_options')) {
    return;
}
```

---

## 5. Release Process

```bash
# Update version in seo-wordpress.php
# Then run:
./release.sh
```

This will:
1. Sync version to readme.txt
2. Commit and tag
3. Push to GitHub
4. Trigger WordPress.org SVN deployment

---

## 6. Testing Checklist

- [ ] Plugin activates without errors
- [ ] Meta box appears on posts/pages
- [ ] Sitemap generates correctly
- [ ] Breadcrumbs display
- [ ] Analytics tracking works
- [ ] No PHP warnings
