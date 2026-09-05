# Diagnosis Engine — Workflow

## 1. Purpose
Turn every student answer — from a quiz item or a homework submission — into
a structured diagnosis across four dimensions (Conceptual, Curriculum,
Method, Reasoning), so that:
- the student gets an instant correctness signal plus (where available) a
  clear reason for the gap
- per-student mastery scores accumulate per skill, from both quiz and
  homework attempts, into one shared profile
- the teacher dashboard aggregates those diagnoses into a class-wide
  "where is the class struggling, and why" view

## 2. The four diagnostic dimensions (recap)
| Dimension | Question it answers |
|---|---|
| Conceptual Understanding | Does the student hold a specific misconception? |
| Curriculum Mastery | Which KSSM learning standard is/isn't demonstrated? |
| Answering Method | Did they answer in the form the question demanded (state vs explain vs calculate)? |
| Scientific Reasoning | Is the cause→effect logic in their explanation sound? |

## 3. Two entry points, one shared pipeline
Both quiz and homework attempts write into the same two tables, so mastery
scoring and the dashboard don't need to know or care which path an answer
came from:
- `attempts` — one row per submission (student_id, question_id, source: quiz|homework, raw_answer, correct, confidence, created_at)
- `attempt_diagnoses` — one row per (attempt_id, dimension, tag) — e.g.
  `(attempt_1, conceptual, metal_inherently_colder)`

## 4. Quiz workflow (diagnostic, concept-inventory style)
```
Student answers an MCQ diagnostic item (+ optional "explain your answer" text)
        │
        ▼
Synchronous, in-request — no AI call, pure lookup:
   - correct/incorrect: does the choice match the marked-correct option?
   - Conceptual tag: pre-mapped from the chosen distractor (authored at
     item-creation time — this is where the diagnostic "intelligence" for
     MCQ lives, decided once per item, not re-derived per student)
   - Curriculum tag: pulled from the item's own metadata (each item is
     authored against one KSSM code)
        │
        ▼
Response returned to student immediately (target: <200ms, no network dependency)
        │
        ▼ (only if the optional "explain" field was filled in)
Queued job dispatched (Laravel queue, `QUEUE_CONNECTION=database`) →
   LLM call diagnoses Method + Reasoning from the free text
        │
        ▼
attempt_diagnoses updated a few seconds later; dashboard reflects it on
next load — this lag is acceptable since the dashboard is an aggregate view
```
Why no live AI call for Conceptual/Curriculum on MCQ: the diagnostic power
is encoded in the item's design (which distractor maps to which
misconception), the same way a lab test's design — not the reading of it —
carries the diagnostic judgment. AI (or a teacher, or both) does that
judgment once, at authoring time, and it's reused for every student who
picks that option.

## 5. Homework / assignment workflow (open-ended, no pre-mapped structure)
Homework has no distractors to pre-map — a student can write anything — so
all four dimensions need a live, grounded AI call. This path trades
determinism for depth and must lean on retrieval to stay accurate rather
than relying on model memory:

```
Student submits homework answer (typed, or photographed handwritten work)
        │
        ▼
If photographed: the model reads the handwritten work directly from the
image/PDF (no separate OCR step) - implemented against Gemini's inline
file input, see `HomeworkDiagnosisService::diagnosePhoto()`
        │
        ▼
Objective sub-part (if any), e.g. a numeric result: deterministic check,
no AI needed for correctness itself
        │
        ▼
Retrieve grounding context for this question's KSSM code:
   - syllabus excerpt for that learning standard
   - official mark scheme / rubric
   - the topic's misconception + method + reasoning tag lists
   (small lookup table for the 2 topics in scope — no vector DB needed at
   this scale)
        │
        ▼
Single LLM call, grounded in the retrieved text, returns structured JSON:
   { correct, conceptual: {gap, tag}, curriculum: {kssm_code, mastered},
     method: {issue}, reasoning: {issue}, confidence, score,
     student_facing_explanation }
     (score: a rubric-based mark out of a fixed total, not just correct/
     incorrect - a partially-correct answer can still score above zero)
        │
        ▼
If confidence is below threshold → flag for teacher review instead of
silently trusting the diagnosis (human-in-the-loop safety net)
        │
        ▼
attempt_diagnoses written — same schema as the quiz path
```
This call happens as a queued job too (never inline/blocking), for the same
latency/connectivity reasons as the quiz's free-text diagnosis — homework
grading doesn't need to be instant the way quiz feedback does, but it
still shouldn't block on a flaky connection.

## 6. From attempts to "finding the weakness"
Two levels of aggregation sit on top of `attempt_diagnoses`:

**Per-student mastery (powers personalized practice):**
```
mastery[student][skill] = EMA(mastery[student][skill], correct ? 1 : 0, weight=0.3)
```
Updated after every attempt, quiz or homework, on that skill — a rolling,
recency-weighted score per skill, not a one-off grade. A skill's tag list
(conceptual misconceptions, KSSM code, etc.) is shared vocabulary across
both quiz items and homework questions, so a gap surfaced in homework can
suppress that skill's mastery score exactly the same way a quiz miss would,
and vice versa — one unified weakness profile per student, not two separate
ones per source.

**Class-wide aggregation (powers the teacher dashboard):**
```sql
SELECT dimension, tag, COUNT(DISTINCT student_id)
FROM attempt_diagnoses
JOIN attempts ON attempt_diagnoses.attempt_id = attempts.id
WHERE attempts.topic_id = ? AND attempts.created_at > ?
GROUP BY dimension, tag
ORDER BY COUNT(DISTINCT student_id) DESC
```
This reproduces the "14 students — conceptual: heat vs temperature
confusion" style breakdown, combined across quiz and homework sources,
refreshed on a schedule or on job completion rather than recomputed live on
every dashboard page load.

## 7. Scope for the prototype
- Subject: Physics only, covering the Form 4 + Form 5 SPM (KSSM) syllabus.
  Multi-subject is an explicit non-goal for this prototype — depth on one
  subject beats shallow coverage across several, given the per-item
  authoring cost (`distractor_map`, `kssm_code`, rubric text) doesn't
  shrink with scope. The curriculum/skill graph (§8) is modeled for the
  whole Form 4 + 5 syllabus even though only a handful of topics get full
  authored content (questions, distractors) for the demo.
- Quiz: 5-8 concept-inventory items per authored topic, hand-authored
  distractors
- Homework: a handful of real open-ended questions per authored topic, with
  rubric + syllabus excerpt attached
- Both feed the same `attempts` / `attempt_diagnoses` tables and the same
  dashboard query

## 8. Curriculum & prerequisite graph (built)
The `Subject → Skill → Question` hierarchy was a tree, so a gap surfaced on
one skill looked unrelated to a gap on a skill it actually depends on. A
small graph over Skills closes that:

- **`skill_prerequisites`** (skill_id, prerequisite_skill_id) — a DAG over
  Skills, hand-authored the same way `distractor_map` is: encode the
  curriculum-expert judgment once, reuse it forever. For Physics Form 4 + 5
  this is on the order of a few dozen edges, not a generic graph database.
  The first real edge authored is **Specific Heat Capacity depends on
  Thermal Equilibrium** — both real Form 4 Physics topics (Theme 3 "Heat");
  "Heat depends on Particle Model," this section's original illustrative
  example, doesn't correspond to an actual Form 4 Physics chapter in the
  real MOE/Sasbadi textbook (there's no "Particle Model of Matter" topic in
  it) and was never seeded.
- Misconception tags stay as plain strings on `attempt_diagnoses` for this
  prototype — a full Misconception node type (with its own `relates_to`
  edges to other misconceptions) is further work, not needed for the wins
  below.

**What it changes:**
- `GapSummaryService` — when two gapped skills are linked by a prerequisite
  edge, each row is annotated `relation: 'root_cause'|'symptom'` plus
  `related_skill_id`, instead of two unrelated-looking rows. Same idea as
  differential diagnosis in medical expert systems: trace symptoms back
  along a dependency graph to the actual cause.
- `AdaptivePracticeService::chooseSkill()` — when selecting the next
  question, bias toward a weak *prerequisite* skill over a weak downstream
  skill, so practice fixes the root cause first. This is an instance of
  **graph-based knowledge tracing** (modeling mastery propagation over a
  skill dependency graph, rather than each skill's Elo/IRT score in
  isolation) — an established technique in the learning-analytics
  literature, not a homegrown heuristic.
- Homework grounding (§5) could eventually retrieve context by walking the
  graph from a question's skill to its prerequisites (**GraphRAG**) instead
  of the flat per-KSSM-code lookup table — noted here as future work, not
  needed for the prototype's authored topics.

**Not building for this prototype:** a graph database, a Misconception node
type, or GraphRAG-based retrieval — the win is entirely in the small
`skill_prerequisites` DAG plus the two service changes above.
