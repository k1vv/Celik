# Celik

Adaptive practice & class-wide progress tracking for teachers and students, built on Laravel, Inertia, and React.

## Stack

- **Backend:** Laravel 13, Inertia (server adapter)
- **Frontend:** React 19, Inertia.js, Tailwind CSS 4, Vite
- **Database:** MySQL 8.4
- **Local environment:** [Laravel Sail](https://laravel.com/docs/sail) (Docker)

## Prerequisites

- Docker (Docker Desktop, or Docker Engine + Compose on Linux)
- Node.js and npm — only needed on the host if you want to run `npm` commands outside the container; everything also runs fine via `sail npm ...`

You do **not** need PHP or Composer installed on your host — both run inside the `laravel.test` container.

## Setup

Clone the repo, then from the project root:

```bash
cp .env.example .env
```

Bring up the containers (first run builds the PHP image and pulls MySQL — expect a few minutes):

```bash
docker compose up -d
```

Once the containers are healthy, install dependencies and prepare the app inside the container:

```bash
docker compose exec laravel.test composer install
docker compose exec laravel.test php artisan key:generate
docker compose exec laravel.test php artisan migrate --seed
docker compose exec laravel.test npm install
```

> If you have `vendor/bin/sail` available after `composer install`, you can use the shorter `./vendor/bin/sail <command>` form instead of `docker compose exec laravel.test <command>` for everything below.

## Running the app

The `laravel.test` container already serves the backend — visit **http://localhost:8000**.

The frontend needs its own dev server for hot-reloading. Start it inside the container (it must run there, not on the host, because `compose.yaml` reserves host port 5173 for the container):

```bash
docker compose exec laravel.test npm run dev
```

Leave that running in its own terminal while you work. Visit http://localhost:8000/login — demo accounts (password: `password`) are seeded by `LearningDataSeeder`:

- Teacher — `teacher@example.com`
- Student — `student1@example.com`

New accounts can also be created at `/register`.

## Running tests

```bash
docker compose exec laravel.test php artisan test
```

Feature tests render real Inertia pages, so they need a built asset manifest at least once:

```bash
docker compose exec laravel.test npm run build
```

Rerun the build after changing anything under `resources/js` if you're only running `php artisan test` (rather than also using `npm run dev`) between changes.

## Stopping

```bash
docker compose down
```
