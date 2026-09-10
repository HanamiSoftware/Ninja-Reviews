# NinjaReviews

Lightweight, self-hosted review widget for websites.

**Your reviews. Your website. One line. Done.**

NinjaReviews lets a business display customer reviews on its own website without relying on a heavy plugin, frontend framework, or third-party JavaScript dependency.

The current release integrates with **Google Places API (New)**. The project is designed so that additional review sources can be added in the future.

## Why NinjaReviews?

Most businesses already have customer reviews. The problem is getting those reviews onto their own website in a way that is simple, lightweight, and visually consistent with the site.

NinjaReviews focuses on that one problem:

- simple installation;
- vanilla JavaScript with no jQuery or Bootstrap dependency;
- responsive review slider;
- mobile touch/swipe support;
- automatic installation and configuration;
- Google Maps URL based place discovery;
- server-side API key handling;
- designed for self-hosted websites;
- open source under the MIT License.

## Current status

NinjaReviews is an early open-source release and is actively evolving.

The current version is focused on Google Reviews through Google Places API (New). Future integrations and improvements may be proposed through GitHub Issues and Pull Requests.

## Requirements

### Server requirements

- PHP 8.0 or newer
- PHP cURL extension
- PHP JSON support
- HTTPS / a valid SSL certificate
- permission to write the configuration file during installation

### Google requirements

You need a Google Cloud project with:

- Places API (New) enabled
- an API key authorized to use Places API (New)
- billing configured according to Google's current API requirements

Google API usage and billing are handled by Google. Review the current Google Maps Platform pricing and terms before deploying NinjaReviews.

## Installation

NinjaReviews is designed around a simple installation flow for self-hosted deployments.

1. Upload the NinjaReviews files to your web server.
2. Open the `/install/` directory in your browser.
3. Enter your Google Places API key.
4. Enter the Google Maps URL of the business you want to display.
5. Let the installer find the matching place.
6. Confirm the place.
7. The installer generates the local configuration.
8. Copy the generated embed code into the website where the reviews should appear.

The intended final integration is a single line of HTML/JavaScript.

Example:

```html
<script src="https://example.com/ninjareviews/js/embed.js" data-api-url="https://example.com/ninjareviews/reviews.php"></script>
```

Replace the example URLs with the location of your own NinjaReviews installation.

## How it works

The browser loads `embed.js` from the NinjaReviews installation.

`embed.js` creates the widget markup, loads the widget stylesheet and initializes `ninjareviews.js`.

`ninjareviews.js` requests review data from `reviews.php`.

`reviews.php` communicates with Google Places API (New) using the configured server-side API key and Place ID, then returns normalized review data to the widget.

The API key is therefore not embedded in the public JavaScript snippet.

## Architecture

```text
Website
   |
   | one-line embed
   v
embed.js
   |
   +--> ninjareviews.css
   |
   +--> ninjareviews.js
            |
            | fetch
            v
       reviews.php
            |
            | Google Places API (New)
            v
        Google Places
```

The separation between the frontend widget and the backend API endpoint is intentional. It keeps Google credentials out of the browser and leaves room for additional review providers in the future.

## Project structure

```text
NinjaReviews/
├── css/
│   └── ninjareviews.css
├── js/
│   ├── embed.js
│   └── ninjareviews.js
├── install/
│   ├── .htaccess
│   ├── index.php
│   └── css/
│       └── installer.css
├── .htaccess
├── config.example.php
├── README.md
├── reviews.php
├── CONTRIBUTING.md
├── LICENSE
├── SECURITY.md
└── CHANGELOG.md
```

## Configuration

The repository contains `config.example.php` as a template.

A real deployment uses a generated `config.php` containing environment-specific values such as:

- Google API key
- Google Place ID
- allowed origins

`config.php` must never be committed to the public repository.

## Security model

NinjaReviews is designed so the Google API key stays on the server side.

The review endpoint also uses origin validation, security headers, strict request handling, HTTPS certificate verification, and controlled error responses.

The project does not intentionally cache Google review content. Google Maps Platform terms and current API policies should always be reviewed before deployment or redistribution.

For security issues, please follow the process described in [SECURITY.md](SECURITY.md).

## Review attribution

Reviews returned by Google must be displayed with the attribution and links required by Google's current policies.

NinjaReviews therefore exposes the review author information, review rating, relative publication information and Google review link as part of the widget data.

Google Maps Platform policies may change. Always verify the current requirements before deploying a production integration.

## Open source

NinjaReviews is developed in the open.

The repository is public so developers can inspect the code, report problems, suggest improvements, and contribute changes.

See [CONTRIBUTING.md](CONTRIBUTING.md) for the contribution workflow.

## Commercial use

The open-source project is released under the MIT License, which permits broad reuse, including commercial use, subject to the license terms.

NinjaReviews may also develop a separate commercial offering around managed hosting, support, additional services, or future functionality. The existence of a commercial offering does not change the license of the open-source code in this repository.

## Roadmap

The roadmap is intentionally open and may evolve with community feedback.

Possible future directions include:

- additional review platforms;
- more widget presentation options;
- additional configuration options;
- improved developer tooling and documentation;
- managed hosting and commercial services.

Ideas are welcome through GitHub Issues.

## Contributing

Contributions are welcome.

For substantial changes, please open an Issue first so the proposal can be discussed before implementation.

Typical branch names are:

```text
feature/geolocation-search
bug/fix-absolute-path
docs/update-installation-guide
refactor/review-rendering
security/harden-installer
```

Then open a Pull Request against the `main` branch.

See [CONTRIBUTING.md](CONTRIBUTING.md) for the complete workflow.

## License

NinjaReviews is released under the [MIT License](LICENSE).

Copyright (c) 2026 Hanami Software / Francesco
