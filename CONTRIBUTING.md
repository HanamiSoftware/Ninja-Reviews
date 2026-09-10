# Contributing to NinjaReviews

Thank you for your interest in contributing to NinjaReviews.

NinjaReviews is an open-source project and contributions are welcome, including bug fixes, documentation improvements, testing, accessibility improvements, performance work, and new features.

## Before you start

For small fixes, you can usually work directly from the current codebase.

For a new feature, architectural change, or substantial refactor, please open an Issue first. This gives the maintainer and contributors an opportunity to discuss the proposal before significant work begins.

Please do not include API keys, credentials, customer data, or other secrets in commits or Pull Requests.

## Fork and branch workflow

The preferred contribution workflow is:

1. Fork the repository.
2. Create a branch for the change.
3. Make and test your changes.
4. Push the branch to your fork.
5. Open a Pull Request against the `main` branch.
6. Respond to review feedback where necessary.

For example:

```text
feature/geolocation-search
bug/fix-absolute-path
docs/update-installation-guide
refactor/review-rendering
security/harden-installer
```

Use a short, descriptive name and keep the branch focused on one change.

## Pull Requests

A good Pull Request should explain:

- what changed;
- why the change was needed;
- how the change was tested;
- whether documentation was updated;
- whether there are any compatibility or breaking-change considerations.

Please keep Pull Requests focused. Large unrelated changes are harder to review and may need to be split into separate Pull Requests.

Use the Pull Request template when opening a PR.

## Code guidelines

NinjaReviews aims to remain lightweight and dependency-free on the frontend.

When contributing:

- keep the existing architecture unless a change has been discussed;
- avoid adding dependencies unless there is a clear benefit;
- keep frontend code independent from jQuery and Bootstrap;
- preserve responsive behavior;
- consider accessibility when changing UI behavior;
- do not expose API keys or credentials to browser-side code;
- keep error messages safe and avoid leaking server-side implementation details.

## Testing

Before opening a Pull Request, test the affected functionality locally when practical.

At minimum, verify that the change does not introduce obvious PHP errors, JavaScript console errors, broken requests, or regressions in the review widget.

For installer changes, also test the installation flow from a clean configuration where possible.

## Commit messages

Commit messages should be concise and describe the change clearly.

Examples:

```text
Fix absolute path in installer
Improve mobile slider behavior
Add installation documentation
Add Tripadvisor review adapter
```

There is no requirement to use a complex commit-message convention.

## Review process

Pull Requests are reviewed by the project maintainer before they are merged.

A Pull Request may be:

- approved and merged;
- returned for changes;
- discussed further before a decision;
- closed when the change is not a good fit for the project.

Opening a Pull Request does not guarantee that the proposed implementation will be merged.

## Commercial product

The open-source repository and any future commercial NinjaReviews offering are separate products with separate purposes.

A contribution to this repository is a contribution to the open-source project. A contributor does not automatically receive ownership, partnership, revenue share, or other rights in any future commercial offering.

Contributors retain the rights granted to them by the applicable license for their contributions.

## Questions and ideas

For feature ideas, design proposals, or questions about the project, please use GitHub Issues before starting substantial implementation work.

Thank you for helping improve NinjaReviews.
