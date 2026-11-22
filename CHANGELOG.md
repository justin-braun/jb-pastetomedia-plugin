# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-11-22

### Added
- Paste images from the clipboard at the cursor in the block editor (Image block) and the Classic editor / Classic block.
- Filename, alt text, title, caption and description templates with tokens (`{post_slug}`, `{date}`, `{time}`, and more).
- Upload subfolder template under `wp-content/uploads` (default `pasted/{year}/{month}`), sanitized on the server.
- Optional review dialog before upload.
- Optional in-browser conversion to JPEG or WebP, with a quality setting and a max width.
- Classic editor options for the inserted size, alignment and link.
- Settings page under Settings > Paste to Media.
