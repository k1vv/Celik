# Architecture

How the system is actually built, as of this session. `engine.md` describes
the diagnosis *workflow* (design intent); this file describes the *system* —
stack, layers, data model, and how a request actually flows through the
running app. `PROGRESS.md` tracks what of this is finished vs. not.

## Stack
- **Backend**: Laravel 13, PHP 8.5
- **Frontend**: Inertia.js + React 19, Tailwind CSS 4, Vite
- **Database**: MySQL 8.4, via the project's existing Laravel Sail/Docker setup — container `celik-laravel.test-1` (app) + `celik-mysql-1` (DB), named via `COMPOSE_PROJECT_NAME=celik` in `.env` (Docker Compose project names default to the directory name otherwise, which was `test_example-*` before this was set). `.env.example` defaults to Sail's MySQL; use `./vendor/bin/sail <command>` for `artisan`/`composer`/`npm`/tests, not the host PHP install and not bare `docker exec` (which defaults to root and creates files the real `sail`-owned web process can't write to — this caused a real 500 once, see `PROGRESS.md`).
- **Queue**: `QUEUE_CONNECTION=database`, run by the `queue` service in `compose.yaml`. Jobs: `GradeSubmission` (PDF grading) and `NotifySubmissionGraded` (n8n teacher email, see `n8n-workflow.md`)
- **Testing**: PHPUnit (not Pest), TDD workflow via the `tdd` skill, Pint for formatting
- **AI**: the quiz path needs none by design (see below). The homework path calls the Gemini `generateContent` API (function calling, mode `ANY`) directly via Laravel's `Http` facade (no SDK dependency added) — wired and tested against `Http::fake()`, and `GEMINI_API_KEY` is now configured and manually verified live against real submitted answers (typed and photographed/PDF). See `PROGRESS.md` for the specific verification runs. (Built against Anthropic first, migrated to Gemini for its free tier - see `PROGRESS.md`.)

## Layers

```
routes/web.php
        │
Http/Controllers  (PracticeController, DashboardController, HomeworkController,
                     StudentDashboardController, SubmissionController, Auth/*)
        │
Services           (AdaptivePracticeService, DiagnosisService, GapSummaryService,
                     HomeworkDiagnosisService, HomeworkPdfExtractionService)
        │
Models / Eloquent  (Subject, Skill, Module, Classroom, Assignment, Question,
                     Attempt, Mastery, AttemptDiagnosis, TopicGrounding, User)
        │
MySQL
```

Controllers stay thin — they validate input, call one or more services, and
hand the result to `Inertia::render()` (or a redirect, for `HomeworkController`,
back to the page `StudentDashboardController::submitHomework()` renders).
Each service owns one concern:

| Service | Owns |
|---|---|
| `AdaptivePracticeService` | Per-skill mastery score (Elo/IRT-style) and next-question selection, biased toward a weak prerequisite skill over its weak dependent (`engine.md` §8) |
| `DiagnosisService` | Turning a quiz answer into a Conceptual + Curriculum diagnosis — pure function, no DB writes, no AI |
| `HomeworkDiagnosisService` | Turning an open-ended homework answer (typed, or photographed/PDF via `diagnosePhoto()`) into all four dimensions via a grounded, schema-forced Gemini function call, with deterministic override, a self-consistency second call, and audit sampling layered on top (`engine.md` §5) |
| `GapSummaryService` | Aggregating `attempt_diagnoses` into the class-wide dimension×tag breakdown, annotated with root-cause/symptom when two gapped skills share a prerequisite edge |
| `HomeworkGoldSetScorer` / `HomeworkEvaluationRunner` | Not part of the request path — power `php artisan homework:evaluate`, which scores a hand-authored gold-set (`database/seeders/GoldSets/HeatHomeworkGoldSet`) against the live `HomeworkDiagnosisService`, repeating each entry 3× to measure cross-call consistency, not just accuracy |
| `PracticeSetService` | Presents a classroom's teacher-authored `PracticeSet`s to a student with real per-student progress (from `Attempt::practice_set_id`) and that student's own open diagnoses on the set's skill as "gaps", replacing the frontend's previously-hardcoded `practiceSets` array |
| `LearningGapService` | Replays a student's own `attempt_diagnoses` history per skill to classify each (dimension, tag) as open or cleared (cleared the first time a later attempt on that skill stops reproducing it), powering the student dashboard's stats/learning-gaps/homework-progress chart — previously all hardcoded in `dashboardData.js` |
| `HomeworkPdfExtractionService` | Segments a homework PDF into per-question units - `extractQuestions()` (teacher's blank PDF → question text + a suggested, never-trusted `kssm_code`) and `extractAnswers()` (student's answered PDF, given the assignment's known questions → transcribed text per question + a `matched` flag). Pure extraction/transcription - grading judgment stays entirely in `HomeworkDiagnosisService`, called unchanged per matched question (multi-question PDF homework redesign, in progress - see `PROGRESS.md`) |

## Data model

```
Subject ──< Skill ──< Question ──< Attempt >── User (student)
   │          │  │       │            │
   │          │  │       │            ├──< AttemptDiagnosis (dimension, tag, evidence)
   │          │  │       │            ├── PracticeSet (nullable practice_set_id - which
   │          │  │       │            │      teacher-authored set this quiz attempt was part of)
   │          │  │       │            └── Submission (nullable submission_id - which
   │          │  │       │                   multi-question PDF this homework attempt was
   │          │  │       │                   graded from, if any; also carries teacher_score)
   │          │  │       │
   │          │  │  (distractor_map, kssm_code, expected_answer — authored per item)
   │          │  │       │
   │          │  │       └──< assignment_questions >── Assignment >── Classroom (title
   │          │  │             "Homework 1", due_at - bundles several Questions, in order,
   │          │  │             mirroring PracticeSet's own pivot; a Submission belongs to
   │          │  │             one Assignment and groups the Attempts graded from one PDF)
   │          │  │       │
   │          │  │       └──< PracticeSet >── Classroom (title, difficulty, due_at - bundles
   │          │  │             several Questions via practice_set_questions, unlike Assignment's
   │          │  │             one-question-only release)
   │          │  │
   │          │  └─< skill_prerequisites >─┘  (self-referencing DAG: Skill::prerequisites()/dependents())
   │          │
   │          └── Module (nullable module_id — curriculum grouping, e.g. "Heat")
   │
   └──< Module   (Module belongs to Subject, not Classroom — see below)

TopicGrounding (kssm_code, syllabus_excerpt, rubric, tag_vocabulary)
      — keyed by kssm_code, not a foreign key to Skill/Question

Classroom >── User (teacher, via teacher_id)
Classroom >──< User (students, via classroom_user pivot)

Mastery — one row per (User, Skill): rolling mastery_score
```

**Why `Module` belongs to `Subject`, not `Classroom`**: `Skill` is already a
shared, subject-level bank reused across every classroom of that subject
(mastery tracking and the `skill_prerequisites` graph both depend on that
sharing). A classroom-owned `Module` with a single `skills.module_id` would
force a Skill to belong to only one classroom's grouping, breaking that
sharing the moment a second classroom of the same subject exists — which is
exactly what happened when `AssignmentSeeder` created the first-ever Physics
classroom (previously only a Math one existed). Scoping `Module` to `Subject`
instead mirrors how `Skill.subject_id`/`Classroom.subject_id` already work
(plain sibling FKs, no pivot).

Key columns beyond the obvious:
- `questions.distractor_map` (json, nullable) — `{"B": "metal_inherently_colder"}`, authored per item, read by `DiagnosisService`, never written at request time
- `questions.kssm_code` (string, nullable) — the curriculum standard this item tests, and the key `HomeworkDiagnosisService` uses to look up `TopicGrounding`
- `questions.expected_answer` (string, nullable) — when set, `HomeworkDiagnosisService` checks correctness deterministically against this instead of trusting the LLM's claim
- `topic_groundings` — `(kssm_code, syllabus_excerpt, rubric, tag_vocabulary)`, hand-authored RAG context for homework grading; `tag_vocabulary` has `conceptual`/`method`/`reasoning` arrays that also constrain the Gemini function declaration's per-property enums
- `skill_prerequisites` — `(skill_id, prerequisite_skill_id)`, a DAG over `Skill`; only real edge seeded so far is Specific Heat Capacity → Thermal Equilibrium
- `modules` — `(subject_id, name, order)`; `skills.module_id` (nullable) groups a Skill under one, e.g. both seeded Skills under "Heat"
- `assignments` — `(classroom_id, title, due_at)` — a teacher's release of one PDF's worth of Questions to one Classroom; this is the link between "homework a student can see" and "their classroom" that `Question` deliberately doesn't have itself (it's a shared bank). `assignment_questions` `(assignment_id, question_id, order)` is the pivot bundling them, unique per pair - mirrors `practice_set_questions`. No teacher-facing create flow yet — only `AssignmentSeeder` makes one, see `PROGRESS.md`
- `submissions` — `(user_id, assignment_id, photo_path, extraction_status: pending|extracted|failed, teacher_validated_at)` — one row per (student, assignment) PDF upload; the Attempts graded from it are linked via `attempts.submission_id`, not the other way around
- `attempt_diagnoses` — `(attempt_id, dimension, tag, evidence)`, one row per gap surfaced; a fully correct attempt writes none. `evidence` is the exact phrase from the student's answer justifying that finding (homework path only)
- `attempts.needs_review` / `attempts.sampled_for_audit` — two independent flags: the former means "not trusted, diagnoses withheld" (low confidence, or the self-consistency double-call disagreed); the latter means "trusted, but randomly queued for teacher spot-check anyway" (~10% of homework attempts)
- `attempts.raw_answer` / `attempts.photo_path` — mutually exclusive: a typed homework answer sets the former, a photographed/PDF answer sets the latter (and stores the file via `Storage`); never both
- `attempts.score` / `attempts.max_score` (homework only) — a rubric-based mark (e.g. 14/20) from `HomeworkDiagnosisService`'s Gemini function schema, distinct from `is_correct`: a partially-correct answer can score above zero. Quiz attempts leave both null - correctness alone is the signal there
- `attempts.teacher_score` (nullable) — a teacher's override of the AI-proposed `score`; `Attempt::effectiveScore()` returns `teacher_score ?? score`. Written by `SubmissionController::validate()`; students see it via `Attempt::isTeacherMarked()`
- `attempts.practice_set_id` / `attempts.submission_id` (nullable FKs, mutually exclusive in practice) — which teacher-authored grouping this attempt belongs to, if any: a `PracticeSet` (quiz) or a `Submission` (multi-question homework PDF)
- `practice_sets` — `(classroom_id, skill_id, title, description, difficulty, estimated_minutes, due_at)`, a teacher's release of several `Question`s (via `practice_set_questions`, ordered) to one classroom
- `practice_set_questions` — `(practice_set_id, question_id, order)` pivot, unique per pair

## Request flow: quiz answer (implemented)

```
POST /practice {question_id, selected_option}
        │
PracticeController::store()
        │
        ├─ DiagnosisService::diagnose($question, $selected_option)
        │     → {correct, conceptual: {gap, tag}, curriculum: {kssm_code, mastered}}
        │     (pure lookup against distractor_map — no AI, no I/O)
        │
        ├─ AdaptivePracticeService::recordAttempt(...)
        │     → writes Attempt, updates Mastery
        │
        └─ if gap: Attempt::diagnoses()->create(...) for each dimension with a tag
        │
Redirect back to /practice with the result
```
Everything here is synchronous and local — no network call, no queue — by
design, so grading latency doesn't depend on connectivity (see `engine.md` §4
for the reasoning).

## Request flow: teacher dashboard (implemented)

```
GET /dashboard
        │
DashboardController::index()
        │
        ├─ existing heatmap/gaps logic (unchanged, per-skill mastery)
        │
        └─ GapSummaryService::forStudents($classroom->students)
              → SQL: attempt_diagnoses JOIN attempts,
                GROUP BY dimension, tag, COUNT(DISTINCT user_id)
        │
Inertia::render('Dashboard', [..., 'gapSummary' => [...]])
        │
Dashboard.jsx renders the "Diagnosed gaps" panel
```

## Request flow: submit-homework page + answer (implemented — typed or photographed/PDF)

```
GET /student/homework/submit
        │
StudentDashboardController::submitHomework()
        │
        ├─ assignmentsFor($classroom) - Assignments for the student's own
        │     classroom only (via Assignment::where('classroom_id', ...))
        └─ pastHomeworkAttemptsFor() - this student's own homework Attempts
        │
SubmitHomework.jsx renders assignment cards (title only; question body
revealed only once a card is clicked) + submission history

POST /homework {question_id, answer} OR {question_id, photo}
        │
HomeworkController::store()  (question_id validated exists:questions,id,type,homework)
        │
        ├─ if photo: store it (Storage), then
        │     HomeworkDiagnosisService::diagnosePhoto($question, $path)
        └─ else: HomeworkDiagnosisService::diagnose($question, $answer)
              │
              ├─ TopicGrounding::forKssmCode($question->kssm_code) - RAG context
              ├─ objectiveCheck() - deterministic override if expected_answer is
              │     set (typed answers only - no transcribed text for a photo)
              ├─ Gemini generateContent call, function calling forced (mode ANY),
              │     tags/evidence schema-constrained; a photo is sent as inline
              │     image/PDF data instead of a text answer, same schema either way
              ├─ if otherwise trusted: a second Gemini call - disagreement
              │     forces needs_review regardless of either call's confidence
              └─ rollForAudit() - ~10% sampled_for_audit, independent of trust
        │
        ├─ Attempt::create(..., raw_answer OR photo_path, needs_review, sampled_for_audit)
        │
        └─ if trusted: Attempt::diagnoses()->create(...) per gapped dimension,
              each with its quoted evidence
        │
Redirect back with a flash result (correct | incorrect | pending_review) AND
the full homeworkDiagnosis (tag/evidence/confidence per dimension) - the page
renders both the fresh result and every past submission's evidence
```
Runs synchronously in-request today (up to two Gemini calls) - per
`engine.md` §5 this should eventually be a queued job so grading doesn't
block on a live network call. Assignment PDFs already are (`GradeSubmission`);
photo and typed answers are not (see `PROGRESS.md`). **Enforced**: `QuestionPolicy::submitHomework()`
(see Authorization below) rejects with 403 if the submitted `question_id`
isn't actually assigned (via `Assignment`) to one of the student's own
classrooms - previously only the UI stopped *showing* unassigned questions.

## Request flow: practice sets (implemented)

```
GET /practice
        │
PracticeController::index()
        │
        ├─ AdaptivePracticeService::nextQuestionFor(...) - unchanged, the
        │     plain single-question adaptive flow
        └─ PracticeSetService::forStudent($student, $classroom)
              → real per-set progress (Attempt::practice_set_id) + the
                student's own open diagnoses on that set's skill as "gaps"
        │
Practice.jsx renders both: an "answer it now" adaptive card, and
teacher-authored PracticeSet cards (assigned/in_progress/completed)

POST /practice {question_id, selected_option, practice_set_id?}
        │
PracticeController::store()
        │
        ├─ QuestionPolicy::attemptQuiz - subject-level check (unchanged)
        └─ if practice_set_id given: PracticeSetPolicy::attempt - 403 unless
              the set was released to one of the student's own classrooms
              (narrower than attemptQuiz: scoped to one classroom, not the
              whole subject)
        │
AdaptivePracticeService::recordAttempt(..., $practiceSetId) - same mastery
update as before, now also stamping practice_set_id when present
```
Each question inside a set still grades instantly and synchronously - a set
is just teacher-curated grouping + progress tracking on top of the same
per-question quiz path, not a new grading mechanism.

## Request flow: student dashboard (implemented)

```
GET /student/dashboard
        │
StudentDashboardController::index()
        │
        ├─ LearningGapService::forStudent($student)
        │     → replays this student's own attempt_diagnoses per skill in
        │       chronological order: a tag is "open" once diagnosed, and
        │       "cleared" the first time a later attempt on that skill stops
        │       reproducing it (can reopen if it resurfaces later) - powers
        │       stats (submitted/gapsCleared/totalGaps/practiceWaiting), the
        │       open+recently-cleared learningGaps list, and the "gaps
        │       cleared per homework" chart
        ├─ nextPracticeFor() - built from the single most urgent open gap
        └─ nextDueFor() - nearest not-yet-submitted Assignment's due_at
        │
Inertia::render('StudentDashboard', ['dashboardData' => [...], 'assignments'
  => [...], 'pastAttempts' => [...]])
        │
StudentDashboard.jsx (overview/progress tabs) + StudentWorkPages.jsx
(due-soon and marked-work tabs, real evidence quotes highlighted directly
inside the student's own raw_answer text via substring match - no separate
highlight-position data needed, since evidence is always an exact quote)
```
Replaces `dashboardData.js`/`studentWorkData.js` (deleted) - every number,
gap, and highlighted answer on these pages is now real, no hardcoded content
remains anywhere in the student-facing frontend.

**Regression found and fixed in the same pass**: a teammate's later commit
(`89a07a8`) had reverted `SubmitHomework.jsx` to reference undefined
variables (`file`, `visibleHomework`) and never actually render the real
`assignments`/`pastAttempts` props the backend already sent - the page would
have thrown at runtime. Rewritten to actually use those props (assignment
picker, typed/photo mode toggle, real diagnosis + past-submission display).

## Authorization (RBAC)
Two roles (`teacher`/`student`), gated at two levels:
- **Coarse, route-level**: the `role:teacher`/`role:student` middleware
  (`EnsureUserHasRole`) - unchanged, existed before this.
- **Fine, per-resource**: Laravel Policies (auto-discovered, no manual
  registration needed - `{Model}Policy` matches `App\Models\{Model}`),
  checked via `$this->authorize()` in the controller:

| Policy method | Enforced in | Rule |
|---|---|---|
| `QuestionPolicy::submitHomework` | `HomeworkController::store()` | An `Assignment` must tie this question to one of the student's own classrooms |
| `QuestionPolicy::attemptQuiz` | `PracticeController::store()` | The question's skill's subject must match one of the student's own classrooms |
| `SubmissionPolicy::create` | `SubmissionController::store()` | The `Assignment` must be released to one of the student's own classrooms |
| `SubmissionPolicy::validate` | `SubmissionController::show()`/`validate()` | The teacher must teach the classroom the submission's assignment was released to |

Both close a real gap: previously any authenticated student could submit an
answer for any question system-wide by ID/guess, homework or quiz, with
nothing checking it was actually theirs to answer.
`DashboardController::index()` was also checked (a teacher passing another
teacher's `?classroom=` id) - it already only selects from
`$teacher->classroomsTeaching()`, so it silently falls back to the teacher's
own classroom rather than leaking another's; this is covered by a regression
test (`DashboardAuthorizationTest`) rather than a new Policy, since there was
no gap to close.

## Request flow: multi-question PDF submission (implemented — grading + teacher validation)

```
POST /submissions {assignment_id, pdf}
        │
SubmissionController::store()
        │
        ├─ SubmissionPolicy::create - assignment must be released to one of
        │     the student's own classrooms (mirrors PracticeSetPolicy::attempt)
        │
        ├─ Store the PDF, create Submission (extraction_status: pending)
        │
        ├─ GradeSubmission::dispatch() and redirect back immediately. The rest
        │     runs on the queue: 3 tries, then extraction_status: failed
        │
        ├─ HomeworkPdfExtractionService::extractAnswers($path, $assignment
        │     ->questions - the known question list) - one Gemini call,
        │     transcribes each answer to plain text, flags any it can't
        │     confidently match to a known question
        │
        ├─ For each matched answer (graded first, then saved in one transaction
        │     together with extraction_status: extracted):
              HomeworkDiagnosisService::diagnose($question, $text) - the
                 EXACT SAME call the single-question path uses, unchanged
              → Attempt::create([..., submission_id, ...]) per question
              → attempt_diagnoses per gapped dimension, same as today
        │
        └─ NotifySubmissionGraded::dispatch() → n8n → teacher email
```
Unmatched answers are skipped entirely - no Attempt is created for them.
This is Phase 3 of the multi-question PDF homework redesign - see
`PROGRESS.md` for the full phase-by-phase log.

## Request flow: teacher validates a submission (implemented)

```
GET /teacher/submissions/{submission}
        │
SubmissionController::show()
        │
        ├─ SubmissionPolicy::validate - teacher must teach the classroom the
        │     submission's assignment was released to (governs both show()
        │     and validate() below)
        │
Inertia::render('SubmissionReview', ['submission' => [...], 'attempts' =>
  [...] - each with the question body, transcribed answer, AI score/
  confidence, needs_review/sampled_for_audit flags, and per-dimension
  diagnoses with quoted evidence])
        │
SubmissionReview.jsx renders one editable score field per question,
defaulting to the AI's proposed score, and flags needs_review/
sampled_for_audit attempts distinctly

POST /teacher/submissions/{submission}/validate {scores: [{attempt_id, teacher_score}]}
        │
SubmissionController::validate()
        │
        ├─ SubmissionPolicy::validate (same rule as show())
        │
        ├─ attempts.teacher_score set per row (Attempt::effectiveScore()
        │     then returns this instead of the AI's score)
        │
        └─ submissions.teacher_validated_at = now()
```
This is Phase 4 of the multi-question PDF homework redesign - see
`PROGRESS.md` for the full phase-by-phase log and what's still missing
(teacher assignment-creation-from-PDF flow). Teachers open it from the
submission list on `/dashboard` or from the n8n email.

## What's designed but not built
- **Method/Reasoning on the quiz path** — the optional "explain your answer"
  field, and the queued job to diagnose it, don't exist yet.
- **Real item bank, partially filled** — `TopicGroundingSeeder`/
  `HomeworkQuestionSeeder`/`PhysicsQuizQuestionSeeder` seed real grounding,
  the two `Skill` rows + their prerequisite edge, a real homework `Question`,
  and now 5 real MCQ `Question`s (with real `distractor_map` values, tagged
  to the same vocabulary as the homework path) across both seeded topics
  (4.1, 4.2) - bundled into 2 real `PracticeSet`s via `PracticeSetSeeder`.
  The quiz seeder still generates Math for the pre-existing baseline, and
  only 2 of the Heat chapter's 4 real sections (4.1, 4.2) have any grounding
  at all - 4.3/4.4 aren't extracted yet.
- **No teacher-facing "create an Assignment"/"create a PracticeSet" flow** —
  `AssignmentSeeder`/`PracticeSetSeeder` are the only ways one gets made today.
  For Assignment specifically, the planned flow uploads a PDF through
  `HomeworkPdfExtractionService::extractQuestions()` for teacher review
  first (Phase 5 of the multi-question redesign, not built).
- **No AI accuracy calibration yet** — `php artisan homework:evaluate`
  (`HomeworkGoldSetScorer` + `HomeworkEvaluationRunner`, scored against
  `HeatHomeworkGoldSet`) is built and tested but hasn't been run against the
  live API yet, so `CONFIDENCE_THRESHOLD` (0.6) is still a guess and the
  PNG-vs-PDF inconsistency finding below is still unconfirmed at scale.

`GEMINI_API_KEY` is configured and the homework path (both typed answers via
`diagnose()` and photographed/PDF answers via `diagnosePhoto()`) has been
manually verified live against real submitted files - see `PROGRESS.md` for
the specific runs, including a real PNG-vs-PDF consistency discrepancy found
in the process.

See `PROGRESS.md` for the concrete build order on all of the above.

## Dev workflow
- TDD: load the `tdd` skill before writing implementation code (red → green,
  one seam at a time, confirmed with the user before each new seam)
- Run tests: `./vendor/bin/sail artisan test --compact`
- Format: `./vendor/bin/sail pint --dirty --format agent`
- Build frontend: `./vendor/bin/sail npm run build`
- Use `./vendor/bin/sail <command>`, not bare `docker exec celik-laravel.test-1
  <command>` — the latter defaults to root, but the real web process runs as
  `sail`; running as root creates root-owned files the real process then
  can't write to (this caused a real 500 once - see `PROGRESS.md`)
