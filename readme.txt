=== JB Paste to Media ===
Contributors: jbraun
Tags: paste, clipboard, images, media library, screenshot
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Paste images from your clipboard straight into posts and pages, with automatic filenames, alt text, captions and upload folders.

== Description ==

Copy a screenshot or an image and paste it into a post or page. JB Paste to Media uploads it to the Media Library and inserts it at the cursor.

* Works in the block editor and in the Classic editor / Classic block.
* Filename, alt text, title, caption and description templates with tokens such as `{post_slug}`, `{post_title}`, `{date}`, `{time}`, `{user}` and `{rand}`.
* Saves pasted images to a subfolder of `wp-content/uploads`, for example `pasted/{year}/{month}`.
* An optional dialog lets you review the name and text before each upload.
* Optional conversion to JPEG or WebP in the browser, with a quality setting and a maximum width.
* No external services, no tracking.

== Installation ==

1. Upload the plugin zip via Plugins > Add New > Upload Plugin, then activate it.
2. Configure it under Settings > Paste to Media.

== Frequently Asked Questions ==

= Does it change drag-and-drop uploads? =

No. Only images pasted from the clipboard are affected.

= What happens when I paste text that includes images (from Word or a web page)? =

WordPress handles it as usual. The plugin only takes over when the clipboard holds just an image.

== Changelog ==

= Unreleased =
* Added: Paste images from the clipboard at the cursor in the block and Classic editors.
* Added: Filename, alt, title, caption, description and upload subfolder templates.
* Added: Optional review dialog and JPEG/WebP conversion with a max width.
