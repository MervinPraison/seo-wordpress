# Breadcrumbs

Navigation trail for better UX and SEO.

```mermaid
graph LR
    A[🏠 Home] --> B[📂 Category]
    B --> C[📄 Post]
    
    style A fill:#6366F1,stroke:#7C90A0,color:#fff
    style B fill:#F59E0B,stroke:#7C90A0,color:#fff
    style C fill:#10B981,stroke:#7C90A0,color:#fff
```

## What It Does

Shows a navigation path like: `Home > Category > Post Title`

## Benefits

| Benefit | Description |
|---------|-------------|
| 🧭 Navigation | Users know where they are |
| 🔍 SEO | Rich snippets in search results |
| ⬅️ Easy Back | Quick return to parent pages |

## Enable Breadcrumbs

1. Go to **SEO WordPress → Breadcrumbs**
2. Enable the feature
3. Add shortcode to your theme

## Shortcode

```php
<?php echo do_shortcode('[praison_breadcrumbs]'); ?>
```

Add this to your theme's `single.php` or `page.php`.

## Customization

Style with CSS:

```css
.praison-breadcrumbs {
    font-size: 14px;
    color: #666;
}

.praison-breadcrumbs a {
    color: #4F46E5;
}
```
