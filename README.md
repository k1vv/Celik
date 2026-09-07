# Celik

Adaptive practice & class-wide progress tracking for teachers and students, built on Laravel, Inertia, and React.

## Stack

- **Backend:** Laravel 13, Inertia (server adapter)
- **Frontend:** React 19, Inertia.js, Tailwind CSS 4, Vite
- **Database:** MySQL 8.4
- **Local environment:** [Laravel Sail](https://laravel.com/docs/sail) (Docker)

## Prerequisites

- Docker with Compose (Docker Desktop, or Docker Engine on Linux). On Windows, clone and run everything inside WSL2.
- Git

You do **not** need PHP, Composer or Node.js on your computer. They run inside containers.

## Setup on a new computer

Run these from the project root after cloning. The steps are the same on every computer.

**1. Create your `.env`.**

```bash
cp .env.example .env
```

Set `GEMINI_API_KEY` to grade homework (free key: [aistudio.google.com/apikey](https://aistudio.google.com/apikey)). Quiz practice works without it. If a port is already in use, change it in `.env`.

**2. Install the PHP packages.** This must come first, because the app image is built from a file inside `vendor/`.

```bash
docker run --rm -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer -v "$PWD:/app" -w /app composer:2 composer install --ignore-platform-reqs
```

**3. Start the containers.** The first run builds the image and takes several minutes.

```bash
./vendor/bin/sail up -d
```

**4. Prepare the app.** If `migrate` cannot connect, wait a few seconds for MySQL and run it again.

```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm ci
./vendor/bin/sail npm run build
```

**5. Restart the queue worker** so it starts cleanly with the new key and database.

```bash
./vendor/bin/sail restart queue
```

Open **http://localhost:8000**. Teacher emails are optional and need a few more steps: see [`architecture/n8n-workflow.md`](architecture/n8n-workflow.md).

Use `./vendor/bin/sail <command>` rather than `docker compose exec`. Sail runs commands as the right user; a bare `docker compose exec` runs as root and can leave files the app cannot write to.

## What a clone does not include

These stay on the computer where they were made:

| Not in git | How to get it on a new computer |
|---|---|
| `.env` (keys and passwords) | Copy it privately, or fill in a new one |
| Database contents | `migrate --seed` recreates the demo data; export real data with `mysqldump` |
| Uploaded homework (`storage/app/private`) | Copy the folder |
| n8n account, credentials and live workflow | Set up again from `architecture/n8n-workflow.md` |
| `vendor/`, `node_modules/`, `public/build/` | Recreated by the steps above |

## Running the app

| Service | Address |
|---|---|
| App | http://localhost:8000 |
| n8n | http://localhost:5678 |

The `queue` service grades submitted PDFs in the background. If a submission stays on "Being graded", check `./vendor/bin/sail logs queue`.

While changing the frontend, run the dev server for hot reloading:

```bash
./vendor/bin/sail npm run dev
```

Demo accounts (password: `password`):

- Teacher — `teacher@example.com` (Math)
- Student — `student1@example.com` (Math)
- Physics teacher — `physics.teacher@example.com`
- Physics student — `physics.student1@example.com` (has "Homework 1" at `/student/homework/submit` and two practice sets at `/practice`)

New accounts can be created at `/register`.

## Running tests

```bash
./vendor/bin/sail artisan test
for file in tests/*.test.mjs; do ./vendor/bin/sail node --test "$file"; done
```

Some PHP tests render pages, so `npm run build` must have been run at least once.

## Continuing development

| File | Read it for |
|---|---|
| [`architecture/overview.md`](architecture/overview.md) | What the project is and how it fits together |
| [`architecture/PROGRESS.md`](architecture/PROGRESS.md) | What is done and what is next |
| [`architecture/architecture.md`](architecture/architecture.md) | Request flows and data model |
| `CLAUDE.md`, `.ai/rules/` | Conventions for AI coding tools |

## Stopping

```bash
./vendor/bin/sail down
```
