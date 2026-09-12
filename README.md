# NinjaReviews

**One line. Done.**

NinjaReviews is a lightweight, dependency-free JavaScript widget that shows your real Google reviews on your website. Self-hosted, open source, free.

```html
<script src="https://your-domain.com/ninjareviews/js/embed.js"></script>
```

That's the entire integration. No jQuery, no Bootstrap, no build step.

---

## What it does

- Pulls reviews directly from **Google Places API (New)** — no scraping, no stale cache.
- Renders a responsive, swipeable carousel: author, avatar, star rating, review text, date, and a link back to the original review.
- Ships as plain, vanilla JavaScript and CSS. No frameworks required on your side.
- Comes with a guided web installer — no manual editing of config files needed.

Google Reviews is the first source. NinjaReviews is architected to support other reputation platforms (Tripadvisor, Trustpilot, Facebook) over time — see [Roadmap](#roadmap).

## Requirements

- PHP **8.0 or higher** on your hosting (this is a hard requirement — the installer and `reviews.php` will not run on older versions).
- The `curl` and `json` PHP extensions (enabled by default on most hosting).
- HTTPS on the domain where you install NinjaReviews.
- A Google Cloud project with **Places API (New)** enabled and billing active, and an API key for it.

## Installation

1. Upload the contents of this repository to your server (e.g. `yourdomain.com/ninjareviews/`).
2. Open `https://yourdomain.com/ninjareviews/install/` in your browser.
3. The installer checks your environment (PHP version, extensions, HTTPS, file permissions).
4. Paste in a Google API key with Places API (New) enabled — no key yet? The installer links you straight to the right page in Google Cloud Console.
5. Paste your business's Google Maps URL.
6. Confirm the business the installer found.
7. Copy the generated embed line into your site.
8. **Delete the `install/` folder from your server.** The installer refuses to run again once configured, but removing it is good practice regardless.

## How the widget resolves its API endpoint

`js/embed.js` inserts a `<section class="ninjareviews">` container next to itself, loads `css/ninjareviews.css` and `js/ninjareviews.js`, and initializes the widget pointed at `reviews.php` alongside it. If you need the widget to call a `reviews.php` hosted on a different path or domain than the embed script itself, pass it explicitly:

```html
<script src="https://your-domain.com/ninjareviews/js/embed.js" data-api-url="https://your-domain.com/ninjareviews/reviews.php"></script>
```

## Theming

The widget's colors, borders, and fonts are controlled by CSS custom properties on `.nr-widget`, so you can restyle it without touching the JavaScript:

```css
.ninjareviews {
    --nr-bg: #ffffff;
    --nr-border: #e5e5e5;
    --nr-star: #f5a623;
    --nr-star-empty: #e5e5e5;
    --nr-date: #888;
    --nr-text: #222;
    --nr-muted: #888;
}
```

Star ratings render as SVG and support partial fills (e.g. `4.5`), not just whole numbers.

## Security notes

- The Google API key is used server-side only and stored in `config.php` — never exposed to the browser.
- `reviews.php` does not cache reviews; every widget load queries Google directly, and it never leaks Google's or cURL's internal error details to the client.
- The installer enforces SSL certificate verification, a CSRF token on all POST requests, and HttpOnly + SameSite=Lax session cookies.
- CORS is configured automatically for your server's origin during installation.
- `config.php` is protected via `.htaccess` (Apache) / `web.config` (IIS) and written atomically with `0640` permissions where the filesystem allows it.
- The installer locks itself after a successful setup and will not run a second time.
- Never commit a real API key to a repository or leave one in client-side code.

## Roadmap

- Additional review sources beyond Google: Tripadvisor, Trustpilot, Facebook.
- A **Managed** hosted option for people who don't want to run PHP themselves — not built yet, evaluating based on interest.

## Contributing

Found a bug? Have an idea? Want to add support for another review platform? See [CONTRIBUTING.md](CONTRIBUTING.md) — issues and pull requests are exactly how this project moves forward.

## Support the project

NinjaReviews is free, self-hosted, and has no paid tier. If it saves you time, you're welcome to [buy the maintainer a coffee](https://buymeacoffee.com/hanamisoftware) — never expected, always appreciated.

## License

Released under the [MIT License](LICENSE).
