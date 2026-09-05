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

Set `GEMINI_API_KEY` in `.env` if you want the homework diagnosis feature to work (a free key is available at [aistudio.google.com/apikey](https://aistudio.google.com/apikey)) — the adaptive quiz path works fine without it, but submitting homework will fail on every request until it's set. See `architecture/architecture.md` and `architecture/engine.md` for how the diagnosis pipeline works.

Bring up the containers (first run builds the PHP image and pulls MySQL — expect a few minutes):

```bash
docker compose up -d
```

Once the containers are healthy, install dependencies inside the container (this first command has to use `docker compose exec` — `vendor/bin/sail` doesn't exist until `composer install` puts it there):

```bash
docker compose exec laravel.test composer install
```

From here on, prefer `./vendor/bin/sail <command>` over `docker compose exec laravel.test <command>` — both work, but a bare `docker compose exec`/`docker exec` defaults to the container's root user, while the app itself actually runs as `sail`. Running commands as root can leave root-owned files (storage dirs, caches) that the real `sail`-owned process then can't write to — this has caused a real 500 error before. `./vendor/bin/sail` runs everything as `sail` automatically:

```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
```

## Running the app

The `laravel.test` container already serves the backend — visit **http://localhost:8000**.

The frontend needs its own dev server for hot-reloading. Start it inside the container (it must run there, not on the host, because `compose.yaml` reserves host port 5173 for the container):

```bash
./vendor/bin/sail npm run dev
```

Leave that running in its own terminal while you work. Visit http://localhost:8000/login — demo accounts (password: `password`) are seeded by `LearningDataSeeder` (Math) and `AssignmentSeeder`/`PracticeSetSeeder` (Physics, including a real homework submission and two real practice sets to try):

- Teacher — `teacher@example.com` (Math)
- Student — `student1@example.com` (Math)
- Physics teacher — `physics.teacher@example.com`
- Physics student — `physics.student1@example.com` (has "Homework 1" waiting at `/student/homework/submit`, and two real practice sets — "Thermal Equilibrium Practice", "Specific Heat Capacity Practice" — at `/practice`)

New accounts can also be created at `/register`.

## Running tests

```bash
./vendor/bin/sail artisan test
```

Feature tests render real Inertia pages, so they need a built asset manifest at least once:

```bash
./vendor/bin/sail npm run build
```

Rerun the build after changing anything under `resources/js` if you're only running `php artisan test` (rather than also using `npm run dev`) between changes.

## Stopping

```bash
docker compose down
```
