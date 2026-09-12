# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Added
- Optional `nr-theme-brand` CSS class on `.ninjareviews` to restyle the widget via CSS custom properties without touching JavaScript.
- Direct link to Google Cloud Console (Places API (New) library page) in the installer, for users who don't have an API key yet.

### Changed
- Star ratings now render as SVG instead of the emoji star, and support **partial fills** (e.g. `4.5`, `3.2`), not just whole numbers.
- Installer error message for a rejected API key (HTTP 401/403) now lists the actual likely causes — API not enabled, billing not active, or key restricted to the wrong domain/API — instead of a generic "not authorized" message.

## [1.1.0]

### Added
- Simplified single-line embed: `js/embed.js` auto-injects the widget container and loads its own CSS/JS, so integrating NinjaReviews is one `<script>` tag.

## [1.0.0]

### Added
- Initial release: PHP + vanilla JS widget pulling reviews from Google Places API (New) Place Details.
- Guided web installer: environment check, API key input, Google Maps URL lookup, business confirmation, `config.php` generation.
- Responsive carousel with autoplay, manual navigation, and touch swipe.
- Security hardening: CSRF protection on installer POST requests, SSL certificate verification, HttpOnly + SameSite=Lax session cookies, atomic `config.php` writes with restrictive permissions, automatic CORS configuration, installer self-lock after setup.
- No caching of review data — every widget load queries Google directly.
