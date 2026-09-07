# Project Progress

Status as of this session. See `engine.md` for the full target design (the
multi-dimension diagnosis workflow this project is building toward).

## Finished

### Pre-existing baseline (committed before this session)
A working single-dimension adaptive quiz app:
- Models: `Subject`, `Skill`, `Classroom`, `Question`, `Attempt`, `Mastery`, `User` (role: teacher|student)
- `AdaptivePracticeService` — Elo/IRT-style mastery score per (student, skill), weakest-skill-first question selection
- `PracticeController` — student MCQ flow (`GET/POST /practice`)
- `DashboardController` — teacher mastery heatmap + a "skills below 40% mastery" gap list (`GET /dashboard`)
- Auth (register/login, role-gated routes), Inertia + React UI, `LearningDataSeeder` (demo Math data)

### This session
- **Environment**: PHP 8.5 / Composer / Laravel installed; found and switched to the project's existing Sail/Docker stack (originally `test_example-laravel.test-1` + MySQL, renamed to `celik-laravel.test-1`/`celik-mysql-1` later this session via `COMPOSE_PROJECT_NAME=celik` in `.env`) for all `artisan`/test runs.
- **Laravel Boost** installed — `CLAUDE.md` now holds tailored guidelines (PHPUnit, Pint-on-dirty, feature-tests-first convention).
- **TDD skill** (mattpocock) installed to `.claude/skills/tdd`, and memorized to load before development work on this project going forward.
- **Multi-dimension diagnosis engine, quiz path only** — built test-first, 3 TDD cycles, all green:
  - `questions.distractor_map` + `questions.kssm_code` columns — a wrong option is pre-mapped to a misconception at authoring time, not inferred by AI at answer time
  - `DiagnosisService::diagnose()` — pure, unit-tested: returns Conceptual (misconception tag) + Curriculum (KSSM code, mastered) for a given answer, no AI call
  - `attempt_diagnoses` table (`AttemptDiagnosis` model) — one row per (attempt, dimension, tag), written only when that dimension surfaces a gap
  - `PracticeController::store()` wired to persist diagnoses on wrong answers
  - `GapSummaryService::forStudents()` — aggregates `attempt_diagnoses` into class-wide `(dimension, tag) → student_count`, matching the query design in `engine.md` §6
  - `DashboardController` exposes `gapSummary`; `Dashboard.jsx` renders it as a "Diagnosed gaps" panel (misconception/standard + how many students share it)
- `engine.md` — full workflow doc, updated this session (quiz path implemented; homework path now implemented end-to-end for typed answers; scope narrowed to Physics Form 4+5 only in §7; prerequisite graph designed in §8, not built)
- Test suite: **27 passing, 100 assertions**; Pint clean; frontend builds clean

### Homework path (typed answers) — built test-first, 5 TDD cycles, all green
Per `engine.md` §5:
- `questions.type` (`mcq` | `homework`), `options`/`correct_option` now nullable — same table serves both paths, matching the shared-vocabulary design in `engine.md` §6
- `attempts` gains `source` (`quiz`|`homework`), `raw_answer`, `confidence`, `needs_review`; quiz-only columns (`difficulty_at_attempt`, `mastery_before/after`, `is_correct`) now nullable so a homework attempt doesn't need fake values for them
- `TopicGrounding` model + `topic_groundings` table — syllabus excerpt + rubric + tag vocabulary per KSSM code, hand-authored, looked up via `forKssmCode()` (the "small lookup table for the 2 topics in scope" from `engine.md` §5)
- `HomeworkDiagnosisService::diagnose()` — grounded prompt (RAG) + an LLM function-calling call forcing a fixed JSON schema (not free-text parsing), via Laravel's built-in `Http` facade — **no new composer dependency added**, per the no-dependency-changes-without-approval rule (originally built against Anthropic; migrated to Gemini's `generateContent` API later this session — see below)
- Confidence gating: `HomeworkDiagnosisService::CONFIDENCE_THRESHOLD` (0.6) — below it, `needs_review` is set and `attempt_diagnoses` are withheld rather than written as if certain
- `HomeworkController::store()` (`POST /homework`) — persists the attempt, writes `attempt_diagnoses` per gapped dimension (conceptual/curriculum/method/reasoning) when not flagged for review
- Tests: `HomeworkSubmissionTest`, `TopicGroundingLookupTest`, `HomeworkDiagnosisServiceTest`, `HomeworkAttemptDiagnosisTest` — all use `Http::fake()`, no real API key or network call needed

### Homework AI-validation hardening + prerequisite graph — built test-first, 9 TDD cycles, all green
- **Tag vocabulary enforced as a schema enum** — `conceptual_tag`/`method_issue`/`reasoning_issue` are constrained to each `TopicGrounding.tag_vocabulary` dimension in the function declaration's schema, not just told to the model in the prompt — prevents near-duplicate tags fragmenting `GapSummaryService`'s aggregation
- **Deterministic objective-answer check** — `questions.expected_answer` (nullable); when set, `HomeworkDiagnosisService::objectiveCheck()` overrides the LLM's `correct` claim rather than trusting it, per `engine.md` §5
- **Quoted evidence** — `conceptual_evidence`/`method_evidence`/`reasoning_evidence` fields on the function schema, persisted to `attempt_diagnoses.evidence`, so a teacher can spot-check a diagnosis without re-reading the whole answer
- **Real textbook-grounded content** — `TopicGroundingSeeder` seeds two real `TopicGrounding` rows (`4.1` Thermal Equilibrium, `4.2` Specific Heat Capacity) sourced via OCR from the actual MOE/Sasbadi Form 4 Physics KSSM textbook (Theme 3 "Heat"), replacing the single hand-typed placeholder sentence. Note: `engine.md` §8's "Heat depends on Particle Model" example doesn't correspond to a real Form 4 Physics chapter — there's no "Particle Model of Matter" topic in this book — so the real edge authored is **Specific Heat Capacity depends on Thermal Equilibrium** instead, both within the real Heat chapter
- **Self-consistency double-call** — a second LLM call runs when the first is otherwise trusted (confidence ≥ threshold); disagreement on `correct`/`conceptual_tag` forces `needs_review`, catching confidently-wrong diagnoses that confidence gating alone can't
- **Always-sample audit** — `attempts.sampled_for_audit` (10% via `HomeworkDiagnosisService::rollForAudit()`), independent of `needs_review` — a trusted diagnosis can still be flagged for teacher spot-check, the mechanism that would eventually let `CONFIDENCE_THRESHOLD` be calibrated against real grading
- **Prerequisite/skill graph, built** (`engine.md` §8) — `skill_prerequisites` table, `Skill::prerequisites()`/`dependents()`; `GapSummaryService::forStudents()` annotates a gapped row as `root_cause`/`symptom` (with `related_skill_id`) when two gapped skills share a prerequisite edge; `AdaptivePracticeService::chooseSkill()` biases toward a weak prerequisite over its weak dependent. Seeded edge: Specific Heat Capacity → Thermal Equilibrium
- Tests: `PhysicsGroundingSeederTest`, `SkillPrerequisiteTest`, `GapSummaryServiceTest`, `AdaptivePracticePrerequisiteTest`, plus extensions to `HomeworkDiagnosisServiceTest`/`HomeworkAttemptDiagnosisTest`/`DashboardGapSummaryTest`
- Test suite: **52 passing, 157 assertions**; Pint clean

### Frontend redesign track — separate contributor, committed to `main` (not this session's work)
While the above was being built (still uncommitted), a teammate (`dannyenyol`) merged three PRs redesigning the student-facing UI, unrelated to the diagnosis-engine backend:
- **Student dashboard** (`ed63529`/#2) — `StudentDashboardController::index()`, `GET /student/dashboard`, `StudentDashboard.jsx` + `Components/Student/{StudentDashboard,StudentSidebar,DashboardComponents}.jsx`, `dashboardData.js` (mock data)
- **Submit-homework page** (`6aaa739`/#3) — `StudentDashboardController::submitHomework()`, `GET /student/homework/submit`, `SubmitHomework.jsx` — a full drag/drop-or-camera upload UI, assignment picker, and a "released homework" list with findings/practice callouts
- **Student work pages** (`3ea143c`) — `StudentWorkPages.jsx` + `studentWorkData.js` (marked work, upcoming assignments, due-soon summary)

**Important: none of this is wired to the real backend yet.** Every list/stat on these pages is hardcoded mock data (`dashboardData.js`, `studentWorkData.js`, the `releasedHomework` array inline in `SubmitHomework.jsx`), and the upload form's submit button is disabled-by-design with the page footnote *"Sample submission interface · uploading is not connected yet."* It does not call `POST /homework` or read anything from `HomeworkDiagnosisService`/`GapSummaryService`. So the "no UI" gap noted below is now more precisely "a UI shell exists but isn't connected" — see Unfinished.

Also added in this track: `tests/student-dashboard.test.mjs` (a `.mjs` test file with no test runner wired into `package.json` — `npm run test` doesn't exist yet, so it's unclear how/whether this currently runs) and some `AuthenticationTest`/`RegistrationTest` PHPUnit changes. Full PHPUnit suite as of this update: **58 passing, 198 assertions**.

### Migrated from Anthropic to Gemini
`HomeworkDiagnosisService` was originally built against Anthropic's Messages API (tool-use); later this session it was rewritten end-to-end to call Gemini's `generateContent` API instead (function calling, `toolConfig.functionCallingConfig.mode: 'ANY'` restricted to one function), for Gemini's no-cost free tier. The public `diagnose()` contract didn't change, but everything underneath it did:
- `config/services.php`: `anthropic` → `gemini` (`GEMINI_API_KEY`, `GEMINI_MODEL` - default model corrected later this session, see below)
- Request shape: Anthropic's `messages`/`tools`/`tool_choice` → Gemini's `contents`/`parts`/`tools[].functionDeclarations`/`toolConfig`
- Schema nullability: Anthropic's `type: ['string','null']` → Gemini's `type: 'string', nullable: true` (enums no longer include `null` in the array — `nullable` handles that separately)
- Response parsing: Anthropic's `content[].tool_use.input` → Gemini's `candidates[0].content.parts[].functionCall.args`
- All `Http::fake()` fixtures across `HomeworkDiagnosisServiceTest`/`HomeworkAttemptDiagnosisTest`/`HomeworkSubmissionTest` rewritten to Gemini's response shape; full suite re-verified green (52 passing) immediately after the rewrite
- **Still no real API key configured either way** — this has only ever been exercised against `Http::fake()`, same gap as before the migration, just against a different provider
- **Known tradeoff, accepted deliberately**: third-party benchmarks found Claude has better tool-use compliance and more consistent rubric-grading than Gemini Flash (the only free-tier model), but Gemini's free tier was chosen for zero-cost demo access. Worth revisiting once real accuracy testing (see Unfinished, below) shows whether that tradeoff actually costs grading quality on this project's content

### Homework submission wired end-to-end (later session) — TDD, real Modules/Assignments
`/student/homework/submit` no longer shows mock data or a raw question-body
dropdown — real submission, real AI diagnosis, live-verified:
- **`Module`** (new model/table) — curriculum grouping ("Heat"), scoped to
  `Subject` (not `Classroom` — `Skill` is already a shared subject-level bank
  reused across every classroom of that subject; a classroom-owned Module
  would break that sharing the moment a second classroom of the same subject
  exists)
- **`Assignment`** (new model/table) — a teacher's release of one `Question`
  to one `Classroom`: `title` ("Homework 1"), `due_at`, unique `question_id`
  (one assignment per question, DB-enforced). This is the link between
  "homework a student can see" and "their classroom" that `Question`
  deliberately doesn't have itself
- `skills.module_id` (nullable FK) added; `Classroom`/`Question` both gained
  `assignments()` for symmetry
- `AssignmentSeeder` — creates the **first-ever Physics classroom**
  ("Form 4 Physics"; previously only a Math classroom existed anywhere),
  its own dedicated demo teacher/student (`physics.student1@example.com` /
  `password` — deliberately not reusing the Math cohort, to avoid the
  single-classroom-assuming dashboard banner and the assignment list ever
  pointing at two different classrooms for the same student), a "Heat"
  Module containing both seeded Skills, and one real "Homework 1" assignment
  releasing the seeded 4.1 spoon question. Idempotent, wired into
  `DatabaseSeeder`
- `StudentDashboardController::submitHomework()` now exposes `assignments`
  (scoped to the student's own classroom) instead of `homeworkQuestions`
  (every homework Question system-wide)
- `HomeworkController::store()` validation tightened: `exists:questions,id`
  → `exists:questions,id,type,homework`
- `SubmitHomework.jsx` rewritten: assignment cards (title only, no question
  body until clicked), an "already submitted" badge, full diagnosis display
  including quoted evidence per dimension (conceptual/method/reasoning, not
  just conceptual as before) both on a fresh submission and in submission
  history
- **Deferred, flagged, not built**: no server-side check that a submitted
  `question_id` is actually assigned to the student's own classroom — only
  the UI stops surfacing unassigned ones. Doing this properly means touching
  4 existing tests (`HomeworkSubmissionTest`, `HomeworkAttemptDiagnosisTest`,
  `HomeworkSubmissionFeedbackTest`, `HomeworkPhotoSubmissionTest`) that all
  post a bare `Question` with no `Assignment`/`Classroom` set up
- Tests: `AssignmentSeederTest` (new), `SubmitHomeworkPageTest` (rewritten,
  includes a negative case: a homework Question with no Assignment for the
  student's classroom must not appear)
- **Real permissions bug found and fixed**: `storage/app/private/homework-photos`
  was root-owned from an earlier root-run `docker exec` session (not `sail`,
  the user the real web process runs as), causing a live 500
  (`file_get_contents(): ... Is a directory`) the first time a real photo
  upload was attempted. Fixed via `chown`; going forward use
  `./vendor/bin/sail <command>`, not bare `docker exec celik-laravel.test-1`
  — see the dev-workflow note in `architecture.md`
- **Live-verified against the user's own real test files**
  (`/mnt/c/Users/azimr/Downloads/test question/` — three image-only,
  scanned/photographed PDFs, confirmed via PDF-object inspection: 0 font
  objects, 2 image objects each) submitted through the real `POST /homework`
  endpoint end-to-end (real login session, real file upload, real Gemini
  call): `correct_answer.pdf` → correct, no gaps; `misconception_answer.pdf`
  → tagged `metal_inherently_colder` with quoted evidence; `ambiguous_answer.pdf`
  → self-consistency double-call disagreed, correctly flagged `needs_review`
- Test suite: **65 passing, 244 assertions**; Pint clean; frontend builds clean

### RBAC / per-resource authorization — built test-first, 2 TDD cycles, all green
A teammate's pulled commit added `.ai/rules/` and a role-aware login (typed
role checked against the DB user's actual role). Separately, this closed the
authorization gap flagged above ("no server-side classroom/assignment
authorization"):
- `QuestionPolicy::submitHomework(User, Question)` — true only if an
  `Assignment` ties the question to one of the student's own classrooms;
  wired into `HomeworkController::store()` via `$this->authorize()`, 403
  otherwise. Previously any question_id could be submitted by any student.
- `QuestionPolicy::attemptQuiz(User, Question)` — true only if the
  question's skill's subject matches one of the student's own classrooms;
  wired into `PracticeController::store()` the same way. Same class of gap,
  quiz side (`PracticeController::index()`'s own question selection was
  already correctly scoped via the classroom's subject — only `store()` had
  the hole).
- Base `Controller` now uses `AuthorizesRequests` (standard Laravel
  convention); both Policies are auto-discovered, no provider registration
  needed.
- Investigated but **not built**: a `ClassroomPolicy` for
  `DashboardController::index()` — its `?classroom=` query param is already
  scoped to `$teacher->classroomsTeaching()` only, so passing another
  teacher's classroom id silently falls back to the requesting teacher's own
  rather than leaking data. Confirmed via a regression test
  (`DashboardAuthorizationTest`, green with no code change) rather than
  adding a Policy for a gap that doesn't exist.
- Fixture fallout: `HomeworkSubmissionTest`, `HomeworkSubmissionFeedbackTest`,
  `HomeworkAttemptDiagnosisTest`, `HomeworkPhotoSubmissionTest`,
  `PracticeAttemptDiagnosisTest` all previously posted a bare `Question` with
  no `Assignment`/`Classroom` — updated to enroll the student /
  release the assignment first, per each new Policy's rule.
- Tests: `HomeworkAuthorizationTest`, `PracticeAuthorizationTest`,
  `DashboardAuthorizationTest` (new)
- Test suite: **70 passing, 274 assertions**; Pint clean

### Frontend fully wired to the backend, no hardcoded data remaining — built test-first
Closes the "frontend redesign track" gap noted above (mock data since
`ed63529`/`6aaa739`/`3ea143c`) and a real regression found along the way:

- **Real bug found and fixed**: a later teammate commit (`89a07a8`) had
  actually broken `SubmitHomework.jsx` — it referenced undefined variables
  (`file`, `visibleHomework`) and never rendered the real `assignments`/
  `pastAttempts` props the backend already sent, so the page would throw at
  runtime. Rewritten to actually use those props: assignment picker (with
  "already submitted" indicator), typed/photo mode toggle, real diagnosis +
  full submission-history display, real derived stats.
- **`PracticeSet`** (new model/tables: `practice_sets`,
  `practice_set_questions`, nullable `attempts.practice_set_id`) — a
  teacher's release of several `Question`s to one classroom, replacing
  `Practice.jsx`'s hardcoded `practiceSets`/`localQuestions` arrays.
  `PracticeSetService` computes real per-student progress and surfaces that
  student's *own* open diagnoses on the set's skill as "gaps" (not a static
  authored tag list — two students on the same set can be struggling with
  different things). `PracticeSetPolicy::attempt` (403 unless released to
  the student's own classroom) + `QuestionPolicy::attemptQuiz` gate
  `POST /practice`'s new optional `practice_set_id`.
- **Real Physics quiz item bank started**: `PhysicsQuizQuestionSeeder` (5
  real MCQ questions, real `distractor_map` tags shared with the homework
  vocabulary) + `PracticeSetSeeder` (2 real `PracticeSet`s releasing them to
  the Physics classroom) — previously **zero** quiz-type Questions existed
  for Physics at all, only Math.
- **Rubric-based homework mark** — `HomeworkDiagnosisService`'s Gemini
  function schema now also returns `score` (out of a fixed `MAX_SCORE=20`),
  persisted on `attempts.score`/`max_score`. Distinct from `is_correct`: a
  partially-correct answer can score above zero.
- **`LearningGapService`** (new) — replays a student's own
  `attempt_diagnoses` per skill in order to classify each (dimension, tag)
  as open or cleared (cleared the first time a later attempt on that skill
  stops reproducing it, can reopen later), replacing every hardcoded number
  in `dashboardData.js`: `stats` (submitted/gapsCleared/totalGaps/
  practiceWaiting), the open+recently-cleared `learningGaps` list, and the
  "gaps cleared per homework" bar chart.
- **`StudentDashboardController::index()`** now exposes `dashboardData`
  (from `LearningGapService` + a derived `nextPractice`/`nextDue`) and also
  `assignments`/`pastAttempts` (reusing `submitHomework()`'s existing
  helpers), so the "Due Soon" and "Marked Work" sidebar tabs
  (`StudentWorkPages.jsx`) render real upcoming assignments and a real past
  submission — including its diagnosis evidence highlighted directly inside
  the student's own answer text via substring match, since evidence is
  always an exact quote (no separate highlight-position data needed).
- **`dashboardData.js`/`studentWorkData.js` deleted** — no mock data files
  remain anywhere in the frontend.
- Tests: `PracticeSetsPageTest`, `PracticeSetAttemptTest`,
  `PracticeSetSeederTest`, `LearningGapServiceTest`,
  `StudentDashboardPageTest` extension to `HomeworkDiagnosisServiceTest`/
  `HomeworkAttemptDiagnosisTest`
- Test suite: **92 passing, 494 assertions**; Pint clean; frontend builds
  clean

### Not yet committed
All of the above is uncommitted in the working tree.

## Unfinished

### Live API — configured and manually verified this session
`GEMINI_API_KEY` is now set and `HomeworkDiagnosisService` has been exercised against the real Gemini API (via `tinker`, not a test - the suite stays on `Http::fake()`). Two live calls against the real seeded `4.1` Thermal Equilibrium content both came back correct: a misconception-laden answer was flagged incorrect, tagged `metal_inherently_colder` (our authored vocabulary), with real quoted evidence and confidence 1.0; a correct answer was flagged correct with no gap.

**Model note**: `config('services.gemini.model')` defaults to `gemini-3.5-flash-lite`, not the original `gemini-2.5-flash` guess - that model (and `gemini-2.5-flash-lite`) both 404 as "no longer available to new users" despite being listed by the models endpoint, and `gemini-flash-latest` (Google's "current recommended" alias) was consistently 503 (overloaded) across several retries at test time. `gemini-3.5-flash-lite` is what Google's own 404 response pointed at as the replacement. Override via `GEMINI_MODEL` if this changes again.

### Photographed/handwritten homework path — built test-first, 2 TDD cycles, all green
Per `engine.md` §5 ("Claude vision reads the handwritten work directly - no separate OCR step"), now implemented against Gemini instead:
- `HomeworkDiagnosisService::diagnosePhoto(Question, string $filePath)` — sends the file as inline image/PDF data (`inlineData` part) alongside the grounding prompt, reusing the same function-declaration schema, self-consistency double-call, and audit sampling as `diagnose()` (both funnel through a shared private `runDiagnosis()`). MIME type is derived from file extension (`jpg`/`jpeg`→`image/jpeg`, `png`→`image/png`, `pdf`→`application/pdf`), not file content, since the controller only ever stores an upload after validating its extension
- `HomeworkController::store()` now accepts `photo` (file, `mimes:jpg,jpeg,png,pdf`, max 10MB) as an alternative to `answer` (`required_without` each other) — stores the file via `Storage`, calls `diagnosePhoto()`, persists `attempts.photo_path` instead of `raw_answer`
- Tests: `HomeworkPhotoSubmissionTest` (uses `Storage::fake('local')` - **the tests originally did not fake storage and were writing real files into `storage/app/private/homework-photos/` on every run**, caught and fixed by inspecting real dev storage after a live run, not by any test failure)
- **Verified live** against real generated sample files (`convert`/ImageMagick, a rendered image of a physics answer, and a PDF wrapping that same image) through both `diagnosePhoto()` directly and the full `HomeworkController::store()` flow with a real `UploadedFile` - reads text directly from both formats correctly, no OCR step
- **Real finding, not resolved**: the same rendered content submitted as PNG vs. PDF got *different* diagnoses in one live comparison - the PDF call caught the `metal_inherently_colder` misconception with quoted evidence (confidence 0.95), the PNG call graded the answer wrong but missed the tag entirely (`gap: false, tag: null`). Each individual call's own self-consistency check passed (`needs_review: false` both times), but the two separate submissions of equivalent content disagreed - this is the "wide variability across equivalent inputs" weakness flagged for Gemini Flash in the earlier model-comparison research, now observed directly on this project's own content, not just cited from a third-party benchmark. Strengthens the case for building `homework:evaluate` before trusting this path at demo time

### Containers and package identity renamed to Celik
The Docker/Sail containers and Composer package name still carried the scaffold's original `test_example` identity even though the app has been called "Celik" since before this session (`APP_NAME=Celik`, `README.md`). Fixed:
- `.env`: added `COMPOSE_PROJECT_NAME=celik` (Docker Compose project names must be lowercase - `Celik` is rejected: *"invalid project name... must consist only of lowercase alphanumeric characters, hyphens, and underscores"*). Containers are now `celik-laravel.test-1` / `celik-mysql-1` (previously `test_example-laravel.test-1` / `test_example-mysql-1`)
- `composer.json`: `"name"` changed from the default `laravel/laravel` scaffold value to `celik/celik`, description updated to describe the actual app
- The old `test_example-*` containers were brought down (`docker compose -p test_example down`) and the new `celik-*` ones brought up fresh, then `migrate --seed` re-ran rather than migrating the old MySQL volume over - everything in the DB was reproducible from seeders, so this was lower-risk than a dump/restore. `.env` (with `GEMINI_API_KEY`) is bind-mounted, not baked into the container, so it carried over automatically
- The `.test` in the container name (`celik-laravel.test-1`) is unrelated to PHPUnit - it's Sail's default compose service key (`services: laravel.test:`), historically paired with a local `.test` domain convention. Not renamed (would also touch `compose.yaml` and every `docker compose exec laravel.test` command in `README.md`) - flagged for you to decide on separately
- Full suite re-verified on the new containers: 56 passing, Pint clean on this session's changes

### Homework path — remaining gaps
- **Wired and working now** (see "Homework submission wired end-to-end" above) — the remaining gap is narrower: no teacher-facing view of `needs_review`/`sampled_for_audit` attempts anywhere. Server-side classroom/assignment authorization is now built (see "RBAC / per-resource authorization" above).
- **No teacher-facing way to create an Assignment** — `AssignmentSeeder` is the only way one gets made; a real "teacher releases homework to their class" flow (form + controller) doesn't exist yet
- **Method/Reasoning issues are still open-vocabulary in the seeded content** — the schema now *supports* an enum per dimension, but `TopicGroundingSeeder`'s `method`/`reasoning` tag lists are short and untested against real model output at volume
- **`homework:evaluate` gold-set harness — built test-first, not yet run live** (this session): `HomeworkGoldSetScorer` (pure, per-diagnosis scoring against a gold label — `tests/Unit/HomeworkGoldSetScorerTest.php`), `HomeworkEvaluationRunner` (diagnoses each gold entry `--repeats=3` times, not once, specifically to catch disagreement *across* separate submissions of equivalent content — the failure mode the PNG-vs-PDF finding above exposed, which the service's own internal self-consistency double-call can't see since that only compares two calls *within* one submission — `tests/Unit/HomeworkEvaluationRunnerTest.php`), and `HeatHomeworkGoldSet` (12 hand-authored, construction-based entries across 4.1/4.2 — each answer written with its gold label known by design, never inferred by asking any LLM, including a different one, to grade it — `tests/Unit/HeatHomeworkGoldSetTest.php`). `php artisan homework:evaluate` (`app/Console/Commands/EvaluateHomeworkDiagnosis.php`) reports accuracy, per-dimension tag-match, confidence calibration (mean confidence when matched vs. mismatched), and cross-call consistency. Wiring itself is feature-tested against a faked `HomeworkDiagnosisService` (`tests/Feature/EvaluateHomeworkDiagnosisCommandTest.php`) — **not yet run against the live API by design** (deliberately not part of the PHPUnit suite, same reasoning as the live `tinker` verification above), so there's still no real accuracy number yet, just the tooling to produce one. The confidence threshold (0.6) stays a guessed default until that live run happens.

### Method + Reasoning dimensions on the quiz path — not started
These only apply to free-text responses (the optional "explain your answer" field on a quiz item). Currently:
- No "explain your answer" field exists in the quiz UI or `PracticeController`
- No queued job exists to run this diagnosis asynchronously (meant to be non-blocking per `engine.md` §4)
- The queue and its worker exist (`app/Jobs`, `queue` service); this job does not

### Content — real grounding exists, item bank now partially filled
- The pre-existing seeder still generates arithmetic Math questions for the baseline, not Physics
- `TopicGroundingSeeder` seeds real, textbook-sourced grounding for 2 KSSM codes (`4.1`, `4.2`) and the 2 real `Skill` rows + prerequisite edge to go with them. `HomeworkQuestionSeeder` (1 homework `Question` per topic) and `PhysicsQuizQuestionSeeder` (5 real MCQ `Question`s with real `distractor_map` tags, bundled into 2 `PracticeSet`s) now give both seeded topics real quiz content too — previously none existed
- Scope is now finalized as Physics, Form 4 + Form 5 SPM (KSSM) only — see `engine.md` §7 — but only 2 of the ~16 topics across that scope (4.1, 4.2) have any real authored content; the rest of the syllabus (4.3/4.4 and Themes 1/2/4) still needs extraction + authoring

### Other gaps
- `PracticeController::index`'s `practiceSets` prop and `StudentDashboardController::index` are now covered (`PracticeSetsPageTest`, `StudentDashboardPageTest`), but the original pre-existing mastery/next-question selection logic inside `PracticeController::index` still has no direct test of its own (only diagnosis-related behavior added across sessions is tested)
- No offline/PWA work (mentioned as a stretch goal, not attempted)
- No resilience/retry testing for AI calls against real network failures (only against faked HTTP responses)

### `homework:evaluate` gold-set harness — built, awaiting a live run
See "Homework path — remaining gaps" above for the full description. Test suite: **80 passing, 385 assertions**; Pint clean.
- New: `app/Services/HomeworkGoldSetScorer.php`, `app/Services/HomeworkEvaluationRunner.php`, `app/Console/Commands/EvaluateHomeworkDiagnosis.php`, `database/seeders/GoldSets/HeatHomeworkGoldSet.php`
- Modified: `database/seeders/HomeworkQuestionSeeder.php` (added the missing real 4.2 homework question, sourced from the specific-heat-capacity formula, so the gold-set has a real question to target for both seeded topics)
- **Next**: run `sail artisan homework:evaluate` against the live API and review the actual accuracy/tag-match/confidence-calibration/consistency numbers before trusting this path further or expanding content (see plan below)

### Multi-question PDF homework — Phases 1-3 built test-first, in progress
Redesign in progress per a full plan (`~/.claude/plans/nested-wishing-dijkstra.md`
on the session's machine — not in-repo, so treat this PROGRESS.md section as
the authoritative portable record, not that file). Target shape: a teacher
uploads a PDF of several questions (objective and/or subjective) for an
Assignment; a student submits one answered PDF back; AI grades every
question and proposes a score; a teacher validates/overrides before it
counts. Scope stays Form 4 Physics Heat only (4.1-4.4). Key design decision:
AI suggests kssm_code/score, a teacher can always change it - never trusted
as final without review, consistent with the confidence-gating/audit-sampling
principle already used everywhere else in this codebase.

**Phase 1 - schema (done)**: `Assignment` is now many-to-many with
`Question` via a new `assignment_questions` pivot `(assignment_id,
question_id, order)`, mirroring the already-proven `PracticeSet` ↔
`practice_set_questions` pattern - `assignments.question_id` (the old
one-question-per-assignment column) is dropped entirely, not kept
alongside, since nothing using it was committed yet. New `Submission` model/
table `(user_id, assignment_id, photo_path, extraction_status, teacher_
validated_at)` - one row per (student, assignment) PDF upload, grouping the
several per-question `Attempt`s a multi-question submission produces via
new `attempts.submission_id` (nullable FK, mirrors `practice_set_id`). New
`attempts.teacher_score` (nullable) sits alongside the existing AI-produced
`score`; `Attempt::effectiveScore()` returns `teacher_score ?? score`.
Fallout fixed: `AssignmentSeeder` (attaches via pivot instead of setting
`question_id`), `StudentDashboardController` (`assignmentsFor()`/
`nextDueFor()` adapted to `questions->first()` - the *existing*
single-question UI keeps working unchanged until Phase 6 replaces it), and
6 test fixtures across `AssignmentSeederTest`/`SubmitHomeworkPageTest`/
`HomeworkSubmissionTest`/`HomeworkSubmissionFeedbackTest`/
`HomeworkPhotoSubmissionTest`/`HomeworkAttemptDiagnosisTest`. New:
`AssignmentQuestionsTest`. **93 passing.**

**Phase 2 - `HomeworkPdfExtractionService` (done)**: a new service, two
Gemini `generateContent` calls (same schema-forced function-calling pattern
as `HomeworkDiagnosisService`), pure extraction/transcription, never grading
judgment. `extractQuestions(string $filePath)` (teacher side, blank PDF) →
`list<{order, question_text, suggested_kssm_code}>` - `suggested_kssm_code`
is schema-enum-constrained to `['4.1','4.2','4.3','4.4']`, a proposal only,
never trusted as final. `extractAnswers(string $filePath, array $questions)`
(student side, answered PDF, given the assignment's already-known question
list as context) → `list<{order, student_answer_text, matched}>` -
`matched: false` means the model couldn't confidently tie an answer region
to a known question, surfaced rather than silently guessed. Tests:
`HomeworkPdfExtractionServiceTest` (3 cases, `Http::fake()`, same convention
as `HomeworkDiagnosisServiceTest`). **96 passing.**

**Phase 3 - student submission flow (done)**: `POST /submissions`
(`SubmissionController::store()`), authorized via new
`SubmissionPolicy::create` (mirrors `PracticeSetPolicy::attempt`'s
classroom-scoped check, since `Assignment` - like `PracticeSet` - belongs
directly to one classroom). Stores the PDF, creates a `Submission`, calls
`extractAnswers()`, then for each **matched** answer calls
`HomeworkDiagnosisService::diagnose()` **completely unchanged** (the plan's
key scope-reducer: extraction transcribes to plain text, so the existing
text-based grading pipeline - grounding lookup, objective check, schema-
forced Gemini call, self-consistency, audit sampling - needed zero changes),
creating one `Attempt` per question tagged with `submission_id`. Unmatched
answers are skipped, not graded. Tests: `SubmissionAuthorizationTest`,
`SubmissionGradingTest` (happy path + unmatched-answer case). **99 passing,
Pint clean.**

**Phase 4 - teacher validation UI (done)**: `GET /teacher/submissions/{submission}`
(`SubmissionController::show()`) renders a new `SubmissionReview.jsx` page -
each graded question's transcribed answer, AI score/confidence, needs_review/
sampled_for_audit flags, and per-dimension diagnoses with quoted evidence,
plus an editable score field per question defaulting to the AI's proposal.
`POST /teacher/submissions/{submission}/validate` (`SubmissionController::validate()`)
sets `attempts.teacher_score` per row and `submissions.teacher_validated_at`.
New `SubmissionPolicy::validate` (teacher must teach the assignment's
classroom) gates both actions - the same authorization boundary governs
viewing and overriding. Tests: `SubmissionValidationAuthorizationTest`,
`SubmissionReviewPageTest`, `SubmissionValidationTest`. **103 passing, Pint
clean, frontend builds clean.**

**Phase 6 - student PDF page (done, 2026-11-24)**: `SubmitHomework.jsx` posts an
assignment's PDF to `/submissions` and shows its status; photo and typed answers
remain for single-question assignments. Built test-first along with:
- `GradeSubmission` job: queued grading, 3 tries, atomic save, `failed` state
- `NotifySubmissionGraded` job + n8n teacher email (`n8n-workflow.md`)
- `queue` and `n8n` services in `compose.yaml`
- teacher dashboard submission list; students see the teacher's mark
- `.env.example` and `README.md` verified from a clean clone on Sail/MySQL

**134 PHPUnit tests passing.** `tests/practice-sets.test.mjs` and the "marked
work shows answer analysis" test in `tests/student-work-pages.test.mjs` fail:
they were written for older versions of those pages. Not yet verified against
the live Gemini API: a real multi-question PDF.

**Not yet built** (Phase 5, see the plan file for full design):
- **Phase 5 - teacher assignment-creation flow**: upload a blank-question
  PDF → `extractQuestions()` → a review screen (edit text, confirm/change
  the suggested `kssm_code`, set `expected_answer` for objective ones) →
  save as real `Question`s + one `Assignment`. `AssignmentSeeder` remains
  the only way an Assignment gets made until this exists.

## Suggested next steps, in priority order
1. Commit the current working slice (the whole diagnosis-engine backend below "Not yet committed" above is still uncommitted, sitting alongside the teammate's already-merged frontend PRs on `main`) — do this before starting on 2-5, to avoid the two tracks drifting further apart
2. **Run `homework:evaluate` live** against the real Gemini API (harness is built and tested, just not yet executed against the live key) and review the real numbers — specifically re-check the PNG-vs-PDF-style disagreement using the consistency metric
3. **Continue the multi-question PDF homework redesign** (Phase 5 above) - teacher assignment-creation flow
4. Extract and seed the rest of the Heat chapter (4.3 Specific Latent Heat, 4.4 Gas Laws) from the real textbook, expand the quiz MCQ item bank (currently 5 items across 4.1/4.2 only — target 5-8 per topic) across all four Heat topics, and extend the gold-set to cover them
5. Add a real teacher-facing "create an Assignment"/"create a PracticeSet" flow, replacing the seeders as the only way either gets made (server-side classroom/assignment/practice-set authorization on `POST /homework` and `POST /practice` is now built — see "RBAC / per-resource authorization")
6. Add the "explain your answer" field + a queued job stub for Method/Reasoning on the quiz path
