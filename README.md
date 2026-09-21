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
- Accessible infinite scrolling with loading, retry, and fallback states.
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

### AIOSEO integration

The AIOSEO integration is disabled by default and can be enabled under **Pictures > Settings**. When enabled, choosing a Picture featured image updates AIOSEO's live editor fields and saving the Picture synchronizes that image to AIOSEO's per-post social settings:

- Facebook/Open Graph image source → custom image
- Facebook/Open Graph custom image → Picture featured image
- Twitter/X image source → custom image
- Twitter/X custom image → Picture featured image
- Twitter/X "use Facebook data" → disabled so the Twitter image is stored explicitly

The integration updates AIOSEO's existing post model instead of sending a partial replacement payload, so unrelated AIOSEO title, description, schema, canonical, and other SEO data are left intact. If AIOSEO is unavailable, the gallery continues working normally.

Removing a featured image does not erase an existing AIOSEO social image, preventing accidental loss of a manually configured value. The persisted update uses the same AIOSEO post-model save, verification, and cache-invalidation path as Social Media Card Generator, and is deferred until the end of the Picture save request so later save handlers cannot overwrite it.

## Upgrading from 1.5.x or Earlier

Version 1.6.0 preserves the existing `pictures` post type and stored Picture content.

The default gallery index remains `/gallery/`, but Picture canonical URLs now live beneath the configured gallery base. With the default settings:

```text
Old: /picture/example-picture/
New: /gallery/example-picture/
```

Pre-1.6 `/picture/{slug}/` links receive a permanent redirect to the current canonical Picture URL. The former `/picture/` archive also redirects to the configured gallery index.

The following legacy storage identifiers are intentionally retained to avoid data migration risk:

- Description meta: `_ipg_description`
- Gallery media meta: `_ipg_gallery_ids`

The old `ipg_load_more` AJAX action remains registered as a compatibility alias, while current plugin code uses `infinite_picture_gallery_load_more`.

## Privacy

PEAL333 Infinite Picture Gallery does not collect analytics, track visitors, contact remote services, or transmit site data to third parties. Gallery data stays in WordPress posts, post meta, plugin settings, and Media Library attachments.

The optional AIOSEO integration only communicates with AIOSEO code installed on the same WordPress site.

## Development and Naming Conventions

The plugin follows WordPress's collision-avoidance guidance for globally accessible identifiers. The established `infinite_picture_gallery_` internal namespace is intentionally retained because it is already distinctive and several identifiers are upgrade-sensitive. WordPress.org-facing identity uses the `peal333-infinite-picture-gallery` slug and text domain.

The main PHP class is `Infinite_Picture_Gallery`, which is already unique to the full plugin name. Constants are class-scoped rather than global. Variables inside class methods remain normally named because they do not enter PHP's global namespace.

Short `ipg_` identifiers are retained only where they are established compatibility contracts, such as pre-1.6 post meta and the legacy AJAX alias.

## Project Structure

```text
peal333-infinite-picture-gallery/
├── assets/
│   ├── admin-gallery.css
│   ├── admin-gallery.js
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

1. Activation and upgrade from the previous plugin version.
2. Creating and editing a Picture.
3. Setting/changing the featured image.
4. Adding, removing, and reordering images and videos.
5. `/gallery/` using default settings.
6. A custom Gallery URL.
7. Picture permalinks beneath the configured Gallery URL.
8. Legacy `/picture/{slug}/` redirects.
9. Classic Cards and Masonry appearances.
10. Infinite scrolling, retry handling, and fallback loading.
11. AIOSEO integration enabled, disabled, active, and unavailable.
12. The official WordPress **Plugin Check** plugin.

## Changelog

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
