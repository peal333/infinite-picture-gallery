=== Infinite Picture Gallery ===
Contributors: peal333
Tags: gallery, image gallery, video gallery, infinite scroll, media gallery
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.0
Stable tag: 1.6.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create image and video collections in WordPress and present them in a configurable, responsive gallery with seamless infinite scrolling.

== Description ==

Infinite Picture Gallery gives you a focused WordPress workflow for publishing visual collections. Each Picture can have a cover photo, an optional description, and an ordered set of additional images or videos from the Media Library.

The plugin builds a central gallery index that automatically loads more collections as visitors scroll. The gallery URL, title, and appearance can be configured under **Pictures > Settings**. Individual Picture permalinks use the same gallery base, for example `/gallery/my-picture/` when the gallery URL is `/gallery/`.

= Features =

* Dedicated **Pictures** content type for visual collections.
* Featured image support for a collection cover photo.
* Optional collection descriptions.
* Multiple images and videos per collection.
* Drag-and-drop media ordering in the WordPress editor.
* Configurable gallery URL and gallery title.
* Two gallery appearances: Classic Cards and Masonry.
* Responsive infinite scrolling with accessible loading and retry states.
* Native WordPress Media Library integration.
* Optional AIOSEO integration that synchronizes a Picture featured image to Facebook and Twitter/X custom images.
* Compatible title handling for WordPress, Yoast SEO, Rank Math, and All in One SEO.
* No external services, tracking, or remote code.

= Existing sites upgrading from an earlier version =

Version 1.6.0 keeps the existing `pictures` post type and existing gallery metadata. The default gallery URL remains `/gallery/`.

Individual Picture canonical URLs now share the gallery base. With the default settings, a Picture is available at `/gallery/{picture-slug}/`. Pre-1.6 `/picture/{picture-slug}/` URLs are retained as permanent redirects to the new canonical URLs, and the old `/picture/` archive redirects to the configured gallery index.

The legacy `ipg_load_more` AJAX action remains available for backwards compatibility while current plugin code uses the longer `infinite_picture_gallery_load_more` action name.

== Installation ==

1. Upload the `infinite-picture-gallery` folder to the `/wp-content/plugins/` directory, or install the ZIP through **Plugins > Add New > Upload Plugin**.
2. Activate **Infinite Picture Gallery** through the **Plugins** screen in WordPress.
3. Open **Pictures > Add New** to create a collection.
4. Set a cover photo, optionally add a description, and add additional gallery media.
5. Open **Pictures > Settings** to customize the public gallery URL, title, appearance, or AIOSEO integration.
6. Visit the configured gallery URL. The default is `/gallery/`.

The plugin automatically refreshes rewrite rules when its gallery URL changes. If a route still returns a 404 after a site move or unusual permalink change, visit **Settings > Permalinks** and save the page once.

== Frequently Asked Questions ==

= Can I change the `/gallery/` URL? =

Yes. Open **Pictures > Settings** and change **Gallery URL**. The same base is used for individual Picture permalinks.

= What happens to old `/picture/` URLs? =

The plugin redirects pre-1.6 `/picture/{slug}/` links to each Picture's current canonical URL so existing shared or indexed links continue to work.

= What gallery layouts are included? =

Classic Cards preserves the original responsive grid design. Masonry keeps the natural proportions of cover media in a flowing column layout. Infinite scrolling works with both.

= How does the AIOSEO integration work? =

The integration is disabled by default. Enable it under **Pictures > Settings**. When enabled, selecting a Picture featured image updates AIOSEO's Facebook and Twitter/X editor fields to use **Custom Image**, and saving the Picture persists that featured image as both social custom images. The integration updates only the related social-image fields and leaves other AIOSEO data untouched.

If AIOSEO is unavailable, Infinite Picture Gallery continues working normally.

= Can I use both images and videos? =

Yes. Additional gallery media can contain images and videos from the WordPress Media Library.

= Does the plugin send data to a third-party service? =

No. Infinite Picture Gallery does not include analytics, tracking, external API calls, or remotely hosted executable code. The optional AIOSEO integration communicates only with AIOSEO code installed on the same WordPress site.

== Privacy ==

Infinite Picture Gallery does not collect analytics, track visitors, contact external services, or transmit site data to third parties. All gallery content is stored using WordPress posts, post meta, options, and Media Library attachments on your site.

== Changelog ==

= 1.6.2 =
* Restored the AIOSEO integration setting default to disabled.
* Matched the proven Social Media Card Generator AIOSEO post-model update flow for Facebook and Twitter/X custom images.
* Deferred AIOSEO persistence until the end of the Picture save request so normal save handlers finish first.
* Updated AIOSEO's live editor state when a Picture featured image is selected so Facebook and Twitter/X immediately show Custom Image and the selected image.

= 1.6.1 =
* Fixed the configured gallery title not appearing when WordPress resolved the gallery URL through the native Pictures archive route.
* Restored the original responsive Classic Cards layout as the default presentation.
* Reduced gallery appearance choices to Classic Cards and Masonry. Existing Cinematic selections safely fall back to Classic Cards.
* Defaulted AIOSEO synchronization on when the supported AIOSEO integration API is available. This behavior was superseded in 1.6.2, which restores the default to disabled.
* Simplified the Gallery URL settings copy.

= 1.6.0 =
* Added **Pictures > Settings**.
* Added a configurable gallery URL, defaulting to `/gallery/`.
* Changed individual Picture canonical permalinks to use the configured gallery base.
* Added permanent redirects from pre-1.6 `/picture/` URLs.
* Added a configurable gallery title.
* Added Classic Cards, Masonry, and Cinematic gallery appearances.
* Added optional AIOSEO featured-image synchronization for Facebook and Twitter/X social images.
* Added AIOSEO availability status to the settings screen.
* Expanded identifier prefixing for new options, query variables, nonces, AJAX actions, and asset handles.
* Retained legacy data keys and AJAX compatibility where changing them could break existing sites.

= 1.5.0 =
* Prepared the plugin for WordPress.org directory submission.
* Added GPL licensing and standard WordPress.org plugin metadata.
* Improved save permissions, nonce handling, sanitization, and escaping.
* Internationalized user-facing strings.
* Refreshed the Picture editor with a responsive, accessible media-ordering interface.
* Added cover and media-count columns to the Pictures list screen.
* Removed inline frontend event handlers and moved presentation styles into plugin assets.
* Improved infinite-scroll error handling and keyboard/focus behavior.
* Preserved existing content types, metadata, URLs, and public gallery behavior.

= 1.4.1 =
* Pre-directory release used on existing sites.
