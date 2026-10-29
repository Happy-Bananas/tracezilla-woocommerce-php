# tracezilla-woocommerce-php

A framework-neutral PHP console application for integrating WooCommerce with
the tracezilla API. It is designed to run locally, from cron, or as a scheduled
container without being installed as a WordPress plugin.

The separate `tracezilla-woocommerce-sandbox` project provides a disposable
WordPress store containing deterministic development data.

## Configure the application

```bash
cp .env.example .env
```

Set the WooCommerce URL, REST API consumer key, and consumer secret in `.env`.
For the local sandbox, retain:

```dotenv
WOOCOMMERCE_URL=http://host.docker.internal:8080
```

Never commit `.env` or API credentials.

## Run with Docker

Install the pinned PHP dependencies:

```bash
docker compose run --rm php composer install
```

## Authenticate with tracezilla

Create an OAuth client in tracezilla (**Settings → My account → API Tokens**)
and set `TRACEZILLA_TEAM_SLUG`, `TRACEZILLA_CLIENT_ID`, and
`TRACEZILLA_CLIENT_SECRET` in `.env`. Then log in once:

```bash
docker compose run --rm php php bin/tracezilla-login
```

The password is not stored. Tokens are saved in `var/tracezilla-token.json`
and renewed automatically 30 days before they expire, so scheduled commands
keep working. Keep the `var/` directory between runs and out of Git.

## Check the connections

Check the WooCommerce and tracezilla credentials with one read-only request
each. Nothing else works until this passes:

```bash
./check-connection
./check-connection --json
```

The helper builds the container and installs the PHP packages when needed, then
runs `bin/check-connection`. Without Docker, run `php bin/check-connection`.
With the local sandbox, the WooCommerce values in `.env.example` work as they
are.

Both systems are always checked, so a problem with one does not hide the state
of the other. The command exits with `0` only when both connections work.

## Seed tracezilla development data

Set `TRACEZILLA_OWN_COMPANY` to your company's partner name in tracezilla.
Then preview and apply `seeds/tracezilla.yaml`:

```bash
docker compose run --rm php php bin/seed-tracezilla
docker compose run --rm php php bin/seed-tracezilla --execute --confirm
```

The seed creates missing partners, locations, SKUs, and received purchase
orders, and skips records that already exist. Never run it against a
production team.

## Workflows

The commands use ordinary PHP and Composer, following the same structure as
the Shopify PHP examples. Additional catalog, order, and inventory workflows
will be added as independent commands in `bin/`.

## Run from cron

Production commands can use the same image. For example, a five-minute order
schedule will have this shape once the order command is implemented:

```cron
*/5 * * * * cd /opt/tracezilla-woocommerce-php && docker compose run --rm php php bin/pull-orders
```

Workflows must be idempotent and prevent overlapping runs before write-mode
commands are scheduled.

## Run the tests

```bash
docker compose run --rm php composer test
```
