# Celik — Project Overview

A short introduction to what Celik is and how its parts fit together. For
setup, see the root `README.md`. For detail, see [Further reading](#further-reading).

## What it is

Celik is a web app for adaptive practice and class-wide progress tracking.
Students get practice matched to their level, and teachers see which concepts
the class is struggling with and why.

It was built for a hackathon under **SDG 4: Quality Education**. The problem:
one lesson is taught to students at very different levels, and teachers often
only learn who is struggling once test results come back.

The target scope is Physics, Form 4 and Form 5 SPM (KSSM). Only two topics have
real content so far: 4.1 Thermal Equilibrium and 4.2 Specific Heat Capacity. A
seeded Math class provides baseline demo data.

## Who uses it

| Role | Can do | Pages |
|---|---|---|
| Student | Practise, submit homework, track learning gaps | `/student/dashboard`, `/practice`, `/student/homework/submit` |
| Teacher | See class mastery, gaps and submissions, review and re-mark them | `/dashboard`, `/teacher/submissions/{id}` |

Access is controlled twice: the `role` middleware limits pages by role, and
policies check that a record belongs to the user's own class.

## The main idea: diagnosis, not just marking

An answer is checked for *why* it is wrong, across up to four dimensions:

| Dimension | Question it answers |
|---|---|
| Conceptual | Does the student hold a specific misconception? |
| Curriculum | Which KSSM standard is not yet demonstrated? |
| Method | Did they answer in the form the question asked for? |
| Reasoning | Is the cause-and-effect logic sound? |

Each gap found is saved as one `attempt_diagnoses` row. Dashboards are built
from those rows, whichever path they came from.

## How answers are processed

| | Quiz practice | Single homework answer | Assignment PDF |
|---|---|---|---|
| Endpoint | `POST /practice` | `POST /homework` | `POST /submissions` |
| Input | Multiple choice | Typed text or photo | One PDF per assignment |
| Uses AI | No, looks up the chosen option | Yes, Gemini | Yes, Gemini |
| Dimensions | Conceptual, Curriculum | All four | All four |
| Updates mastery score | Yes | No | No |
| Notifies the teacher (n8n) | No | No | Yes, if answers need review |
| Student page | `/practice` | `/student/homework/submit` | `/student/homework/submit` |

### Quiz practice
`DiagnosisService` looks up the misconception each wrong option was written to
reveal. `AdaptivePracticeService` then updates the student's mastery score for
that skill and picks the next question: usually the weakest skill, or a weak
prerequisite behind it, with an occasional review question.

### Homework
`HomeworkDiagnosisService` sends Gemini the answer with the matching syllabus
excerpt and rubric, and requires a fixed response structure. AI results are not
trusted blindly:

- Confidence below 0.6 flags the answer for teacher review.
- Otherwise a second AI call is made, and disagreement also flags it.
- About 10% of answers are sampled for teacher spot-checks.
- If a typed or transcribed answer has a known expected value, correctness is
  checked in code rather than by AI.
- Flagged answers keep their diagnoses withheld.

For an assignment PDF, `HomeworkPdfExtractionService` first transcribes each
answer. Answers it cannot match to a question are skipped, and the student is
told how many were read. A teacher can then override each AI score on the review
page, and the student sees the teacher's mark.

PDFs are graded in the background by the `GradeSubmission` job, so the student
can leave the page. The page refreshes itself until grading ends. If Gemini is
unavailable the job tries 3 times over about 70 seconds, then marks the
submission `failed`. Nothing is half-graded, and the student is asked to hand it
in again.

Assignments with several questions accept only a PDF. Photo and typed answers
are offered when there is exactly one question.

## Architecture

```
Browser (React 19 + Inertia + Tailwind)
        │
Laravel 13 / PHP 8.5
   controllers → services → Eloquent models
        │              │
        │              ├── Gemini API   (photo and typed answers, in the request)
        │              └── jobs table → queue worker ─┬─ Gemini API (PDF grading)
        │                                             └─ n8n → teacher email
        │
MySQL 8.4
```

Local development uses Docker Compose (Laravel Sail):

| Service | Purpose |
|---|---|
| `laravel.test` | Web app |
| `mysql` | Database |
| `queue` | Grades PDFs and sends notifications |
| `n8n` | Sends teacher emails, see [n8n-workflow.md](n8n-workflow.md) |

## Where things are

| Path | Contents |
|---|---|
| `routes/web.php` | All app routes |
| `app/Http/Controllers` | Request handling |
| `app/Services` | Grading, practice and gap logic |
| `app/Jobs` | Background jobs |
| `app/Models`, `app/Policies` | Data and authorization rules |
| `resources/js/Pages` | React pages |
| `resources/n8n` | n8n workflow export |
| `database/seeders` | Demo accounts and authored content |
| `tests` | PHPUnit tests and `.mjs` frontend tests |

## Main data

| Model | Meaning |
|---|---|
| `Subject`, `Module`, `Skill` | Curriculum; skills can have prerequisites |
| `Classroom` | One teacher, one subject, many students |
| `Question` | Shared question bank |
| `Assignment` / `PracticeSet` | Homework / quiz questions released to a class |
| `Submission` | One student's PDF for one assignment |
| `Attempt` | One answer to one question |
| `AttemptDiagnosis` | One gap found in an attempt |
| `Mastery` | A student's score for one skill |
| `TopicGrounding` | Syllabus text and rubric given to the AI |

## Known limitations

- Photo and typed answers do not notify the teacher and have no review page.
- Teachers cannot create assignments or practice sets in the app; seeders do.
- Photo and typed answers are graded during the request, so students wait.
- Homework results do not change mastery scores, although `engine.md`
  describes a shared score.
- AI accuracy has not been measured live; the 0.6 threshold is a guess.

## Further reading

| File | Covers |
|---|---|
| [architecture.md](architecture.md) | Request flows, data model, authorization |
| [engine.md](engine.md) | Diagnosis engine design |
| [ai-implementation.md](ai-implementation.md) | How and where AI is used |
| [n8n-workflow.md](n8n-workflow.md) | Teacher email notifications |
| [PROGRESS.md](PROGRESS.md) | Build log and next steps |
| [hackathon.md](hackathon.md) | Brief and judging criteria |
