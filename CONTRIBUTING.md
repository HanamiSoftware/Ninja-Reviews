# Contributing to NinjaReviews

Thanks for considering a contribution — this project is built in the open, and issues and pull requests are exactly how it improves.

## Ways to help

- **Found a bug?** Open an issue with steps to reproduce, your PHP version, and your browser/OS.
- **Have an idea?** Open an issue describing the use case before writing code — it saves everyone time if the direction gets discussed first.
- **Want to add a review source** (Tripadvisor, Trustpilot, Facebook, etc.)? Open an issue first; the goal is to keep the integration pattern consistent across sources.
- **Improving docs, translations, or the installer UX** is just as welcome as code.

## Workflow

1. **Fork** the repository.
2. **Branch** off `dev` with a short, descriptive name (e.g. `fix/embed-api-url`, `feat/tripadvisor-source`).
3. **Build** your change.
4. Open a **Pull Request** describing what changed and why. Link the related issue if there is one.
5. **Review** — expect discussion and requested changes before merging. That's normal, not a rejection.

## Ground rules for code

- **No new runtime dependencies** for the widget itself (`js/ninjareviews.js`, `css/ninjareviews.css`, `js/embed.js`). It stays vanilla JS/CSS — no jQuery, no framework, no bundler required to use it.
- **PHP 8.0+ syntax is fine** — that's the project's minimum supported version, not a ceiling to avoid.
- Keep `reviews.php` from leaking internal cURL/Google error details to the client (see the existing error handling for the pattern).
- Don't introduce caching of review data unless it's part of an explicitly discussed feature — the current design always queries Google live.
- Match the existing code style in the file you're editing rather than introducing a new one.

## Testing your change locally

- You'll need a PHP 8+ environment with the `curl` and `json` extensions, and a Google Cloud project with Places API (New) enabled and billing active to test against real data.
- Run the installer flow (`install/`) end to end after touching anything in `install/` or `reviews.php`.
- Check both the default styling and the widget's CSS-variable theming still render correctly after any CSS change.

## Reporting security issues

If you find a security issue (e.g. a way to expose the API key, bypass CSRF protection, or read `config.php`), please don't open a public issue — flag it privately first so it can be fixed before it's public knowledge.

## Code of conduct

Be respectful and constructive in issues, PRs, and discussions. Disagreements about direction are normal and welcome — personal attacks aren't.
