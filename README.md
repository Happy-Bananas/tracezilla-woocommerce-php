# tracezilla-woocommerce-php

A framework-neutral PHP console application for integrating WooCommerce with
the tracezilla API. It is designed to run locally, from cron, or as a scheduled
container without being installed as a WordPress plugin.

The separate `tracezilla-woocommerce-sandbox` project provides a disposable
WordPress store containing deterministic development data.

## Commands

Every command runs through one entry point:

```bash
php bin/bifrost-connect help
```

| Command | Purpose |
|:--|:--|
| `connection:check` | Check the WooCommerce and tracezilla connections (read only) |
| `tracezilla:login` | Log in to tracezilla once and store renewable tokens |
| `tracezilla:seed` | Create the development test data in tracezilla (dry run by default) |

Commands that write take a run lock in `var/locks/`, so two scheduled runs
never overlap. A run that finds the lock taken is skipped with exit code `75`
and the next scheduled run picks up the work. `connection:check` and
`tracezilla:login` do not take the lock.

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
docker compose run --rm php php bin/bifrost-connect tracezilla:login
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
runs `bin/bifrost-connect connection:check`. Without Docker, run
`php bin/bifrost-connect connection:check`.
With the local sandbox, the WooCommerce values in `.env.example` work as they
are.

Both systems are always checked, so a problem with one does not hide the state
of the other. The command exits with `0` only when both connections work.

## Seed tracezilla development data

Set `TRACEZILLA_OWN_COMPANY` to your company's partner name in tracezilla.
Then preview and apply `seeds/tracezilla.yaml`:

```bash
docker compose run --rm php php bin/bifrost-connect tracezilla:seed
docker compose run --rm php php bin/bifrost-connect tracezilla:seed --execute --confirm
```

The seed creates missing partners, locations, SKUs, and received purchase
orders, and skips records that already exist. Never run it against a
production team.

## Workflows

The commands use ordinary PHP and Composer, following the same structure as
the Shopify PHP examples. Each command is a script in `bin/`, registered in
`bin/bifrost-connect`. Additional catalog, order, and inventory workflows will
be added the same way.

## Run from cron

Production commands can use the same image. For example, a five-minute order
schedule will have this shape once the order command is implemented:

```cron
*/5 * * * * cd /opt/tracezilla-woocommerce-php && docker compose run --rm php php bin/bifrost-connect orders:import
```

The run lock prevents overlapping runs. Workflows must also be idempotent, so
a run that is repeated or skipped never creates duplicates.

## Run the tests

```bash
docker compose run --rm php composer test
```
