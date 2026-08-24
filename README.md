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

Validate the WooCommerce connection:

```bash
docker compose run --rm php php bin/test-connection
```

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
