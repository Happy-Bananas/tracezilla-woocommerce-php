# tracezilla WooCommerce PHP

Framework-neutral PHP examples for integrating WooCommerce with the tracezilla API.

The integration is a standalone command-line application. It communicates with WooCommerce through the REST API and does not need to be installed as a WordPress plugin.

## Local WooCommerce sandbox

Start a disposable WordPress, WooCommerce and MariaDB environment:

```bash
docker compose --profile sandbox up -d
```

The first start downloads and activates WooCommerce. Follow its progress with:

```bash
docker compose --profile sandbox logs -f wp-cli
```

Open <http://localhost:8080/wp-admin> and sign in with:

- Username: `admin`
- Password: `tracezilla`

### Create WooCommerce REST API credentials

After signing in, open the REST API settings directly:

<http://localhost:8080/wp-admin/admin.php?page=wc-settings&tab=advanced&section=keys>

The corresponding WordPress menu path is:

**WooCommerce → Settings → Advanced → REST API**

If WooCommerce displays its setup or onboarding screen, it is safe to skip it
for this local integration sandbox. You do not need to configure payments,
shipping or a theme before creating an API key.

Select **Create an API key** or **Add key**, then use:

- Description: `Tracezilla local development`
- User: `admin`
- Permissions: `Read/Write`

Select **Generate API key**. Copy both values immediately: WooCommerce only
shows the complete Consumer Secret once.

If the **WooCommerce** menu is entirely missing, confirm that its plugin is
active:

```bash
docker compose run --rm --entrypoint wp wp-cli plugin status woocommerce
```

If necessary, activate it with:

```bash
docker compose run --rm --entrypoint wp wp-cli plugin activate woocommerce
```

Reload the WordPress administration page after activation.

Copy the example environment file and add the generated credentials:

```bash
cp .env.example .env
```

For the local sandbox, retain this URL:

```dotenv
WOOCOMMERCE_URL=http://host.docker.internal:8080
WOOCOMMERCE_CONSUMER_KEY=ck_...
WOOCOMMERCE_CONSUMER_SECRET=cs_...
```

Install the PHP dependencies and test the connection:

```bash
docker compose run --rm app composer install
docker compose run --rm app php bin/test-connection
```

## Existing WooCommerce store

Only the `app` service is required when connecting to an existing store. Set `WOOCOMMERCE_URL` in `.env` to the public store URL, add its REST API credentials, and run the same commands.

## Stop or reset the sandbox

Stop the containers while retaining the store:

```bash
docker compose --profile sandbox down
```

Delete the disposable store and database completely:

```bash
docker compose --profile sandbox down --volumes
```

The reset command permanently removes the local sandbox data. It does not affect a remote WooCommerce store or tracezilla.
