# Deployment

recettIA deploys continuously to a single Hetzner server. Every push to `main` that passes CI is built on GitHub Actions and shipped over SSH as an immutable release directory. Going live means switching one symlink, and so does rolling back.

The pipeline lives in the `deploy` job of [`.github/workflows/tests.yml`](../.github/workflows/tests.yml).

---

## Server layout

```text
/var/www/recettia/                  # owned by the Linux user `recettia`
├── releases/
│   ├── <git sha>/                  # one directory per deployed commit (last 5 kept)
│   │   ├── .env -> /var/www/recettia/shared/.env
│   │   ├── storage -> /var/www/recettia/shared/storage
│   │   ├── public/storage -> <release>/storage/app/public  (php artisan storage:link)
│   │   └── …                       # app code, vendor/ (no dev packages), public/build/
│   └── <older sha>/
├── shared/
│   ├── .env                        # production configuration, never in Git
│   └── storage/                    # persistent: logs, sessions, cache, uploads
└── current -> releases/<git sha>   # the live release
```

- **Nginx** serves `/var/www/recettia/current/public`.
- **PHP 8.4-FPM** runs a dedicated pool as the user `recettia`, which must be able to write `shared/storage` and each release's `bootstrap/cache`.
- **PostgreSQL 16** runs locally. The connection settings live in `shared/.env`.
- Release files belong to `recettia` with modes `755` (directories) and `644` (files), set by `rsync --chmod=D755,F644`. Nginx (`www-data`) can read the static assets, and only `recettia` can write.

---

## One-time setup checklist

Do these once, on the server and in GitHub. Keep real values out of this repository.

### Server

- [ ] `rsync` is installed. The deploy uploads with it.
- [ ] The PHP 8.4 **CLI** is on the `recettia` user's `PATH`, with the same extensions as FPM (at least `pdo_pgsql`, `mbstring`, `xml`, `intl`, `bcmath`, `curl`, `zip`). The deploy runs `php artisan` over SSH.
- [ ] Directories exist and belong to `recettia`: `releases/`, `shared/`, `shared/storage/`.
- [ ] `shared/storage` has the standard Laravel structure:

  ```bash
  mkdir -p shared/storage/{app/public,framework/{cache/data,sessions,views},logs}
  ```

- [ ] `shared/.env` exists with mode `600` and contains at least `APP_NAME`, `APP_ENV=production`, `APP_KEY` (generate it once with `php artisan key:generate --show`), `APP_DEBUG=false`, `APP_URL`, `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`. Use [`.env.example`](../.env.example) as the template.
- [ ] The PostgreSQL database and role exist, and the role owns the database (migrations create tables).
- [ ] Nginx passes the **resolved** path to PHP-FPM, so a symlink switch takes effect without reloading FPM and OPcache never serves a stale release:

  ```nginx
  root /var/www/recettia/current/public;
  location ~ \.php$ {
      fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
      fastcgi_param DOCUMENT_ROOT   $realpath_root;
      # … fastcgi_pass to the recettia pool socket
  }
  ```

- [ ] `current` either does not exist yet or points somewhere harmless. The first deploy replaces it. A `current` that does not point to a real release (a directory under `releases/` that contains `artisan`) counts as "no previous release", and rollback is disabled for that first deploy.
- [ ] A dedicated deploy key pair (for example ed25519, no passphrase) exists. Its **public** key is in `~recettia/.ssh/authorized_keys`. Do not reuse a personal key.

### GitHub (Settings → Environments → `production`)

| Name | Kind | Content |
|---|---|---|
| `DEPLOY_HOST` | secret | Server hostname or IP that the runner connects to |
| `DEPLOY_USER` | secret | The SSH user (`recettia`) |
| `DEPLOY_SSH_KEY` | secret | The deploy key's **private** key, full text including the `BEGIN`/`END` lines |
| `DEPLOY_KNOWN_HOSTS` | secret | The server's host key line(s), exactly as they appear in a `known_hosts` file. The host field must match `DEPLOY_HOST` (hostname vs IP). Get them with `ssh-keyscan -t ed25519 <host>` and **check the fingerprint against the server** (`ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub`) before saving. |
| `APP_URL` | variable | Public base URL, e.g. `https://example.org` (no trailing slash needed). The health check calls `${APP_URL}/up`. |

Optional but recommended: protect the environment with "Deployment branches: `main` only" and, if you want a manual gate, "Required reviewers".

SSH uses port 22. For a different port, add `Port <n>` to the generated SSH config in the workflow's *Configure SSH* step.

---

## How a deploy works

The `deploy` job runs only on a **push to `main`**, and only after `php-quality`, `frontend` and `tests` have all passed. It uses the `production` environment and the concurrency group `deploy-production`: two deploys never overlap, a running deploy is never cancelled, and a newer push waits its turn. Runs on `main` are also exempt from the workflow-level "cancel superseded runs" rule, so a deploy can't be killed halfway.

1. **Build on the runner**
   - Checkout, PHP 8.4, `composer install --no-dev --prefer-dist --optimize-autoloader`.
   - Node at the version allowed by `engines` in `package.json`, `npm ci`, `npm run build`.
   - The order matters: the Wayfinder Vite plugin runs `php artisan wayfinder:generate`, so `vendor/` must exist first. It does **not** need Composer dev packages (`laravel/wayfinder` is a runtime dependency). It does need npm devDependencies (Vite+, the Wayfinder plugin), which is fine because `node_modules` is never shipped. The build needs no `.env`.
2. **Assemble** a release directory without `.git`, `.github`, `node_modules`, `tests`, any `.env*`, `storage`, `public/storage` and `public/hot`.
3. **SSH setup**: the private key goes into a temp file with mode `600`, and the host key into a dedicated `known_hosts` file used with `StrictHostKeyChecking yes` (an unknown or changed host key aborts the deploy). No secret is printed. The temp files are removed at the end of the job, even after a failure.
4. **Record** which release is live now (the rollback target).
5. **Upload** with `rsync --chmod=D755,F644` to `releases/<git sha>/`.
6. **Activate** over SSH, inside the new release:
   - link `shared/.env` as `.env` and `shared/storage` as `storage`
   - `php artisan migrate --force`
   - `php artisan optimize` (config, routes, events and views caches)
   - `php artisan storage:link`
   - switch `current` atomically: `ln -sfn` creates `current.next`, then `mv -T` renames it over `current`, so no request ever sees a missing link.

   If any of these fails before the switch, `current` is untouched and the job fails.
7. **Health check**: `GET ${APP_URL}/up`, up to 10 attempts, 6 s apart.
8. **On failure, roll back**: `current` is relinked to the release recorded in step 4, and the job fails with an error annotation. On the **first deploy** there is nothing to roll back to. The job fails with a clear message, and `current` stays on the new release so you can inspect it.
9. **Prune**: only the 5 most recent releases are kept. The live release is never deleted.

> **Migrations are not rolled back automatically.** A rollback only switches code. Keep migrations backward compatible (add first, remove in a later release), so the previous release still works against the migrated schema.

---

## Manual rollback

On the server, as `recettia`:

```bash
cd /var/www/recettia
ls -1t releases/            # newest first
readlink current            # what is live now

# Relink current to the release you want, atomically:
ln -sfn /var/www/recettia/releases/<previous sha> current.next
mv -Tf current.next current
```

Then check `https://<your domain>/up`. If a migration has to be reverted too, run `php artisan migrate:rollback --step=1` **from the newer release's directory before switching**, because the older release doesn't contain that migration file.

A manual rollback is overwritten by the next push to `main`. Revert the faulty commit in Git so the next deploy does not bring it back.

To redeploy a given commit without a new push, re-run its workflow run in GitHub Actions.

---

## Not covered yet

- Queue workers and the scheduler: none exist yet. When they do, the deploy should run `php artisan queue:restart` after the switch, and the scheduler's cron entry should point to `current/artisan`.
- Zero-downtime migrations for large tables, and database backups before migrating.
