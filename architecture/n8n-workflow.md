# n8n Workflow — Teacher Review Emails

[n8n](https://n8n.io) is a self-hosted workflow automation tool. Celik uses it
for one thing: emailing a teacher when a graded PDF submission contains answers
that need review. It does not grade anything.

> **When it fires:** a student uploads a PDF at `/student/homework/submit` and
> at least one answer is flagged for review. Photo and typed answers use
> `/homework` and do not notify.

## Flow

```
Student PDF → POST /submissions → SubmissionController::store()
        │
GradeSubmission job   queue worker grades every matched answer
        │
NotifySubmissionGraded job
        │
queue worker → POST http://n8n:5678/webhook/submission-graded
        │        header: X-Celik-Secret
        ▼
n8n:  [Submission graded] → [Needs review?] ── yes → [Email teacher]
           Webhook               If          └─ no  → stop
```

Both jobs are queued, so a slow or stopped n8n does not delay the student. If
grading fails, the submission is marked `failed` and nothing is sent.

## Nodes

| Node | Type | What it does |
|---|---|---|
| Submission graded | Webhook | Accepts `POST /webhook/submission-graded`, rejects a wrong secret with 403, replies immediately |
| Needs review? | If | Continues only if `needs_review` is greater than 0 |
| Email teacher | Send Email | Sends a plain-text email with a link to the review page |

## Data sent

```json
{
    "submission_id": 3,
    "assignment": "Homework 1",
    "teacher_email": "teacher@school.test",
    "needs_review": 2,
    "review_url": "http://localhost:8000/teacher/submissions/3"
}
```

`needs_review` counts this submission's flagged answers. Student names, answers
and files are deliberately left out, and a test checks the exact payload. Keep
it that way when adding fields. n8n can store this payload, including the
teacher's email, in its execution history.

Example email:

```
Subject: Review needed: Homework 1

A submission for "Homework 1" was graded, and 2 answer(s) need your review.

Review it here: http://localhost:8000/teacher/submissions/3
```

## Files

| File | Role |
|---|---|
| `app/Jobs/NotifySubmissionGraded.php` | Sends the webhook |
| `app/Jobs/GradeSubmission.php` | Grades the PDF, then dispatches the notification |
| `app/Http/Controllers/SubmissionController.php` | Dispatches grading |
| `config/services.php` (`n8n`) | Reads the settings |
| `compose.yaml` (`n8n`, `queue`) | Runs n8n and the worker |
| `resources/n8n/submission-graded.json` | The workflow, mounted at `/workflows` |
| `tests/Feature/NotifySubmissionGradedTest.php` | Job tests |
| `resources/js/Pages/SubmitHomework.jsx` | Student page that uploads the PDF |
| `tests/Feature/SubmissionGradingTest.php` | Checks it is sent once, after grading |
| `tests/Feature/GradeSubmissionTest.php` | Checks grading, retries, and that a failure sends nothing |

## Setup

1. Create a secret with `openssl rand -hex 32`, then set in `.env`:
   ```dotenv
   QUEUE_CONNECTION=database
   N8N_SUBMISSION_WEBHOOK_URL=http://n8n:5678/webhook/submission-graded
   N8N_WEBHOOK_SECRET=<the secret>
   ```
   Use `n8n` as the host, not `localhost`: the worker runs inside Docker. Leave
   the URL empty to turn notifications off.
2. Start the services with `./vendor/bin/sail up -d`.
3. Create the owner account at http://localhost:5678.
4. Import the workflow:
   ```bash
   docker compose exec n8n n8n import:workflow --input=/workflows/submission-graded.json
   ```
5. Open the workflow and add its credentials:

   | Node | Credential | Values |
   |---|---|---|
   | Submission graded | Header Auth | Name `X-Celik-Secret`, value = the secret |
   | Email teacher | SMTP | Your mail server (Gmail needs an app password) |

   Also change **From** on the email node to an address your server allows.
6. Click **Publish**. n8n refuses if a credential is missing.

## Test it

This checks n8n and SMTP without Laravel:

```bash
curl -X POST http://localhost:5678/webhook/submission-graded \
  -H 'X-Celik-Secret: YOUR_SECRET' -H 'Content-Type: application/json' \
  -d '{"assignment":"Test","teacher_email":"YOU@EXAMPLE.COM","needs_review":1,"review_url":"http://localhost:8000"}'
```

Set `needs_review` to `0` to check that no email is sent.

To test the whole path, sign in as `physics.student1@example.com` (password
`password`) and submit a PDF for Homework 1. Seeded teachers have `@example.com`
addresses, which cannot receive mail, so change that teacher's email first.

The Laravel side:

```bash
./vendor/bin/sail artisan test --compact tests/Feature/NotifySubmissionGradedTest.php tests/Feature/SubmissionGradingTest.php
```

## When something fails

| Failure | Result |
|---|---|
| n8n is down or returns an error | Up to 3 attempts, waiting 10 then 60 seconds, then saved as a failed job |
| SMTP fails | **Laravel does not retry**, because n8n has already replied. Check **Executions** in n8n |
| A retry after a lost reply | The teacher could get a duplicate email |

```bash
docker compose logs queue
./vendor/bin/sail artisan queue:failed
./vendor/bin/sail artisan queue:retry all
```

| Symptom | Likely cause |
|---|---|
| Jobs stay in the `jobs` table | The `queue` service is not running |
| Submissions stay on "Being graded" | The `queue` service is not running |
| 403 | The secret in `.env` differs from the n8n credential |
| 404 | The workflow is not published |
| Success in n8n but no email | `needs_review` was 0, or check spam |
| The link opens the dashboard | The teacher was logged out: login goes to the dashboard, so open the link again |

## Changing the workflow

n8n keeps its live copy in the `sail-n8n` Docker volume, so edits in the editor
are **not** in git. To save them, export and review the diff before committing (it
may include your n8n account's name or email):

```bash
docker compose exec n8n n8n export:workflow --id=celikSubmissionGrd --output=/tmp/workflow.json --pretty
docker compose cp n8n:/tmp/workflow.json resources/n8n/submission-graded.json
```

**Re-importing overwrites the live workflow and unpublishes it.** The webhook
returns 404 until you reselect the credentials and publish again.

`docker compose down -v` deletes the volume, including the account, workflow
and credentials.

## Production notes

- Serve n8n over HTTPS and do not expose the editor publicly.
- Set and back up `N8N_ENCRYPTION_KEY`. Without it, saved credentials cannot be
  decrypted.
- Set `APP_URL` correctly; the email link is built from it.
- Run `php artisan queue:work` under a process manager.
- n8n's licence is fair-code. Check it before offering n8n itself to customers.
