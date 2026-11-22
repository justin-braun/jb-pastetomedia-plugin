# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Updates from GitHub Releases with no setup. The plugin header's `Update URI` points at the public repo, so WordPress.org is never asked about this plugin. Supports "View details" release notes and WordPress auto-updates, and shows a failed check on the Plugins screen. Optional wp-config.php constants: `JBPTM_DISABLE_GITHUB_UPDATES` turns it off, and `JBPTM_GITHUB_TOKEN` sends a read-only token for higher GitHub rate limits.

### Changed
- The paste mode now defaults to "Show a dialog to review name and text first". Sites that already saved their settings keep their choice.
- readme.txt: "Tested up to" is now 7.1, and the unconfirmed Contributors line is removed.

## [0.1.0] - 2026-11-22

### Added
- Paste images from the clipboard at the cursor in the block editor (Image block) and the Classic editor / Classic block.
- Filename, alt text, title, caption and description templates with tokens (`{post_slug}`, `{date}`, `{time}`, and more).
- Upload subfolder template under `wp-content/uploads` (default `pasted/{year}/{month}`), sanitized on the server.
- Optional review dialog before upload.
- Optional in-browser conversion to JPEG or WebP, with a quality setting and a max width.
- Classic editor options for the inserted size, alignment and link.
- Settings page under Settings > Paste to Media.
