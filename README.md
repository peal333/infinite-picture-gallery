# PEAL333 Infinite Picture Gallery

A lightweight WordPress plugin for publishing image and video collections in a configurable gallery with responsive infinite scrolling.

Each **Picture** can have a cover image, optional description, and ordered collection of images or videos from the WordPress Media Library. The plugin creates a central gallery index and gives every Picture its own shareable permalink beneath the same gallery URL.

## Features

- Dedicated **Pictures** content type.
- Native WordPress Featured Image / cover photo support.
- Optional descriptions.
- Mixed image and video collections.
- Drag-and-drop media ordering.
- Configurable gallery URL and title under **Pictures > Settings**.
- Two gallery appearances:
  - **Classic Cards** — the original responsive square card grid.
  - **Masonry** — a flowing layout that preserves media proportions.
- Customizable Picture detail pages with responsive grid, stacked, or masonry media.
- Optional published date, cover, description, previous/next navigation, and accessible image lightbox.
- Accessible infinite scrolling with a manual control, status announcements, failure fallback, and crawlable pagination.
- Optional AIOSEO integration for Facebook and Twitter/X custom images.
- Compatible title handling for WordPress, Yoast SEO, Rank Math, and All in One SEO.
- No external services, analytics, tracking, or remotely hosted executable code.

## Requirements

- WordPress 5.0 or newer
- PHP 7.0 or newer
- Tested up to WordPress 7.1

## Installation

1. Upload the plugin ZIP through **Plugins > Add New > Upload Plugin**, or copy `peal333-infinite-picture-gallery` to `/wp-content/plugins/`.
2. Activate **PEAL333 Infinite Picture Gallery**.
3. Open **Pictures > Add New** and publish a Picture collection.
4. Open **Pictures > Settings** to customize the gallery.
5. Visit `/gallery/` by default.

## Settings

### Gallery URL

The default public gallery URL is:

```text
/gallery/
```

Individual Pictures use the same base:

```text
/gallery/example-picture/
```

Changing the Gallery URL updates both patterns. The plugin refreshes its rewrite rules after the setting changes and prevents obvious conflicts with WordPress-reserved routes or an existing Page using the same path.

### Gallery title

The main gallery heading is configurable. The default is **Picture Gallery**. The configured title is also used for the gallery document title and supported SEO title integrations.

### Gallery appearance

Two infinite-scroll appearances are included:

- **Classic Cards** — the original responsive design and default behavior.
- **Masonry** — natural media proportions in a flowing column layout.

The setting changes presentation only; the same Picture data and infinite-scroll endpoint are used for both appearances.

### Picture detail pages

The **Picture Detail** settings section controls:

- Contained, wide, or full-width pages
- Left or centered headings and descriptions
- Responsive grid, stacked, or masonry media layouts
- One to four desktop columns
- Square crops or natural media proportions
- Compact, standard, or spacious gaps
- Square, soft, or rounded corners
- An optional background color
- Visibility of the title, published date, cover photo, description, and previous/next Picture navigation
- An optional keyboard-accessible image lightbox

Theme developers can override `peal333-infinite-picture-gallery/gallery-template.php` or `peal333-infinite-picture-gallery/single-pictures.php` from the active theme. The `pealipg_gallery_template` and `pealipg_single_template` filters provide an additional prefixed extension point.

### AIOSEO integration

The AIOSEO integration is disabled by default and can be enabled under **Pictures > Settings**. When enabled, choosing a Picture featured image updates AIOSEO's live editor fields and saving the Picture synchronizes that image to AIOSEO's per-post social settings:

- Facebook/Open Graph image source → custom image
- Facebook/Open Graph custom image → Picture featured image
- Twitter/X image source → custom image
- Twitter/X custom image → Picture featured image
- Twitter/X "use Facebook data" → disabled so the Twitter image is stored explicitly

The integration updates AIOSEO's existing post model instead of sending a partial replacement payload, so unrelated AIOSEO title, description, schema, canonical, and other SEO data are left intact. If AIOSEO is unavailable, the gallery continues working normally.

Removing a featured image does not erase an existing AIOSEO social image, preventing accidental loss of a manually configured value. The persisted update uses the same AIOSEO post-model save, verification, and cache-invalidation path as Social Media Card Generator, and is deferred until the end of the Picture save request so later save handlers cannot overwrite it.

## Internal Identifier Namespace

Version 2.0.0 standardizes all plugin-owned shared identifiers on the unique `PEALIPG` / `pealipg` namespace. This includes:

- PHP class: `PEALIPG_Plugin`
- Custom post type: `pealipg_pictures`
- Description meta: `pealipg_description`
- Gallery media meta: `pealipg_gallery_ids`
- Settings option: `pealipg_settings`
- Version option: `pealipg_version`
- Rewrite flag option: `pealipg_rewrite_flush_needed`
- Infinite-scroll AJAX action: `pealipg_load_more`
- Query variables, nonces, request fields, asset handles, JavaScript globals, admin columns, and UI selectors: `pealipg`-prefixed

The default gallery index remains `/gallery/`, and Picture canonical URLs live beneath the configured gallery base. Version 2.0.0 intentionally contains no automatic migration layer or legacy identifier aliases.

## Privacy

PEAL333 Infinite Picture Gallery does not collect analytics, track visitors, contact remote services, or transmit site data to third parties. Gallery data stays in WordPress posts, post meta, plugin settings, and Media Library attachments.

The optional AIOSEO integration only communicates with AIOSEO code installed on the same WordPress site.

## Development and Naming Conventions

Plugin-owned globally shared identifiers use the unique `PEALIPG` / `pealipg` prefix to avoid collisions with themes and other plugins. The main PHP class is `PEALIPG_Plugin`; class constants and methods are scoped within that class. File-scope template variables are also `pealipg_`-prefixed. JavaScript globals supplied by WordPress are `pealipg`-prefixed, while JavaScript implementation variables remain enclosed in local scopes.

The WordPress.org-facing slug and text domain are `peal333-infinite-picture-gallery`.

## Project Structure

```text
peal333-infinite-picture-gallery/
├── assets/
│   ├── admin-gallery.css
│   ├── admin-gallery.js
│   ├── detail-gallery.js
│   ├── gallery.css
│   └── infinite-scroll.js
├── templates/
│   ├── gallery-template.php
│   └── single-pictures.php
├── peal333-infinite-picture-gallery.php
└── readme.txt
```

## Release Testing

Before publishing a release, test at minimum:

1. Activation and deactivation on a clean WordPress installation.
2. Creating and editing a Picture.
3. Setting/changing the featured image.
4. Adding, removing, and reordering images and videos.
5. `/gallery/` using default settings.
6. A custom Gallery URL.
7. Picture permalinks beneath the configured Gallery URL.
8. Classic Cards and Masonry appearances.
9. Infinite scrolling, retry handling, and fallback loading.
10. Direct `/gallery/page/2/` pagination and JavaScript-disabled navigation.
11. Picture detail settings, Show Date, previous/next navigation, and lightbox keyboard behavior.
12. AIOSEO integration enabled, disabled, active, and unavailable.
13. The official WordPress **Plugin Check** plugin.

## Changelog

### 2.0.1

- Added comprehensive Picture detail-page controls, including the requested Show Date option.
- Added responsive grid, stacked, and masonry detail layouts with configurable columns, image proportions, spacing, corners, width, alignment, and background.
- Added optional previous/next Picture navigation and an accessible image lightbox.
- Rebuilt infinite scrolling as progressive enhancement over crawlable paginated gallery URLs.
- Added a visible Load More control, accessible status announcements, request timeout, duplicate-card protection, deterministic ordering, explicit end-state data, and a full-page failure fallback.
- Ensured published Pictures without media render a safe placeholder instead of interrupting pagination.
- Added keyboard media reordering in the Picture editor.
- Added theme template overrides and prefixed template filters.
- Strengthened setting type validation, attachment validation, and namespace consistency.
- Reorganized the settings experience and added a View Gallery shortcut.

### 2.0.0

- Standardized plugin-owned identifiers on the unique `PEALIPG` / `pealipg` namespace.
- Changed the custom post type to `pealipg_pictures`.
- Changed plugin meta keys, options, AJAX action, query variables, nonces, request keys, asset handles, JavaScript globals, admin columns, and UI selectors to collision-safe `pealipg` identifiers.
- Removed old generic compatibility aliases and legacy identifier fallbacks identified during WordPress.org manual review.
- Preserved gallery routes, layouts, infinite scrolling, editor behavior, and AIOSEO integration.


### 1.6.4

- Renamed the plugin to **PEAL333 Infinite Picture Gallery** for its WordPress.org directory identity.
- Changed the submission slug/text domain to `peal333-infinite-picture-gallery`.
- Preserved established internal identifiers and stored data for upgrade compatibility.

### 1.6.3

- Prefixed all template-scope variables with the plugin namespace to satisfy WordPress Plugin Check global naming requirements.

### 1.6.2

- Restored the AIOSEO integration setting default to disabled.
- Matched the proven Social Media Card Generator AIOSEO post-model update flow for Facebook and Twitter/X custom images.
- Deferred AIOSEO persistence until the end of the Picture save request so normal WordPress/AIOSEO save handlers finish first.
- Synchronized AIOSEO's live post-editor state when a Picture featured image is selected, including the Custom Image source and custom image URL for Facebook and Twitter/X.

### 1.6.1

- Fixed the gallery route when WordPress resolves it as the native Pictures archive, ensuring the configured gallery title, bundled template, assets, and infinite-scroll UI are used.
- Restored the original responsive Classic Cards markup and layout as the default.
- Removed the Cinematic appearance; existing Cinematic settings fall back safely to Classic Cards.
- Made AIOSEO synchronization enabled by default when its supported integration API is available. (Superseded by 1.6.2, which restores the default to disabled.)
- Removed the legacy redirect explanation from the Settings screen.

### 1.6.0

- Added **Pictures > Settings**.
- Added configurable gallery URL and title.
- Moved Picture canonical permalinks beneath the configured gallery base.
- Added redirects for pre-1.6 `/picture/` URLs.
- Added Classic Cards, Masonry, and Cinematic gallery appearances.
- Added optional AIOSEO featured-image synchronization for Facebook and Twitter/X.
- Added AIOSEO availability status.
- Expanded collision-safe naming for new global identifiers while retaining compatibility aliases for established data/actions.

### 1.5.0

- Prepared the plugin for WordPress.org directory submission.
- Improved security, internationalization, editor UI, accessibility, and infinite-scroll handling.
- Added cover and media-count columns to the Pictures list.

### 1.4.1

- Pre-directory release used on existing sites.

## License

PEAL333 Infinite Picture Gallery is licensed under the **GNU General Public License v2.0 or later**.

See <https://www.gnu.org/licenses/gpl-2.0.html>.

## Author

**Panupan Sriautharawong**
