# Remaining gaps

Internal checklist for later verification. Do not invent search ranks, volumes, or competitor positions. This file is excluded from the WordPress.org zip.

## Editor

- Gutenberg sidebar and improver have not been verified in a real browser on the local HTTPS test site.
- Table of contents is an analysis hint only; there is no insertable TOC block.
- Copy SEO fields to translations from the post editor (sync already exists on CLI/REST).
- Dual surfaces: classic metabox and block-editor sidebar both remain.

## WooCommerce

- Extra product identifier fields in the metabox (brand, GTIN) beyond what WooCommerce already stores and Product schema already reads.
- Dedicated shop-page SEO screen (the shop page currently uses the normal page metabox).
- Product category archive extras beyond the shared taxonomy SEO fields.

## Search data

- Google Search Console OAuth has not been live-tested with a real Google Cloud client.
- Search Analytics currently shows a 28-day site total plus top queries and pages; no countries, devices, or sitemap status.
- Rank tracking CLI/REST still returns an error until a real search-results provider is connected. Do not invent positions.

## Migrations and redirects

- Import redirects from the Redirection plugin (CSV import already exists).
- Redirect CSV help previously omitted 308/410; keep that copy in line with the form.

## Monitoring

- On-page competitor URL form in the Monitoring tab (CLI/class already fetch a public page; no traffic or backlink guesses).
- Backlink add/check form in the Monitoring tab (CLI already exists; no invented domain authority).
- 404 email or Slack notifications.

## Templates and builders

- Custom-field / ACF placeholders in title and description formats.
- Elementor, Divi, or Bricks integrations.

## Admin quality

- SEO list-table columns are limited to sitemap-enabled post types; products and other public types can lose the columns.
- Remaining inline `style=` attributes on some admin views.
- Main menu is visible to `edit_posts`; settings are capability-gated but authors still see the plugin.
- Settings is still one form; the side nav only jumps to sections.

## WordPress AI (as of November 2026)

Core and canonical plugins, not our own OpenAI client:

- Gutenberg SEO sidebar still uses custom AJAX on 7.0+, not `@wordpress/abilities` `runAbility()`.
- Official AI plugin Meta Description: PHP filter is registered; Gutenberg `ai.metaDescription.seoAdapters` JS adapter not added (REST meta already `show_in_rest`).
- WP AI Client is used for text generation when Connectors work; vision/alt still uses the OpenAI path.
- WordPress 7.2 (RC in November 2026, due 8 December) adds `required_capabilities` and `prepare_input()`. Keep `permission_callback` until 7.2 is the floor.
- Official AI plugin also has alt text, slug suggestions, editorial SEO notes, and markdown feeds. We already cover alt, permalinks, and llms.txt ourselves; only share keys/connectors, do not duplicate those UIs.

## AI assistants

- MCP abilities are registered for WordPress 6.9+ with output schemas, `meta.public`, `meta.show_in_rest`, and `meta.mcp.public`. Verified on the local HTTPS site against official MCP Adapter 0.7.2: HTTP `initialize`, `tools/call` discover, and `tools/call` execute for site SEO, post SEO, and list posts. A named desktop assistant (Claude Desktop / Cursor MCP config) has not been click-tested.

## Internationalisation

- No shipped `.pot` / translations. Do not add `load_plugin_textdomain()` on WordPress 6.7+ (Plugin Check ERROR).

## Planned earlier, still open

These were identified and deferred rather than shipped in this cycle:

- Dedicated WooCommerce product SEO UI (brand/GTIN metabox, shop screen).
- Import from the Redirection plugin.
- Competitor URL analysis form on Monitoring.
- Page-builder and ACF template tokens.
- Live Search Console OAuth against a real Google client.
- Rank tracking with a real provider.
- Gutenberg browser verification on Valet HTTPS.
- Desktop MCP client config (Claude Desktop / Cursor) against the local Valet HTTPS endpoint.
- Gutenberg `runAbility()` instead of custom AJAX on WordPress 7.0+.
