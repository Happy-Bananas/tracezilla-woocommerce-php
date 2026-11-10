# Changelog

What changed in each release of this template, and what a project created
from an older release has to change to get it. A project's `TEMPLATE_VERSION`
says which release it started from; see
[Update Your Integration](https://happy-bananas.github.io/tracezilla-integrations-docs/update.html).

Changes in the shared code (tracezilla client, login, run lock, seed) are in
the [bifrost-core changelog](https://github.com/Happy-Bananas/bifrost-core/blob/main/CHANGELOG.md)
and reach a project with `composer update happy-bananas/bifrost-core`.

## Unreleased

- The integration logs in to tracezilla by itself with the API user in `.env`.
  `tracezilla:login` is removed.

What a project changes: add `TRACEZILLA_USERNAME` and `TRACEZILLA_PASSWORD`
(the API user from tracezilla's Resource Owner Password Credentials) to
`.env`, delete `bin/tracezilla-login` and remove `tracezilla:login` from
`bin/bifrost-connect`.

## 0.1.0 (2026-11-10)

First release.

- `connection:check` (also `./check-connection`) and `tracezilla:login`.
- Scenarios: `scenario:create` and `scenario:run`, with the templates
  `confirm-credentials`, `compare-catalogs`, `missing-in-woocommerce` and
  `create-tracezilla-skus`.
- The optional `tracezilla:seed`.
- Requires bifrost-core `^0.6`.
