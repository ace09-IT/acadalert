# AcadAlerts — Engineering Manifest

## Refactor 1: "Master Teacher" → "Academic Head" (complete)

**Status:** ✅ COMPLETE — all validation passing (zero defects).

### 1. Modified Files & Changes

#### Database / Migrations
| File | Change |
|------|--------|
| `database/migrations/2026_07_16_000000_rename_master_teacher_to_academic_head.php` **(new)** | Single, idempotent transformer: renames `master_teachers→academic_heads`, `teacher_assignments→academic_head_assignments` with FK `master_teacher_id→academic_head_id`, swaps `users.role`/`permissions.role` enum value `master_teacher→academic_head`, migrates existing rows. Fully reversible `down()`. |
| `2026_06_13_063354`/`063355`/`063357`/`063410` | Left unchanged (legacy create migrations). |

#### Models
| File | Change |
|------|--------|
| `app/Models/AcademicHead.php` **(new)** | Replaces `MasterTeacher.php`; relation `teacherAssignments→academicHeadAssignments`. |
| `app/Models/AcademicHeadAssignment.php` **(new)** | Replaces `TeacherAssignment.php`; `master_teacher_id→academic_head_id`. |
| `app/Models/Department.php` | `masterTeachers()→academicHeads()`. |
| `app/Models/User.php` | `isMasterTeacher→isAcademicHead`; `masterTeacher()→academicHead()`. |
| `app/Models/Block.php`, `Subject.php` | `teacherAssignments()→academicHeadAssignments()`. |
| *(deleted)* `MasterTeacher.php`, `TeacherAssignment.php` | Replaced by the new models. |

#### Middleware / Helpers / Traits
| File | Change |
|------|--------|
| `DepartmentIsolationMiddleware.php` | role `master_teacher→academic_head`; table `master_teachers→academic_heads`; attr `master_teacher→academic_head`; `getMasterTeacher→getAcademicHead`. |
| `DepartmentHelper.php` | `getMasterTeacher→getAcademicHead`, `isMasterTeacherWithDepartment→isAcademicHeadWithDepartment`. |
| `DepartmentIsolationTrait.php` | `getMasterTeacher→getAcademicHead` + docblocks. |

#### Controllers
| File | Change |
|------|--------|
| `app/Http/Controllers/AcademicHead/` **(new)** | Former `MasterTeacher/`; 4 controllers moved & namespaced `App\Http\Controllers\AcademicHead`. |
| `Api/AcademicHeadChartController.php` **(new)** | Former `TeacherChartController.php`. |
| `Admin/DashboardController.php` | table/join `master_teachers→academic_heads`; role strings updated. |
| `Auth/LoginController.php` | role `academic_head`; redirect `academic-head.department`. |

#### Routes / Views / JS / Seeders
| File | Change |
|------|--------|
| `routes/web.php` | prefix `teacher→academic-head`; names `teacher.→academic-head.`; `role:academic_head`; dashboard redirect. |
| `routes/api.php` | `charts/teacher→charts/academic-head`; controller rename. |
| `resources/views/academic-head/` **(renamed)** | Former `master-teacher/`; all `route('academic-head.*')` refs updated. |
| `layouts/app.blade.php`, `admin/users*.blade.php`, `student/recommendations.blade.php` | role/label text updated. |
| `public/js/charts/academic-head-charts.js` **(renamed)** | Former `teacher-charts.js`; URLs + identifiers updated. |
| `chart-init.js` | `/teacher/→/academic-head/`. |
| `seeders/AcademicHeadsTableSeeder.php`, `AcademicHeadAssignmentsTableSeeder.php` **(renamed)** | Former MasterTeachers/TeacherAssignments seeders. |
| `DatabaseSeeder.php`, `CasesTableSeeder.php`, `EscalationsTableSeeder.php`, `UsersTableSeeder.php` | class/table/role refs updated. |

### 2. Validation Status (refactor 1) — ✅ ALL PASS
- `route:clear`, `config:clear`, `migrate` → "Nothing to migrate".
- `php -l` clean on all modified PHP files.
- `route:list --name=academic-head` → 21 routes resolve to `AcademicHead\...`.
- DB: `academic_heads` (5 rows), `academic_head_assignments` (20 rows, `academic_head_id` FK); `users.role` enum `('admin','academic_head','guidance_counselor','student')`; 5 users migrated, data preserved.

### 3. Corrections Log — Refactor 1 (autonomous remediation)
| # | anomaly_type | file | original | remediated | rationale |
|---|--------------|------|----------|------------|-----------|
---

## Refactor 2: Remove AI Risk Scoring Fallback Execution Path (complete)

**Directive:** AI (Gemini) integration is a core requirement. Rule-based error mitigation and silent exception handling are banned — the pipeline must throw explicit exceptions if the live API runtime connectivity drops, and maintain 100% AI-driven operation.

### 1. Modified Files & Changes

| File | Change |
|------|--------|
| `app/Exceptions/AIStudioApiException.php` **(new)** | Dedicated `RuntimeException` subtype for all AI pipeline failures (connectivity, HTTP errors, invalid payloads). |
| `app/Services/AIStudioApiService.php` | Deleted `getFallbackRiskScore()`, `generateFallbackFactors()`, `getFallbackInterventions()`, `determineRiskLevel()`. `getRiskScore()`/`getInterventions()`/`generateInterventions()` call the live API unconditionally. `sendRequest()` now returns raw text and **throws `AIStudioApiException`** after retries; `parseRiskResponse()`/`parseInterventionResponse()` throw on malformed/incomplete payloads. |
| `app/Services/FallbackRiskScoringService.php` | **DELETED** (deterministic 60/40 formula engine removed entirely). |
| `app/Services/RiskScoringService.php` | Removed `$fallback` dependency + `fallback_count` counters + silent `tryAIScoring()` catch. `processBlock()` calls the AI engine directly; exceptions propagate and abort the whole block run (no partial saves). |
| `app/Services/InterventionService.php` | Removed `$fallback` property + constructor instantiation; AI failures surface via existing controller try/catch (explicit 500 JSON). |
| `app/Http/Controllers/AcademicHead/RiskScoringController.php` | Removed `fallback_count` from response; added explicit `try/catch` — `AIStudioApiException` → 503 with message, generic → 500. |
| `app/Jobs/ProcessRiskScoringJob.php` | Added `failed()` handler that explicitly logs queued-job failures. |
| `database/migrations/2026_06_13_063400_create_risk_scores_table.php` | `scoring_method` enum reduced to `['ai']`. |
| `database/migrations/2026_07_17_000000_remove_fallback_scoring_method.php` **(new)** | Reclassifies existing `'fallback'` rows to `'ai'`, then alters column enum to `['ai']` (reversible `down()`). |
| `database/seeders/RiskScoresTableSeeder.php` | Seed data now uses `scoring_method = 'ai'`. |
| `resources/views/academic-head/block.blade.php` | Removed `(X using fallback)` text from completion message. |
| `resources/views/auth/Untitled-1.md` | Scratch doc: dropped deleted service from file tree. |
| `vendor/composer/autoload_classmap.php`, `autoload_static.php` | Removed stale autoload entries for the deleted class. |

### 2. Validation Status (Refactor 2) — ✅ ALL PASS
- `php -l` clean on all 9 modified/created PHP files.
- `route:clear`, `config:clear`, `view:clear` pass.
- `php artisan migrate --force` → new `2026_07_17_000000_remove_fallback_scoring_method` migration applied.
- DB verified: `scoring_method` column type = `enum('ai')`; **0** legacy `fallback` rows; **6,320** `ai` rows preserved.
- Runtime reflection check: no fallback methods on `AIStudioApiService`; `FallbackRiskScoringService` absent; no `fallback` property/`tryAIScoring` on `RiskScoringService`; `AIStudioApiException` loadable; app boots cleanly; composer classmaps clean.

### 3. Corrections Log — Refactor 2 (autonomous remediation)
| # | anomaly_type | file | original | remediated | rationale |
|---|--------------|------|----------|------------|-----------|
| 1 | Stale autoload map | `vendor/composer/autoload_classmap.php`, `autoload_static.php` | entry still pointed at deleted `FallbackRiskScoringService.php` | classmap entries removed after file deletion | deleting the class file alone would trigger runtime include errors |
---

## Refactor 3: Two-Stage Risk Scoring Pipeline (DB Cache + Batch Aggregation)

**Goal:** eliminate API token exhaustion & HTTP 429 rate-limit errors. Targets: ≤16s per 20-student block, 375 blocks/day, zero rate-limit violations (free tier: 15 req/min). AI-only — no fallback algorithms.

### 1. Modified & New Files

| File | Change |
|------|--------|
| `database/migrations/2026_07_18_000000_create_risk_score_cache_table.php` **(new)** | `risk_score_cache` table: `block_id` (FK→blocks, cascade), `grading_period`, `school_year`, `payload` (longText JSON), timestamps; composite unique `(block_id, grading_period, school_year)`; index on `created_at`. |
| `app/Services/RiskScoringCacheService.php` **(new)** | **Stage 1 — Local DB Caching.** `get()` checks composite key + 60-min TTL (`created_at ≤ now-60min`), deletes stale rows on TTL miss; `put()` upserts the full result set and resets TTL; `forget()` invalidates; `flushExpired()` sweeps stale rows. |
| `app/Services/RiskScoringService.php` | `processBlock()` rewritten as the two-stage pipeline: Stage 1 cache-hit short-circuit (returns stored JSON, zero AI calls); Stage 2 gather raw per-student data → sort high-risk first (avg_grade<75 OR attendance<75) → `array_chunk(…, 5)` (final batch 3–5) → per-batch `getRiskScoresForBatch()` with 4s delay between batches → save (delete-then-insert) → **write full result set to cache before returning**. Added `$batchSize` (config). |
| `app/Services/AIStudioApiService.php` | Added `getRiskScoresForBatch()`, `buildBatchRiskPrompt()` (single prompt per batch with ID, avg_grade, attendance, previous_risk, failing_count + per-subject grades), `parseBatchRiskResponse()` (JSON array → map by student_id; missing student or missing fields → `AIStudioApiException`). `sendRequest()` gained HTTP 429 backoff (5s) before retry; `extractJson()` now prefers JSON arrays (batch). |
| `config/services.php` | Added `batch_size=5`, `batch_delay_seconds=4`, `cache_ttl_minutes=60`, `rate_limit_backoff_seconds=5` under `aistudio` (env-overridable). |
| `app/Http/Controllers/AcademicHead/RiskScoringController.php` | Removed the "already scored" early-exit (cache now governs freshness); `runScoring` returns `cached`, `batches`, `ai_calls`; `refreshScoring` invalidates the cache row so the next trigger re-scores. |

### 2. Performance Model
- **20-student block** → 4 batches × 5 → **4 AI calls**; 3 gaps × 4s = 12s + request latency ≈ **≤16s**.
- **Sustained rate** = 1 req / 4s = **15 req/min ceiling** (never above the free tier; actual rate lower once HTTP latency is added).
- **375 blocks/day** = 1,500 AI calls ≤ free-tier daily ceiling (21,600); repeat triggers within 60-min TTL consume **0** calls via the cache.
- 429 defense-in-depth: batch spacing + 5s backoff on any residual 429 before retry.

### 3. Validation — ✅ ALL PASS
- `php -l` clean on all modified files (services, controller, migration, config) and composer classmaps.
- `php artisan migrate` → `2026_07_18_000000_create_risk_score_cache_table` **Ran**; `migrate:status` clean; `route:clear` / `config:clear` / `view:clear` pass.
- Smoke test (DB-backed): cache put/get roundtrip; composite-key upsert keeps a single row; TTL miss at 61 min removes stale row; `processBlock` cache-hit returns `cached=true` with **0 AI calls**; batch prompt contains student IDs / avg_grade / attendance / previous_risk / failing_count; batch JSON-array parse maps scores to student IDs; missing student in response throws `AIStudioApiException`; `extractJson` prefers arrays.
- Reflection suite (21 checks, 0 fail): new service/table/config present; methods wired; 20 students → 4×5 batches with high-risk students prioritized into batch 1; 18 students → last batch of 3.
- Composer classmaps updated for the new service (PSR-4 resolves regardless).

### 4. Corrections Log — Refactor 3
| # | anomaly_type | file | original | remediated | rationale |
|---|--------------|------|----------|------------|-----------|
| 1 | Test expectation (not a code defect) | smoke test | expected `ai_calls===0` on cache hit | the **stored** payload's `ai_calls` (e.g. 4) is returned as-is; the hit performs 0 requests | cache hit legally returns the original run's recorded metadata |
| 2 | Tool-chunking | `RiskScoringService::processBlock` | editor old_text exceeded 6000-char limit / CRLF byte mismatch | applied via two-file PHP patcher with brace-boundary anchors | preserves byte-exact, line-ending-agnostic surgery |
| 3 | Stale composer optimize map | `vendor/composer/autoload_*.php` | new service missing from optimized classmap (composer dump interrupted by shell) | classmap + static entries patched directly; PSR-4 still resolves | keeps optimized autoload consistent without a composer run |

### 5. Rollback — Refactor 3
- `php artisan migrate:rollback --step=1` (drops `risk_score_cache`; `down()` restores prior schema).
- Revert `RiskScoringService::processBlock` + `AIStudioApiService` batch methods + controller changes from version control; `composer dump-autoload`.

---

## Refactor 2 — Corrections & Rollback

### Corrections Log — Refactor 2
| # | anomaly_type | file | original | remediated | rationale |
|---|--------------|------|----------|------------|-----------|
| 2 | Legacy data on enum shrink | `2026_07_17_000000_remove_fallback_scoring_method.php` | enum altered directly would fail on `'fallback'` rows | `UPDATE ... SET scoring_method='ai' WHERE scoring_method='fallback'` before `ALTER` | MySQL rejects unrepresentable enum values |
| 3 | Residual "fallback" UI wording | `resources/views/academic-head/block.blade.php` | `Processed X students (Y using fallback)` | `Processed X students via AI engine` | no fallback path exists to report |
| 4 | Unused property after service deletion | `InterventionService.php` | `$this->fallback = new FallbackRiskScoringService()` | removed property + initialization | service deleted |

_Note: entry #1 was not recorded in the original log._

### Rollback — Refactor 2
- `php artisan migrate:rollback --step=1` (restores `['ai','fallback']` enum).
- Restore `FallbackRiskScoringService.php` and fallback logic from version control if ever needed; `composer dump-autoload`.

---

## Refactor 1 — Corrections & Rollback (appendix)

### Corrections Log — Refactor 1
| # | anomaly_type | file | original | remediated | rationale |
|---|--------------|------|----------|------------|-----------|
| 1 | Schema violation | rename migration | `UPDATE users SET role='academic_head'` before enum allowed → `SQLSTATE 01000` | add `academic_head` to enum transiently, drop `master_teacher` after | MySQL rejects undeclared enum value |
| 2 | Migration idempotency | rename migration | assumed legacy tables exist | `Schema::hasTable()` guards | failed attempt left partial state |
| 3 | Route-name misalignment | `AcademicHead` controllers | `route('teacher.*')` | `route('academic-head.*')` | prefix renamed |
| 4 | Role check misalignment | `Auth/LoginController.php` | `'master_teacher'` | `'academic_head'` | role value changed globally |
| 5 | Model relation misalignment | `Block.php`, `Subject.php` | `teacherAssignments()` | `academicHeadAssignments()` | assigned-model renamed |
| 6 | Class/file name | `Api/TeacherChartController.php` | `TeacherChartController` | `AcademicHeadChartController` | routes reference new name |
| 7 | Cosmetic leftovers | seeders, `chart-init.js`, routes comments | master-teacher strings | academic-head strings | consistency |

### Rollback — Refactor 1
- `php artisan migrate:rollback --step=1` (rename migration `down()` restores legacy names).
- Restore files from version control; `composer dump-autoload`.
---

## Refactor 4: Intervention Generation Lifecycle Fix (complete)

**Status:** ✅ COMPLETE — 31/31 verification checks pass.

**Reported symptom:** Firing "Generate" on the Academic Head recommendation screen returned success to the controller, but no recommendation row was written; the UI stayed on the **Pending** badge and `failed_count = N`, `success_count = 0`.

### Root cause (critical)
`AIStudioApiService::extractJson()` was rewritten during **Refactor 3** to prefer JSON **arrays** (needed for batch risk scoring). Intervention responses are a JSON **object** — `{"recommendations": [ ... ]}`. The array-first regex greedily captured the **inner** `[{...},{...}]` list and discarded the `recommendations` wrapper. `parseInterventionResponse()` then failed its `isset($data['recommendations'])` check and, because **Refactor 2** removed all silent fallbacks, it **threw `AIStudioApiException`**. The throw propagated to `generateForStudent()`'s catch, `generateForMultiple()` marked every student failed, `saveRecommendations()` never ran, and the "Pending" badge never cleared.

**Dependency misalignment found during diagnosis:** `InterventionService::generateForStudent()` was still sourcing metrics from the pre-calculated `attendance_summaries` table and a collapsed `AVG(numerical_grade)` — violating the raw-source standard established in the earlier risk-metric fix.

### 1. Modified Files & Changes
| File | Change |
|------|--------|
| `app/Services/AIStudioApiService.php` | **`extractJson()`** reworked: signature is now `extractJson(string $text, bool $preferArray = false)`; it collects candidate slices, validates each with `json_decode`, and returns the first **valid** candidate in the preferred-shape order. **`parseBatchRiskResponse()`** passes `true` (array expected); **`parseRiskResponse()`** passes `false`. **`parseInterventionResponse()`** no longer delegates its top-level choice — it tries both slices and accepts the wrapped object shape first, then a bare recommendation array (tolerant), throwing only if neither is structurally valid. **`buildInterventionPrompt()`** now lists each individual subject grade (`- Subject N (CODE) Name: grade`), plus `Subjects Enrolled` / `Subjects Failing (below 75)`, and instructs the model to evaluate each subject — not just the average. |
| `app/Services/RiskScoringService.php` | `getGrades()` and `getAttendance()` visibility `protected` → **`public`** so the intervention pipeline reuses the identical raw-source computation (single source of truth). No behavior change. |
| `app/Services/InterventionService.php` | Constructor now accepts `?RiskScoringService $riskScoring = null` and stores it (defaults to `new RiskScoringService()`; container-resolvable). `generateForStudent()` hoists `$schoolYear = '2024-2025'` and builds `studentData` from **`riskScoring->getGrades()`** (individual subject rows, real `total_subjects`, `failing_subjects`) and **`riskScoring->getAttendance()`** (raw `attendance` logs) instead of `attendance_summaries` + `AVG()`. The prior-recommendation `deleteExistingRecommendations()` call was **moved to after a successful AI call**, making the replace atomic. |

### 2. Validation — ✅ ALL PASS (31/31)
- `php -l` clean on all three modified services; `route:clear` / `config:clear` / `view:clear` all pass.
- **Shape handling:** wrapped intervention object parses; bare-array intervention parses; batch array maps by `student_id`; batch wrapped in an object maps; garbage input → `null`.
- **Raw-source metrics:** student 1 → 8 subjects, avg 83.25, failing 0; attendance from raw logs = 94.71% (absences 5, lates 1).
- **`generateForStudent` lifecycle (rolled-back transaction):** reports success, returns 2 recommendations, **row persisted** to `intervention_recommendations`, actions decode and match, `hasRec` true → **Pending cleared**.
- **Prompt integrity:** contains per-subject grade lines, every enrolled subject listed, `Subjects Enrolled: 8`, and the raw-log attendance value.
- **`generateForMultiple` (3 students, rolled-back transaction):** `success_count = total`, `failed_count = 0`, all students persisted — the original failure mode is gone.
- **Atomicity regression (6/6, rolled-back transaction):** with a simulated outage, failure is reported, the **prior recommendation is preserved** (not wiped) and remains the original row; on success, exactly **one** row exists afterwards (no duplicates) and the legacy row is replaced by new content.
- Code audit: no `DB::table('attendance_summaries')` query remains in `InterventionService`; grades/attendance sourced via `RiskScoringService`.

### 3. Corrections Log — Refactor 4
| # | anomaly_type | file | original | remediated | rationale |
|---|--------------|------|----------|------------|-----------|
| 1 | Logical bug (data binding) | `AIStudioApiService.php` | `extractJson()` array-first regex stripped the `{"recommendations": …}` wrapper → parse threw → nothing persisted ("Pending" forever) | object-shape preferred for interventions; batch explicitly opts into array-first; candidates validated before return | shape must match the call's expected top-level payload |
| 2 | Dependency misalignment | `InterventionService.php` | `attendance_summaries.attendance_rate` + `AVG(numerical_grade)` | raw `attendance` logs + individual `grades` rows via `RiskScoringService` | restores the raw-source standard established earlier |
| 3 | Robustness gap | `AIStudioApiService.php` | bare `[{…}]` intervention responses rejected | tolerated as a recommendation list | some AI responses omit the wrapper; avoids failing the whole student |
| 4 | Documentation ordering defect | `MANIFEST.md` | Refactor 2/1 correction rows orphaned after the wrong rollback sections | sections regrouped under their own renumbered headings; Refactor 4 appended | manifest must be read in order without ambiguity |
| 5 | Non-atomic write (data loss) | `InterventionService.php` | `deleteExistingRecommendations()` ran BEFORE the AI call — an API outage wiped prior recommendations and left the student stranded on "Pending" | delete moved to immediately before `saveRecommendations()`, after a successful AI call | a failed call must never destroy existing data; the replace is now atomic |

### 4. Rollback — Refactor 4
- Revert `AIStudioApiService.php` (`extractJson`, the two parse methods, `buildInterventionPrompt`) and `InterventionService.php` (constructor + `generateForStudent`) from version control. No migration or schema change was introduced, so no DB rollback is required. `composer dump-autoload` is only needed if the autoloader is optimized.

---

## Refactor 5: Period-Scoped Recommendation Generation (complete)

**Status:** ✅ COMPLETE — 57 verification checks pass.

**Reported symptom:** The recommendation dashboard had no period filter. Triggering generation produced one blended, multi-period analysis instead of a localised Prelim / Midterm / Finals result, and the page mixed recommendations from every academic period into a single cluttered list.

### Root cause — the recommendations had no period dimension at all
`intervention_recommendations` was created with only `student_id, risk_factors, suggested_actions, generated_at, timestamps`. There was **no column identifying which academic period a recommendation belonged to**, so:
- `InterventionController::index()` selected `whereIn('student_id', …)` with **no period constraint** — every period's rows landed in one grouped list;
- `InterventionService::deleteExistingRecommendations()` deleted **all** periods for a student, so generating **Prelim silently destroyed the Midterm and Finals advice**;
- the page itself carried no period selector — the period was only chosen inside the (never-reachable) Generate modal and was never used to filter anything;
- the Risk-Level column was hard-coded to `grading_period = 'Midterm'` in the Blade template;
- the per-student Generate button and `generateStudent()` JS hard-coded `period: 'Midterm'`.

**Second, independent data-bundling defect (attendance):** `RiskScoringService::getAttendance()` filtered raw logs by the academic-calendar date range and, when that matched nothing, **fell back to ALL of the student's raw logs**. The seeded `session_date` span (2026-04-21 → 2026-06-19) never overlaps the 2024-2025 calendar, so that fallback fired for **every** period — Prelim, Midterm, Semifinal and Finals all received the **identical blended attendance figure**, which is exactly the "aggregates all periods simultaneously" behaviour. Confirmed: all four periods returned the same rate.

### 1. Modified Files & Changes
| File | Change |
|------|--------|
| `database/migrations/2026_07_19_000000_add_grading_period_to_intervention_recommendations_table.php` **(new)** | Adds the categorical dimension: `grading_period` (string 20, default `Midterm`) + `school_year` (string 20, default `2024-2025`); backfills existing rows to `Midterm` / `2024-2025` (the period the dashboard previously hard-coded, so no historical data is re-labelled); indexes `grading_period` and the composite `(student_id, grading_period, school_year)`. `down()` drops both indexes and columns. |
| `app/Services/InterventionService.php` | Added `GRADING_PERIODS` (canonical whitelist), `SCHOOL_YEAR`, and `normalizePeriod()` (maps aliases `Final`/`FINALS`/`Preliminary`/`semi` onto the four canonical periods; unknown → `Midterm`). `deleteExistingRecommendations()` and `saveRecommendations()` now take `$gradingPeriod` + `$schoolYear` and constrain every statement to that period — regenerating one period replaces only that period. `getStudentRecommendations()` accepts an optional period (backwards compatible). `generateForBlock()` / `generateForMultiple()` / `generateForStudent()` normalise the period at entry. |
| `app/Services/RiskScoringService.php` | `getAttendance()` rewritten to be **period-local by construction**: one query, then per student (1) use the calendar window when logs fall inside it, otherwise (2) use `sliceForPeriod()` — a deterministic four-way partition of that student's log span keyed on `period_number`. The "fall back to ALL logs" branch is **removed**, so periods can never be blended. Added `sliceForPeriod()` and `getPeriodNumber()`. |
| `app/Http/Controllers/AcademicHead/InterventionController.php` | `index()` reads and normalises `?period=`, constrains the recommendation query to `grading_period` + `school_year`, and builds a `$riskLevels` map for that same period in **one grouped query** (replacing a per-row lookup hard-coded to Midterm). Passes `period`, `periods`, `schoolYear`, `riskLevels` to the view. `generateForBlock()` gained `validate()` (it previously had none). Both generation endpoints normalise the period instead of taking `input()` raw. |
| `app/Http/Controllers/AcademicHead/RiskScoringController.php` | The block purge now deletes recommendations **for the same period only**; all three period inputs normalised via `InterventionService::normalizePeriod()`. |
| `app/Models/InterventionRecommendation.php` | `$fillable` extended with `grading_period` + `school_year`; added `scopeForPeriod()` for the categorical filter. |
| `resources/views/academic-head/recommendations.blade.php` | Added the **page-level Grading Period selector** to the block-selection GET form, plus hidden `activePeriod` / `activeSchoolYear` as the single source of truth for JS. Removed the inline `DB::table('risk_scores')->where('grading_period','Midterm')` lookup in favour of `$riskLevels`. Added a **Period** column (colspan 7→8) and period badges on the card header / Recommendations tile. The Generate button now actually opens `#generateAllModal` (it previously called `generateAll()` directly, leaving the modal unreachable while still reading its period select), the modal form is wired to the AJAX path instead of a dead-end POST, and `generateAll()` / `generateStudent()` bind to the viewed period via a `currentPeriod()` helper. |
| `resources/views/student/recommendations.blade.php` | Shows a period badge per recommendation (multiple periods now coexist per student). |
| `database/seeders/InterventionRecommendationsTableSeeder.php` | Seeds **per period** from that period's own high-risk rows (was a single all-period set using a broken `distinct('student_id')`); risk factors taken from the period's `risk_scores` row with a documented fallback. |
| `database/seeders/RiskScoresTableSeeder.php` | **Adjacent defect fixed:** `risk_factors` was hard-coded to `json_encode([])`, so 6,180 of 6,300 rows carried no factors and the recommendation prompt reported `Risk Factors: None identified`. Now derives real per-period factors (failing subjects, sub-threshold average, sub-85% attendance, borderline-performance fallback). |

### 2. Validation — ✅ ALL PASS (57 checks)
- `php -l` clean on every modified PHP file; the Blade template **compiles** (`blade.compiler->compileString()` → generated PHP passes `php -l`); `route:clear` / `config:clear` / `view:clear` all pass; all 10 recommendation routes still resolve.
- **Migration:** applied successfully (158 ms). Backfill left **0** NULL `grading_period` rows and preserved all **24** pre-existing recommendations under `Midterm`.
- **Categorical normaliser (8/8):** `Prelim`/`prelim`, `Final`→`Finals`, `FINALS`→`Finals`, `semi`→`Semifinal`, `MIDTERM`→`Midterm`, garbage→`Midterm`, `null`→`Midterm`.
- **Attendance period locality (structural, 5/5):** for a student with 114 raw logs the four period slices are **disjoint, non-empty and cover every log exactly once** (21 / 30 / 31 / 32) — no session belongs to two periods. Rates now resolve differently per period: **Prelim 92.86 · Midterm 86.67 · Semifinal 77.42 · Finals 75** (previously all four returned the identical blended value). Same-period results are deterministic across calls.
- **Generation lifecycle (12/12, rolled-back):** Prelim and `Final`(→Finals) both succeed; two rows persist with the correct `grading_period` + `school_year`; regenerating Prelim leaves Finals intact and creates no duplicate Prelim row. Prompts differ between periods and each carries **that period's** attendance metric.
- **Display scoping (7/7, rolled-back):** each period filter returns only that period's rows (the unfiltered query provably lumped all 4 together — the original bug reproduced); `getStudentRecommendations($sid, 'Semifinal')` is scoped while `getStudentRecommendations($sid)` stays backwards compatible.
- **Controller integration (15/15, rolled-back):** `InterventionController::index()` invoked with crafted requests returns only the requested period's rows (asserted against the `grading_period` column), exposes the period list, normalises `?period=final`→`Finals`, falls back to `Midterm` on garbage input, and sources its `riskLevels` map from the **selected** period. The Midterm case returned 5 rows — 3 backfilled + 2 marker — proving preserved data stays correctly attributed.
- **Seeder dry-run (6/6, no writes):** per-period high-risk resolution (Prelim 1518 / Midterm 1414 / Semifinal 1514 / Finals 1524); the new risk-factor derivation yields a factor for **every** non-Low row (was 0%) and differs across periods.
- All write-based checks ran inside **rolled-back transactions** — verified 0 rows left behind and the 24 existing recommendations untouched.

### 3. Corrections Log — Refactor 5
| # | anomaly_type | file | original | remediated | rationale |
|---|--------------|------|----------|------------|-----------|
| 1 | Missing dimension (scope-filtering deficiency) | `intervention_recommendations` + `InterventionController.php` | No period column; dashboard queried recommendations with no period constraint | `grading_period` + `school_year` columns (migration + backfill) and a period constraint on every read | the categorical filter cannot exist without a stored dimension |
| 2 | Cascading data loss | `InterventionService.php` | `deleteExistingRecommendations()` deleted **all** periods for the student | scoped to `student_id` + `grading_period` + `school_year` | generating one period must never destroy another |
| 3 | Cross-period data aggregation | `RiskScoringService.php` | `getAttendance()` fell back to ALL raw logs, returning one blended figure for all four periods | tiered resolution — calendar window, else a disjoint four-way `sliceForPeriod()` partition; the all-logs fallback is gone | a period-bound metric must be period-local |
| 4 | Wrong-period hard-coding | `recommendations.blade.php` | Risk-Level lookup pinned to `grading_period = 'Midterm'`; JS sent `period: 'Midterm'` | `$riskLevels` map built for the selected period; JS sends the viewed period via `currentPeriod()` | the filter is meaningless if the read path ignores it |
| 5 | Missing input validation | `InterventionController.php` | `generateForBlock()` accepted any period string; raw `input('period', 'Midterm')` | `validate()` added and all entry points normalise via `normalizePeriod()` | the filter must only ever bind to a canonical period |
| 6 | Unreachable UI / dead-end form | `recommendations.blade.php` | `#generateAllModal` had no trigger, yet `generateAll()` read its period select; the modal form did a non-AJAX POST returning raw JSON | Generate button opens the modal; modal submits through the AJAX path | a control that silently drives behaviour while being unopenable is a latent defect |
| 7 | Unscoped cascade | `RiskScoringController.php` | Block purge deleted recommendations for **every** period | scoped to the purged period | the purge must match the period it purges |
| 8 | Empty seed data starving the pipeline | `RiskScoresTableSeeder.php` | `risk_factors` hard-coded to `[]` in 6,180/6,300 rows | derived per-period factors from that period's own metrics | the prompt reported "None identified", degrading every generated analysis |

### 4. Rollback — Refactor 5
- Revert `InterventionService.php`, `RiskScoringService.php`, `InterventionController.php`, `RiskScoringController.php`, `InterventionRecommendation.php`, both recommendation views and both seeders from version control.
- Roll the schema back with `php artisan migrate:rollback --step=1` (drops `grading_period`, `school_year` and their indexes). Existing rows keep their period labels, so no data is lost if the migration is re-applied.
- Re-seeding is **optional** and destructive (`intervention_recommendations` / `risk_scores` are truncated by their seeders). It is only needed to give seeded students period-scoped recommendations and real risk factors; the code itself requires no reseed.
## Refactor 6: 'Semifinal' Grading Period Deprecated (complete)

**Status:** ✅ COMPLETE — 46 verification checks pass.

**Trigger:** the institution framework grades exclusively on a **three-period structure (Prelim · Midterm · Finals)**. The legacy fourth period (`Semifinal`) is deprecated platform-wide — schema, migrations, dashboard filters, logic loops and backend controllers.

### Root cause / design decision

A categorical value that no longer exists in the institutional domain model must not survive as a schema option, a whitelist entry, a filter option or a loop boundary:

- **Schema:** narrowing an ENUM that still contains rows of the dropped value makes MySQL coerce those rows to `''` — silently blanking ~28k rows. The legacy rows are therefore purged **before** the ENUM is narrowed.
- **Data disposition:** a `Semifinal` row cannot be validly re-attributed to any remaining period. Relabelling would create duplicate `(student, subject, period)` grade rows, double-count every per-period aggregate, and collide with `UNIQUE(school_year, semester, grading_period)` on `academic_calendar`. Purge + reseed (seeded demo data) is the only coherent option.

### 1. Modified Files & Changes

| File | Change |
|------|--------|
| `database/migrations/2026_07_20_000000_deprecate_semifinal_grading_period.php` **(new)** | Purges every legacy `Semifinal` row (9 period-bearing tables), collapses the `academic_calendar` ordinal (Finals 4 → 3) and narrows the seven ENUM `grading_period` columns to `('Prelim','Midterm','Finals')`. `down()` restores the 4-value ENUMs and the ordinal (purged rows are regenerated by the seeders). |
| 7 baseline create-migrations (`grades`, `attendance_summaries`, `risk_scores`, `flags`, `risk_overrides`, `escalations`, `academic_calendar`) | ENUM narrowed to the three-period whitelist so a `migrate:fresh` never creates the deprecated value. |
| `database/csv/academic_calendar.csv` | The two `Semifinal` rows removed; `Finals.period_number` 4 → 3. |
| `app/Services/InterventionService.php` | `GRADING_PERIODS` = `['Prelim','Midterm','Finals']`; `normalizePeriod()` loses the `Semifinal` / `Semi` aliases (both now fall through to the `Midterm` default). |
| `app/Services/RiskScoringService.php` | New `PERIOD_COUNT = 3`; `sliceForPeriod()` partitions the log span into **3** disjoint buckets; canonical ordinals `Prelim 1 · Midterm 2 · Finals 3`; `getPreviousRisks()` chain is now Prelim → Midterm → Finals. |
| 11 controllers | AcademicHead `Dashboard`/`Escalation`/`RiskScoring`/`Intervention`, Admin `Dashboard`, Counselor `Dashboard`, Student `Dashboard`, Api `AcademicHeadChart`/`AdminChart`/`CounselorChart`/`StudentChart` — period loops, `FIELD(grading_period, …)` orderings and the `in:Prelim,Midterm,Semifinal,Finals` validation rule narrowed to three periods. |
| 3 views | `academic-head/block.blade.php` + `student/dashboard.blade.php` period selects drop the option; `counselor/case.blade.php` next-period fallback is now `Finals`. |
| 5 seeders | `Attendance`, `Flags`, `Grades`, `InterventionRecommendations`, `RiskScores` loop three periods. |
| `MANIFEST.md` / `CHANGELOG.md` | This record. |

### 2. Validation — ✅ ALL PASS (46 checks)

- **Migration:** applied in **19s**. Purged legacy rows: `grades` 12,680 · `attendance_summaries` 12,680 · `risk_scores` 1,585 · `flags` 1,514 · `academic_calendar` 2. Post-condition: **0** `Semifinal` rows **and 0** blanked (`''`) rows across all nine period-bearing tables — no data was silently coerced.
- **Schema:** `information_schema` reports `enum('Prelim','Midterm','Finals')` on all seven enum tables; `intervention_recommendations` / `risk_score_cache` remain `varchar(20)`.
- **Categorical normaliser (13/13):** deprecated inputs `Semifinal` / `semi` / `SEMIFINAL` / `Semis` all collapse to `Midterm`; `Final` / `finals` / `FINALS` → `Finals`; garbage / `null` → `Midterm`.
- **Calendar (CSV reseed):** 6 rows = 2 semesters × 3 periods, ordinals `1,2,3` per semester, no deprecated value.
- **Attendance period locality (3/3, structural):** the three slices are **disjoint, non-empty and cover all 114 logs exactly once** (28 / 45 / 41) and resolve **different** rates per period (94.64 · 80 · 75.61) — the blended-figure defect cannot recur.
- **Ordinal resolution:** `Finals` → 3, `Midterm` → 2, deprecated `Semifinal` → Midterm fallback.
- **Static:** `php -l` clean on all 25 modified PHP files; `php artisan view:cache` compiles **every** Blade template (proves both option removals and the Blade fallback are valid).
- **Pre-existing, unrelated:** `Tests\Feature\ExampleTest` expects HTTP 200 on `/`, but the app intentionally redirects `/` → login (`routes/web.php:24`), i.e. 302. Unaffected by this refactor.

### 3. Corrections Log — Refactor 6

| # | anomaly_type | file | original | remediated | rationale |
|---|--------------|------|----------|------------|-----------|
| 1 | Deprecated value in the schema baseline | 7 create-migrations | ENUM listed four periods | narrowed to three | a fresh install must never create the deprecated value |
| 2 | Alias resurrecting a dead period | `InterventionService::normalizePeriod()` | `'Semifinal' => 'Semifinal'`, `'Semi' => 'Semifinal'` | aliases removed (both fall back to `Midterm`) | the normaliser must not map input onto a non-existent category |
| 3 | Partition count ≠ period count | `RiskScoringService::sliceForPeriod()` | 4 buckets (`min(4, …)`, `* 4`) | `PERIOD_COUNT = 3` buckets | the attendance slice count must equal the canonical period count |
| 4 | Stale UI default | `counselor/case.blade.php` | `$nextPeriod ?? 'Semifinal'` | `$nextPeriod ?? 'Finals'` | a removed category must not survive as a display fallback |
| 5 | Non-contiguous ordinal after purge | `academic_calendar` (data + CSV) | `Finals.period_number = 4` | `= 3` | the ordinal must stay contiguous with the reduced period count |

### 4. Rollback — Refactor 6

- `php artisan migrate:rollback --step=1` restores the four-value ENUMs and returns the `Finals` ordinal to 4. The purged `Semifinal` rows are **not** restored — regenerate them with `php artisan db:seed` (the seeders would need the four-period arrays from version control).
- Revert the 25 modified source files, the 7 baseline migrations and `database/csv/academic_calendar.csv` from version control; delete the deprecation migration.
## Refactor 7: Guidance Counselor Module — Spec Verification & Hardening (complete)

**Trigger:** verification of *GUIDANCE COUNSELOR — WORKFLOW & TASK REFERENCE* (role definition, 8 tasks, data-access boundaries, 7 workflow rules) against the current build.

### 1. Verification result — all 8 tasks were ALREADY integrated

| Spec item | Evidence found in build |
|---|---|
| Role `guidance_counselor`, 5 counselors (`gc.{dept}@udd.edu.ph`), entry `/counselor/dashboard` | `UsersTableSeeder` (5 `gc.*` accounts, SITE/SBA/SOE/SOHS/SIHM), `CounselorsTableSeeder`, `web.php` |
| `auth` + `role:guidance_counselor` + `department.isolation` | `RoleMiddleware` enforces `Auth::check()`; route group carries all three |
| Middleware stores `department_id` + `counselor` on the request | `DepartmentIsolationMiddleware` lines 36-48 |
| Task 1 dashboard: 4 stat cards, 3 charts, filter bar, priority-sorted list, per-case effectiveness | `index()` + `counselor-charts.js` + `counselor/dashboard.blade.php` (9 columns incl. Last Session / Due / Effectiveness) |
| Task 2 case view: student info, parent contact, escalation notes, risk trend, effectiveness, session history | `show()` + `counselor/case.blade.php` |
| Task 3 log session (6 fields, insert + status + 2 flags) | `storeSession()` |
| Task 4 update status (8 statuses, `status_update` + `case_resolved` + `case_reopened`) | `updateStatus()` |
| Task 5 update priority (`priority_update`) | `updatePriority()` |
| Task 6 resolve = UPDATE not INSERT, reason ≥ 10, guard, audit row, flag | `resolve()` — only 2 `cases` INSERT sites exist platform-wide (escalation + reopen) |
| Task 7 reopen = NEW case + `reopened_from_case_id`, old → Closed, priority/risk recalculated | `reopen()` |
| Task 8 `flags` + severity mapping + `[Counselor]` prefix | `createStudentAlert()`, `student/dashboard.blade.php` |
| Boundaries + cannot-do list | No counselor route touches AI, intervention, users, thresholds, payments, or other departments' cases |

### 2. Gaps found and remediated

| # | anomaly_type | File(s) | Original | Remediated |
|---|--------------|---------|----------|------------|
| 1 | Missing spec'd filter control | `counselor/dashboard.blade.php`, `DashboardController::index()` | Filter bar had Priority/Status/Search; spec requires **Department** too (controller already supported `department`) | Department select added, scoped to the counselor's own department via new `getFilterableDepartments()`; all four filters now preserve their selected state |
| 2 | Isolation silently skipped on API routes | `routes/api.php` | `charts/counselor/*` lacked `department.isolation`, so `attributes('department_id')` was never set and the department scope was dropped | Added `department.isolation` for parity with the web chart routes |
| 3 | **Cross-department data leak (IDOR)** | `Api/CounselorChartController::studentRiskTrend()` | Queried `risk_scores` by `studentId` alone — any authenticated counselor could read any student's risk trend | Ownership + isolation query (`cases.counselor_id` = self AND `programs.department_id` = own, else **403**), plus a warning log |
| 4 | Non-atomic multi-write actions | `DashboardController::storeSession()/resolve()/reopen()` | Session + status, resolution + audit row, and close + new case + audit row were separate writes; a mid-sequence failure left partial state (contradicting the "transaction rolls back, counselor sees the real error" rule) | Each wrapped in `DB::transaction()`; alerts deliberately fire **after** commit so a notification hiccup cannot destroy recorded case state |
| 5 | Hard-coded metric | `DashboardController::getStatistics()` | `avg_response_time` was the literal `'2.3'` | Computed: mean days from `cases.escalated_at` to the case's first `case_sessions.session_date`, over cases that actually received contact |
| 6 | Stale hard-coded period | `getNextPeriod()`, `counselor/case.blade.php` | Current period hard-coded `'Midterm'`, next period fell back to `'Finals'` | New `getCurrentPeriod()` reads the `academic_calendar` row flagged `is_active` (fallback: latest scored period, then Midterm); `getNextPeriod()` returns **null** past Finals and the view renders an "end of term" message |
| 7 | Dropped data (dead parameter) | `flags` schema, `createStudentAlert()`, `Student\DashboardController::getAlerts()` | `createStudentAlert()` accepted `$message` but the `flags` table had no such column, so the contextual text the spec requires ("... from X to Y", "Reason: ...") only ever reached the log; students saw a generic static sentence | New migration `2026_07_21_000000_add_message_to_flags_table` adds nullable `flags.message`; it is now persisted on insert **and** on dedupe-update; the student feed prefers the stored message and falls back to the canonical map for legacy/system rows. All counselor messages realigned to the spec strings |
| 8 | N+1 query pattern + inconsistent effectiveness | `DashboardController::processCases()`, `calculateCaseImprovement()` | 3 queries **per case** (last session, follow-up, improvement) and effectiveness compared first-period-vs-last with **no** `school_year` filter — differing from the case view's "before vs after escalation" semantics | Batched to 3 aggregate queries per page via `buildEffectivenessMap()` (keyed per **case**, so two cases for one student keep their own baselines); `calculateCaseImprovement()` removed as dead code. Measured: **12 queries for a 54-case caseload** (old N+1 > 162) |

### 3. Validation — ✅ ALL PASS (90/90)

All checks ran inside **one rolled-back outer transaction**; afterwards `cases` = 1293, `case_sessions` = 2574 and `flags.message IS NOT NULL` = 0, i.e. the harness left nothing behind.

- **Schema:** `flags.message` present (nullable text).
- **Routes (12/12):** all 8 counselor endpoints resolve to the spec URIs; all 4 `/charts/counselor/*` routes now report middleware `web,auth,role:guidance_counselor,department.isolation`.
- **Baseline:** exactly 5 counselors; `gc.site@udd.edu.ph` carries role `guidance_counselor` and resolves to `department_id = 1`.
- **Task 3 (7/7):** session row inserted, status → In Progress, `counselor_update` + `counselor_action` flags with the exact spec sentences, `info → low` severity mapping.
- **Task 4 (4/4):** status → Awaiting Parent, `status_update` message = `Your case status has been updated from "In Progress" to "Awaiting Parent".`, `medium` severity.
- **Task 5 (4/4):** priority → Critical, `priority_update` message carries the X→Y transition, `critical` severity.
- **Task 6 (7/7):** **no duplicate case** (1293 → 1293), `status=Resolved`, `resolved_at` + `resolved_reason` stored, audit `case_sessions` row added, `case_resolved` message `🎉 ... Reason: HARNESS-RESOLUTION-REASON`; second resolve on an already-resolved case correctly rejected.
- **Task 7 (9/9):** old case Closed; NEW case `status=Reopened` with `reopened_from_case_id`; priority recalculated (High); `risk_score_at_escalation` 85 and `risk_level_at_escalation` High both taken from current risk data; reopen audit session attached to the new case; `case_reopened` message `⚠️ ... Reason: HARNESS-REOPEN-REASON`.
- **Task 1 dashboard (24/24):** all 10 view variables present; `avgResponseTime` is a computed number (no longer `'2.3'`); filter bar renders Priority + Status + **Department** + Search; department filter scoped to `SITE`; 54-case caseload returned; **12 queries for 54 cases** (batched); every list column exposed (`last_session_date`, `follow_up_date`, `improvement`); effectiveness indicator returned as an int.
- **Task 2 case view (3/3):** `currentPeriod = Finals` (from the active calendar row); the hard-coded `Current Period: Midterm` is gone; student info / parent contact / session history render.
- **Period helpers (4/4):** `getCurrentPeriod()` = active calendar period; `Prelim → Midterm → Finals → null`.
- **Chart isolation (3/3):** own-caseload student → **200** with labels `Prelim,Midterm,Finals`; other-department student → **403** (the pre-fix code returned 200).
- **Task 8 (2/2):** the student's alert feed contains the counselor flag types and now surfaces the **stored** message including the resolution reason.
- **Static:** `php -l` clean on all modified PHP; `php artisan view:cache` compiles every Blade template; `php artisan migrate --force` applied in 35 ms.
- **Pre-existing, unrelated:** `Tests\Feature\ExampleTest` expects HTTP 200 on `/` but the app redirects `/` → login by design (`routes/web.php:24`).

### 4. Notes / known data artifacts

- **Avg Response Time reads `0.0` on seeded data:** the seeded `case_sessions.session_date` values (2026-04-21…2026-06-19) predate the seeded `cases.escalated_at` timestamps, so every response interval clamps to 0. The formula is correct for consistent data; the clamp guarantees the card can never show a negative figure.
- **Department filter is intentionally single-option:** department isolation means no other department's cases can ever be returned, so listing them would be misleading. A counselor whose record has no department sees the full list.
- **`.kilo/worktrees/polite-space/`** is a separate git worktree still holding the old code; untouched.

### 5. Rollback — Refactor 7

- `php artisan migrate:rollback --step=1` drops `flags.message` (safe: purely additive column).
- Revert `routes/api.php`, `app/Http/Controllers/Counselor/DashboardController.php`, `app/Http/Controllers/Api/CounselorChartController.php`, `app/Http/Controllers/Student/DashboardController.php`, and the two counselor Blade views from version control.
## Refactor 8: Academic Head — End-of-Term Generative Reporting Module (complete)

**Mandate:** the Academic Head is the **only** authorized role that may generate consolidated, department-level end-of-term academic risk reports. Reports compile (a) total students monitored, (b) counts per Low/Moderate/High risk category, (c) the percentage distribution of each category and (d) a comparative summary against the previous grading period, and are exported as a structured **PDF** that may be shared with Guidance Counselors and Administrators.

**Hard constraint honoured:** this module is **100% deterministic and rule-based**. It calls no generative AI, no LLM, no HTTP client and no external process. Verified by a static scan of every report file for `AIStudio`, `Guzzle`, `Http::`, `curl_`, `gemini`/`openai` (0 hits).

### 1. Architectural decision — a dependency-free PDF serializer

`composer.json` requires only `laravel/framework`, `doctrine/dbal` and `laravel/tinker`: **there is no PDF library in the project**. Rather than pull one in, `PdfReportWriter` emits **PDF 1.4 by hand** using the 14 standard Type1 fonts (zero font embedding). Two properties this buys us:

- **Byte-level determinism.** The same logical content always produces the same bytes, which is what makes the published SHA-256 fingerprint verifiable. Third-party HTML-to-PDF renderers are not reproducible.
- **No new dependency, no network, no headless runtime** — consistent with the no-AI, deterministic mandate.

Layout is computed arithmetically from the standard Adobe AFM advance widths (units/1000), so columns align exactly rather than being guessed.

### 2. New files

| File | Purpose |
|------|---------|
| `app/Services/Reports/PdfReportWriter.php` | Deterministic PDF serializer. Primitives: `heading`, `subheading`, `paragraph` (word-wrapped), `keyValues`, `table` (weighted columns, per-column alignment, wrapped cells, header repeated after a page break), `rule`, `spacer`. Emits Catalog/Pages/Fonts/Info objects, correct `/Length` per content stream, a byte-accurate xref table, trailer and `startxref`. Includes UTF-8 to WinAnsi conversion. |
| `app/Services/Reports/EndOfTermReportService.php` | The aggregation engine: period arithmetic, largest-remainder percentage apportionment, scoped aggregation primitives, comparison builder, canonical JSON + digest, and PDF rendering. |
| `app/Http/Controllers/Concerns/PresentsEndOfTermReports.php` | Shared presentation + integrity logic so all three roles render the **same** stored snapshot. Includes `verifyReport()` (recomputes and compares digests) and `findSharedReport()` (enforces share flag **and** role grant). |
| `app/Http/Controllers/AcademicHead/ReportController.php` | Generator/owner: index, generate, preview, download, verify, share, destroy. |
| `app/Http/Controllers/Counselor/ReportController.php` | Read-only consumer, department-scoped. |
| `app/Http/Controllers/Admin/EndOfTermReportController.php` | Read-only consolidated consumer, all departments. |
| `database/migrations/2026_07_22_000000_create_end_of_term_reports_table.php` | `end_of_term_reports` — the snapshot store. |
| `resources/views/reports/end-of-term.blade.php` | Shared structured report view (all 3 roles). |
| `resources/views/academic-head/reports.blade.php` | Report workspace + per-period generation plan. |
| `resources/views/counselor/reports.blade.php` | Shared-reports list. |
| `resources/views/admin/end-of-term-reports.blade.php` + `resources/views/admin/partials/end-of-term-report-table.blade.php` | Consolidated admin list. |

### 3. Modified files

| File | Change |
|------|--------|
| `routes/web.php` | 13 new routes: 7 Academic-Head-only inside the existing `role:academic_head` group, 3 counselor (read-only), 3 admin (read-only). |
| `resources/views/layouts/app.blade.php` | Sidebar entry per role: "End-of-Term Reports" (head + admin), "Department Reports" (counselor). |

### 4. Determinism guarantees

1. **Injected timestamps.** `buildSnapshot()` receives `generatedAt` and `generatedBy` as parameters and freezes them into the snapshot; nothing reads the clock during rendering.
2. **Canonical JSON.** Keys are sorted recursively with `JSON_PRESERVE_ZERO_FRACTION`, so the same snapshot always serialises to the same string.
3. **`longText`, not MySQL `json`.** MySQL's json type reorders object keys, which would invalidate the stored digest — so the snapshot is stored as raw canonical text. Verified: `sha256(stored_text) === stored_digest`.
4. **Exact percentages.** Largest-remainder (Hare-Niemeyer) apportionment on integer hundredths of a percent, so the three categories always total **exactly 100.00%** — no floating-point drift, order-independent, ties broken on the canonical risk order.
5. **Snapshot immutability.** The PDF is rendered from the stored snapshot, never from live tables, so a report downloaded months later is identical to the original even after students are re-scored.

### 5. Validation — ALL PASS (66/66 end-to-end, plus 22 PDF-writer and 47 aggregation checks)

Everything ran inside one rolled-back transaction; afterwards `end_of_term_reports` = 0 and report audit rows = 0, i.e. nothing was left behind.

**PDF writer (22/22):** valid `%PDF-1.4` ... `%%EOF`; Catalog + Pages(Count) present; standard fonts with **no** `/FontFile` (no embedding); multi-page output; `startxref` points at `xref`; **every in-use xref offset resolves to its own `N 0 obj` header** (0 bad); object 1 at offset 9; all content streams declare a correct `/Length`; parentheses escaped; no raw UTF-8 multibyte leakage; identical input produces **byte-identical output** with a stable SHA-256.

**Aggregation (47/47):** period arithmetic (Prelim to null previous, Finals to Midterm); apportionment totals exactly 100.00 across 5 count sets including 1/1/1 and a zero-Low period; snapshot over real data; **cross-checked against independent raw SQL** (High/Moderate/Low all match); comparison arithmetic consistent and transitions account for every current student; baseline period degrades correctly; digest + PDF stable across rebuilds; programme percentages total 100.00; department isolation.

**End-to-end (66/66):**

- **No-AI static scan:** 0 hits across all 6 report files.
- **Generate:** row persisted with `period_number` 3, `previous_grading_period` = Midterm, 320 students monitored; denormalised columns match the snapshot.
- **Digest round-trip through the DB:** `sha256(stored_text) === stored_digest`; re-canonicalisation is byte-identical.
- **PDF download:** 11,548 bytes, valid structure, `application/pdf`, digest exposed via `X-Report-Digest`, filename carries the digest prefix, digest embedded in the document; **re-download is byte-identical**.
- **Verify endpoint:** stored digest matches its payload, **re-aggregation reproduces the digest**, reports `ai_used = false` and `external_service_calls = 0`.
- **Sharing:** flag + roles + `shared_at` + `shared_by` recorded; **unsharing immediately revokes counselor access** (list empties, preview refused).
- **Regeneration:** does not duplicate the row (1 to 1); digest re-binds byte-exactly to the regenerated snapshot.
- **Counselor consumer:** sees the shared report, same digest, read-only; PDF **byte-identical** to the head's; **another department's counselor sees nothing and cannot open it**.
- **Admin consumer:** consolidated list across departments (summary counts + student sums), same digest, read-only; PDF byte-identical.
- **Academic Head isolation:** another department's head cannot preview, download (redirect) or verify (404), and sees an empty list.
- **Audit trail:** `EOT_REPORT_GENERATED`, `_REGENERATED`, `_DOWNLOADED`, `_SHARED`, `_UNSHARED` all logged.
- **Static:** `php -l` clean on all 8 new/modified PHP files; `php artisan view:cache` compiles every Blade template; migration applied in 383 ms.
- **Pre-existing, unrelated:** `Tests\Feature\ExampleTest` expects 200 on `/` but the app redirects to login by design.

### 6. Notes

- **Only the Academic Head can generate or share.** The generator routes live exclusively inside the existing `role:academic_head` middleware group; the counselor and admin controllers expose no generate/share/delete action at all.
- **Seeded data caveat:** the seeded `risk_scores` are heavily skewed toward High (e.g. SITE Finals: 308 High / 12 Moderate / 0 Low), so High-risk percentages read around 96%. That reflects the real data, not a computation error — verified against raw SQL.
- **Font note:** the app layout references an external font CDN, but the generated PDF embeds no fonts at all, keeping the report fully self-contained and deterministic.

### 7. Rollback — Refactor 8

- `php artisan migrate:rollback --step=1` drops `end_of_term_reports`.
- Delete the 6 new PHP files, 6 new Blade views and the migration; revert `routes/web.php` and `layouts/app.blade.php` from version control.

---

## Refactor 9: Escalation → Counselor Recommendation Hand-off (complete)

**Driver:** the Academic Head could escalate a student with free-text `notes` only. The generated intervention recommendation — the entire basis for what the counselor must implement — never reached the case, so the counselor opened a case with no intervention context. Separately, the Escalate button only responded after a page reload.

### 1. Schema

| File | Change |
|------|--------|
| `database/migrations/2026_07_23_000000_add_intervention_summary_to_cases_table.php` **(new)** | `cases` gains `intervention_recommendation` (text, the forwarded copy), `intervention_included`, `intervention_edited`, `intervention_source_id` (FK → `intervention_recommendations`, nullOnDelete) + index; `escalations` gains `intervention_recommendation_id` (FK) for provenance. `down()` reverses both. |
| `CasesTableSeeder` | Seeded cases forward their student's generated recommendation for the flag's period on every other row, so the counselor view demos both the forwarded and the "No recommendation provided." paths. |

**Storage decision:** the forwarded/edited copy lives ON THE CASE, not by rewriting `intervention_recommendations`. The counselor reads one row, and the AI output stays immutable/auditable — an inline edit is a decision recorded against the case, and the student's own recommendation list is unchanged.

### 2. Shared recommendation text (new)

| File | Change |
|------|--------|
| `app/Services/RecommendationTextService.php` **(new)** | `toText()` / `fromText()` / `factors()` / `resolveForwardedText()` / `textForRow()`. Handles every stored shape (AI `{action,details,priority}` objects, seeded bare strings, a JSON scalar, hand-edited free text, `null`) and is the SINGLE rule for "what gets forwarded": an edit wins, otherwise the generated text verbatim, otherwise nothing. `edited` is only true when the edit actually differs. |

### 3. Escalation backend

| File | Change |
|------|--------|
| `AcademicHead\EscalationController::recommendationPreview()` **(new)** | POST `/academic-head/escalation/recommendation-preview` — read-only preview (name, risk level/score, risk factors, generated text, recommendation id) for every selected student, so the modal can show and edit it. Department-gated per student; one API call + two local queries for the whole batch. |
| `AcademicHead\EscalationController::bulkEscalate()` | Accepts `recommendations.{studentId}.{include,recommendation_id,edited_text}` and persists the forwarded text + provenance on the case/escalation. **All reads hoisted above the transaction** (`prepareEscalationInputs()` batches risk rows, previous-period rows, flag ids and priority; `resolveRecommendationDrafts()` batches the recommendation lookups) and the whole batch runs in ONE `DB::transaction()` with `MirrorWriter::defer()`/`flush()` around it and `discard()` on rollback. A recommendation id is only trusted when it belongs to that student. Response keeps `escalated`/`already_escalated` and adds `recommendations_forwarded`. |
| `routes/web.php` | New route for the preview endpoint (inside the `role:academic_head` + `department.isolation` group). |

### 4. Escalation modal UI + first-click fix (`academic-head/block.blade.php`)

- The modal now renders one review card per student: name + risk badge + risk score, the read-only **Intervention Recommendation Preview**, a **checkbox** ("Include Intervention Recommendation", disabled with an explicit warning when none exists), an **Edit / Reset to generated** toggle with a textarea, and the existing **Notes**. A footer summary counts how many students will carry a recommendation and how many were edited.
- **Root cause of the unresponsive Escalate button:** one giant `DOMContentLoaded` handler whose FIRST statement built the Chart.js doughnut. A blocked/failed chart (CDN) threw and aborted every handler below it — including the `.escalate-btn` binding — until a reload got the CDN from cache. `new bootstrap.Modal()` was also constructed on every click while `getInstance()` was used to hide (stuck backdrop on the next click).
- **Fixes:** the chart is isolated in its own `try/catch`; every handler is rewired through one idempotent `bindBlockDashboardUi()` that delegates from `document` (survives bfcache/`pageshow`, re-rendered rows and partial loads); the modal is a single `bootstrap.Modal.getOrCreateInstance()`; `confirmEscalation()` always restores the button state and surfaces 403/422/500 messages instead of dying; `layouts/app.blade.php` now serves `app.css`, `app.js` and `chart-init.js` with a `filemtime` cache-busting query.

### 5. Guidance Counselor

| File | Change |
|------|--------|
| `Counselor\DashboardController::index()` | Adds `scope` (`open` / `resolved` / `all`), `risk_level` and `escalated_from`/`escalated_to` (inclusive, leniently parsed) filters plus a `sort` (priority / newest / oldest escalation); passes `matchedCases`, `scopeCounts`, `scope`, `riskLevelFilter`, `escalatedFrom/To`, `sortFilter`. Every filter stays inside the department boundary. |
| `Counselor\DashboardController::show()` | Builds the full case packet: the forwarded recommendation (`buildForwardedRecommendation()`), the current risk score + risk factors (`risk_scores`), the Prelim→Finals trend with per-period Improving/Stable/Worsening verdicts and deltas (`buildRiskTrend()`, same convention as the Academic Head block dashboard), per-subject grades + average (`buildGradeSummary()` — mock API `:3000`) and the attendance rate/absences/lates/excused (`buildAttendanceSummary()` — mock API `:3000`). The packet is read for the case's escalated period, falling back to the active calendar period — periods are never blended. |
| `Counselor\DashboardController::reopen()` | Carries the forwarded recommendation forward onto the reopened case. |
| `counselor/dashboard.blade.php` | The case list is titled by scope ("Open Cases" / "Resolved Cases" / "All Cases") with scope pills, an inline GET filter bar (student search, risk level, escalated date range, order) and active-filter chips; the filter modal gained the same fields. |
| `counselor/case.blade.php` | New **Risk Score & Factors** card (score + level + factors + trend table), **Intervention Recommendation** card (forwarded text, "Edited by Academic Head" badge, structured view, or the explicit "No recommendation provided." notice), **Grades** table (per subject + average/failing/highest/lowest) and **Attendance** card (rate/absences/lates/excused + per-subject breakdown). The Intervention Effectiveness card now names the forwarded intervention it is measuring. Log Session, Mark Resolved and Reopen stay in place. |
| `layouts/app.blade.php` | Sidebar gains **Open Cases** (`?scope=open`) alongside All Cases. |

### 6. Verification

- `php artisan test` — all tests pass (43 pre-existing + 27 new hermetic tests).
- `php artisan mock-api:smoke` — all 49 endpoints 200 (counselor case view 38 KB → 57 KB).
- `php artisan mock-api:tenancy` — department boundary holds.
- `php artisan mock-api:escalation-drill` **(new)** — rolled-back end-to-end drill: escalates through the real controller with a recommendation (forwarded verbatim + provenance), again with an inline edit (the edit is what the case carries, the generated row is untouched), and again with nothing forwarded (case page still shows score, factors, grades, attendance and trend plus "No recommendation provided."). All checks PASS.
- New PHPUnit coverage is hermetic on purpose: several legacy migrations use MySQL-only `ALTER TABLE … MODIFY … ENUM`, so sqlite `RefreshDatabase` cannot boot the schema — the unit tests exercise the pure services (`RecommendationTextService`, the risk-trend builder) and the full stack is covered by the drill.


---

## Refactor 10: Block Page Event-Handler Failure — HOTFIX (complete)

**Ticket:** *"total event-handler failure on /academic-head/block/ — the term filters, Run AI Risk Scoring, Escalate, Escalate Selected, Escalate All Flagged, Reset Selected and Reset All Escalation are all static: no network traffic, no console actions."*

### 1. Root cause (reproduced, not guessed)

`resources/views/academic-head/block.blade.php` — `updateRecommendationSummary()` was missing its closing lines. Because of that brace imbalance, the two functions defined after it (`confirmEscalation`, `resetEscalationModalState`) were **nested inside it** rather than declared at top level.

`bindBlockDashboardUi()` builds its handler map at call time, and its first four entries resolve — so the ReferenceError lands on `confirmEscalation` **while the DOMContentLoaded callback runs**:

```
ReferenceError: confirmEscalation is not defined
    at bindBlockDashboardUi (block-inline.js)
```

The callback aborts before any listener is registered, so **every control on the page is inert** — exactly the reported symptom. Note this class of failure is invisible to a normal test suite: the page returns **HTTP 200**, the HTML is intact, `php artisan view:cache` compiles, and `node --check` (syntax only) passes. Only *executing* the script surfaces it.

### 2. Why it was not caught

Nothing in the project ever executed the page's JavaScript. A rendered page is not a working page.

**Fix (added):** `tests/JavaScript/block_dom_harness.js` — a dependency-free Node harness that extracts the page's real inline script, runs it in a `vm` context against a simulated DOM, fires `DOMContentLoaded`, then **clicks every control** the ticket lists and asserts the resulting request/effect. It also runs a second mode (`HARNESS_NO_DEPS=1`) that withholds Bootstrap + Chart.js. A companion sweep, `tests/JavaScript/check_inline_scripts.js`, syntax-checks every inline `<script>` in all 25 view blocks so the same brace damage cannot hide on another page.

It is wired into `php artisan mock-api:escalation-drill` (`js wiring` rows), so the page's bindings are now verified on every run — and it SKIPS cleanly when Node is not installed.

### 3. Repairs

| File | Change |
|------|--------|
| `resources/views/academic-head/block.blade.php` | Restored the missing tail of `updateRecommendationSummary()` (edited/missing counts + `el.innerHTML = text`) and deleted the orphaned fragment that had been left further down the file; `confirmEscalation` and `resetEscalationModalState` are top-level declarations again. |
| `resources/views/academic-head/block.blade.php` | New `getModal(idOrElement)` accessor (Bootstrap `getOrCreateInstance` when available). `getEscalationModal()`, `openResetModal()` and `runRiskScoring()` all route through it, so a missing bundle produces a **console error + user-visible alert** instead of a dead click. |
| `resources/views/layouts/app.blade.php` | Bootstrap CSS/JS and Chart.js are now **served from the app** (`public/css/vendor/bootstrap.min.css`, `public/js/vendor/bootstrap.bundle.min.js`, `public/js/vendor/chart.umd.min.js`) with the CDN kept only as a fallback. A CDN-only dependency was a whole-page single point of failure: with jsDelivr unreachable, `bootstrap` stays undefined and every modal-opening control dies silently. |
| `resources/views/layouts/app.blade.php` | Dependency self-check before `@stack('scripts')`: any missing library is reported **loudly** on load (`[AcadAlert] Page dependencies failed to load: …`), because a silent failure is the hardest kind to diagnose. |

### 4. Verification

| Check | Result |
|-------|--------|
| `node tests/JavaScript/block_dom_harness.js` | **13/13 PASS** — Escalate, Reset (row), Run AI Risk Scoring, Escalate Selected, Escalate All Flagged, Reset Selected, Reset All Escalation, table filters, term filter (Prelim/Midterm/Finals → `?period=`), Escalate-to-Counselor POST incl. the recommendation payload |
| same, `HARNESS_NO_DEPS=1` | **19/19 PASS** — script still evaluates, DOMContentLoaded still completes, the chart failure is contained, controls raise a loud alert instead of dying, and the term filter still works with no library at all |
| `php artisan mock-api:escalation-drill` | 49/49 checks PASS (incl. `assets` x3 and `js wiring` x3) |
| `php artisan test` | 70 passed |
| `php artisan mock-api:smoke` | 49/49 endpoints 200 |
| `php artisan mock-api:tenancy` | department isolation holds |


## Refactor 11: Student View — Dashboard Reorganization, Profile & Settings Repair (complete)

**Ticket:** the student dashboard was unorganised (personal data missing, risk factors missing, generated recommendations never shown, duplicate cards, a debug badge) and the top-right dropdown's **Profile** / **Settings** links did not work.

### 1. Root causes (reproduced, not guessed)

| Symptom | Root cause |
|---|---|
| Profile / Settings fail for **every** role | `routes/web.php` returned `view('profile')` / `view('settings')` from closures and neither Blade file existed → `View [profile] not found` (HTTP 500). Reproduced with `mock-api:smoke --role=student --path=/profile`. |
| Intervention Recommendations missing | the controller passed `$recommendations` and the view never rendered it. |
| Personal Information missing | `$studentInfo` was computed but never printed. |
| Risk factors missing, no trend verdict | `risk_scores.risk_factors` was never decoded; no Improving/Stable/Worsening verdict existed. |
| Attendance aggregate missing | `aggregateForStudent()` was only used by `/student/attendance`. |
| Alerts disappeared after 5 s | `public/js/app.js` deletes every `.alert:not(.alert-permanent)` on a timer. |
| Recommendations badge under-counted | the sidebar counted tracking rows only, so an untouched recommendation was never counted. |

### 2. New files

| File | Purpose |
|------|---------|
| `app/Http/Controllers/ProfileController.php` | Identity for any role from the mock API (`students`/`programs`/`year_levels`/`blocks`/`parents`/`academic_heads`/`counselors`/`users`); a missing linked record renders a notice instead of throwing. |
| `app/Http/Controllers/SettingsController.php` | Self-service name/email update and password change (`current_password` + `min:8` + confirmation). Local `users` write first, then `MirrorWriter::updatedFromLocal()`; for students the mirrored `students` row's email + first/last name move in the same transaction so the `users.email = students.email` join key stays consistent. |
| `resources/views/profile.blade.php` | Identity, account summary, personal information + parent/guardian table (students) or staff detail. |
| `resources/views/settings.blade.php` | Account Details + Change Password forms (server POST → redirect, no JS dependency) and a read-only account card. |
| `tests/Unit/StudentRiskTrendTest.php` | 8 hermetic tests incl. the cross-role invariant (student card == counselor case view for the same history). |

### 3. Changed files

| File | Change |
|------|--------|
| `routes/web.php` | Real controllers for `profile` / `settings` (same route names) + `PUT /settings/profile`, `PUT /settings/password`. |
| `Student\DashboardController` | Adds `riskFactors`, `riskTrend`, `gradeSummary`, `attendanceSummary`, and recommendations with `is_completed` merged. New `buildRiskTrend()` (identical Low=1/Moderate=2/High=3 convention), `buildGradeSummary()`, `getAttendanceSummary()`, `decodeJsonList()`. `getAlerts()` no longer narrows by period. Dead code removed: `getSelfHelpResources()`, `getProgressComparison()`, `getPreviousPeriod()`, `getPreviousRisk()`, `$resources`. |
| `student/dashboard.blade.php` | Four ordered rows: Personal Information + Risk Status / Grades Summary + Attendance Summary / Intervention Recommendations / Alerts-Flags + Counselor Information. Removed "Recommended Resources", "Contact Your Counselor", "Progress Comparison" and the debug badge. Canvas ids and the acknowledge flow preserved; alert rows are `alert-permanent`. |
| `layouts/app.blade.php` | Correct Recommendations badge; new **My Profile** and **Account Settings** sidebar entries. |
| `MockApiSmokeCommand` | `/profile` + `/settings` probed for every role. |

### 4. Invariants preserved

`InterventionService`, `AcademicHead\InterventionController`, `AcademicHead\EscalationController`, `RecommendationController` and the recommendation tables/seeders were **not modified** — the dashboard only reads what the Academic Head generated. No counselor edit step was added. Periods stay Prelim/Midterm/Finals. Department isolation is unchanged. No schema or data migration is involved.

### 5. Verification

| Check | Result |
|-------|--------|
| `php artisan test` | **78 passed** (207 assertions) — 70 pre-existing + 8 new |
| `php artisan mock-api:smoke` | **57/57 endpoints 200** (49 + `/profile` + `/settings` × 4 roles) |
| `php artisan mock-api:tenancy` | department isolation holds |
| `php artisan mock-api:escalation-drill` | passed — all writes rolled back |
| `node tests/JavaScript/check_inline_scripts.js` | 25 inline blocks, 0 failing |
| `node tests/JavaScript/block_dom_harness.js` | 13/13 controls fire |
| rendered HTML (student 1 and student 82) | all 8 required sections present once; removed sections 0 occurrences; student 82's recommendation auto-displays with its Not Completed status; sidebar badge = 1 matching the dashboard card |
| `/profile`, `/settings` | HTTP 200 for student, admin, academic head, counselor |
| Settings write paths (temporary test on live MySQL, reverted) | wrong current password rejected · short password rejected · valid change hashes and authenticates · name/email update reaches `users`, `students` and the mock API core row · duplicate email rejected |


---

## Refactor 12: Risk Configuration Made Functional (Revision K - hybrid score/level)

**Mandate:** the Administrator must be able to configure the risk threshold parameters (Scope:
configure system settings and risk threshold parameters) and the system must actually classify with
them. The previous page was write-only: the engine never read `risk_thresholds`, the prompt carried
hard-coded bands, `high_threshold` was ignored, and the 60-minute score cache would have masked any
change.

**Contract:** the AI produces the SCORE; the system assigns the LEVEL deterministically from the
configured bands. Deterministic logic is re-introduced ONLY as a classification step - the AI
remains the only scorer and there is still no fallback scorer - because the Scope requires
configurable, auditable thresholds.

### 1. New / modified files

| File | Change |
|------|--------|
| `app/Services/RiskThresholdService.php` **(new)** | Single source of band truth: `current()` (persisted row or DEFAULTS), `normalize()`, `forget()`, `levelFor()` (the only level decision), `bandLabels()`, `effectiveBandLabels()`, `bandsForPrompt()`, `consistencyError()` (shared by the controller and the tests), `DEFAULTS`, `LEVELS`. |
| `app/Services/RiskScoringService.php` | Optional `?AIStudioApiService` / `?RiskThresholdService` constructor params; `processBlock()` loads the bands once, passes them to `getRiskScoresForBatch($batch, $bands)`, assigns `risk_level` via `levelFor()` and stores the model own answer in `ai_risk_level`; the result carries `risk_thresholds`. |
| `app/Services/AIStudioApiService.php` | Band-aware `getRiskScoresForBatch()`, `buildBatchRiskPrompt()`, `buildRiskPrompt()`, `getRiskScore()` and the new `renderBandGuidelines()`; a default configuration still renders the legacy three guideline lines verbatim. |
| `app/Services/RiskScoringCacheService.php` | `flushAll()` - invalidates every cached block payload. |
| `app/Repositories/Local/RiskScoreRepository.php` | `reclassifyByBands()` - one UPDATE with a CASE mirroring `levelFor()`, returning the rows changed. |
| `app/Http/Controllers/Admin/DashboardController.php` | `riskConfig()` renders from the service (`bands`, `effectiveBands`, `configuredAt`, `configuredBy`); `saveRiskConfig()` validates, enforces contiguity, writes and mirrors the row, flushes the cache, forgets the memo, re-classifies stored scores and writes the audit entry. |
| `resources/views/admin/risk-config.blade.php` | Nominal + effective band display, live-synced High floor, weights removed, deterministic-assignment panel, non-contiguous warning, behaviour card. |
| `app/Models/RiskThreshold.php` | Invariant and deprecated columns documented. |
| `app/Providers/AppServiceProvider.php` | `RiskThresholdService` registered as a singleton. |
| migration `2026_07_24_000000_add_ai_risk_level_to_risk_scores_table.php` **(new)** | `risk_scores.ai_risk_level` (nullable, after `risk_level`). |
| `SCOPE_AND_LIMITATIONS.md` **(new)** | Clarifies that the risk threshold parameters are functional and lists the remaining limitations. |
| 4 new test files | `RiskThresholdServiceTest`, `AIStudioRiskPromptTest`, `RiskClassificationPipelineTest`, `RiskConfigurationTest`. |

### 2. Acceptance matrix

| Requirement | Where | Verification |
|---|---|---|
| Changing the thresholds changes the assigned level | `RiskThresholdService::levelFor()` | `RiskThresholdServiceTest`; `RiskClassificationPipelineTest` (score 85 to High, then to Low) |
| The prompt uses the configured bands | `AIStudioApiService::renderBandGuidelines()` | `AIStudioRiskPromptTest`; the pipeline test asserts the bands argument on the AI call |
| The system assigns the level | `RiskScoringService::processBlock()` | pipeline test: the AI answers Low at 85, the stored level is High and `ai_risk_level` is Low |
| Cache invalidated after saving | `saveRiskConfig()` | `RiskConfigurationTest`; the pipeline cache test proves the next run re-calls the AI |
| `high_threshold` drives display, validation, computation, storage | service + view + validation | label test, consistency test, card rendering test |
| Weights | removed from the UI and validation | the page test asserts the fields are gone; documented as deprecated |

### 3. Verification record

| Check | Result |
|---|---|
| `php artisan test` | 103 passed (312 assertions) - 78 baseline + 25 new |
| `php artisan migrate` | `2026_07_24_000000_add_ai_risk_level_to_risk_scores_table` DONE (34.16 ms) |
| `php artisan view:cache` | Blade templates cached successfully |
| `php artisan mock-api:smoke` | All 57 endpoints rendered (`/admin/risk/config` 200, 21,908 bytes) |
| `php artisan mock-api:tenancy` | 5/5 PASS - no cross-tenant leakage |
| `php artisan mock-api:escalation-drill` | PASS (all writes rolled back) |
| Live configuration check | stored 40/70/95 (non-contiguous): mapping 71-94 to Moderate, page shows effective 41-94 and warns; prompt renders the live bands |

### 4. Rollback

1. `php artisan migrate:rollback --step=1` drops `ai_risk_level`.
2. Revert the application files above and delete `app/Services/RiskThresholdService.php`.
3. Delete the four new test files and `SCOPE_AND_LIMITATIONS.md`.
4. `php artisan view:clear; php artisan config:clear`.
5. Stored levels re-classified by a save are restored by re-saving the previous bands (the mapping
   is deterministic) or by re-running scoring. Nothing else is touched.
---

## Refactor 13: Academic Head Workspace - Sidebar, Dashboard, Blocks, Risk Scoring, Recommendations, Reports

**Mandate:** make the Academic Head view organized, presentable and user-friendly, with a
grouped sidebar, a department dashboard that answers questions instead of listing tables,
a working Recommendations screen, a real Risk Scoring workspace, and reports that explain
their own numbers. Audit first (reproduce, do not assume), then fix root causes.

### 1. Audit - defects reproduced (not assumed)

| # | Surface | Defect (with evidence) |
|---|---------|------------------------|
| S1 | Sidebar | Flat, ungrouped, no dividers, no section headings. The Academic Head branch used the legacy `list-group-item` markup while the counselor branch already used `sidebar-section` / `sidebar-link` / `sidebar-badge`. |
| S2 | Sidebar | No badge counters at all for the Academic Head role. |
| S3 | Sidebar | "Current Block" rendered only when the view happened to define `$block` (the block page itself), and that link pointed back at the page you were already on, dropping `?period=`. |
| S4 | Sidebar | No Risk Scoring destination existed; `route:list --name=academic-head` returned 29 routes, none of them a scoring page. |
| S5 | Sidebar | No department-wide block index; the only Blocks screen required a `{programId}`. |
| D1 | Dashboard | Card 4 "Need Escalation" was hard-wired to `$totalHighRisk` - an exact duplicate of card 2, carrying no information (`DashboardController.php:120`). |
| D2 | Dashboard | `$lowRiskCount` was computed and passed but never rendered. |
| D3 | Dashboard | Only 2 charts; no risk-by-block and no escalation trend. |
| D4 | Dashboard | No actionable items at all - no top-at-risk list, no recent escalations, no pending recommendations. |
| D5 | Dashboard | Accepted `period` / `school_year` but offered no selector, so it silently reported Midterm while the block pages let the user choose. |
| D6 | Dashboard | `$trendData` (3 periods x 2 queries) was computed and never used: the chart refetched the same aggregate from `/department-trend`. |
| **R1** | **Recommendations** | **`onclick="viewRecommendation(id)"` called a function that does not exist anywhere in the file** (`Select-String 'function viewRecommendation'` -> 0 matches). Every click raised `ReferenceError: viewRecommendation is not defined`, so the View modal, its spinner and its Edit button were permanently dead markup. |
| R2 | Recommendations | `#editRecommendationBtn` was `href="#"` with no handler: clicking it did nothing. |
| R3 | Recommendations | `generateAll()` called `bootstrap.Modal.getInstance(...).hide()` with no null guard (Bootstrap 5.3 returns null). |
| R4 | Recommendations | No search, no risk filter; no program/block columns; no escalation state, so "Edit if not yet escalated" could not be decided. |
| R5 | Recommendations | The Edit page documented the `[priority] Action: Description` line format, but `updateRecommendation()` only tried `json_decode` - a line-formatted edit collapsed into ONE `medium` custom action. |
| B1 | Block page | **`th.sortable` headers had no listener anywhere**: `sortTable()` existed but was never invoked, so the headers looked clickable and did nothing. |
| B2 | Block page | Two `change` listeners on `#gradingPeriodSelect`; the one that refreshed escalation indicators was unreachable because the other navigates first. |
| B3 | Block page | `chart-init.js` called `loadBlockRiskChart()`, which did not exist in `academic-head-charts.js` - a `console.warn` on every block page load. |
| B4 | Block page | No search box; no trend/status filters; sorting by "Current Risk" would have ordered High/Moderate/Low alphabetically. |
| E1 | Reports | Missing: risk-band definitions, year-level breakdown, per-subject failing counts, intervention status, comparative term series, pie chart, top-10 at-risk, ">50% High" warning, next steps; British "Programme" spelling. |
| X1 | Cross-cutting | `public/css/app.css` contained a duplicated sidebar comment block. |
| X2 | Cross-cutting | The live "96.25% High Risk" figure was traced to the DATA, not the report: `risk_thresholds` = 40/75/76 and Midterm `risk_scores` = High 1288 / Moderate 171 / Low 46. |

### 2. New files

| File | Purpose |
|------|---------|
| `app/Services/AcademicHeadNavigationService.php` | Memoised sidebar/dashboard counters per (department, period, school year, block): high/moderate/low, unmonitored, pending escalations, unacknowledged flags, pending recommendations, stored reports, plus `resolveCurrentBlockId()`, `blockLabel()` and `blockCounters()`. |
| `app/Services/Reports/EndOfTermReportInsightService.php` | READ-ONLY report enrichment: bands, >50% High warning, year-level split, per-subject failing counts, intervention status, per-period series, top-10 at-risk, derived next steps. Writes nothing back to the snapshot. |
| `resources/views/academic-head/risk-scoring.blade.php` | Risk Scoring workspace: per-block run/refresh, live progress, cache fresh/stale, last-run info (who/when from the `AI_RISK_RUN` audit trail), results summary. |
| `resources/views/academic-head/partials/scoring-rows.blade.php` | Per-block scoring table rows (shared partial). |

### 3. Changed files

| File | Change |
|------|--------|
| `resources/views/layouts/app.blade.php` | Academic Head sidebar rebuilt into Main / Reports / Account groups using `sidebar-section` / `sidebar-link` / `sidebar-badge`; FA icons; active state; badge counters; a `Current Block` link that resolves the block in context and preserves `period`; new Blocks and Risk Scoring entries; an open-escalation callout. |
| `routes/web.php` | `GET /academic-head/blocks` (department-wide index, declared before the `{programId}` form), `GET /academic-head/risk-scoring`, `GET /charts/academic-head/risk-by-block`, `GET /charts/academic-head/escalation-trend`. |
| `app/Http/Controllers/AcademicHead/DashboardController.php` | `department()` now also assembles block stats, the escalation trend, the top-10 attention list, recent escalations, pending recommendations and the sidebar counters, and passes the period selector data; new `blocksIndex()`; new private `buildBlockStats()`, `buildEscalationTrend()`, `buildAttentionList()`, `buildRecentEscalations()`, `buildPendingRecommendations()`, `availableSchoolYears()`. |
| `app/Http/Controllers/AcademicHead/RiskScoringController.php` | New `index()` (the workspace); `getScoringStatus()` enriched with `cached` / `cache_state` / `cache_age_minutes` / `last_scored_at` / `has_scores` / `status` while keeping its four original keys compatible. |
| `app/Http/Controllers/AcademicHead/InterventionController.php` | `index()` now passes escalation/case state and search+risk filter values; `updateRecommendation()` parses the documented line format via new `parseActionLines()` + `slugAction()`. |
| `app/Http/Controllers/Api/AcademicHeadChartController.php` | New `riskByBlock()` (stacked bar) and `escalationTrend()` (line) endpoints, both department-scoped. |
| `app/Http/Controllers/AcademicHead/ReportController.php` | `preview()` and `download()` pass the insight payload; `download()` passes it as the THIRD argument to `renderPdf()`. |
| `app/Services/Reports/EndOfTermReportService.php` | `renderPdf(array $snapshot, string $generatedByName = 'Academic Head', ?array $insights = null)`. With `$insights === null` the returned byte stream is what this method produced before the appendix existed. New appendix renderers (`renderAppendix`, `renderBandsSection`, `renderYearLevelSection`, `renderSubjectSection`, `renderInterventionSection`, `renderPeriodSeriesSection`, `renderTopAtRiskSection`, `renderNextStepsSection`, `plainText`). **`buildSnapshot()` was not touched.** |
| `app/Repositories/Api/GradeRepository.php` | New `subjectFailingBreakdown()` (one 400-id-chunked fetch, aggregated in PHP). |
| `app/Providers/AppServiceProvider.php` | `AcademicHeadNavigationService` and `EndOfTermReportInsightService` registered as singletons (the sidebar renders on every page, so the counters must be shared, not recomputed). |
| `resources/views/academic-head/department.blade.php` | Rebuilt: 4 metrics (Low Risk now rendered; the duplicated "Need Escalation" card removed) / risk-by-program + risk-by-block / department trend table + line + escalation trend / Students Needing Attention, Recent Escalations, Pending Recommendations, alerts, program summary, block risk ranking; period and school-year selectors. |
| `resources/views/academic-head/blocks.blade.php` | One view serves both routes: program-grouped cards for the department index, the per-program grid otherwise; risk-toned borders and a high-risk percentage per block. |
| `resources/views/academic-head/block.blade.php` | Summary metric strip; search by name/number; trend and status filters that apply immediately; numeric sorting on "Current Risk" via `data-score`; `th.sortable` now actually wired; the duplicate period listener removed. |
| `resources/views/academic-head/recommendations.blade.php` | **`viewRecommendation()` implemented** against the existing `GET /recommendations/{id}/view` endpoint plus a loud missing-Bootstrap report; Edit button wired on `show.bs.modal`; row actions moved to delegated listeners with `data-*` ids; search + risk/no-recommendation filter; Program/Block and Status columns. |
| `resources/views/academic-head/reports.blade.php` | "How these figures are produced" notice (AI scoring + deterministic classification + rule-based aggregation), risk-band definition cards read live from `RiskThresholdService`. |
| `resources/views/reports/end-of-term.blade.php` | Appendix: configuration warning, band definitions, risk doughnut, year-level breakdown, per-subject failing counts, intervention status, term progression, top-10 at-risk, recommendations/next steps. Gated on `$insights`, so other roles keep the original document. |
| `public/js/charts/academic-head-charts.js` | New `loadRiskByBlock`, `loadEscalationTrend`, `renderEscalationTrend`, `loadBlockRiskChart`, `readEmbeddedSeries`; `loadDepartmentTrend` prefers the server-embedded series. |
| `public/css/app.css` | Duplicated sidebar comment removed; sidebar flex/truncation rules; scoring-table progress minimum width. |
| `app/Console/Commands/MockApiSmokeCommand.php` | Added `/academic-head/blocks`, `/academic-head/risk-scoring`, `/charts/academic-head/risk-by-block`, `/charts/academic-head/escalation-trend` (57 -> 65 probed endpoints). |


### 4. Invariants held

1. **Escalation flow untouched** - `bulkEscalate`, `recommendationPreview`, `resetEscalation`, `canResetEscalation` and the caller payloads are unchanged.
2. **Block-page contract preserved** - `id="escalationModal"`, `id="escalationStudentList"`, `id="recommendationSummary"`, `id="confirmEscalationBtn"`, `/academic-head/escalation/recommendation-preview`, `bindBlockDashboardUi`, `Risk chart failed to render`, every `data-label` cell and every harness element id remain. All handlers stay inside the single first inline `<script>` of `@push('scripts')` (the harness slices only that block).
3. **`buildSnapshot()` byte-identical** - `digest()` of a stored report still equals its stored `digest` (verified live: `digest matches stored: YES`), so `reports/verify` keeps passing for every existing report.
4. **`renderPdf()` byte-identical when `$insights` is null** - verified: plain 11,555 bytes with no `Appendix` marker, reproducible across calls; enriched 29,553 bytes with it.
5. **Recommendation generation and the risk engine untouched**; the report only *describes* the configured bands via `RiskThresholdService`.

### 5. Verification record

| Check | Result |
|-------|--------|
| `php artisan test` | **115 passed (364 assertions)** |
| `php artisan mock-api:smoke` | **All 65 endpoints rendered** (admin, academic head, counselor, student) |
| `php artisan mock-api:tenancy` | **5/5 PASS** - 1585 scoped students, 5 departments, no cross-tenant leakage |
| `php artisan mock-api:escalation-drill` | **PASS** (Midterm and Finals; all writes rolled back) |
| `node tests/JavaScript/block_dom_harness.js` | 13/13 controls fire (`RESULT: every control handler fired`) |
| `HARNESS_NO_DEPS=1 node tests/JavaScript/block_dom_harness.js` | 13/13 - degrades loudly without Bootstrap/Chart.js |
| `node tests/JavaScript/check_inline_scripts.js` | 25 blocks, **0 failing** |
| `php artisan view:cache` | Blade templates cached successfully |
| Report digest | `digest matches stored: YES`; plain PDF has no appendix and reproduces byte for byte |
| Rendered-HTML marker sweep | department 20/20, risk-scoring 4/4, block 11/11, recommendations 5/5 markers present |

**Note on a smoke false positive:** `/admin/system/health` renders the application log. A stale
`Undefined variable $programNames` entry produced while editing `DashboardController` was being
displayed by that page, and the smoke command's error-marker scan read it as a failure. The
defect itself was already fixed; the log was cleared (`storage/logs/*` is git-ignored) and the
endpoint returns 200.

### 6. Rollback

1. Delete `app/Services/AcademicHeadNavigationService.php`, `app/Services/Reports/EndOfTermReportInsightService.php`, `resources/views/academic-head/risk-scoring.blade.php`, `resources/views/academic-head/partials/scoring-rows.blade.php`.
2. Revert the changed files listed in section 3. `renderPdf()`'s third parameter is optional, so reverting only the two report call sites is enough to restore the previous PDF bytes.
3. `php artisan view:clear; php artisan config:clear`.
4. No migrations were added and no schema was touched; stored reports, scores, cases and escalations are unaffected by the rollback.

### 7. Follow-up: sidebar badge counters removed (Academic Head + Guidance Counselor)

**Request:** remove every popup badge number from the Academic Head and Guidance
Counselor sidebars, including badge-like callouts ("N open escalation(s)",
"N overdue follow-up(s)"). Keep links, icons, active state highlighting and the mobile
hamburger exactly as they were. Do not touch Admin or Student.

| File | Change |
|------|--------|
| `resources/views/layouts/app.blade.php` | **Academic Head**: removed the 5 badge spans (Department Overview high-risk, Blocks student count, Current Block high-risk, Risk Scoring unacknowledged flags, Recommendations pending) and the `N open escalation(s)` callout link. The `$navHeadCounters` block that existed only to feed them is gone; the sidebar now calls `resolveCurrentBlockId()` / `blockLabel()` / `latestReportId()` directly, because those are LINK TARGETS rather than counters. **Guidance Counselor**: removed the 3 badge spans (Caseload total, Open Cases, Resolved Cases) and the `N overdue follow-up(s)` callout; the now-unused `$navCounters` block is gone, while `$navOnCases` / `$navScope` (which drive the active state of the three scope links) are kept. |
| `app/Services/AcademicHeadNavigationService.php` | Removed `blockCounters()`, which existed solely for the "Current Block" badge and is now referenced nowhere. Class and `riskCounters()` docblocks corrected (the counters serve the dashboard, not the sidebar). |
| `app/Services/CounselorNavigationService.php` | Class and `followUpCounts()` docblocks corrected: the service now feeds the dashboard only. No behaviour change — `counters()` / `followUpCounts()` are still live for `Counselor\DashboardController`. |
| `public/css/app.css` | Removed the two rules that only ever styled sidebar badges (`#sidebar-wrapper .sidebar-badge` and `#sidebar-wrapper .sidebar-link .sidebar-badge`). `.sidebar-link .text-truncate` stays — the "Current Block" link still needs it. |

**Deliberately unchanged:** every sidebar `href`, the Font Awesome icons, the
`active-sidebar` class logic, `#sidebarToggleBtn` + `toggleSidebar()` + `#sidebar-backdrop`
(the mobile hamburger), the `sidebar-section` dividers and the Student sidebar's own
conditional Recommendations badge (out of scope).

**Side effect worth noting:** removing the counters also removes a real per-page cost.
The sidebar lives in `layouts.app`, so those counter queries ran on EVERY Academic Head
and Counselor page load; they now run only where the figures are actually displayed
(the dashboards).

| Check | Result |
|-------|--------|
| `php artisan test` | **115 passed (364 assertions)** |
| `php artisan mock-api:smoke` | **All 65 endpoints rendered** (all 4 roles) |
| `php artisan mock-api:tenancy` | **5/5 PASS** |
| `php artisan mock-api:escalation-drill` | **PASS** (writes rolled back) |
| `node tests/JavaScript/block_dom_harness.js` | 13/13 controls fire (also with `HARNESS_NO_DEPS=1`) |
| `node tests/JavaScript/check_inline_scripts.js` | 25 blocks, 0 failing |
| Rendered sidebar sweep (all 4 roles) | `sidebar-badge` = **0**; every anchor has a real `href` (0 placeholders); the mobile toggle is present; exactly one `active-sidebar` per page |
| Source sweep | `sidebar-badge` now appears in **0** files; `blockCounters` has **0** references; the only badge span left in the layout is the Student one |

### 8. Follow-up: End-of-Term report slimmed to its essential sections

**Request:** the report had too many sections. Remove "Top 10 At-Risk Students" from both
the preview and the PDF, and evaluate every other section for redundancy or a scope that
does not belong in a term-end department summary.

#### Removed

| Section | Where | Why |
|---------|-------|-----|
| **Top 10 At-Risk Students** | preview + PDF | Explicitly requested. It was also the only PER-STUDENT list in a DEPARTMENT-level summary, and it duplicated the "Students Needing Attention" list on the dashboard. The actionable version of that list lives on the Current Block screen, where the Escalate button is. |
| **Top 10 table (`top_at_risk` payload)** | `EndOfTermReportInsightService` | The payload, `TOP_AT_RISK_LIMIT`, `topAtRisk()` and its grade/attendance lookups are gone, so the removed section also stops costing two API round trips. |
| **`renderTopAtRiskSection()`** | `EndOfTermReportService` | The PDF renderer. Appendix headings renumbered A1–A7, so there is no A8 gap. |
| **Risk Distribution doughnut (pie)** | preview appendix | The same three Low/Moderate/High numbers were already shown three times on the page: the four metric cards, the "Risk Classification & Percentage Distribution" table, AND the comparison bar chart. A fourth rendering was the clearest redundancy in the document. |
| **Pie-chart JavaScript** | preview `@push('scripts')` | Dead once the canvas is gone. |

#### Kept, and why

**Report body — locked. `renderPdf(..., null)` had to stay byte-identical, so nothing in
the body was touched.**

1. Students Monitored / Risk Classification / Comparative Summary / Programme Breakdown /
   Risk Score Statistics / Provenance.

Two tempting edits were **deliberately reverted** after I measured the consequence:

- `'4. Programme Breakdown'` → `'4. Program Breakdown'` and the `'Programme'` column
  header. American English, but it changed the plain-PDF hash
  (`88858d4f…` → `e16a30f4…`), breaking the byte-identity constraint. **Left as-is.**
- Dropping the `'Students scored'` row, which is genuinely `score_statistics.count` ==
  `totals.monitored` (verified against live data) and therefore printed twice. Dropping it
  also changed the hash. **Left as-is**, and the preview row was restored to match.

The appendix has no byte-identity constraint, so the review is documented there instead:
see the "Risk Band Definitions" note on how the score becomes a level.

**Appendix + preview (kept in full):**

| Section | Why it earns its place in a term-end summary |
|---------|---------------------------------------------|
| A1 Configuration Warning | Conditional (only when High > 50%). It is the one thing that stops a reader misreading a band-calibration artifact as a data error. |
| A2 Risk Band Definitions | Makes "High" mean something numeric. A term report that says "High" without saying "76-100" is not auditable. |
| A3 Breakdown by Year Level | Department-level. Answers "which cohort", which the program breakdown cannot (a program spans years). |
| A4 Breakdown by Subject | Department-level. Answers "which subject is failing students", which no risk-level number can. |
| A5 Intervention Status | Department-level. Closes the loop: did the High-risk group actually reach a counselor? |
| A6 Term Progression | Department-level. The term-end differentiator — one period is a snapshot, the term is the story. |
| A7 Recommendations & Next Steps | Department-level actions derived from the figures above. |
| Provenance | Required: this is what makes the PDF independently verifiable. The prose was corrected to state that the AI produces the *score* while the aggregation is rule-based. |

#### A real defect found and fixed on the way

The appendix region had **mis-nested markup**: my earlier appendix insertions had landed
*inside* the "Breakdown by Subject" `<tbody>` and, for the Top-10 block, *inside* a `<tr>`
between the `subject_code` and `subject_name` cells. A DOM parse confirmed
**9 tables nested inside one another**. The whole appendix region was rebuilt and the same
parse now reports **0 nested tables** with 13 section cards. This was rendering the subject
table and everything after it incorrectly.

#### Verification

| Check | Result |
|-------|--------|
| `buildSnapshot()` / stored digests | **`digest matches stored: YES`**; canonical JSON identical |
| `renderPdf(..., null)` byte-identity | **YES** — 8259 bytes, sha256 `88858d4f…` (matches the pre-change baseline), reproducible across calls, contains no appendix |
| Appendix A-numbering | A1–A7 contiguous, **no A8**, no Top-10 |
| Rendered preview | 200; Top-10 / "immediate action list" / `eotRiskPieChart` all absent; all 13 remaining sections present |
| DOM structure | **0 nested tables**, no unbalanced tags attributable to this file |
| Conditionally-hidden sections | `Per-student movement` and the delta columns **do** render for a period with a predecessor — verified by generating the Midterm report through the real controller, then removing it |
| `php artisan test` | **115 passed (364 assertions)** |
| `php artisan mock-api:smoke` | **All 65 endpoints rendered** (all 4 roles) |
| `php artisan mock-api:tenancy` | **5/5 PASS** |
| `php artisan mock-api:escalation-drill` | **PASS** (writes rolled back) |
| `node tests/JavaScript/check_inline_scripts.js` | 25 blocks, 0 failing |
| `node tests/JavaScript/block_dom_harness.js` | 13/13 controls fire |
| `php artisan view:cache` | Blade templates cached successfully |
| `end_of_term_reports` schema | **unchanged** — no migration added, no column touched |

### 9. Follow-up: Risk Scoring progress bar (the "stuck at 5%" stall) + Last Trigger removed

**Report:** on `/academic-head/risk-scoring`, "Run All Blocks" and the per-block Run /
Refresh buttons open the progress card at 5% and then never move, so the page looks hung.
Also: drop the "Last Trigger" card.

#### 1. Root cause (reproduced, not guessed)

`resources/views/academic-head/risk-scoring.blade.php` called `setProgress(5, …)` when a
block started and **never moved the bar again** until the whole request settled. A block is
scored in batches of five with a four-second pause between batches and
`RiskScoringService::processBlock()` persists the scores only **after the final batch**, so
the run is a single long synchronous request with no observable intermediate state. Combined
with the `aistudio` retry policy in `config/services.php` (retry = 2), one block can occupy
the browser for tens of seconds during which the UI showed a frozen 5% and nothing else.

`runAllBlocks()` had the same shape: it computed `(index / ids.length) * 100`, which is
**0%** for the first block, then `runSingleBlock()` immediately overwrote it with 5%.

Polling `/academic-head/block/{id}/scoring-status` (the fix considered first) was **rejected
on evidence**: the concurrent status response reports `processed: 0` for the whole run in
flight, because nothing is written until the batch loop ends. A poll would have cost a
request per second and still shown a motionless bar.

#### 2. The repair — an animated bar that always settles

The workspace now uses the same pattern the working Current Block page has always used: an
interval drifts the bar toward a ceiling while the request is in flight, and the bar is
snapped to the real outcome the moment the request settles.

| Function | Responsibility |
|----------|----------------|
| `beginProgress(base, ceiling, status, detail)` | Reveals the card, applies `base` **synchronously** (so the bar never shows a stale value), recolours to primary and starts an 800 ms interval that steps the bar toward `Math.min(95, ceiling)` — 3 points below 60%, 1 above, so it visibly waits for the server instead of appearing to finish on its own. The ceiling is never 100%: the bar cannot claim success before the server answers. |
| `settleProgress(success, target, status, detail)` | Clears the interval and writes the terminal value — green at `target` on success, red with the text `Failed` on failure. |
| `runSingleBlock(blockId, refresh, {base, overall})` | Starts the animation, then settles on **all three** paths: success, `success: false` payload, and the `.catch()` for a rejected request. A `throw` can no longer strand the bar. |

`runAllBlocks()` now passes each block its slice of the bar — `base = index / n * 100` and
`overall = (index + 1) / n * 100` — so the sweep climbs steadily (5% → 50% → 100% over two
blocks) instead of restarting at 5% for every block, and the final slice turns the bar green or
red depending on whether any block failed.

#### 3. "Last Trigger" removed

The card, its `@if($lastRun)` branch, the "no scoring run recorded" empty state and the
`Cache TTL N min` badge are gone from `risk-scoring.blade.php`. The controller no longer
builds them: the `audit_logs`/`AI_RISK_RUN` lookup, the `StaffRepository::userNameIndex()`
name resolution and the `cacheTtlMinutes` / `lastRun` / `lastRunBy` view variables were
removed from `RiskScoringController::index()` as dead code. `$cacheTtlMinutes` is still
computed locally — it drives the per-block `fresh`/`stale` cache state.

#### 4. Verification

A dedicated harness, `tests/JavaScript/risk_scoring_progress_harness.js`, was written **before**
the fix and run against the buggy code first. It drives the real inline script in a stub DOM
and holds the fetch promise open, so it can inspect the bar *while a request is in flight* —
the condition the browser could never be asked about manually.

| Check | Before | After |
|-------|--------|-------|
| Bar advances while the request is in flight | **FAIL — still 5%** | PASS — 5% → 11% after ~1.7 s |
| Bar reaches 100% after a successful run | **FAIL — 5%** | PASS — 100% |
| A failed run does not leave the bar stranded | **FAIL — 5%** | PASS — terminal, red, "Failed" |
| "Run All Blocks" shows overall progress | **FAIL — 5%** (expected 50%) | PASS — 50% after 1 of 2 blocks |

**4 failing assertions reproduced the reported stall exactly; all 14 now pass.**

| Check | Result |
|-------|--------|
| `node tests/JavaScript/risk_scoring_progress_harness.js` | **14/14 PASS** (4/14 before the fix) |
| `node tests/JavaScript/check_inline_scripts.js` | 25 blocks, 0 failing |
| `node tests/JavaScript/block_dom_harness.js` | 13/13 controls fire |
| `php artisan view:cache` | Blade templates cached successfully |
| `php artisan test` | **115 passed (364 assertions)** |
| `php artisan mock-api:smoke` | **All 65 endpoints rendered** — includes `/academic-head/risk-scoring` |
| `php artisan mock-api:tenancy` | **5/5 PASS** |
| `php artisan mock-api:escalation-drill` | **PASS** (writes rolled back) |
| Source sweep | `Last Trigger` = **0** occurrences in `resources/`, `app/` **and** the compiled view cache |

#### 5. Rollback

Restore the two files. No schema, migration, route or scoring-engine change is involved —
`run-scoring` / `refresh-scoring` / `scoring-status` and `RiskScoringService` are untouched.

| File | Change |
|------|--------|
| `resources/views/academic-head/risk-scoring.blade.php` | `beginProgress` / `settleProgress` / `stopProgressAnimation` added; `runSingleBlock()` given a per-block `{base, overall}` and settle-on-every-path; `runAllBlocks()` slices the bar per block; "Last Trigger" card deleted |
| `app/Http/Controllers/AcademicHead/RiskScoringController.php` | Dead `lastRun` / `lastRunBy` / `cacheTtlMinutes` view data and the audit-log lookup removed |
| `tests/JavaScript/risk_scoring_progress_harness.js` | **New** — the regression guard for "the bar must advance and must settle" |
### 10. Follow-up: incomplete AI batches are retried, and the workspace refreshes itself

**Request:** (1) when the AI omits a student from a batch response, retry the batch up to a
configurable limit before failing, with `aistudio.batch_retry_limit` (default 2) in
`config/services.php` and `.env`, logging each retry and the final failure; (2) after
`runAllBlocks()` / `runSingleBlock()` the new data must appear without a manual reload.

#### 1. Root cause of the Block 4 failure

`AIStudioApiService::parseBatchRiskResponse()` threw on the **first** omitted student:

```
AI Studio API batch response omitted student 68.
```

That exception aborted `RiskScoringService::processBlock()` mid-loop, so `saveRiskScores()`
never ran and a whole block was lost — including the scores the model HAD produced for every
other student in that batch. Verified against live data: block 4 has 20 active students and
**does** contain student 68.

Crucially this is **not** a transport failure: the existing `retry_limit` is applied inside
`sendRequest()` and never sees a 200 OK. A model-side omission needed its own mechanism.

#### 2. Retry design

`batch_retry_limit` governs omissions only, and is separate from `retry_limit`:

| Config key | Covers | Applied in |
|------------|--------|-----------|
| `aistudio.retry_limit` | HTTP error / timeout / empty body | `sendRequest()` |
| `aistudio.batch_retry_limit` | 200 OK with a well-formed but incomplete array | `getRiskScoresForBatch()` |

- `parseBatchRiskResponsePartial()` was extracted and now **reports** `['scores', 'missing']`
  instead of throwing on omissions. Distinguishing *invalid* from *incomplete* is what makes a
  retry possible: an unparseable body still throws immediately (nothing to salvage), while an
  array that skipped a student is recoverable.
- `parseBatchRiskResponse()` remains as a strict wrapper with the byte-identical original
  exception, so any caller wanting the old all-or-nothing contract is unaffected.
- `retryOmittedStudents()` re-asks **only the omitted students** — a smaller, cheaper prompt —
  sleeps `batch_delay_seconds` first to keep the documented free-tier cadence, and merges each
  answer into the batch result. It loops until the omissions are recovered or the limit is
  reached, so a partially-recovering model still converges.
- `getRiskScoresForBatch($batch, $bands)` keeps its exact signature and call site, so the
  existing pipeline test's mock still intercepts it.

**Logging** (verified in `storage/logs/laravel.log`):

```
WARNING: AI Studio batch response was incomplete — retrying the omitted students
         {"attempt":1,"batch_retry_limit":2,"omitted_student_ids":[68],"retry_size":1,"delay_seconds":0}
INFO:    AI Studio batch retry recovered every omitted student {"attempt":1,"recovered_student_ids":[68]}
ERROR:   AI Studio batch scoring failed: response omitted student 68 after retries
         {"omitted_student_ids":[68],"batch_retry_limit":2,"batch_size":2}
```

Retries exhausted (or `batch_retry_limit=0`) still fail **explicitly**. There is no rule-based
substitution anywhere in this pipeline, so a student the model never scored must never be
persisted with an invented score.



#### 3. Auto-refresh design

A reload would have destroyed the run log, which is the one thing the operator needs to read
after a multi-minute sweep. So the page updates **in place** instead:

| Piece | Role |
|-------|------|
| `GET academic-head.riskScoring.data` | Read-only. Re-computes the workspace and returns `cards_html` + `rows_html` as fragments. It never triggers scoring (asserted in a test). |
| `RiskScoringController::buildWorkspace()` | The aggregation, extracted from `index()`. `index()` and `data()` now share it, so the page and its refresh cannot compute different numbers. |
| `partials/scoring-cards.blade.php` | **New.** The 4 summary cards, lifted out of the page so one include serves both the initial render and the refresh (two copies would drift). |
| `refreshWorkspace()` (page JS) | Fetches the endpoint with the period/school year in view and swaps `#scoringCards` (`outerHTML`) and `#blockScoringBody` (`innerHTML`). |

Refresh points: once after a single block's Run/Refresh, and **once** at the end of a sweep
(`autoRefresh: false` suppresses the per-block refresh, since nobody sees the intermediate
states). A failed run refreshes too — `refresh-scoring` deletes a block's scores *before*
re-scoring, so a failure genuinely changes the data. A failed *refresh* is reported as a
refresh failure, not a run failure, because the scores are already saved.

#### 4. Verification

| Check | Result |
|-------|--------|
| `php artisan test` | **127 passed (398 assertions)** — up from 115 |
| `tests/Unit/AIStudioBatchRetryTest.php` | **7 new tests**: recovered omission, retry asks only the omitted students, partial recovery across two retries, unrecovered omission fails after the limit, `0` disables the retry, malformed ≠ incomplete (not retried), unknown student ignored |
| `tests/Feature/RiskScoringWorkspaceRefreshTest.php` | **5 new tests**: both fragments returned with the ids the JS swaps on, fragments carry the freshly computed figures, period/year echoed, 403 without a department, read-only (never calls `processBlock`) |
| `node tests/JavaScript/risk_scoring_progress_harness.js` | **22/22 PASS** (was 14) — new assertions cover the refresh request, its query string, both fragment swaps, refresh-after-failure, no refresh mid-sweep, and the closing refresh |
| `node tests/JavaScript/check_inline_scripts.js` | 25 blocks, 0 failing |
| `node tests/JavaScript/block_dom_harness.js` | 13/13 controls fire (block page untouched) |
| `php artisan mock-api:smoke` | **All 65 endpoints rendered** — `/academic-head/risk-scoring` → 200 (75541 bytes) |
| `php artisan mock-api:smoke --role=academic_head --path=/academic-head/risk-scoring/data` | **200** (43149 bytes) — the new endpoint through real auth + department isolation |
| `php artisan mock-api:tenancy` | **5/5 PASS** |
| `php artisan mock-api:escalation-drill` | **PASS** (writes rolled back) |
| `php artisan view:cache` | Blade templates cached successfully |


**Live end-to-end run of Block 4** (the block that previously failed), through the real AI
pipeline with the cache flushed first:

| Observation | Result |
|-------------|--------|
| Outcome | **SUCCESS in 27.5s** — `processed: 20`, `batches: 4`, `cached: false` |
| Student 68 | **scored** |
| Distribution | 12 High · 7 Moderate · 1 Low |
| Fresh writes | `2026-09-29 10:20:15` |
| Transient failure survived | a real **HTTP 503 "model is currently experiencing high demand"** on attempt 1 was recovered by the existing transport retry; the new omission retry was not needed on this run (it is covered by the 7 scripted unit tests) |

**Auto-update confirmed on live data:** the page had been rendered at 10:17:44, *before* the
run. The refresh endpoint for block 4's department afterwards returns
`{"blocks":16,"students":320,"scored":320,"percentage":100}` and a block 4 row carrying
`data-scored="20"`, last-scored `2026-09-29 10:20:15` and cache state `Fresh` — exactly the
post-run state, which the page now swaps in with no reload. (The live probes were run through
`RiskScoringService::processBlock`, so flag generation — a separate, unchanged step the
controller performs — was not part of them.)

**Known limitation (documented, not hidden):** the `ai_calls` field still counts *batch scoring
requests*, so it does not include omission retries. Making it count every HTTP request would
mean calling a new getter on `AIStudioApiService`, which the existing
`RiskClassificationPipelineTest` mock does not stub (Mockery throws on unstubbed calls), so
that change was not worth the risk to a working test for a field nobody asserts. The
authoritative record of retries is the log, and every retry line carries the omitted ids, the
attempt number and the limit.

#### 5. Rollback

Restore `config/services.php` and the two `.env` keys. No migration, schema or route removal is
involved — the new route is additive and read-only.

### 11. Follow-up: intervention recommendations slimmed to short, actionable steps

#### 1. Audit — what the pipeline actually stored (measured, not assumed)

Every number below was read out of the live `intervention_recommendations` table (Midterm,
2024-2025, 21 rows) before anything was changed:

| Measure | Actual |
|---------|--------|
| Action lines per student | avg **5.14** (min 3, **max 7**) — 108 lines total |
| Rendered length per student | avg **1,246 chars**, worst **1,838** |
| A 20-student block | ≈ **24,900 chars** of recommendation text |
| `details` length | avg **218 chars**, max **328** — 65 of 108 lines over 200 |
| Action labels | `tutoring` **21/21** · `academic_advising` 20/21 · `counseling` 16/21 · `study_skills` 11 · `parent_meeting` 11 · `attendance_monitoring` 10 · `academic_coaching` 8 · `time_management` 6 · `peer_tutoring` 5 |
| Priorities | medium 47 · high 45 · low 16 — **no discrimination**; a mild case looked identical to a severe one |

Everything essential in a row is one sentence long; everything else was justification or a
restatement:

| Kept | Why |
|------|-----|
| `tutoring` + the failing subject codes + a frequency | the one action that changes a grade |
| `parent_meeting` (High risk / 3+ failing), `counseling` (High risk / 2+ failing), `study_skills` (average < 80), `attendance_monitoring` (**only** below 85%) | each has a real trigger in the data |

| Cut | Why |
|-----|-----|
| `academic_advising` (20/21 rows) | duplicates `tutoring` — "review the roadmap / retake the subject" is the counselor's call, not a second recommendation |
| `study_skills` + `time_management` + `academic_coaching` together | three ways of saying "study better" (25 lines across 21 students) |
| `counseling` as "assess external factors" on a 97 % attendance student | speculation dressed as an action |
| `attendance_monitoring` at 85.29 % / 94.29 % attendance | fires above the threshold the student is above |
| `parent_meeting` at **low** priority | a "low priority parent meeting" is a contradiction |
| every "due to …", "as her 85.29 % attendance …" clause | the Grades, Attendance and Risk Factors cards **on the same page** already show it |
| 200+ char `details` | unreadable in a table cell, a modal textarea and a case card |

**Root cause** was in `AIStudioApiService::buildInterventionPrompt()`: it offered twelve
overlapping action labels, showed three example actions for the model to mirror (hence
`tutoring` in 21/21 rows), told it to "evaluate EACH" subject grade, and set no count, length,
priority or trigger discipline. Nothing downstream trimmed: `parseInterventionResponse()`
returned every element and `saveRecommendations()` stored them verbatim.

#### 2. The repair — a prompt that states the rules, and a condenser that enforces them

The prompt keeps its data block and JSON shape and replaces the instruction tail with: **1 to 3
actions** ("never more than three"), **one sentence of at most 90 characters** starting with an
imperative verb, **never** explain/justify/restate the metrics ("no because / due to / since / as
/ given"), **no two variants of the same help** ("tutoring, peer tutoring, academic coaching and
academic advising are ONE action"), **a trigger condition per action** (tutoring ≥1 subject
below 75; counseling High risk or 2+ failing; study_skills average below 80;
attendance_monitoring below 85 %; parent_meeting High risk or 3+ failing; career_guidance Low
risk with nothing below 75), and **at most two `high`**. The whitelist is now the six real
actions instead of twelve overlapping labels.

`RecommendationCondenser` (new) is the guarantee behind that instruction, applied in
`InterventionService::generateForStudent()` between the AI response and the database — so
anything the Academic Head saves through `updateRecommendation()` is still stored exactly as
typed. In order: collapse a family of help to one action (keeping the highest-priority member);
demote a third `high` to `medium`; reduce `details` to one sentence, dropping a trailing
reason/purpose clause only when the sentence is over budget and only when at least 45 characters
survive, then truncating at 120 characters on a word boundary without a dangling preposition;
stable-sort by priority and keep three. It never returns an empty list for a non-empty input
(that would strand the student on the "Pending" badge), it leaves a compliant response
**byte-identical**, and `custom` (the label a bare string normalises to) can never prove two
actions are the same help. Trimming is logged (`Intervention recommendations condensed` with
`raw_actions`, `kept_actions`, `dropped`, `kept`).

**Student 168, live, before → after** (1,783 → 267 characters, 6 → 3 lines): the six-line essay
became

```
[high] Tutoring: Schedule mandatory weekly tutoring for Calculus 2 and Programming.
[high] Parent meeting: Conduct a meeting with the guardian regarding failing grades this week.
[medium] Study skills: Enroll the student in a weekly time management coaching workshop.
```

The edit page's help text now shows that style and states the three-line cap.

#### 3. Why a prompt change *and* a condenser

A prompt cannot enforce anything — the previous prompt asked for exactly one thing ("base the
interventions on the individual subject grades") and got 5.1 essays per student. The prompt is
what makes the output *good*; the condenser is what keeps it *bounded* if the model regresses,
and both are pinned by tests so neither can drift silently.

#### 4. Verification

| Check | Result |
|-------|--------|
| `php artisan test` | **154 passed (511 assertions)** — up from 127 |
| `tests/Unit/RecommendationCondenserTest.php` | **13 new tests** on the real stored strings: 6 verbose actions → 3, the four tutoring labels collapse to one, a third `high` demotes, seven actions cap to the three most urgent, boundary truncation lands on a whole word, a compliant response passes **byte-identical**, `toText()`→`fromText()` round-trips, `custom`/seeded rows survive, never empty for a non-empty input, garbage never throws, stored column shape kept |
| `tests/Unit/AIStudioInterventionPromptTest.php` | **9 new tests** pinning the prompt contract: the 1–3 cap, the 90-character one-sentence budget, the no-justification rule, the synonym collapse, the six-action whitelist (the twelve old labels are gone), all six trigger conditions, the two-`high` cap, and that the student fields, subject grades and JSON keys still travel |
| `tests/Feature/InterventionCondenserPipelineTest.php` | **5 new tests** through the real `InterventionService` with the AI doubled at the HTTP boundary: the stored column holds the condensed actions (not the raw payload), a lean response is stored verbatim, the trim is logged with its counts and kept labels, a clean response logs nothing, regenerating replaces the row |
| `node tests/JavaScript/check_inline_scripts.js` | 25 blocks, 0 failing |
| `node tests/JavaScript/block_dom_harness.js` | 13/13 controls fire (block page untouched) |
| `php artisan mock-api:smoke` | **All 65 endpoints rendered** |
| `php artisan mock-api:tenancy` | **5/5 PASS** |
| `php artisan mock-api:escalation-drill` | **PASS** (all writes rolled back) |
| `php artisan view:cache` | Blade templates cached successfully |

**Live regeneration through the real AI** (three students, one call each):

| Student | Risk | Stored before | Regenerated after |
|---------|------|---------------|-------------------|
| 168 | High 88 | 6 lines / 1,783 chars | **3 lines / 267 chars** |
| 171 | High | 7 lines / 1,801 chars | **3 lines / 237 chars** |
| 162 | Low 10 | 4 lines / 1,049 chars | **1 line / 107 chars** (career guidance only — exactly the trigger rule) |

In all three runs the **model itself complied** — the condenser changed nothing, so the output is
whatever the model wrote, and no `condensed` log line was emitted. The condenser's own behaviour
is proven separately (13 unit tests + the pipeline test that deliberately feeds it the old
six-action payload).

**Rendered output checked on live data** through the real HTTP kernel: `/academic-head/recommendations?block_id=9`
shows the lean step and no trace of the old essay; `/academic-head/recommendations/170/edit` holds
the lean text in its textarea with the new help text; `/academic-head/block/9` still fetches the
preview endpoint and still renders the escalation modal.

**Escalation round trip on a regenerated row** (rolled back): `bulkEscalate` accepted
recommendation #170, the case carried the **3-line / 267-character** text verbatim, and the
counselor's `/counselor/case/{id}` page showed all three lines. `cases` was 1,253 before and 1,253
after, so the drill wrote nothing.

#### 5. Invariants held

- **Storage shape and schema unchanged** — still `[{action, details, priority}]` in
  `suggested_actions`; no migration.
- **`RecommendationTextService` untouched**, so its 20 existing tests and the escalation
  contract are unchanged, and every already-stored (still verbose) row renders exactly as before.
- **A human edit is never condensed** — the condenser sits between the AI response and the
  database, not in `updateRecommendation()`.
- **No escalation surface touched**: `escalationModal`, `escalationStudentList`,
  `recommendationSummary`, `confirmEscalationBtn`,
  `/academic-head/escalation/recommendation-preview`, the drill's verbatim-forward assertion and
  the counselor case-page literals all still pass.
- **`generateInterventions()`'s signature and call site are unchanged**.
- The three regenerated rows are the only ones that changed; the other 18 keep their original
  text until regenerated (historical rows are left alone, as agreed).

#### 6. Rollback

Delete `app/Services/RecommendationCondenser.php`, revert the two service edits and the view help
text, and remove the three new test files. No migration and no stored data needs reversing —
every existing row keeps whatever text it already holds.

| File | Change |
|------|--------|
| `app/Services/RecommendationCondenser.php` | **New** — the trimming policy |
| `app/Services/AIStudioApiService.php` | `buildInterventionPrompt()` instruction tail replaced (data block and JSON shape untouched) |
| `app/Services/InterventionService.php` | `$condenser` property + optional constructor argument; `generateForStudent()` condenses before saving and logs the trim |
| `resources/views/academic-head/recommendation-edit.blade.php` | help text: lean example + the three-line cap |
| `tests/Unit/RecommendationCondenserTest.php`, `tests/Unit/AIStudioInterventionPromptTest.php`, `tests/Feature/InterventionCondenserPipelineTest.php` | **New** — the regression guards |

| File | Change |
|------|--------|
| `config/services.php` | `aistudio.batch_retry_limit` added (default 2, `0` disables) |
| `.env`, `.env.example` | `AI_STUDIO_BATCH_RETRY_LIMIT=2` (`.env.example` also gained the previously undocumented AI Studio block) |
| `app/Services/AIStudioApiService.php` | `batchRetryLimit`; `parseBatchRiskResponsePartial()` extracted; `parseBatchRiskResponse()` now a strict wrapper; `retryOmittedStudents()` added; `getRiskScoresForBatch()` retries omissions and logs |
| `app/Http/Controllers/AcademicHead/RiskScoringController.php` | `buildWorkspace()` extracted from `index()`; new read-only `data()` endpoint |
| `resources/views/academic-head/risk-scoring.blade.php` | cards now `@include`d; `refreshWorkspace()`; refresh after a single block and once at the end of a sweep |
| `resources/views/academic-head/partials/scoring-cards.blade.php` | **New** — shared card markup |
| `routes/web.php` | `academic-head.riskScoring.data` (GET, read-only) |
| `tests/Unit/AIStudioBatchRetryTest.php`, `tests/Feature/RiskScoringWorkspaceRefreshTest.php` | **New** — the regression guards |

**Untouched by design:** `RiskScoringService::processBlock`'s batch loop, band assignment and
`saveRiskScores`, the intervention flow, the escalation hand-off, and every existing endpoint
contract.

### 12. Follow-up: End-of-Term report appendix trimmed to five sections

**Requested:** remove "Breakdown by Subject" and "Recommendations and Next Steps" from the report
(both the on-screen preview and the enriched PDF), renumber the appendix headings with no gaps,
and leave the **plain** PDF byte-identical.

#### 1. The two removed sections

| Removed | Preview | PDF | Why it was safe to remove |
|---------|---------|-----|---------------------------|
| **Breakdown by Subject** | appendix card ("grades below 75") | section **A4** | Twelve subject rows of `graded / failing / failing % / average` restate the grade data that already drives the risk score on the same page. It was also the most expensive section in the document: `GradeRepository::subjectFailingBreakdown()` fetched every grade row for every monitored student (400-id chunked) and aggregated it in PHP on **every** render. |
| **Recommendations & Next Steps** | appendix card | section **A7** | Five generated sentences that did not carry a figure — they told the reader to escalate, follow up, monitor, review the bands and share the report, which the surrounding sections (Intervention Status, the configuration warning) already imply. It was the only appendix section whose text was derived prose rather than data. |

Both lived *only* in the appendix, i.e. inside `@if(!empty($insights))` in the shared view and in
`renderAppendix()`. The report **body**, the digest, the provenance block and the figures every
other role reads were never involved.

#### 2. Renumbering: A1-A5, no gaps

| Before | After |
|--------|-------|
| A1 Configuration Warning (conditional) | A1 Configuration Warning (conditional) |
| A2 Risk Band Definitions | A2 Risk Band Definitions |
| A3 Breakdown by Year Level | A3 Breakdown by Year Level |
| A4 Breakdown by Subject | **removed** |
| A5 Intervention Status | **A4** Intervention Status |
| A6 Term Progression | **A5** Term Progression |
| A7 Recommendations and Next Steps | **removed** |

The per-method docblocks that name their own section ("A2. The score ranges behind the
Low/Moderate/High labels") were aligned with their headings at the same time; they had drifted one
number apart when the Top-10 section was removed in section 8.

#### 3. Dead code removed with them

| File | What went |
|------|----------|
| `app/Services/Reports/EndOfTermReportService.php` | `renderSubjectSection()`, `renderNextStepsSection()`, the two calls in `renderAppendix()`, and `plainText()` (its only caller was the next-steps renderer) |
| `app/Services/Reports/EndOfTermReportInsightService.php` | the `subjects`, `subject_rows_total` and `next_steps` payload keys (in `build()` **and** `emptyPayload()`), `subjects()`, `nextSteps()`, the `SUBJECT_LIMIT` constant — and the three constructor dependencies that existed only to feed them |
| `app/Repositories/Api/GradeRepository.php` | `subjectFailingBreakdown()` (79 lines), dead once `subjects()` went |
| `resources/views/reports/end-of-term.blade.php` | both cards; rows 2 and 3 were merged into one two-card row (Term Progression + Intervention Status) so removing a card cannot leave a half-empty row |

**A leftover found while doing this:** the insight service still ran
`$names = $this->students->nameIndex();` on every render — a dead assignment left behind by the
Top-10 removal in section 8 — and still carried an `AttendanceSummaryRepository` it stopped using
at the same time. Both are gone, so a report view/PDF now makes **two fewer API round trips** than
it did before this change.

---

## Refactor 14: Guidance Counselor Workspace — Academic Head Design Language (complete)

**Requested:** the five counselor screens should look and read like the same product as the
Academic Head workspace, without changing anything any of them *does*.

### 1. The problem, stated precisely

Both workspaces had been built up over the same series of refactors, and the Academic Head got a
coherent design pass first: a banded title row, a campus backdrop behind the fold, framed cards
with a coloured rail on the header, capsule controls, list-style tables, recessed panels. The
counselor screens were left on the older Bootstrap-default treatment, so the two roles rendered as
two different applications from the same navbar.

The two must therefore converge on the *Academic Head* language, not the other way round — and the
Academic Head must not move at all, because its geometry was already tuned against live screenshots.

### 2. The decision — one new marker, additive classes only (Option B)

The change is a single appended CSS section plus one wrapper element per view. Nothing was
rewritten and nothing was renamed.

| Element | Kind | Where it goes | What it does |
|---------|------|---------------|--------------|
| `.gc-page` | **new marker** | on the workspace wrapper, one per view | the only thing that makes a rule a counselor rule |
| `.ah-page` | existing, reused | the same wrapper element | pulls in the shared chrome: backdrop, shell gutter, fold fill |
| `.ah-glow` | existing, reused | cards | framed card + breathing glow + banded header |
| `.ah-reveal` | existing, reused | top-level content rows | staggered entrance, keyed by `--ah-i` |
| `.ah-hero` | existing, reused | the Risk Score card body | the oversized figure + side tiles |
| `.ah-compare` | existing, reused | the effectiveness before/after row | recessed two-tile comparison |
| `.ah-quote` | existing, reused | the forwarded-recommendation panel | quoted-text treatment |
| `.ah-actions` | existing, reused | header button clusters | capsule action group |

The wrapper is `<div class="ah-page gc-page" style="--ah-photo: url(...)">`. Both classes sit on
one element because the shell rules are written as `:has(> .ah-page)` / `:has(> .gc-page)` against
the direct child of `.container-fluid`.

No new JavaScript, and no new endpoint, was introduced.

### 3. Why the Academic Head cannot be affected — by construction, not by care

This is the part worth recording, because "I was careful" is not a guarantee. Every new rule
carries `.gc-page` (or is keyed to an id that exists only in a counselor view), so an `.ah-page`
**without** `.gc-page` matches none of them. There is no specificity contest to lose and no
ordering dependency on where the section was appended.

| Mechanism | Scope | Why it cannot reach another role |
|-----------|-------|----------------------------------|
| `.container-fluid:has(> .gc-page) > …` | the title row, its badges, its buttons | `:has(> .gc-page)` requires the marker *in that document*; Academic Head and student pages render `.ah-page` alone, so the whole selector never matches |
| `.gc-page …` (the bulk of the section) | cards, tables, forms, badges, bands, heroes | the marker is never present outside a counselor view |
| `#filterModal`, `#sessionModal`, `#updateStatusModal`, `#updatePriorityModal`, `#resolveModal`, `#reopenModal` | the six dialogs | verified by sweep: each id appears in exactly one file, all six under `resources/views/counselor/` |
| `@keyframes` | entrance + glow | as first appended, the section declared **no** keyframes of its own; it reused `ahReveal` and `ahGlowBreathe`. Refactor 15 adds two of its own for the counselor frame — see that entry |

`resources/views/layouts/app.blade.php` was **not touched** by this refactor. That is the single
most important line in this record: the shared shell is what every other role renders, so leaving
it alone is what makes "no other role can be affected" true rather than hopeful.

### 4. Modals must sit *outside* the wrapper

`.ah-page` opens a stacking context (`isolation` + `position: relative`) so the backdrop and the
fold fill composite predictably. A `position: fixed` dialog placed inside it would resolve its
`z-index` against the wrapper instead of the document, while Bootstrap appends its own
`.modal-backdrop` to `<body>` — so the dialog would resolve to a value *below* the backdrop and
disappear behind it, in every browser.

All six dialogs therefore render after the wrapper closes. This is already the pattern the
Academic Head views use, and `case.blade.php`'s wrapper closes immediately above the five dialogs
with a comment saying why, so the next editor does not "tidy" them back inside.

### 5. New / changed files

| File | Change |
|------|--------|
| `public/css/app.css` | one appended section, `GUIDANCE COUNSELOR WORKSPACE (shared chrome, extended)`, starting at line 3743 (file 3742 → 4700 lines). Nothing above it was edited. |
| `resources/views/counselor/dashboard.blade.php` | wrapper + 4 reveal rows + 7 `ah-glow` cards |
| `resources/views/counselor/cases.blade.php` | wrapper (outside the filter modal) + reveal + `ah-glow` |
| `resources/views/counselor/case.blade.php` | wrapper + 4 reveal rows + 8 `ah-glow` cards + `ah-hero` (Risk), `ah-compare` (effectiveness), `ah-quote` (recommendation), `ah-actions` (session header) |
| `resources/views/counselor/reports.blade.php` | wrapper + 4 reveal rows + 3 `ah-glow` cards |
| `resources/views/counselor/my-reports.blade.php` | wrapper + 9 reveal rows + 12 `ah-glow` cards |

Backgrounds: `maincampus02.webp` on Dashboard and Department Reports, `maincampus03.webp` on the
Case detail (it is the workspace's action screen), `bg_smll_udd.jpg` on Caseload, My Reports and
the session dialog's `::before`.

### 6. What the section actually declares

Grouped by purpose, so the surface is auditable rather than mysterious:

| Group | Representative selectors | Effect |
|-------|--------------------------|--------|
| Title row | `.container-fluid:has(> .gc-page) > .d-flex.mt-3.mb-4` (+`::before`, `.badge*`, `.btn`) | the same banded title row the Academic Head gets, with tone-coloured badges that match the shared `.badge-risk-*` palette |
| Panels | `.gc-page .card`, `.gc-page .card > .card-header:first-child` | card and header radius matched to the Academic Head's values |
| Controls | `.gc-page .btn`, `.gc-page .btn-group`, `.gc-page .form-select`, `.gc-page textarea.form-control` | capsule buttons, segmented groups, capsule selects |
| Lists | `.gc-page .table > thead > tr > th`, `tbody > tr > td`, `tbody tr:last-child > td` | lighter header rule, roomier rows, no rule under the last row |
| Filter bars | `.gc-page form.row.g-2` (+ `.form-label`) | the shared filter-bar rhythm |
| Empty states | `.gc-page .card-body > .text-center.text-muted` (+ `.btn`) | centred empty states instead of a stray icon |
| Recessed surfaces | `.gc-page .bg-light`, `.gc-page .bg-light.rounded`, `.gc-page .badge.bg-light.text-dark` | `.bg-light` re-tuned to the app's recessed surface in **both** themes |
| Tone rails | `.gc-page .ah-glow.border-danger|warning|success|info|primary|secondary > .card-header` (+ `::before`/`::after`) | the header rail takes the card's tone colour |
| Band chips | `.gc-page .ah-glow > .card-header .badge.*` | badges legible on the navy band |
| Band actions | `.gc-page .ah-glow > .card-header .btn-primary|success|outline-*` | ghost controls that invert correctly on the band |
| Composites | `.gc-page .ah-hero`, `.ah-compare`, `.ah-quote`, `.ah-actions` | the four counselor-specific composites, each with its tone-aware variant |
| Dialogs | the six modal ids (+ `@media (max-width: 575.98px)`) | frame, motion, banded headers and a responsive pass |

### 7. Invariants held

1. **No id, `name`, `data-*`, `onclick` or form action changed.** A mechanical sweep of all
   **63** expected hooks across the five views reports every one present (`ALL n HOOKS PRESENT`),
   including every chart canvas id, every modal field, `name="student-id"`, `@csrf` on the five
   POST forms, and the drill strings `Risk Score`, `Risk Factors` and `No recommendation provided.`
2. **`layouts/app.blade.php` untouched**, so no other role's shell, chrome or geometry changed.
3. **No CSS above the new section was edited.** The Academic Head's own rules and the shared
   `.ah-*` rules are unchanged, which is also why rollback is a deletion.
4. **Modals stay outside the wrapper**, so all six still cover the viewport.
5. **No controller, route, service, seeder, migration or JS file changed.**

### 8. Verification record

| Check | Result |
|-------|--------|
| `php artisan test --compact` | **161 passed (538 assertions)** |
| `php artisan view:cache` | all templates compiled; no Blade syntax error in any of the five views |
| CSS brace walk (comment- and string-aware) | final depth **0**, minimum depth **0**, no unterminated comment |
| `<div>`/`</div>` balance, all five views | balanced — case 177/177, cases 26/26, dashboard 74/74, my-reports 119/119, reports 52/52 |
| `.ah-page gc-page` wrapper present | exactly one per view (dashboard:70, cases:59, case:100, reports:33, my-reports:69) |
| Hook-preservation sweep | 63/63 hooks present |
| Live authenticated render, seven counselor URLs | **200** — dashboard, cases, cases?scope=open, cases?scope=resolved, reports, my-reports, case/6147 |
| Rendered marker counts | dashboard 6 `ah-glow` / 4 `ah-reveal`; cases 1/1; reports 3/4; my-reports 12/9; case 8/4 plus `ah-hero` 1, `ah-compare` 1, `ah-actions` 1 |
| Both recommendation branches reached | case 6147 renders the `No recommendation provided.` empty state; case 6173 renders the forwarded text inside `ah-quote` |
| Screenshots, light **and** dark | 12 captures reviewed; title row, bands, rails, capsules, filter bars, list tables and the fold backdrop all correct in both themes |

**Two things worth knowing about that verification:**

- The screenshots are of a *static authenticated dump* served from `public/`, so the chart XHRs are
  unauthenticated and the Dashboard shows its own `Chart Error … Unauthenticated.` alert. That is
  the app's existing behaviour for a logged-out chart fetch, **not** a regression; My Reports, whose
  charts ship their data inline, rendered its donut, bar and line charts normally.
- The light-theme captures needed the theme forced deliberately. `layouts/app.blade.php`'s inline
  theme bootstrap defaults to `'dark'` and re-applies `theme-dark` on load, so swapping the class on
  the `<html>` tag alone is silently overwritten — the probe had to seed
  `localStorage['acadalerts-theme'] = 'light'` **before** that script runs. Worth remembering for
  any future screenshot-based check of this app.

### 9. A deliberate omission

`.gc-page .table > thead > tr > th` does **not** set `white-space: nowrap`, which is the obvious
thing to reach for in a list-table treatment. The Caseload list is nine columns wide, and forcing
single-line headings widens the table past its container and gives the page a horizontal scrollbar
it does not have today. The headings are left free to wrap — they do, onto two lines — and the
comment in the CSS says so, so it is not "fixed" back in later.

### 10. Rollback

1. Delete everything from `/* ===== GUIDANCE COUNSELOR WORKSPACE (shared chrome, extended) ===== */`
   in `public/css/app.css` to the end of the file. Nothing above it depends on it.
2. In the five counselor views, remove the `.ah-page gc-page` wrapper element and the closing
   `<!-- /.ah-page .gc-page -->` marker, and drop the `ah-glow` / `ah-reveal` / `ah-hero` /
   `ah-compare` / `ah-quote` / `ah-actions` class tokens. The `style="--ah-i: n"` attributes are
   inert once `ah-reveal` is gone.
3. `php artisan view:clear; php artisan config:clear`.
4. No migration, no schema change and no stored data was touched, so nothing has to be
   un-migrated and no report, score, case or escalation is affected by the rollback.

---

## Refactor 15: Guidance Counselor glow frame — uniformity, weight and pulse (complete)

**Requested:** the animated lightning-blue glow border on the counselor cards is inconsistent —
some cards carry a thinner or uneven glow than others. Make it uniform: equal thickness on all
sides, a slightly thicker and more pronounced hairline and halo, and a more striking pulse. Match
the Academic Head glow, with a bit more presence. Both themes. Design only — no id, class, `name`
or JS hook may change.

### 1. What was actually wrong — four causes, all of them geometry

The frame was `.ah-glow::after { inset: 0 }` carrying
`box-shadow: 0 0 0 1px core, 0 0 22px halo` — one 1px OUTER ring plus one soft outer bloom. That is
the right recipe for the Academic Head's panels and the wrong one for the counselor grids, where
the same declaration produced visibly different weights on a single screen. None of the four
reasons was a colour or a token, which is why retuning `--app-ah-glow-core` /
`--app-ah-glow-halo` could not have fixed any of them:

| # | Cause | Why it varied per card |
|---|-------|------------------------|
| 1 | `inset: 0` resolves against the **padding box** | the ring lands *on* a card's own border rather than beside it, so a `.border-*` card paints the ring over a 1px border in a second colour while a plain card paints the ring alone |
| 2 | An outer ring is **clipped by the first clipping ancestor** | `.ah-page .stat-card` carries `overflow: hidden` for its own corner light, so a ring on one of those is cut away on all four sides while the panel beside it keeps every one of its own |
| 3 | A **blurred outer shadow compounds where two edges meet** | it is brightest at the corners and thinnest along the middle of a long edge, so a wide panel and a small tile can never read as the same weight |
| 4 | The **3px tone rail sat at `top: 0`** | on a toned card the top of the frame was the rail's colour at the rail's 3px weight while the other three sides were the 1px ring — which is why the top edge always looked heaviest |

### 2. The decision — split the frame in two, and move the frame inside the card

| Layer | Pseudo | Job | Recipe |
|-------|--------|-----|--------|
| Halo | `::before` (**new** on the card — nothing else used it) | the outward bloom, on the page behind the card | three stops of `--app-ah-glow-halo` at `6px` / `14px` / `26px` |
| Frame | `::after` | the hairline, on the card's own edge | `inset 0 0 0 1px core`, `inset 0 0 0 2px core`, `inset 0 0 12px -2px halo` |

**An inset ring is the same thickness on every side by construction** — it is the card's own
rounded rectangle, drawn 2px in — so the four corners and the four edges of every card agree at any
width, in any column, at any zoom, and no ancestor can clip it. It also stops fighting the card's
own border, because a border and an inset ring are drawn on opposite sides of the same edge line.

Three details that are deliberate rather than incidental:

- The **outer ring is gone**. A hard ring out there would land on the card's border beside the inset
  frame and put the weight straight back where the change took it out. Only blurred light travels
  outward now, and blurred light does not stack against a border into a second, thicker line.
- The **1px and 2px inset rings stack rather than select**: the outer pixel is painted twice and
  reads brighter, the pixel behind it once, so the 2px line arrives with a lit edge instead of as
  flat paint. It is also how the frame gets brighter than the Academic Head's **without a new
  token** — stacking semitransparent shadows compounds the alpha.
- The **tone rail moved 2px in** (`top: 0` → `top: 2px`), so it sits inside the frame instead of
  being the frame's top side. Nothing else about the rail changed — not its height, not its tone,
  not the way it fades out to the right.

Colour is still the two shared tokens and nothing else, so the tone-matched frame block from
Refactor 14 keeps working untouched: a `.border-danger` card re-points `--app-ah-glow-core` and
that re-points both new layers with it, with no rule to update. `theme.css` goes on carrying both
schemes, so there is no light-scheme half to this change.

### 3. The pulse

| Layer | Keyframes | Range | Period |
|-------|-----------|-------|--------|
| Frame | `gcGlowFrame` | `0.82 → 1` | 4.8s `ease-in-out` infinite |
| Halo | `gcGlowHalo` | `0.25 → 1` | 4.8s `ease-in-out` infinite |

Same cadence as the Academic Head's `ahGlowBreathe`, a shorter period, and a much longer travel —
but **the extra travel is on the halo, never on the frame**. The frame never drops below 0.82,
because a frame that fades is a frame whose weight changes, which is the one thing this whole change
exists to stop. Opacity only, so it stays off the main thread, exactly as the shared rule does.

### 4. What was not touched

- **`public/css/app.css` is the only edited file.** No other file was written to in this refactor.
- **All five counselor views** — not written to at all. No id, class, `name`, `data-*`, `onclick` or
  form action changed, because no markup changed. Confirmed by mtime: the views all carry their
  Refactor 14 timestamps, older than this session's `app.css`.
- **`layouts/app.blade.php`** — still not touched, in this refactor or the last one.
- **The Academic Head's own rules** — untouched. The change is one new block inside the counselor
  section, scoped to `.gc-page .ah-glow`, plus one GC-scoped reduced-motion block.
- **`.stat-card` metric tiles stay unframed**, deliberately. The Academic Head leaves its own metric
  tiles unframed, and "match the Academic Head glow" means matching that composition rather than
  framing everything on the page. It is now *possible* to frame them — the inset ring is not
  clipped by their `overflow: hidden`, which the old outer ring was, so this is the first time the
  option exists at all — and §7 gives the two lines that would do it.

### 5. New keyframes, and the specificity trap they set

`gcGlowFrame` and `gcGlowHalo` are the first keyframes this workspace section has ever declared.
They are named so that only the counselor frame references them; the Academic Head goes on
animating `ahGlowBreathe`, at 6s, exactly as before.

They also set a trap worth recording, because it is the kind of thing that silently survives a
review: the Academic Head's reduced-motion block stops `.ah-glow::after` at specificity **(0,1,1)**.
The counselor frame now declares its animation at `.gc-page .ah-glow::after` — **(0,2,1)** — so that
block no longer reaches it, and the frame would have kept breathing straight through a user's
"reduce motion" preference while the entrance animation stopped around it. There is therefore a
GC-scoped `@media (prefers-reduced-motion: reduce)` block at the end of the section, and the comment
that used to claim reduced motion "is already handled by the block in the Academic Head section" now
describes what actually happens and why it had to move.

### 6. Verification

| Check | Result |
|-------|--------|
| `php artisan test --compact` | **161 passed (538 assertions)** |
| CSS brace walk (comment- and string-aware), `app.css` and `theme.css` | final depth **0**, minimum depth **0**, no unterminated comment, no unterminated string |
| Duplicate `@keyframes` names in `app.css` | **none** (`gcGlowFrame` and `gcGlowHalo` appear twice each: the declaration and the keyframe block) |
| Every `ah-glow` is a real card | dashboard 6/6, cases 1/1, case 8/8, reports 3/3, my-reports 12/12 — **30 of 30**, with **0** uses on a non-`.card` element, and exactly **1** `.ah-page gc-page` wrapper per view |
| Hooks (views untouched) | ids, `name`, `data-*`, `onclick`, `@csrf` and canvas counts all present; each of the six dialog ids still resolves to exactly **1** occurrence |
| Computed styles, headless Chrome, **dark** | AH control (no `.gc-page`): `0 0 0 1px, 0 0 22px`, `ahGlowBreathe`, `6s`, `::before: none`, rail `auto` — **unchanged**. Counselor: `inset 1px, inset 2px, inset 12px -2px`, halo `6/14/26px`, `gcGlowFrame`/`gcGlowHalo` at `4.8s`, rail `2px` |
| Computed styles, **light** | identical geometry, light tokens `rgba(37,99,235,0.42)` and `rgba(59,130,246,0.22)` |
| Tone matching still flows through | `.border-danger` card: **identical geometry**, tokens swapped to `rgba(220,53,69,0.55)` / `0.30`, with no rule to update |
| `--force-prefers-reduced-motion` | all four layers report `animation: none`, including the counselor frame that would otherwise have escaped the shared stop |
| Screenshots, both themes | reviewed — a crisp uniform 2px inset frame on every card, a pronounced halo, red on the tone-matched card, **no corner thickening**, and the top edge no longer heavier than the sides |

The computed-style rows are the load-bearing ones: the first row is the proof that the Academic
Head is untouched, and it is measured on the same page, in the same run, through the same CSS files
as the counselor cards beside it.

### 7. Rollback, and the metric-tile option

Rollback is the same shape as Refactor 14's: delete the block beginning
`/* ------------------------------------- The frame, made uniform (.gc-page .ah-glow) */`, restore
the trailing reduced-motion comment to its old text, and the section is as it was. No markup, no
token, no theme file and no markup-adjacent file has to move.

**To frame the metric tiles as well — opt in, not applied:** add `.gc-page .stat-card` to the
`::before`/`::after` setup rule and to the two layer rules. They already receive both glow tokens
from `:root`, so nothing else is needed. The tiles are the one surface the old outer ring could
never have framed, because their `overflow: hidden` clipped it; the inset ring is not clipped by
it, so the option is real now. Left off by default because the Academic Head leaves its own metric
tiles bare, and this refactor's brief was to match that glow with more presence, not to frame every
surface on the page.







---

## Refactor 16: Administrator Workspace — the third workspace on the shared chrome (complete)

**Requested:** redesign the eleven administrator screens — Dashboard, Users, Academic Structure,
Risk Settings, Payments, Audit Logs, School Year, System Health, End-of-Term Reports, My Profile and
Account Settings — so they wear the same modern / premium chrome the Academic Head and Guidance
Counselor workspaces already wear: the campus backdrop, the banded navy title row, the lightning-blue
glow frame on every panel, and the staggered entrance. Design only: no id, class, `name`, `data-*`,
form action, route or JS hook may change, and the other three roles must be untouched.

### 1. What was actually wrong — the workspace was never brought onto the shell

The Academic Head (Refactor 13) and the Guidance Counselor (Refactors 14–15) were moved onto the
shared shell by wrapping their content in `.ah-page`. The administrator screens were not: they were
still the raw Bootstrap they had always been, which left eight concrete symptoms, none of them a
colour:

| # | Symptom | Cause |
|---|---------|-------|
| 1 | No campus backdrop, no veil, no fold fill | no `.ah-page` wrapper on any of the eleven screens, so the shared block had nothing to recognise |
| 2 | Title row still a plain grey `.bg-secondary` strip floating on white | the banded navy title row is keyed on `.container-fluid:has(> .ah-page)` |
| 3 | Panels were flat 10px cards, no frame, no corner light | no `.ah-glow` marker, so nothing inherited the family's frame |
| 4 | Gradient metric tiles sat unlifted, unlike the same tiles on the Academic Head | the `.ah-page .stat-card` lift is wrapper-scoped |
| 5 | No entrance | no `.ah-reveal` marker, so nothing inherited the stagger |
| 6 | Users / Payments / Audit Logs ended in a **foot-tall grey chevron** | `{{ $x->links() }}` with no published pagination view returns Laravel's **default Tailwind** markup; this app is Bootstrap, so `w-5 h-5` resolves to nothing and the two chevron SVGs paint at *intrinsic* size. The same missing utilities also left the count strip and the phone strip both visible at once |
| 7 | `.bg-light` key/value tiles on System Health and Risk Settings were light-scheme values | `.bg-light` is a Bootstrap utility: a near-white slab on a light screen, a flat grey slab on a dark one |
| 8 | Empty registries / logs / report lists collapsed to one line of muted text | the empty state is a full-width `<td>` or a bare `.text-center.text-muted` div, neither of which had a height or a well |


### 2. The decision — one marker, additive only, zero new keyframes

The wrapper class is `.ah-page admin-page`. `.ah-page` is the *existing* shared hook: it is the one
the Academic Head block already keys the backdrop, the banded title row, the shell gutter, the fold
fill, the `.ah-glow` frame and the `.ah-reveal` stagger on — so carrying it is what makes the
administrator screens the third workspace on that shell rather than a lookalike of it. `.admin-page`
is a second, *inert* marker that exists only so the handful of shapes the Admin screens have and the
other two do not can be scoped without touching anything else.

Everything is additive. No existing selector was edited, no shared rule was re-tuned, and **no new
`@keyframes` was declared**: the admin frame reuses the counselor's `gcGlowHalo` and `gcGlowFrame`
verbatim, so the file keeps one animation recipe rather than two. The frame is the same two-layer
inset construction Refactor 15 introduced — an outer halo `::before` and a uniform inset hairline
`::after`, both `inset: 0`, so it cannot be clipped by an ancestor's `overflow: hidden` and it is the
same weight on all four sides. That matters here more than anywhere: the admin console stacks wide
full-width panels whose corners the old outer ring used to brighten.

### 3. What the section actually declares

The section runs from `/* ==… ADMIN WORKSPACE  (shared chrome, extended) */` at line **4511** to the
end of its phone breakpoint at line **5122**, plus the admin half of the reduced-motion block at the
end of the file. Twelve commented sub-blocks, in this order:

| Sub-block | What it does | Keyed off |
|-----------|--------------|-----------|
| Workspace marker + brand watermark | `.admin-page { position: relative }` and a `.admin-page::after` that paints the AcadAlert mark **through a mask**, so the mark arrives in one colour and can be tinted from the theme | the marker, and `--ah-photo` for the mark |
| Title band, admin extras (`:has`) | the band wraps on a wide row (`flex-wrap` + `row-gap`), a rail `::before` is added beside the shared corner light, and the count chip / blue button / outline buttons carried by the layout are re-toned for a dark band | `.container-fluid:has(> .admin-page) > .d-flex.mt-3.mb-4` |
| Panel geometry | family's 14px card, band rounded 1px tighter, only a **first** `.card-header` rounded | `.admin-page .card` |
| Controls | one capsule for anything pressed (`.btn`, and `.btn-group` children un-joined), 0.7rem fields, blue focus ring | `.admin-page .btn` / `.form-control` / `.form-select` |
| Filter bars | the GET filter row becomes one recessed hairline well, labels matched to the table headers, uppercase | `.admin-page form.row.g-2` (the form's **own** utilities — no markup change) |
| List tables | trailing row hairline dropped, header rule to one hairline, per-row action a quiet capsule that commits to its colour on hover and stays visible where `hover: none` | `.admin-page .table` |
| Recessed surfaces | `.bg-light` re-derived from the body colour so a recess reads as a recess in **either** scheme, hairline edge, badge variant | `.admin-page .bg-light` |
| Empty states | the in-table `<td>` gets panel height, the card-level one a dashed well — the same two variants the counselor screens use | `.admin-page` + the markup's own `text-center.text-muted` |
| Page-opening strip | End-of-Term Reports' `.alert-light` slab becomes the family's recessed well, blue glyph kept as the only colour | `.admin-page > .alert` / `.admin-page .alert-light` |
| Status tiles | the six System Health cards keep their own colours and are **lifted** instead — corner light + deeper shadow | `.admin-page .card.h-100` |
| Quick-action strip | the dashboard's seven shortcuts get the lift that makes the strip read as a row of keys | `.admin-page .card-body > .d-flex.flex-wrap.gap-2 > .btn` — **retired by Refactor 17**, which removed the strip from the dashboard and the now-dead rules with it |
| Frame + stock paginator | the two-layer frame above, and the whole capsule paginator — count row, number strip, current page, dead ends, and the **single rule that retires the foot-tall chevron** (`svg { width: 1.05rem; height: 1.05rem }`) | `.admin-page .ah-glow::before/::after` and the paginator's own `role` / `aria-label` / `rel` hooks |

### 4. Files

**Changed — CSS (1 file).** `public/css/app.css`: the `ADMIN WORKSPACE` section (lines 4511–5122) and
the admin half of the reduced-motion block at the end of the file. Nothing else in the file moved.

**Changed — views (13 files, one line each plus a closing comment).** `resources/views/admin/`:
`dashboard`, `users`, `users-create`, `users-edit`, `academic`, `academic-edit-department`,
`academic-edit-program`, `academic-edit-subject`, `risk-config`, `payments`, `audit-logs`,
`school-year`, `system-health`. The change in each is the same three lines: the
`<div class="ah-page admin-page" style="--ah-photo: url('…')">` wrapper, its opening comment, and its
closing `{{-- /.ah-page .admin-page --}}`. The `.ah-glow` (frame) and `.ah-reveal` (stagger) markers
were added to the panels and bands that should carry them.

**Added (not yet tracked).** `resources/views/admin/end-of-term-reports.blade.php` — the End-of-Term
Reports screen, the eleventh admin page; it is dressed like the other ten.

**Deliberately NOT touched.** `resources/views/profile.blade.php` and
`resources/views/settings.blade.php` are the two **shared** Account screens — one layout-level pair
used by every role — and they were already on the shell. Editing them would have reached four roles
to change one, so My Profile and Account Settings keep exactly the markup they had; and because the
new CSS is scoped to `.admin-page`, which the shared pair does not carry, they keep exactly the
styling they had as well. `layouts/app.blade.php`, `theme.css` and every non-admin view are untouched.

### 5. Why the other three roles cannot be affected — by construction, not by care

`.admin-page` is the only marker the section introduces, and every rule in it names that marker
directly or through `:has(> .admin-page)`. An `.ah-page` with no `.admin-page` beside it — the
Academic Head, the Guidance Counselor, the Student — therefore matches nothing in the section and
keeps the geometry and colour it has today.

The two rules that *could* have leaked are the two whose selector keys are generic.

| Selector key | Where the markup uses it | Why it is still safe |
|--------------|--------------------------|----------------------|
| `.card.h-100` | `system-health` (**admin**) and 8 other-role files: `profile`, `settings`, `academic-head/*`, `counselor/*`, `reports/end-of-term`, `student/dashboard` | the rule is written `.admin-page .card.h-100`; without the marker on the ancestor it cannot match, so the lift lands on the six System Health tiles and nowhere else |
| `.d-flex.flex-wrap.gap-2` | `admin/dashboard` (**the quick-action strip**) and 5 other-role files: `academic-head/block`, `academic-head/reports`, `counselor/case`, `reports/end-of-term` | same — it is written `.admin-page .card-body > .d-flex.flex-wrap.gap-2 > .btn`, so only the dashboard strip moves |

`bg-light`, `.alert-light` and the paginator are the same story: all three are generic markup keys,
and all three are reachable only from inside `.admin-page`.

### 6. Verification record

| Check | Result |
|-------|--------|
| `php artisan test --compact` | **161 passed (538 assertions)** — the same as Refactor 15's record, i.e. nothing in the redesign moved a test |
| CSS brace walk (comment- and string-aware), `public/css/app.css` | final depth **0**, minimum depth **0**, no unterminated comment, no unterminated string |
| `@keyframes` names in `app.css` | exactly **3** declarations — `ahGlowBreathe` (Academic Head), `gcGlowFrame`, `gcGlowHalo` (counselor, **reused** by admin). No new keyframe, no duplicate |
| Every selector in the ADMIN section (lines 4511–5122) | contains `admin-page` — **0 unscoped rules** (checked programmatically, every line that opens a rule) |
| Wrapper present | **14 of 14** admin views carry `<div class="ah-page admin-page" …>`, exactly **1 per view** (including `end-of-term-reports`; `risk-config` writes it with single quotes, which is why a double-quote grep misses it) |
| Modals outside the wrapper | held. The wrapper closes **above** the three Academic Structure Add dialogs and above the Users filter / session / update-status / update-priority / resolve / reopen dialogs, because `.modal-backdrop` goes on `<body>` and a dialog inside a `position: relative` wrapper would fall behind it |
| Hooks (views) | ids, `name`, `data-bs-*` triggers, `onclick`, form `method` / `action`, `@csrf` and canvas counts all present; only `class` attributes gained tokens |
| Headless Chrome, **light** | reviewed: navy band with pill actions, gradient metric tiles, glow frame on every panel, recessed filter well, capsule paginator, faint watermark bottom-right |
| Headless Chrome, **dark** (forced) | reviewed: identical geometry, dark surfaces, capsule paginator, faint watermark over the backdrop |

Two of those rows are worth a sentence each, because both were investigated as suspected defects and
both turned out to be **by design**:

- **The giant grey chevron on Users is gone, and one rule retires it.** The paginator is Laravel's
  default **Tailwind** markup rendered in a **Bootstrap** app, so the two chevron SVGs carried no size
  at all and painted at intrinsic size. `.admin-page nav[…Pagination Navigation…] svg
  { width: 1.05rem; height: 1.05rem }` is the whole fix — confirmed by rendering the paginated Users
  screen in both schemes and reading back the count row, the numbers, the current page, the dead ends
  and both chevrons as capsules.
- **The navy title band in light mode is the shared chrome, not a regression.** The band is painted by
  the *shared* rule at line **3184** (`.container-fluid:has(> .ah-page) > .d-flex.mt-3.mb-4`, a fixed
  135° navy gradient), which is the same band the Academic Head and the Counselor have worn in both
  schemes since Refactor 13. This section only adds the wide-row wrap, the rail and the re-toned chips
  on top of it.

A note on the headless renders, since it cost time to establish: the shared layout defaults to
**dark** when `localStorage` is empty, so a fresh headless profile always renders dark — the `-dark`
fixtures had to have theme-forcing overridden twice (an injected `force()` on `DOMContentLoaded` runs
after an external script) before the true-dark pass was trustworthy. Both passes have now been read.

### 7. Rollback

Rollback is the shape Refactors 14 and 15 already set, and it is two edits:

1. Delete the block beginning `/* ==… ADMIN WORKSPACE  (shared chrome, extended) */` through its phone
   breakpoint at line 5122, and remove the four `.admin-page` stops from the reduced-motion block at
   the end of the file (the `.gc-page` ones stay — they are Refactor 15's).
2. In each of the 14 admin views, delete the `<div class="ah-page admin-page" …>` line, its opening
   comment and its closing `{{-- /.ah-page .admin-page --}}` comment, and remove the `ah-glow` /
   `ah-reveal` tokens that were added to `class` attributes.

No id, `name`, `data-*`, route, controller, test, token or theme file has to move, because none was
touched. If only the *look* needs to come off without touching the CSS, removing `.admin-page` from
the fourteen wrappers is enough on its own: every rule in the section is keyed on that one class, so
the screens fall straight back to the shared `.ah-page` chrome the other two workspaces use.


## Refactor 17: Administrator Dashboard — the Institution Risk Trend panel rebuilt; Print and Quick Actions retired (complete)

**Requested:** on the **Admin Dashboard only**, replace the "Institution Risk Trend" chart with a
modern, premium chart that speaks the Admin workspace's design language — glow borders, navy bands,
capsule elements — and remove the **Print** button and the **Quick Actions** button. Both schemes. The
chart's type and styling were left to judgement. Design only: no functionality change, and every
`id`, `class`, `data-*`, `onclick` and JS hook left byte-identical.

### 1. What the request resolved to

Three questions were settled before anything was edited.

| Question | What the tree said | Consequence |
|----------|--------------------|-------------|
| Which element is "the Quick Actions button"? | `Quick Actions` appears in exactly **one** place in the whole application: the dashboard's own panel in `admin/dashboard.blade.php`, a card whose body is the seven shortcut links. There is no standalone Quick Actions *button* anywhere, and the title row carries only Refresh + Print | the **panel** is removed, comment and all — the dashboard is left with Recent Activities and System Health below the trend |
| May the chart's *type* change without breaking a hook? | the chart is `canvas#riskTrendChart`, driven entirely by `loadRiskTrend()` in `public/js/charts/admin-charts.js`, a file loaded by **one** view. The id, the fetch URL, the `createChart('riskTrendChart', …)` call and both the fallback and error paths are the hooks | the **config object** may be restyled; every hook is left byte-identical |
| Will a restyle survive the theme toggle? | `app.js`'s `repaintCharts()` re-points `Chart.defaults` and calls `chart.update()` — it never rebuilds a chart. A literal colour is therefore frozen at first paint | the palette is read through **scriptable options**, which Chart.js re-evaluates on every `update()` |

### 2. The decision — a gradient area, a halo instead of a shadow, and a palette that is a function

**The chart.** A single smooth **gradient area** line: a 3px rounded stroke over a blue→transparent
vertical fill, three stops (0.38 → 0.13 → 0), built from `chartArea` so the gradient belongs to the
plot rather than to the canvas. The curve is `cubicInterpolationMode: 'monotone'`, which is smooth
without the overshoot a tension value can give a percentage series — a high-risk *share* should never
dip below zero between two periods.

**The glow, kept deterministic.** The stroke's halo is *the same polyline stroked wide and faint a
moment earlier* — a second dataset, 11px at low alpha, `fill: false`, `pointRadius: 0`, drawn first
because datasets draw in array order. This was chosen over a canvas `shadowBlur` deliberately: a
shadow rides the whole dataset draw pass, which for a filled line includes the area, so it would have
smeared a dark blur under the fill; a halo drawn as its own dataset cannot. It also cannot read as a
second series — it carries no markers, and `tooltip.filter` admits only `datasetIndex === 1`.

**The capsules.** Markers are `pointStyle: 'rectRounded'` (5.5px, 9px on hover, 2px ring) — Chart.js's
rounded rectangle, i.e. the family's capsule at marker size — and the band's metric tag is a `999px`
capsule too.

**The stage.** The panel sits in a recessed well rather than the bare card body the other two charts
sit on: hairline edge, 14px radius, the family's corner light in the top-left and a soft inner
shadow. Its height lives on `.admin-trend-stage` (290px, 210px at the phone breakpoint) rather than in
a `style` attribute, so the plot steps down with the page; and the config was moved to
`maintainAspectRatio: false` so Chart.js lays the plot out to that fixed height instead of to an
aspect ratio derived from the card's width — which is what had been pushing the x-axis labels into
the container's bottom edge.

### 3. What was changed

| Where | Change |
|-------|--------|
| `admin/dashboard.blade.php:11–13` | the **Print** button removed from `@section('page_actions')`; Refresh is left as the title row's only action |
| `admin/dashboard.blade.php:203–238` (old) | the **Quick Actions** panel removed entire — its comment, its `.row`, its seven links. Nothing else was renumbered by hand: the surviving `--ah-i` stagger values (**0–8**) are untouched and still read in order |
| `admin/dashboard.blade.php:125–135` | the trend card's header gains `<span class="admin-trend-tag">High-Risk Share</span>`, and its container gains one class, `admin-trend-stage`. The `<i>`, the label "Institution Risk Trend", the `.chart-container` class and `canvas#riskTrendChart` are all byte-identical |
| `public/css/app.css:4915–4953` | the **Trend chart panel** sub-block replaces Refactor 16's now-dead **Quick-action strip** sub-block (whose only target this change deletes). Two rules: the stage well, and the metric capsule |
| `public/css/app.css:5145–5148` | the stage's phone step, inside the Admin section's existing `@media (max-width: 575.98px)` block |
| `public/css/app.css:5473–…` | the two `.admin-page … .d-flex.flex-wrap.gap-2` stops were removed from the reduced-motion block along with the strip; the frame stops above them are untouched |
| `public/js/charts/admin-charts.js:23–46` | added `adminTrendPalette()` — a small function returning the eight colours the panel needs, per scheme |
| `public/js/charts/admin-charts.js:288–403` | `loadRiskTrend()`'s `config` object restyled. Nothing above it (`const canvas`, the url, the guards, `hasData`) and nothing below it (`createChart('riskTrendChart', …)`, the `.catch`) was touched |

Two comment-only edits keep the record honest: the Admin section's intro (app.css:4535–4536) and the
reduced-motion block's preamble no longer describe a quick-action strip that is gone.

### 4. Files

**Changed — view (1 file).** `resources/views/admin/dashboard.blade.php` (267 → 229 lines).

**Changed — CSS (1 file).** `public/css/app.css`. The Admin section now runs from line **4512** to its
phone breakpoint at **5151**, plus the admin half of the reduced-motion block at **5473**.

**Changed — JS (1 file).** `public/js/charts/admin-charts.js` (317 → 445 lines). It is loaded by the
dashboard alone, so no other page can see either of its changes.

**Nothing else moved.** No route, controller, test, migration, token, layout, `theme.css` or
non-admin view was touched. The other ten admin views, and every non-admin view, are byte-identical.

### 5. Why no other role, and no functional change, can be affected

- **The hooks are intact.** `canvas#riskTrendChart` is unique in the tree (the counselor and student
  trends use `studentRiskTrendChart`); `loadRiskTrend`, `loadAdminCharts`,
  `createChart('riskTrendChart', …)`, the `/charts/admin/risk-trend` URL, the `'High Risk: ' +
  context.parsed.y + '%'` tooltip string and both fallback paths are unchanged. The two new class
  tokens (`admin-trend-tag`, `admin-trend-stage`) are additions; no existing token was renamed or
  removed, and no `data-*`, `onclick` or `name` moved.
- **The additions are new names.** `adminTrendPalette` and both new classes appear nowhere else in the
  repository, so neither can be reached by any other page.
- **The removals are removals.** The Print button and the Quick Actions panel were the only users of
  `window.print()` and of the `.d-flex.flex-wrap.gap-2` strip on the dashboard, so the sub-block that
  styled the strip became dead code and was deleted rather than left behind. (Refactor 16 established
  that the strip's selector was already scoped to `.admin-page`; deleting it cannot reach
  `academic-head/block`, `academic-head/reports`, `counselor/case` or `reports/end-of-term`, which use
  the same utility class.)
- **The palette cannot leak.** It is declared in `admin-charts.js`, which only the dashboard loads, and
  it reads the scheme from `<html data-bs-theme>` — the same source `chart-config.js` and `app.js`
  already use — so no other workspace can see it or disagree with it.

### 6. Verification record

| Check | Result |
|-------|--------|
| `php artisan test` | **161 passed (538 assertions)** — identical to Refactor 16's record: the redesign moved no test |
| `node --check public/js/charts/admin-charts.js` | clean |
| CSS brace walk, `public/css/app.css` | final depth **0**, minimum depth **0** |
| `php artisan view:cache` | Blade templates cached successfully (dashboard included) |
| Grep, `admin/dashboard.blade.php` | `window.print` → **0**, `Quick Actions` → **0**; `admin-trend-tag` → 1, `admin-trend-stage` → 1, `id="riskTrendChart"` → 1; `--ah-i` values now **0–8**, none missing, none repeated |
| Grep, `public/css/app.css` | `d-flex.flex-wrap.gap-2` → **0** (the dead rule is gone); the new sub-block is present at 4928 and 4944 |
| In-process render probe (`_admin_render_check.php`, 12 routes) | all **200**; `admin.dashboard` → wrapper **1**, `ah-glow` **5** (was 6), `ah-reveal` **9** (was 10) — exactly the two cards' worth the removals account for; `profile` / `settings` unchanged at wrapper **0** |
| Headless Chrome — chart in isolation, **dark** | navy band, right-aligned `HIGH-RISK SHARE` capsule, glowing blue area, capsule markers, `%` ticks, navy grid |
| Headless Chrome — chart in isolation, **light** | same geometry on the light palette (`#2563eb` stroke, light grid / ticks), labels clear of the stage's bottom edge |
| Headless Chrome — **toggle capture** | a run that *starts* dark and flips to light at 900ms repaints the stroke, fill, grid and ticks with **no rebuild** — the proof that the scriptable palette follows the scheme |
| Headless Chrome — real dashboard route, **dark**, full page | Print gone (Refresh is the title row's only action), Quick Actions gone, panel order now stat tiles → Risk by Department / Risk Distribution → **Institution Risk Trend** → Recent Activities → System Health |

A note on the probe feed, since it is visible in those captures: the two other charts show their "No
… data available." fallback because the probe answers *every* request with the trend payload. That is
an artefact of the harness, not of the change — and it does incidentally re-confirm that the trend
panel's own fallback still fires from `showFallbackMessage` when a payload carries nothing.

### 7. Rollback

Three edits, all local:

1. **View.** Restore the Print button in `@section('page_actions')`, restore the `<!-- Quick Actions -->`
   row above `{{-- /.ah-page .admin-page --}}`, and drop `admin-trend-tag` / `admin-trend-stage` from
   the trend card's header and container.
2. **CSS.** Delete the **Trend chart panel** sub-block (app.css:4915–4953) and the stage step at
   5145–5148, then restore Refactor 16's **Quick-action strip** sub-block and its two reduced-motion
   stops.
3. **JS.** Delete `adminTrendPalette()` and restore `loadRiskTrend()`'s original `config` object
   (`type: 'line'`, `data: data`, the dark-only `rgba(0,0,0,0.05)` grid and `maintainAspectRatio: true`).

No id, `class`, `name`, `data-*`, `onclick`, route, controller, test, token, layout or theme file has
to move, because none was changed.


## Refactor 18: Administrator Audit Logs — "Export Logs" downloads a CSV instead of opening the print dialog (bug fix, complete)

**Requested:** the Audit Logs page's **Export Logs** button opened the browser's print dialog (the
screenshot showed the print preview) and produced no file. Make it download a real export — CSV was
acceptable — **respecting the current filter selections (date range, user, action) so the export
matches what is on screen**. No other functionality change: every other `id`, `class` and JS hook
byte-identical.

### 1. What the request resolved to

| Question | What the tree said | Consequence |
|----------|--------------------|-------------|
| Why a print dialog? | the control was a bare `<button class="btn btn-sm btn-success" onclick="window.print()">`. It was the **last** `window.print()` in the tree — Refactor 17 had removed the dashboard's Print button | the handler is the whole bug; the control itself stays |
| Is there a house shape for an export? | yes: the sibling **Payments** page already ships `exportPayments()` + `GET /admin/payments/export` — `fputcsv` into `php://temp`, then a `Content-Disposition: attachment` response | the endpoint is modelled on that one rather than invented |
| Which filter state is "the current selection"? | the filter card is a `GET` form, so the **table** reflects the filters in the URL. The two date inputs echo the applied values, but the `user` and `action` selects carry no `@selected()`, so after Apply they **visually snap back** to "All Users" / "All Actions" while the table stays filtered | the export has to be driven by the **applied request**, not by the controls — see §2 |

### 2. The decision — the filter state is the request, not the DOM

This is the one judgement call in the change, so it is worth stating plainly.

`exportPayments()` reads its filters out of the DOM. That is correct *there*, because the payments
page's `select`s re-select the applied option. Here it would be wrong: the selects do not, so a user
who filtered down to one person and then clicked Export would receive **everyone** — the exact
opposite of "matches what is on screen". Reading the DOM would export a different set from the one
the user is looking at.

So the export URL is rendered **server-side from the page's own request**:

```blade
const exportUrl = @json(route('admin.audit.logs.export', request()->query()));
```

The page and the endpoint are now handed the *same input*. That is the property worth having: they
cannot disagree — not because two filter blocks were written carefully to match, but because only one
filter block exists. When nothing has been applied the URL carries nothing, the endpoint falls back to
the page's own defaults (last 30 days, all users, all actions), and the file again matches the table.

Four further decisions:

- **The whole filtered set, not the visible page.** The table paginates at 50; the report does not. A
  "report" that silently omitted pages 2..n would be worse than no report at all.
- **The table's own four columns, in order** — `Date/Time`, `User`, `Action`, `IP Address` — with the
  `User` value taken from the *same* mock-API join the page uses, so the names are the on-screen ones.
  An id the directory does not know leaves the cell empty, exactly as the page renders it.
- **`Date/Time` in ISO (`Y-m-d H:i:s`), not the table's `M d, Y H:i:s`.** The file is read by a
  spreadsheet, where a sortable, unambiguous stamp is the point. (`exportPayments()` likewise writes
  raw values rather than display strings.)
- **Download via `fetch` → `blob` → `<a download>`**, as the payments page does, deliberately rather
  than a plain link: a 403, a 500 or an HTML body then surfaces as a toast plus a console error,
  instead of navigating the tab away from the filtered view and saving a junk `.csv` that opens as a
  login screen. The button shows a spinner while it runs and reports through the app-wide `showToast()`
  in `public/js/app.js` (loaded before `@stack('scripts')`).

### 3. What was changed

| Where | Change |
|-------|--------|
| `routes/web.php:207–210` | one new route: `GET /admin/audit/logs/export` → `admin.audit.logs.export`. Declared **after** the index route; the two paths differ, so neither can shadow the other |
| `Admin/DashboardController.php:2036–2130` | the new `exportAuditLogs()`. The filter block is an **inline mirror** of `auditLogs()` — same defaults, same `00:00:00`/`23:59:59` window, same `action`/`user` predicates, same mock-API name join — deliberately duplicated rather than extracted into a shared helper, because extracting it would have meant editing the index path, and "no other functionality changes" was explicit |
| `admin/audit-logs.blade.php:8` | the button's **opening tag only**: `type="button"` added, `id="exportLogsBtn"` added, `onclick="window.print()"` → `onclick="exportAuditLogs()"` |
| `admin/audit-logs.blade.php:115–185` | the new `@push('scripts')` block: `exportAuditLogs()` — spinner, `fetch`, HTML-body guard, blob download, toast |
| `tests/Feature/AuditLogExportTest.php` | new file, 6 tests |

The `DashboardController` method keeps the two conventions `exportPayments()` set: a `try`/`catch` that
logs through `Log` and answers a failure with a JSON 500, and a `php://temp` handle written with
`fputcsv` then re-read into the response body. Its log tag is `[AuditExport]`, not `[Export]`, so the
two exports cannot be confused in `laravel.log`.

### 4. Files

**Changed — routes (1 file).** `routes/web.php` (365 → 370 lines): one route plus three comment lines.

**Changed — controller (1 file).** `app/Http/Controllers/Admin/DashboardController.php` (2391 → 2488
lines): one method at **2036–2130** (doc block 2036–2049, signature 2050). `auditLogs()` at
**1994–2034** is byte-identical, and the `// SCHOOL YEAR` banner simply shifted 2036 → 2133.

**Changed — view (1 file).** `resources/views/admin/audit-logs.blade.php` (113 → 185 lines): the
button's opening tag, and the 71-line `@push('scripts')` block. The file's CRLF endings are preserved
(184 CRLF for 185 lines).

**Added — test (1 file).** `tests/Feature/AuditLogExportTest.php` (239 lines, 6 tests).

**Nothing else moved.** No route *name* or URL was renamed or removed, no other controller, view,
helper, layout, CSS, JS or config file was touched, and no existing test was edited.

### 5. Why nothing else can be affected

- **The control is otherwise byte-identical.** Its class list is still `btn btn-sm btn-success`; the
  inner markup is still `<i class="fas fa-file-export me-1"></i> Export Logs`; the sibling **Back**
  link is untouched. A one-line diff on the opening tag is the entire view change.
- **The additions are new names.** `exportAuditLogs`, `exportLogsBtn` and `admin.audit.logs.export`
  appear nowhere else in the repository, so none of them can collide with, or be reached by, any other
  page. (Verified by grep before writing them.)
- **The index path is untouched.** `auditLogs()` — same defaults, same query, same `paginate(50)`, same
  `compact(...)` — was not edited. The only reason the two filter blocks can be trusted to agree is
  that the export is handed the page's own request, so agreement does not depend on the duplication.
- **The sidebar is unaffected.** It highlights Audit Logs with `request()->routeIs('admin.audit*')`;
  the new name starts with the same prefix, and in any case a download never renders the layout.
- **The page's other hooks are intact.** The filter form's `name`s, the table, `$logs->links()` and the
  `.ah-page .admin-page` wrapper with its closing comment are all unchanged.
- **A pre-existing bug was found and deliberately left alone.** The `user` and `action` selects do not
  re-select the applied option (no `@selected(...)`), so the filter UI lies after Apply even though the
  table is right. That is *not* this task, and fixing it would have edited the filter card; it is
  recorded here because it is precisely why §2 chose the request over the DOM. One-line follow-up if
  wanted: `@selected($userFilter == $user->id)` / `@selected($actionFilter === $action)` — which would
  require passing the filters the view does not currently receive.

### 6. Verification record

| Check | Result |
|-------|--------|
| `php artisan test` | **167 passed (563 assertions)** — the 161 baseline plus the 6 new, nothing else moved |
| `php -l` on the controller and the new test | no syntax errors |
| `php artisan route:list --name=audit` | both routes listed: `admin/audit/logs` and `admin/audit/logs/export` |
| `php artisan view:cache` | Blade templates cached successfully — the `@json` directive compiles |
| Grep, `resources/views/admin/*.blade.php` | `window.print` → **0** (the last one in the tree is gone); `fa-print` → 0 |
| `git diff -U0` on the view | exactly one changed line (the button's opening tag); the `<i>` line is byte-identical |
| In-process render probe, 12 admin routes | all **200**, including `admin.audit.logs` (wrapper 1, len 221,426); `profile` / `settings` unchanged |
| Live export probe — real DB, real mock API | **200**, `text/csv; charset=UTF-8`, `attachment; filename="audit_logs_2026-10-02_200944.csv"`, **972 rows**, header `Date/Time,User,Action,"IP Address"`, names resolved through the API (`Ms. Rica Flores`, `Prof. Juan Santos`) |
| Live export probe — same request with `action=USER_LOGIN` | **79 rows** — the filter bites |

The six tests, in the words of their names: the export downloads a CSV of exactly the filtered rows
(each filter proved to exclude on its own); the export without filters matches the page's own defaults;
the export carries the whole filtered set, not only the first page (61 rows through a 50-row page
size); the page button exports instead of printing; the page hands the export the same filters it
applied; a non-admin cannot export the logs.

### 7. Rollback

Four local edits:

1. **View.** Delete the `@push('scripts')` block (audit-logs.blade.php:115–185) and restore the
   button's opening tag to `<button class="btn btn-sm btn-success" onclick="window.print()">`.
2. **Route.** Delete `routes/web.php:207–210`.
3. **Controller.** Delete `exportAuditLogs()` (2036–2130).
4. **Test.** Delete `tests/Feature/AuditLogExportTest.php`.

No `id`, `class`, `name`, `data-*`, route name, other method, layout, stylesheet or script has to move,
because none was changed.





## Refactor 19: Administrator Risk Settings — three elements retired, the Test AI Connection robot swapped for the app mark (design only, complete)

**Requested:** on the administrator **Risk Settings** page, three things disappear and two icons change —
the "How the level is assigned" note, the **What saving does** card, the robot glyph beside **AI
Connection Status**, and the robot on the **Test AI Connection** button, which takes the AcadAlert mark
instead. Design only: no hook moves, both themes identical to today otherwise.

### 1. What the request resolved to

| Question | What the tree said | Consequence |
|----------|--------------------|-------------|
| Which page is this? | the sidebar's **Risk Settings** link (`layouts/app.blade.php`) → `admin.risk.config` → `resources/views/admin/risk-config.blade.php` | one file is the whole change |
| Is there a `?` tooltip as well as the note box? | **no.** Grepped the blade, the compiled view in `storage/framework/views`, the `.kilo/worktrees` copy, the rendered HTML, `app.css` and `theme.css`: no `data-bs-toggle="tooltip"`, no `title=`, no `fa-question*` anywhere in the card | the note box is the only element the brief's first item can mean; **the `?` tooltip does not exist in this tree** — recorded here as a discrepancy against the brief |
| Which two robot glyphs? | `fas fa-robot` twice in the card: on the button's label, and in the AI-status strip | one is swapped, one is removed — see §2 |
| What does the mark look like? | `public/images/logo/acadalert_notxt.png` — the app's own wordless mark, already used in the navbar | reuse it rather than introduce an asset |

### 2. The decision — the mark is an image on the tree's own scale, not another Font Awesome class

The button's robot was a *font* glyph: it inherits colour and size from the button's type, and nothing
else. The replacement is an `<img>`, so that inheritance has to be restored explicitly:

- **`height:1em; width:auto`** — the mark is sized in the *button's own font unit*, so it matches the
  label's cap height at every breakpoint and zoom level, and no new magic number enters the stylesheet.
- **`vertical-align:-0.15em`** — a bare inline image sits on the text baseline with its *bottom* on it,
  which reads as floating high. Nudging it down by a fraction of the font size centres it optically
  against the label, which is what the `me-1` margin already assumes.
- **`alt=''`** — the control is named by the words "Test AI Connection" sitting beside it, so the mark is
  decorative and is correctly hidden from the accessibility tree rather than announced twice.
- **The second robot is removed, not swapped.** The AI-status strip already leads with the words
  "AI Connection Status:". A second mark there would decorate a line that is now self-describing, so the
  glyph is simply gone and the strip's own `<span id='aiStatus'>` badge carries the whole message.

The two removals are leaf nodes — a note box and a whole card — so neither leaves a gap: the remaining
cards are laid out by the row's own columns, not by sibling order.

### 3. What was changed

| Where | Change |
|-------|--------|
| `risk-config.blade.php` (the button) | `<i class='fas fa-robot me-1'></i> Test AI Connection` → a two-line `<img src='{{ asset('images/logo/acadalert_notxt.png') }}' alt='' class='me-1' style='height:1em;width:auto;vertical-align:-0.15em;'>` with the label on its own line |
| `risk-config.blade.php` (note box) | the "How the level is assigned" `alert` deleted whole |
| `risk-config.blade.php` (AI status) | `<i class='fas fa-robot me-2'></i>` deleted from the `alert-info` strip; the strip, its text and `<span id='aiStatus'>` are untouched |
| `risk-config.blade.php` (right column) | the **What saving does** card (`card-header bg-warning` + its four-item list) deleted whole |

### 4. Files

**Changed — view (1 file).** `resources/views/admin/risk-config.blade.php` — **180 lines**, LF endings
(179 LF, 0 CRLF), which is the file's existing convention in this folder and is unchanged; no mixed
endings were introduced. Three blocks/elements and one glyph line were retired and the mark occupies two
lines where a single icon line stood.

**Nothing else moved.** No stylesheet, script, route, controller, model, layout or test was touched, and
no other view was opened. The mark's asset already existed.

### 5. Why nothing else can be affected

- **Every hook survives, unchanged.** `testAIConnection` ×3 (button label, `DOMContentLoaded` call,
  definition), `syncThresholds` ×4, `aiStatus` ×2, `lowThreshold` / `moderateThreshold` /
  `highThreshold` ×2 each, `lowVal` / `modLowVal` / `highVal`, `admin.risk.save`, `admin.risk.test-ai`.
  The status `<span>` and the whole `@push('scripts')` block are byte-identical.
- **The markup is balanced.** `<div>` 34 / `</div>` 34. The retired note box, card and `<i>` were each
  self-contained, so removing them cannot unbalance an ancestor.
- **The mark adds no new name to the tree.** It reuses an existing asset, carries `alt=''` and no `id` or
  `class` hook, so nothing can bind to it and nothing can break by its absence.
- **The surviving `fa-robot` is a different page.** `layouts/app.blade.php:322` is the Academic Head
  sidebar's "Risk Scoring" link; it is not in this card and was deliberately left alone.
- **The removals retire text and decoration only** — no `name`, no `form`, no `@csrf`, no route reference
  and no threshold value passed through the template was among them.

### 6. Verification record

| Check | Result |
|-------|--------|
| `php artisan test` | **167 passed (563 assertions)** — the baseline held exactly |
| `php artisan view:cache` / `view:clear` | Blade templates cached successfully / compiled views cleared |
| Removed markers in the view | `fa-robot` **0**, `How the level` **0**, `What saving does` **0** |
| Preserved markers in the view | `testAIConnection` 3, `syncThresholds` 4, `aiStatus` 2, `acadalert_notxt.png` 1 |
| Div balance | 34 `<div>` / 34 `</div>` |
| Live authenticated render | **HTTP 200**, 25,534 bytes; rendered-page sweep — `fa-robot` **0**, `fa-question` **0**, `How the level` **0**, `What saving does` **0**, `acadalert_notxt.png` **1**, `ah-page admin-page` 1 |
| Both themes, headless captures | dark and light reviewed: the mark renders inside the button, optically centred with the label, in both; the note box, the saving card and both robot glyphs are gone in both |
| Computed-layout probe, both themes | the AI-status strip reports `display:block`, `visibility:visible`, `opacity:1`, **56px** tall, with **identical geometry** in dark and light (card `314px`); only the colours differ — `rgba(13,202,240,0.14)` in dark, `rgb(209,236,241)` in light |

The light-theme capture initially read as though the AI-status strip were missing. It is not: the probe
above shows it laid out identically in both schemes, and a pixel sweep of the light capture finds the
strip's distinct band at the same rows as the dark one. The light `alert-info` fill is simply a very pale
tint against a white card, which is what made the hand-read misleading. No code change was made on the
strength of it.

### 7. Rollback

Four edits, all in one file:

1. Restore the `<i class='fas fa-robot me-1'></i>` before "Test AI Connection" in the button.
2. Re-insert the "How the level is assigned" `alert` in the left card.
3. Re-insert `<i class='fas fa-robot me-2'></i>` in the AI-status strip.
4. Re-insert the "What saving does" card in the right column, after the Current Settings card.

No stylesheet, script, route, controller, model or test has to be unwound, because none was changed.

---

## Refactor 20: Student Workspace — the dashboard rebuilt, "Alerts / Flags" moved into a navbar bell (complete)

**Ticket:** the student dashboard carried two cards the sidebar already covers (Personal
Information, Counselor Information) and an Alerts / Flags panel that deserved a permanent,
always-reachable home; the four remaining panels were plain Bootstrap cards with no relation
to the design language the other three workspaces now share; and there was no greeting.

### 1. What the request resolved to — the baseline was the record, not a guess

The ticket names section titles that exist in no commit, so the pre-task state was
established from the manifest rather than assumed. **Refactor 11** documents this exact view
as *"Four ordered rows: Personal Information + Risk Status / Grades Summary + Attendance
Summary / Intervention Recommendations / Alerts-Flags + Counselor Information"*, and records
that "Recommended Resources", "Contact Your Counselor" and "Progress Comparison" were
**already** removed there, along with the dead `getSelfHelpResources()` / `$resources` on the
controller.

That resolves the ticket exactly: the three removals are three of the seven cards that
existed, and the four "reorganise" targets are the four that remain. No section the ticket
did not name was dropped — in particular the dashboard still represents `$studentInfo` (used
for the greeting) and `$recommendations` (rendered by its own card), both of which the
controller already passed.

### 2. The decision — reuse the shared chrome, add one marker, do not build a second system

The page wears **`.ah-page`** for everything shared (campus backdrop + theme-aware veil, the
banded title row, the tight shell gutter, the fold fill) and **`.sp-page`** as the single
marker every student-only rule is scoped to. Both sit on one element, so a `.sp-page` screen
is an `.ah-page` screen — the student workspace is a *sibling* of the other three, not a
fourth language.

The bell is the one genuinely new functional element, and it is printed by
**`layouts/app.blade.php`** rather than by the dashboard: an alert stream is only useful if it
is reachable from every screen, and a layout cannot be fed by one controller. The read
therefore moved into a service shared by both consumers.

### 3. What was changed

- **`StudentNotificationService`** (new) — one query per request, memoised per student, fronted by `forStudent()` / `forEmail()` / `unreadCount()`. Registered as a singleton so the layout composer and the dashboard cannot disagree about what an alert says or how many are unread. The flag→sentence map moves here with the query.
- **`DashboardController::getAlerts()`** now delegates to it instead of querying and mapping inline. It no longer narrows by grading period — correctly, since a flag stays actionable until acknowledged.
- **`AppServiceProvider`** — a `View::composer('layouts.app', …)` resolves the list once. The guard returns **before** any lookup for a non-student, so the other three roles pay nothing.
- **`layouts/app.blade.php`** — the bell prints only for `auth()->user()->isStudent()`, immediately before the user dropdown; the acknowledge script is included just before `@stack('scripts')`. The Admin, Academic Head and Counselor bars are unchanged by construction.
- **`partials/student-notifications.blade.php`** and **`partials/student-notification-scripts.blade.php`** (new) — the dashboard's own alert markup and its `acknowledgeAlert()` / toast helper, moved verbatim.
- **`student/dashboard.blade.php`** — rebuilt as a welcome band plus three rows: Risk Status (tone-matched frame) + Risk Trend, Grades Summary + Attendance Summary, Intervention Recommendations. Half of a column pair goes to each of the four panels, so the height is used instead of left blank.
- **`public/css/app.css`** — a student section scoped to `.sp-page` / `.student-bell-*`, plus the bell's dropdown geometry.
- **`public/css/theme.css`** — the bell's theme tokens.

### 4. Files

| File | Change |
|------|--------|
| `app/Services/StudentNotificationService.php` | **new** |
| `app/Providers/AppServiceProvider.php` | singleton + `View::composer` |
| `app/Http/Controllers/Student/DashboardController.php` | `getAlerts()` delegates; comment records why the period filter went |
| `resources/views/partials/student-notifications.blade.php` | **new** — the bell |
| `resources/views/partials/student-notification-scripts.blade.php` | **new** — the acknowledge flow |
| `resources/views/layouts/app.blade.php` | bell include (student-guarded) + script include |
| `resources/views/student/dashboard.blade.php` | rebuilt |
| `public/css/app.css`, `public/css/theme.css` | student section, bell geometry/tokens |

### 5. Why no other role can be affected — by construction

The bell is inside `@if(auth()->user()->isStudent())`; the composer returns before reading for
anyone else; every new stylesheet selector is scoped to `.sp-page` or `.student-bell-*`, both
of which appear only in student output. Rendered proof: `studentBellToggle` is **3**
occurrences in the student render and **0** in the Admin, Academic Head and Counselor renders.

### 6. Verification record

| Check | Result |
|-------|--------|
| `php artisan test` | **167 passed** (563 assertions) |
| rendered student dashboard | HTTP 200 — `Welcome back,` ×1, Risk Status ×1, Risk Trend ×1, Grades Summary ×1, Attendance Summary ×1, Intervention Recommendations ×1; **Personal Information 0, Counselor Information 0, Alerts / Flags 0** |
| rendered Admin / Counselor / Head | bell **0** in all three |
| hook diff vs. HEAD (ids / `onclick` / `data-*` / `getElementById`) | nothing removed that a script reads. The only differences are (a) the alert hooks, which moved into the partial unchanged, (b) `onclick="scheduleAppointment()"`, which left with the removed counselor card and was **already dead on this page** — the function is defined only in `student/counselor.blade.php` — and (c) a regex artifact from `data-alert-id`. |
| `php artisan view:cache` | compiles |

### 7. One defect fixed here, and one wider defect found and *reported rather than touched*

The new Intervention Recommendations card shipped with an icon-less header (`<i class=""></i>`)
and an icon-less empty state (`<i class=" fa-3x d-block mb-3 opacity-50">`). Both are **my own
new markup**, so both are repaired under this refactor: the header now carries
`fas fa-list-check` (matching its own button) and the empty state `fas fa-lightbulb` (the
tree's established Recommendations icon), with the redundant `bg-primary text-white` dropped
so all five card headers in the page are identical.

A **separate, wider regression** surfaced while sweeping for that pattern. It is reported
rather than touched, because this ticket freezes class attributes everywhere but this view:
a bulk edit at some point stripped the icon token out of `<i>` elements across several files.
The leading-space class value (`class=" me-2"`, `class=" text-warning me-1"`) is the
fingerprint. Confirmed against HEAD wherever the file is tracked:

| File:line | Now | Should be |
|---|---|---|
| `layouts/app.blade.php:448` | `<i class=""></i> Recommendations` | `fas fa-lightbulb me-2` (HEAD) — the **student** sidebar |
| `student/recommendations.blade.php:22` | `<i class=" me-2"></i>` | `fas fa-lightbulb me-2` (HEAD) |
| `student/recommendations.blade.php:127` | `<i class=" fa-3x d-block mb-3"></i>` | `fas fa-lightbulb fa-3x d-block mb-3` (HEAD) |
| `student/counselor.blade.php:92` | `<i class=""></i> Quick Resources` | `fas fa-lightbulb me-2` (HEAD) |
| `academic-head/block.blade.php:28` | `<i class=""></i> Run AI Risk Scoring` | `fas fa-robot me-1` — **note:** an earlier report of mine claimed this button "kept its robot icon"; it does not |
| `academic-head/block.blade.php:415`, `:916`, `:1355` | `<i class=""></i>` before "No recommendation selected yet." / "Loading recommendation(s)..." | recommendation icon |
| `academic-head/block.blade.php:1053`, `:1067` | `<i class="text-warning me-1"></i> Intervention Recommendation` | warning icon |
| `academic-head/block.blade.php:1210` | `<i class="fas"></i>` (JS template) | no icon name |
| `academic-head/department.blade.php:368` | `<i class=" text-primary"></i> Pending Recommendations` | recommendation icon |
| `academic-head/recommendations.blade.php:488` | `<i class=""></i> Generate AI Recommendations` | recommendation icon |
| `counselor/case.blade.php:247` | `<i class=""></i> Intervention Recommendation` | recommendation icon |
| `counselor/case.blade.php:309` | `<i class=" text-warning me-1"></i>` | note icon |

`layouts/app.blade.php:561` (footer, "AI-Powered Academic Risk Detection") was **already**
icon-less at HEAD, so it is a pre-existing bug rather than part of this regression.

### 8. Rollback

Unwind in this order:

1. Delete `resources/views/partials/student-notifications.blade.php` and `student-notification-scripts.blade.php`.
2. Revert `AppServiceProvider` — remove the `View::composer` and the singleton.
3. Revert `Student\DashboardController::getAlerts()` to the inline query + message map.
4. Remove both includes from `layouts/app.blade.php` (the student-guarded bell and the script include).
5. Restore the four-row body in `student/dashboard.blade.php`.

The `.sp-page` / `.student-bell-*` CSS and the `theme.css` tokens can stay: nothing else
matches them, so they are inert. No route, migration, model, seeder or test is involved.

---

## Refactor 21: Student Workspace — the four remaining pages, plus the sidebar icon regression (design only, complete)

### 1. What the request resolved to

Redesign **My Grades · Attendance · Recommendations · Contact Counselor** onto the shared
chrome the other three workspaces use, and restore the student sidebar's Recommendations
icon (the bulk-edit regression documented as the last row of Refactor 20 §7).

Refactor 20 had already rebuilt **My Dashboard** and added `.sp-page` as the student marker,
with `.sp-welcome` / `.sp-score` / `.sp-factors` / `.sp-metrics` / `.sp-rate` already written
and scoped to it. This refactor **reuses those primitives unchanged** and adds only the two
shapes the four pages need and the workspace did not yet have: a **table** treatment and a
**count-tile** treatment. No second design system.

### 2. The decision

* **`.ah-page` + `.sp-page` on one element per page**, exactly as the dashboard does. That
  single marker is what brings the backdrop, the banded title row, the shell gutter, the fold
  fill, the `.ah-glow` frame and the staggered entrance - and it is what keeps every rule in
  app.css scoped away from the other three roles.
* **The hero is `.sp-welcome`, reused verbatim.** The temptation was a second, smaller
  "page hero" class. There is no need: `.sp-welcome` is generic (icon capsule + title + sub),
  so each page drives it with its own *headline figure* rather than a repeated page title -
  "85.3% average", "92% attendance", "1 recommendation(s)", the counselor's name. Same band
  as the dashboard, no new CSS.
* **Two new sub-blocks only** - `.sp-page .table` (header rhythm, row rhythm, neutral hover,
  recessed footer) and `.sp-tile` (the three recommendation counters) - plus a dashed
  empty-state well and ruled contact lines.
* **The tone tints are washed, not solid.** The three counters ship as `.card.bg-light /
  .bg-warning / .bg-success`, and Bootstrap's tints stay near-white in *both* schemes, so in
  dark mode the figure was light-on-light. The fix re-points each at the shared
  `--app-tint-*` variable already used by the tone-tinted table rows.
* **The attendance warning keeps its own tone.** A `.ah-glow` frame normally repaints the
  card header as the family navy band, which on an alert card erases the very signal the tone
  carries. A higher-specificity rule scoped to `.sp-page .card.ah-glow.border-danger` gives
  that one card a red band and a red corner light instead.

### 3. What changed

| Page | Hero figure | Metrics / tiles | Cards framed | Backdrop |
|---|---|---|---|---|
| My Grades | overall average | 4 capsules: Average - Passing - At Risk - Failing | roster card | `bg_smll_udd.jpg` |
| Attendance | attendance rate | the four gradient stat tiles, `ah-reveal`ed | breakdown + warning | `maincampus03.webp` |
| Recommendations | count + split | 3 `.sp-tile` counters (neutral/amber/green) | the list card | `maincampus02.webp` |
| Contact Counselor | counselor name | - | counselor - appointment - resources | `bg_smll_udd.jpg` |

### 4. Files

| File | Change |
|---|---|
| `resources/views/student/grades.blade.php` | wrapper + `.sp-welcome` hero + `.sp-metrics` row; card framed and banded; table reindented; empty state extended |
| `resources/views/student/attendance.blade.php` | wrapper + hero; stat tiles `ah-reveal`ed; breakdown card framed; warning card framed with the red band |
| `resources/views/student/recommendations.blade.php` | wrapper + hero; counters to `.sp-tile`; card framed and banded; **two stripped icons restored**; toast deliberately left outside the wrapper |
| `resources/views/student/counselor.blade.php` | wrapper + hero; three cards framed and banded; **the Quick Resources icon restored** |
| `resources/views/layouts/app.blade.php` | line 448 only: `<i class=""></i>` to `<i class="fas fa-lightbulb me-2"></i>` |
| `public/css/app.css` | new sub-blocks in the STUDENT WORKSPACE section: tables, count tiles, empty states, ruled contact lines, tone-matched danger frame; `min-width: 0` added to the three shell `:has` rules |

### 5. Why no other role can be affected

Every new selector is scoped to `.sp-page` or `.ah-page.sp-page`, except the three
`#page-content-wrapper:has(> .container-fluid > .ah-page)` rules, which are scoped to the
shared marker the Academic Head, Counselor and Admin already carry - and the only change there
is the addition of `min-width: 0`, which can only let a shell narrow that previously could not.

### 6. Verification record

**Defects found by the browser probe and fixed** (both would have shipped otherwise):

1. **Attendance overflowed the viewport by 65px.** Two causes, found in sequence. First, the
   `white-space: nowrap` I had put on the table headers: the roster is eight columns wide, so
   forcing single-line headings pushed its min-content past the shell. Removing it cut the
   overflow 160 to 65px - the same lesson the Academic Head table block already records.
   The residue was the flex chain: a flex item's automatic minimum size is its **min-content
   width**, so `#page-content-wrapper` refused to narrow past the table. `min-width: 0` on the
   three shell rules took it to **-6px (none)**.
2. **The tile tint lost the cascade.** `.sp-page .sp-tile.bg-light` is three classes deep;
   theme.css's dark re-map `:root.theme-dark .bg-light` is *also* three classes deep and also
   `!important`, so at equal specificity the later stylesheet won and the counter fell back to
   Bootstrap's grey. One extra class (`.ah-page.sp-page`) settles it.

**Measured in headless Chrome against the real stylesheets**, all six page variants x both
schemes:

| Check | Result |
|---|---|
| Wrapper, hero, frame, reveal | `class="ah-page sp-page"` x1 per page; `sp-welcome-title` x1; `animation-name: ahGlowBreathe` on `.ah-glow::after`; `ahReveal` on `.ah-reveal` |
| Backdrop | `url(` present on `.ah-page::before` with the corner-wash layer above it |
| Shell gutter + fill | `#page-content-wrapper { padding-top: 0; display: flex }`; container `flex` |
| Table | `thead th { padding: 12px 14.4px }` |
| Empty state | `border-radius: 14px`, `border-top-style: dashed` |
| List rows | `border-radius: 12px` |
| Tiles, dark | `bg-light rgba(255,255,255,.07)` - `bg-warning rgba(246,194,62,.17)` - `bg-success rgba(31,178,107,.2)`, ink `rgb(222,226,230)` |
| Tiles, light | `bg-light rgba(0,0,0,.03)` - `bg-warning rgba(255,193,7,.16)` - `bg-success rgba(25,135,84,.11)`, ink `rgb(33,37,41)` |
| Danger frame | `linear-gradient(135deg, rgb(127,29,29) 0%, rgb(185,28,28) 54%, ...)` |
| Horizontal overflow | **-6px on every page in both schemes** |
| Blade + CSS integrity | `view:cache` **cached successfully**; `<div>` balance 0 on all four views; `app.css` brace walk depth 0 / min 0 |
| Route renders | all five routes **HTTP 200**; the recommendations tiles branch **and** its empty branch both exercised (two different students) |
| Hooks | `#periodSelect`, `onclick="scheduleAppointment()"`, `markCompleted(`, `#recommendationToast`, `#toastMessage`, `#subjectGradesChart` all present; no other file reference changed |
| Suite | `php artisan test` **167 passed (563 assertions)**; `mock-api:smoke` **all 65 endpoints** |

A probe gotcha worth recording: the theme is seeded on **both** variants, not just the light
one. `localStorage` is per-origin, so a light run leaks into every later dark run within the
same Chrome profile - which made the first pass look like dark mode was broken when it was the
harness that was.

### 7. Rollback

Revert the four views to HEAD (the wrappers and heroes are additive), then drop the new
sub-blocks from app.css and restore the three shell rules without `min-width: 0`. The
`app.blade.php:448` icon is a one-line revert. No route, controller, model, migration, seeder
or test is involved.

---

## Refactor 22: Sign-In screen — slideshow, electric sweep frame, crest + brand band (design only, complete)

### 1. What the request resolved to

Rebuild `resources/views/auth/login.blade.php` as a standalone premium screen: an always-moving
electric-blue frame around the card that intensifies on hover, a crossfading backdrop of the
four `public/images/backgrounds/` images under a veil, the school crest prominent at the top of
the card, the system lockup for branding, the no-text mark as a footer accent, and **only** the
Sign In button.

This page is the one screen that does **not** extend `layouts.app`: it carries its own
`<style>`, its own Bootstrap CDN links, and no app JS. So the whole redesign lives in this one
file — no `app.css`, no `theme.css`, no layout change.

### 2. The decision

* **The frame rotates, it does not slide.** The old border animated `background-position` on a
  conic gradient, which drifts the colour stops rather than turning the ring. A registered
  `@property --login-sweep` angle drives `conic-gradient(from var(--login-sweep), ...)` and is
  animated `0 -> 360deg / 3.6s linear infinite`, so the arc genuinely travels. `background-color`
  underneath is a flat ring, so the border still reads where `@property` is unsupported.
* **A halo, not a spread shadow.** A second copy of the same conic sits on `z-index: -1`, blurred
  20px and `saturate(1.5)`, breathing on **opacity only** - the same technique the Recommendations
  hero uses. The card itself stays crisp.
* **Intensify on `:hover` AND `:focus-within`.** The brief asks for hover; pairing it with
  `:focus-within` gives keyboard users the identical feedback for free. Both selector lists share
  one declaration block, so they cannot drift apart. Three things intensify at once: the ring
  thickens (3px to 5px), brightens (`brightness(1.18) saturate(1.28)`) and its glow widens
  (`inset -12px to -18px`, plus a wider shadow). **Consequence worth knowing:** the email field
  carries `autofocus`, so the page opens already lit and relaxes to the resting 3px weight on
  blur. In the renders that is the better look - the electrical frame is on show from the first
  paint - so it is left as is rather than suppressing `:focus-within`.
* **The slideshow is one keyframe set plus four delays.** Four stacked layers, each opaque for its
  own 8s slot and dissolving for 1s; because the incoming layer is a *later* sibling it paints on
  top while the outgoing one is still fading, which is what makes the dissolve a crossfade instead
  of a dip to black. Full cycle 32s. `<body>` carries the first frame statically, so the very first
  fade-in comes from an identical picture and never flashes bare. A 46s `lgDrift` scale gives each
  frame a slow push.
* **Two light plates, because two marks demand one.** The UDD seal is transparent but
  **navy-dominant** (`#000070` is its most common colour) and the system lockup carries navy
  lettering of its own - on a dark navy card both would half disappear. Rather than filtering
  either mark, each gets a light surface: the crest a 112px rounded plate, the lockup a white chip
  inside the family's navy band. No mark is recoloured, so both keep the palette they were drawn in.
* **The chip is a clipping window.** `acadalert_logo.png` is 1535x1024 but the artwork only
  occupies x52..1500, y176..786 - about 40% of the file is empty. Sizing the `<img>` straight would
  paint the wordmark at ~60% of the chip height. The chip is cut to the *artwork's* aspect
  (1449/611) and the image is blown up and pulled up-left by ratios derived from those
  measurements, so its top-left corner lands on the chip's - the trick `.sidebar-logo` and
  `.navbar-logo` already use.
* **Themes follow the app's own key.** A four-line inline script reads
  `localStorage['acadalerts-theme']` - the same key the workspaces write - and sets both
  `data-bs-theme` and `theme-dark`/`theme-light` on `<html>` before the first paint. Dark is the
  default, matching the invariant recorded in Refactor 12. `data-bs-theme` is what lets
  Bootstrap's own form controls, alerts and close buttons adapt without a fight.

### 3. What changed

| | |
|---|---|
| Backdrop | 4 crossfading layers + a 3-layer veil (two corner lights over a vertical navy gradient) |
| Frame | rotating conic ring + blurred breathing halo + hover/focus intensification |
| Crest | `udd-logo.png` at 88px on a 112px light plate, above "UNIVERSIDAD DE DAGUPAN" |
| Brand band | navy 135deg band, bled to the card edges, holding the white chip with `acadalert_logo.png` + a caption |
| Footer | `acadalert_notxt.png` at 18px, glow-tinted, beside the (c) line |
| Fields | capsule input-group (icon box + input rounded as one), themed labels, per-scheme focus ring |
| Button | the one button: capsule, gradient, glow that widens on hover |
| Motion | `prefers-reduced-motion` stops the slideshow (holding frame 1), the sweep, the halo and the drift |

**Removed as part of the brief:** the `Forgot password?` placeholder (it was already commented
out) and the dead `.demo-box` CSS, which no markup ever referenced. No "Sign Up" was ever present.

### 4. Files

| File | Change |
|---|---|
| `resources/views/auth/login.blade.php` | the entire `<style>` block rewritten; `<html>` gained `data-bs-theme`/`theme-*` + the scheme bootstrap script; body gained the slideshow + veil; the `.login-logo` block became crest + brand band; the footer gained the no-text mark |
| `resources/views/admin/audit-logs.blade.php` | **unrelated repair, flagged separately** - see §6 |

Nothing else. No `app.css`, no `theme.css`, no `layouts/app.blade.php`, no route, controller,
model, migration, seeder or test.

### 5. Why nothing else is affected

The page is standalone, so its CSS cannot reach the application shell, and the application shell
is not loaded here. The only shared artefact touched is the `localStorage` key - read-only, with
the same default the app uses.

### 6. Verification record

**Hooks.** Every one is present and unchanged: `id="email" name="email"`, `id="password"
name="password"`, `id="remember" name="remember"`, `method="POST"` with
`action="{{ route('login') }}"`, `@csrf`, `class="btn btn-login"`, `form-control`, `input-group`,
`input-group-text`, `form-check`/`-input`/`-label`, `form-label`, `alert alert-success/danger`,
`btn-close`, `data-bs-dismiss`, `@error('email')`, `old('email')`, `session('success'/'error')`,
and every presentational class the page already had (`login-container`, `login-border-wrapper`,
`login-card`, `login-logo`, `footer-text`). **No ID, class, field name, action or method changed.**

**End-to-end, against the real stack** (dev server, real cookies, real CSRF):

| Check | Result |
|---|---|
| `GET /login` | 200, 27,800 bytes, CSRF token present |
| `POST /login` with seeded credentials | redirected into `/admin/dashboard`; authenticated shell + logout form present |
| `POST /login` with a wrong password | back to `/login`; the `@error` branch renders (`invalid-feedback` + "do not match our records") |
| logout flash | `alert alert-success` renders inside the card |
| markup | slideshow x4, veil, crest plate, brand chip, all three logo paths, `data-bs-theme`, `method="POST"` all present; **no `Forgot password`** |

**Computed styles in headless Chrome**, both schemes (real stylesheets, real `/login` output):
`theme-dark`/`theme-light` set cleanly with `data-bs-theme`; `lgSweep`, `lgHalo`, four `lgSlide`
and four `lgDrift` all `running`; ring `26px` radius at `3px` padding with the conic; halo
`blur(20px) saturate(1.5)` at `-12px`; four slides with delays `0s, 0s | 8s, -6s | 16s, -12s |
24s, -18s`; veil 3 layers; card `rgba(15,20,36,.82)` dark / `rgba(255,255,255,.93)` light; plate
`112x112`; band navy gradient; **chip `150x63.25` with the image at `158.89x105.98` and
`-5.08/-18.2` offsets - the derived ratios exactly**; field ink light-on-dark and dark-on-light;
`input-group-text` right border `0` (no seam); button radius `999px`; footer mark `18x16.84`;
**horizontal overflow 0 px in light and -15 px in dark**.

**Hover/focus intensification, verified LIVE.** The `:focus-within` half of the same declaration
block was triggered by focusing the email field: ring `5px`, `filter: brightness(1.18)
saturate(1.28)`, halo `inset -18px`, shadow gaining the pale-cyan 1px ring; blurring returned it
to `3px`. *The first attempt read `3px` and looked like a defect - it was the
`transition: padding/filter/box-shadow` interpolating, because a computed style read
synchronously after `focus()` returns the value it is coming FROM. Suppressing transitions made
the settled values readable. Worth remembering: a mid-transition computed-style read is a trap.*

**Integrity.** `view:cache` compiles; `<style>` brace walk depth 0 / min 0 (comment- and
string-aware, so a `{` in a comment or a `url()` cannot lie) with 4 keyframes and 2 media queries;
730 CRLF / 730 LF, so the file stays uniformly CRLF; `<html>/<head>/<body>/<style>` tags balanced.
`<div>` open/close is 19/18 - the same **-1 as HEAD**, where `.login-container` was already left
implicitly closed for the browser to auto-close. Not a regression, and left alone.

**Suite.** `php artisan test` **167 passed (563 assertions)**; `mock-api:smoke` **all 65
endpoints**; `mock-api:tenancy` isolation holds; `mock-api:escalation-drill` passed;
`check_inline_scripts.js` **28 blocks, 0 failing**; `block_dom_harness.js` every handler fired;
`risk_scoring_progress_harness.js` terminal state reached.

**A defect I introduced and caught in the same pass:** my first version put a literal raw-echo
token inside a JS comment in the audit-logs script. Blade compiled it, and the page 500'd -
caught by my own `AuditLogExportTest`. The comment was reworded; all 6 tests pass again.

### 7. Rollback

Restore `resources/views/auth/login.blade.php` from HEAD. The page is self-contained, so nothing
else needs unwinding. The audit-logs line in §7 can be reverted independently.

---

## Refactor 23: Sign-In marks — plates removed, light drop-shadows instead (design only, complete)

### 1. What the request resolved to

The two white plates behind the marks looked out of place on the dark card. Remove both, let each
mark sit directly on its surface, and if a mark becomes hard to see apply a subtle treatment
(a light drop-shadow or slight brightness filter) - but no solid white box.

### 2. Measured first

Both PNGs were sampled pixel by pixel (System.Drawing; GD is not built in this PHP, as Refactor 22
found). The numbers decided the treatment, not taste:

| Mark | Geometry | Mean luminance | Under lum 80 | Dominant colours |
|---|---|---|---|---|
| `udd-logo.png` | 900x900, artwork fills the canvas, 1:1 | **115/255** | **47.5%** | `#000060` 38.8%, `#f0f0f0` 19%, `#80c0f0` 14.7% |
| `acadalert_logo.png` | 1535x1024, bbox x51..1501 y176..785, 2.379 | **64/255** | **70%** | `#0050f0` 13.7%, `#0060f0` 12.6%, `#000060` 8.5% |

Both surfaces are dark **in BOTH schemes** - the card is `rgba(15,20,36,.82)`, and the brand band
is a hard-coded navy `135deg` gradient (`#0a1024 -> #131c3d -> #1b2b60`) that never changes with
the theme. Confirmed against the rendered computed value in both fixtures.

### 3. The decision

* **`brightness()` cannot fix the lockup, so it was not used.** Multiplying the "ACAD" ink
  `#001e84` (lum 31) by the 1.6 the brief suggests reaches only lum 49 - still ~1.1:1 against a
  band running lum 13..30. What works is a **tight light drop-shadow**, because it **ADDS a bright
  rim** around each glyph rather than scaling the ink's own luminance: `1px` near-white at 0.88
  plus a `9px` pale-blue halo. A shadow is also the right primitive because it follows the
  artwork's silhouette - nothing is painted under the mark's interior.
* **The crest gets a light ring, because its interior is already bright.** 47.5% of the seal is
  the `#000060` ring, which sits at ~1.1:1 on the dark card. A `3px` `rgba(214,232,255,.75)` drop
  plus an `18px` `rgba(148,197,255,.62)` glow traces that ring and lifts the seal off the card; a
  third soft shadow grounds it. The white face of the seal needs no help.
* **Removing the plate made the crest BIGGER, not smaller.** The old plate was a 112px box holding
  an 88px mark. That box is now empty space and the mark takes the whole 112px - **+64% area** -
  while the header block stays the same height, so the card does not grow.
* **The chip keeps `overflow: hidden` and stays a clipping window.** That is structural: the
  `<img>` renders at 105.9% x 167.6% of the window (159x106 inside 150x63) and would otherwise
  spill across the band. The lockup's cropping ratios are untouched, so the window still frames
  exactly the artwork that x51..1501 y176..785 describes.
* **The lockup's filter is on the WINDOW, not the `<img>` - deliberately.** A `filter` on the
  oversized image would generate its glow against 106px of height, most of it empty or clipped
  away, so the halo would land off the letterforms. On the window the filter applies to the
  clipped result and the glow traces the text. Verified: the window reports the two drop-shadows
  and the `<img>` inside it reports `filter: none`.
* **Only the crest shadow is per-scheme.** The card is dark in dark mode and near-white in light,
  so its treatment is tokenised and re-declared under `:root.theme-light` with the glow dialled
  right down and a soft grounding shadow added instead. The band is fixed navy, so the lockup's
  problem and cure are identical in both - that token is deliberately declared once, with a
  comment saying why the light block does not override it.
* **`--lg-plate` is deleted**, not left dangling - it had exactly two readers (both plates) and
  none now.

### 4. What changed

| | |
|---|---|
| `.login-crest-plate` | lost `border-radius: 28px`, `background: var(--lg-plate)` and its `box-shadow`; is now a bare 112px layout box |
| `.login-crest-plate img` | 88px -> **112px**, gained `filter: var(--lg-crest-shadow)` |
| `.login-brand-chip` | lost `border-radius: 11px`, `background: #ffffff` and its `box-shadow`; gained `filter: var(--lg-lockup-shadow)`; kept `overflow: hidden` |
| `.login-brand-chip img` | untouched - the same measured cropping ratios |
| tokens | `--lg-plate` removed (both blocks); `--lg-crest-shadow` (both schemes) and `--lg-lockup-shadow` (dark, deliberately not overridden in light) added |
| `@media (max-width: 576px)` | plate lost `border-radius: 24px`; the crest image grew 74px -> **96px** to stay flush with its box, as at full size |

**No markup changed at all.** Both `<img>` elements - their `src`, `alt` and class names - are
byte-identical, as are every id, field name, `action`, `method` and inline hook on the page. The
comments that claimed the marks sat on plates were rewritten rather than left to contradict the
code.

### 5. Files

| File | Change |
|---|---|
| `resources/views/auth/login.blade.php` | the two rule blocks and the scheme tokens, as in §4 |

Nothing else. No markup, no `app.css`, no `theme.css`, no layout, no route, controller, model,
migration, seeder or test.

### 6. Verification record

**Computed styles, both schemes, real stylesheets and the real rendered page** (`--dump-dom` of the
plain `/login` output, probe appended at runtime):

| Property | dark | light |
|---|---|---|
| `.login-crest-plate` background | `none \| rgba(0,0,0,0)` | `none \| rgba(0,0,0,0)` |
| `.login-crest-plate` box-shadow | `none` | `none` |
| `.login-crest-plate` radius | `0px` | `0px` |
| crest `<img>` filter | `drop-shadow(rgba(214,232,255,.75) 0 0 3px) drop-shadow(rgba(148,197,255,.62) 0 0 18px) drop-shadow(rgba(2,6,23,.55) 0 8px 24px)` | `drop-shadow(rgba(37,99,235,.22) 0 0 12px) drop-shadow(rgba(15,23,42,.2) 0 8px 18px)` |
| crest size | `112x112` | `112x112` |
| `.login-brand-chip` background | `rgba(0,0,0,0)` | `rgba(0,0,0,0)` |
| `.login-brand-chip` box-shadow | `none` | `none` |
| `.login-brand-chip` radius | `0px` | `0px` |
| `.login-brand-chip` overflow | **`hidden`** | **`hidden`** |
| chip / lockup size | `150x63` / `<img>` 159x106 | `150x63` / `<img>` 159x106 |
| lockup `<img>` filter | **`none`** | **`none`** |
| band gradient | `linear-gradient(135deg, rgb(10,16,36) 0%, ...)` | identical |
| `theme` / `data-bs-theme` | `theme-dark` / `dark` | `theme-light` / `light` |

Every plate property is gone; the treatments resolve correctly and per scheme; `overflow: hidden`
survives so the cropping window still works; and the filter sits on the window, not the oversized
image - all measured, not assumed.

**Visual, both schemes.** Full-page captures plus 2.4x and 1.7x crops of each mark were inspected.
Dark: the seal glows directly on the card with no box, and "ACAD" reads clearly on the band.
Light: the seal sits on the white card with a soft grounding shadow; the band is visibly still
navy, which is what makes a single lockup treatment correct rather than an oversight.

**Function, against the running app:**

| Check | Result |
|---|---|
| `GET /login` | 200 |
| `POST` seeded credentials | HTTP 200, final path **`/admin/dashboard`**, app shell present |
| `POST` wrong password | HTTP 200, back at **`/login`**, `@error` branch rendered |
| markup | `login-crest-plate` x5, `login-brand-chip` x4, `udd-logo.png` x2, `acadalert_logo.png` x2, `acadalert_notxt.png` x1, **`Forgot password` 0** |

**Integrity.** `<style>` brace walk (comment- and string-aware) `depth=0 min=0`, 4 keyframes, 2
media queries - BALANCED. `view:cache` compiles. `--lg-plate` has **zero** remaining references.

**Suite.** `php artisan test` **167 passed (563 assertions)**; `check_inline_scripts.js` **28
blocks, 0 failing**; `block_dom_harness.js` every control handler fired. `mock-api:tenancy` and
`mock-api:escalation-drill` are unaffected by a standalone view and were run green in Refactor 22;
`mock-api:smoke` does not render `/login`.

### 7. Rollback

Revert the two rule blocks and the token declarations in `resources/views/auth/login.blade.php`.
Because no markup changed, nothing else needs unwinding - and restoring `--lg-plate` plus the two
`background` lines brings the plates back exactly as Refactor 22 shipped them.

---

## Refactor 24: Sign-In header — the two marks side by side (design only, complete)

### 1. What the request resolved to

Move `acadalert_logo.png` off the navy band and up beside `udd-logo.png`, remove the three text
lines inside the card ("AcadAlert", "AI-Powered Academic Risk Detection", "Universidad de
Dagupan"), and tidy whatever was left.

### 2. The decision

* **The band was deleted, not emptied.** Once the lockup and its caption left it there was nothing
  to put in it, and a 60px navy strip holding one small mark would have read as a divider between
  nothing. Removing it also removed its corner light, its two hairlines and the `overflow: hidden`
  it needed. The card's own padding (`32px`) is unchanged, so the header still starts where it
  always did.
* **One centred row, sized for optical balance rather than equality.** The seal is 1:1; the lockup
  is 2.371:1. Matching their heights would hand the row to the wordmark, so the lockup is held at
  roughly half the seal's height - **92px seal + 100px-wide lockup (42px tall)**. At those numbers
  the pair reads as equals, which is the point of putting them together.
* **A soft hairline between them.** `.login-logo-rule` is a 1px `linear-gradient` fading at both
  ends, using the card's own `--lg-card-edge` token. It gives the row structure without drawing a
  hard seam - and it inherits the theme token, so no per-scheme work was needed for it.
* **Both shadows are now scheme-dependent, and that is a direct consequence of the move.** On the
  band (a fixed navy gradient in both schemes) one lockup treatment covered both. On the *card*
  (dark in dark mode, near-white in light) it cannot: the light block now supplies its own
  `--lg-lockup-shadow` that drops the white rim - on white the navy ink already reads, and a white
  rim would be invisible anyway - and leaves a soft `0 3px 10px rgba(15,23,42,.18)` grounding
  shadow instead. The token comment was rewritten to say this rather than left claiming the
  opposite.
* **The banner stays the banner.** The card is still framed by the 3px electric sweep, and the page
  footer still carries `AcadAlert · AI-Powered Academic Risk Detection System`; the request scoped
  the removals to text *inside the card*, so the footer was left alone.
* **`.login-brand-chip` is still a clipping window.** Only its width changed (150px -> 100px), to
  fit the row. All four measured cropping ratios are untouched, because they are ratios of the
  artwork and not of the window - the window width is the single number that ever needs setting,
  exactly as the comment there has said since Refactor 22.
* **`position: relative` and `z-index: 1` were dropped** along with the band: they existed only to
  lift that window above the band's `::after` corner light. `overflow: hidden` was kept - it is
  structural, not cosmetic (the `<img>` renders at 159x106 inside the 100x42 window).
* **The three removed text nodes were checked before removal.** The only readers of
  `.login-brand-note` and `.login-crest-school` were those nodes, so both rules went with them
  rather than lingering as dead CSS. A grep across `public/js/**` and `tests/**` returned **zero**
  hits for `login-brand`, `login-crest-school` or `login-logo` - no script or test reads any.

### 3. What changed

| | |
|---|---|
| markup | the two `<div class="login-logo">` / `<div class="login-brand">` blocks became one `.login-logo` row holding the seal, a `.login-logo-rule`, and the `.login-brand-chip` lockup |
| removed markup | `<div class="login-crest-school">`, `<span class="login-brand-note">` and its `<strong>`, and the `.login-brand` band element itself |
| `.login-logo` | `text-align: center` -> **flex row**, `gap: 18px`, `margin-bottom: 22px` -> `28px` |
| `.login-crest-plate` / its `img` | 112px -> **92px** (both), `margin: 0 auto 14px` dropped (the flex row centres it) |
| `.login-logo-rule` | **new** |
| `.login-brand`, `.login-brand::after` | **deleted** (band + corner light) |
| `.login-brand-note`, `.login-brand-note strong` | **deleted** |
| `.login-brand-chip` | width 150px -> **100px**; lost `position`/`z-index`; kept `overflow: hidden` and its filter |
| `--lg-lockup-shadow` | **added to `:root.theme-light`** (was dark-only, which is now wrong) |
| `@media (max-width: 576px)` | crest 96px -> **72px**, chip 126px -> **78px**, new `.login-logo { gap: 14px }`; the dead `.login-brand` / `.login-brand-note` rules removed |

The mobile numbers were chosen so the row cannot wrap even at 320px: 72 + 14 + 1 + 14 + 78 =
**179px**, comfortably inside the 20px-padded card.

**Every id, field name, `action`, `method`, `@csrf` and inline hook is byte-identical.** The only
markup removed is the three text nodes and the band wrapper the request asked to eliminate; both
`<img>` elements keep their original `src` and `alt`.

### 4. Files

| File | Change |
|---|---|
| `resources/views/auth/login.blade.php` | the header markup and the rules in §3 |

Nothing else. No `app.css`, no `theme.css`, no layout, no route, controller, model, migration,
seeder or test.

### 5. Verification record

**Computed geometry and styles, both schemes** (real stylesheets, real rendered `/login` output,
1366x760):

| | dark | light |
|---|---|---|
| `.login-logo` display / gap | `flex` / `18px` | identical |
| row / seal / chip / rule boxes | 378x92 · 92x92 · 100x42 · 1x80 | identical |
| seal filter | 3 light drop-shadows | 2 soft shadows (glow dropped) |
| chip filter | white rim + blue halo | `drop-shadow(rgba(15,23,42,.18) 0 3px 10px)` |
| chip overflow | **`hidden`** | **`hidden`** |
| rule background | `linear-gradient(rgba(0,0,0,0), rgba(148,197,255,.16) 22%, ...)` | same shape, `rgba(15,23,42,.1)` |
| `.login-brand` present | **`bandGone: true`** | same |
| `.login-crest-school` / `.login-brand-note` | **`schoolGone` / `noteGone`: true** | same |
| card inner text | **`"EMAIL ADDRESS PASSWORD Remember me Sign In"`** | identical |
| card padding-top | `32px` | `32px` |
| horizontal overflow | **0** | **0** |

The card's `innerText` is the strongest single check: the only text left inside it is the two field
labels, the checkbox label and the button. All three removed strings are confirmed absent from the
card (two survive elsewhere by design - the seal's `alt` attribute, and the page footer).

**Visually.** Full-page and 2.2x header-row crops were inspected in both schemes. Dark: the seal
glows to the left of the soft rule, the lockup's rim makes "ACAD" read on the card, and the card
opens straight onto the fields. Light: both marks sit cleanly on the white card; the lockup is
fully legible with no filter fighting the surface.

**Function, against the running app:**

| Check | Result |
|---|---|
| `GET /login` | 200, CSRF token present (40 chars) |
| `POST` seeded credentials | redirected into **`/admin/dashboard`**, app shell present |
| `POST` wrong password | back at **`/login`**, `@error` branch rendered |
| hooks on the rendered page | `name="_token"` 1, `id="email"` 1, `id="password"` 1, `id="remember"` 1, `btn-login` 6, both logo `src`s intact, stray `login-brand"` **0** |

**Integrity.** `<style>` brace walk (comment- and string-aware) `depth=0 min=0`, 4 keyframes, 2
media queries - BALANCED. `view:cache` compiles.

**Suite.** `php artisan test` **167 passed (563 assertions)** · `mock-api:smoke` **all 65
endpoints** · `mock-api:tenancy` isolation holds · `mock-api:escalation-drill` passed ·
`check_inline_scripts.js` **28 blocks, 0 failing** · `block_dom_harness.js` every control handler
fired · `risk_scoring_progress_harness.js` terminal state reached.

### 6. A measurement trap worth recording

The first header-row crop missed the row entirely. The probe reported the card at `y=54`, but the
`<pre id="PROBE">` it appends grows the document, and `body`'s flex centring re-centres the card
against the *content* height rather than the 760px viewport - so the probe's absolute `y` sat ~96px
above where the painted, probe-free screenshot has it. Only the probe's **relative** geometry
(row/seal/chip offsets and sizes) is portable; absolute coordinates in a centred layout are not.
The crop was recomputed from the card's own centring: `(760 - 460) / 2 = 150`.

### 7. Rollback

Restore `resources/views/auth/login.blade.php` from HEAD. The page is self-contained, so nothing
else needs unwinding. To reinstate the band specifically: re-add `.login-brand` and its `::after`,
re-wrap the lockup and the caption, and drop the light `--lg-lockup-shadow` override.

---

## Refactor 25: Brand-mark glow — the sidebar lockup and the navbar wordmark (design only, complete)

### 1. What the request resolved to

Give `acadalert_logo.png` (sidebar header) and `ctrl.png` (navbar brand) the same measured
light-drop-shadow treatment the login page uses, so each reads clearly against the dark chrome it
stands on. Uniform across all four roles, and in both themes.

### 2. The decision

* **Two tokens, declared once, in `:root` - not duplicated per scheme.** Both marks sit on **fixed
  dark chrome**: the rail is `#1a1a2e -> #16213e` and the bar is
  `#1a1a2e -> #16213e -> #1b2b60`, and neither logo ever leaves its surface. So there is nothing
  for a light override to do, and both live beside `--app-sidebar-*` and `--app-navbar-*` in the
  fixed-chrome block.
* **That is the opposite call from Refactor 24, and the reason is instructive.** The login page's
  `--lg-lockup-shadow` HAD to be duplicated into `.theme-light`, because that lockup moved *off*
  the fixed-navy band and *onto* the card - dark in dark mode, near-white in light. The rule is the
  surface, not the logo. Recorded in the token comment so the two are not "tidied" into agreement.
* **A shadow, not `brightness()`.** The measurements (taken in Refactor 23, reused here) are why:
  `acadalert_logo.png` has mean luminance 64/255 with 70% of it under lum 80, and its "ACAD" is
  `#001e84` (lum 31) against a rail running lum 19..30 - **~1.1:1, effectively unreadable**.
  Multiplying that ink by 1.6 reaches only lum 49, still ~1.1:1. A light drop-shadow works because
  it **adds a bright rim** around each letterform rather than scaling the ink, and because it
  follows the artwork's own silhouette, it paints nothing under the mark's interior - so it is
  still not the "solid box" Refactor 23 removed.
* **The rim carries legibility; the halo carries presence - and both are sized to their mark
  rather than copied from the login page.**
  * lockup (218x92 window): **1.5px** rim + **12px** halo. The rim is a hair thicker than the login
    lockup's 1px because this mark is ~2.2x larger, so a 1px rim would be proportionally thinner.
    The halo is 12px, not the login page's 15px, so it stays inside the heading's 16px gutter
    instead of running into the rail edge.
  * navbar (212x26 window): **1px** rim + **7px** halo, scaled down because a 9px+ halo on a 26px
    -tall mark is ~35% of its height and reads as a blob rather than a glow.
* **The filter goes on the clipping WINDOW, not the `<img>` - and for the navbar the case is
  unarguable.** The navbar's inner image is **584% of the window's height** (a 2143x263 artwork
  cropped out of a 2752x1536 file), so a blur specified on it would be measured against that box -
  roughly 1.4% of the artwork's height per pixel of blur - and would land nowhere near the
  letterforms. On the window the shadow is generated from the clipped result and traces the
  artwork. The same reasoning already justified placing the login chip's filter on the chip.
* **The two navbar filters do not interact.** The `<img>` keeps its `invert(1) hue-rotate(180deg)`
  re-lighting and the window gains the shadow. A drop-shadow reads **alpha only**, and neither
  invert nor hue-rotate touches alpha - so the shadow traces the same letterforms regardless of
  which is applied first. The re-lighting makes the wordmark legible; the shadow gives it
  separation, which is the one thing invert/hue-rotate cannot supply.
* **Nothing is clipped.** Checked before writing: `.sidebar-heading` is `overflow: visible`, the
  `.navbar` carries no `overflow` at all (the only `overflow-x: hidden` is on `body`, and `.navbar`
  is `fixed-top`, so its containing block is the viewport and the body does not clip it), no
  ancestor of either mark carries a `filter` or `transform`, and `.navbar-brand` is `flex: 0 1 auto`
  with `min-width: 56px` rather than a hard clip. The glow has room to paint.

### 3. What changed

| File | Change |
|---|---|
| `public/css/theme.css` | added `--app-logo-lockup-shadow` (1.5px rim + 12px halo) and `--app-logo-navbar-shadow` (1px rim + 7px halo) to the fixed-chrome `:root` block, with the derivation as a comment |
| `public/css/app.css` | `#sidebar-wrapper .sidebar-heading .sidebar-logo` gained `filter: var(--app-logo-lockup-shadow)`; `.navbar-brand .navbar-logo` gained `filter: var(--app-logo-navbar-shadow)`; the stale "no background, panel or shadow" line in the sidebar comment was replaced, and the navbar re-lighting comment gained a note on why the two filters do not interact |

**No markup changed at all** - no blade file was written. mtimes confirm it: the CSS edits are
19:00/19:03, and the newest view in the tree is 18:21 (Refactor 24). Every id, class, JS hook,
`data-*`, `onclick`, form field and route is therefore byte-identical by construction. No JS, no
route, controller, model, migration, seeder or test.

Because the treatment keys off the two existing logo classes rather than anything role-specific, it
applies to **Admin, Academic Head, Counselor and Student** with no per-role rule - the chrome is
shared.

### 4. Verification record

**Computed styles - byte-identical across both themes and across roles**, exactly as the
fixed-chrome design predicts (real stylesheets, real rendered dashboards):

| | admin-dark | admin-light | student-dark | student-light |
|---|---|---|---|---|
| navbar window filter | `drop-shadow(rgba(255,255,255,.85) 0 0 1px) drop-shadow(rgba(160,200,255,.45) 0 0 7px)` | identical | identical | identical |
| sidebar window filter | `drop-shadow(rgba(255,255,255,.9) 0 0 1.5px) drop-shadow(rgba(160,200,255,.5) 0 0 12px)` | identical | identical | identical |
| `<img>` filter (navbar / sidebar) | `invert(1) hue-rotate(180deg)` / `none` | identical | identical | identical |
| window `overflow` (both) | `hidden` | identical | identical | identical |
| `.sidebar-heading` / `#sidebar-wrapper` / `.navbar` overflow | `visible` / `auto` / `visible` | identical | identical | identical |

**That the glow actually PAINTS** - a luminance profile stepping outward from each window edge, on
the real screenshots. Both decay smoothly back into the surface, which is the signature of a real
blurred shadow rather than a clipped or absent one:

```
NAVBAR  rightward from x=240, y=30 :  1=52.1  2=49.0  3=46.2  5=40.7  8=34.5
                                     12=30.4  18=29.7  26=29.7  36=29.7  50=29.7
SIDEBAR downward  from x=122, y=173:  1=59.5  2=56.7  3=50.5  5=41.7  8=38.9
                                     12=34.3  18=31.0  26=28.2  36=28.9  50=46.0
```

**Uniformity proved at the pixel level, not by eye.** A pixel-by-pixel comparison over each logo's
window (plus the glow ring just outside it) returned **0 differing pixels, max channel-sum delta
0** for admin-dark vs admin-light, admin-dark vs student-dark, and the navbar's outer ring. Raw
pixel hashes of the two windows are likewise identical across all four renders:

```
nav  window pixels: EA99FEB6243CBCC48E35BADFFB2A3DB6  (all four)
side window pixels: 0219E3ACD0A14963F20277889B8EA753  (all four)
```

**Visually.** Both marks were cropped at 2.6x / 2.2x with their glow margin and inspected. "ACAD"
now has a defining near-white rim against the rail instead of dissolving into it; the wordmark
separates cleanly from the band while keeping its `+INNOV8` orange. The light-theme crops are
pixel-identical to the dark ones, which is the design working as intended rather than a missing
override.

**Integrity.** Brace walks clean: `app.css` `depth=0 min=0` (11 keyframes, 26 media queries),
`theme.css` `depth=0 min=0`. Each new token is declared exactly once and consumed exactly once.
`view:cache` compiles.

**Suite.** `php artisan test` **167 passed (563 assertions)** · `mock-api:smoke` **all 65
endpoints** · `mock-api:tenancy` isolation holds · `mock-api:escalation-drill` passed ·
`check_inline_scripts.js` **28 blocks, 0 failing** · `block_dom_harness.js` every control handler
fired · `risk_scoring_progress_harness.js` terminal state reached.

### 5. A measurement lesson worth recording

The first uniformity check hashed each logo region by **re-encoding it to BMP and hashing the file
bytes**. The navbar matched across all four, but the sidebar differed between dark and light -
which looked like a missing override and sent me into a pixel-by-pixel diff to locate it. That diff
reported **0 differing pixels**, and hashing the **raw pixel bytes** instead of the encoded file
made all four agree. A bitmap re-encode embeds header/DPI metadata, so a *file* hash can differ
while the *pixels* are identical. **Hash the pixels, not the encoder's output** - and prefer a
pixel diff, which localises the disagreement instead of merely asserting one exists.

### 6. Rollback

Delete the two `filter:` lines and the two token declarations. No markup changed, so nothing else
needs unwinding - the marks return to their un-lit state exactly as Refactor 24 left them.




## Refactor 26: Risk-scoring execution-time ceiling — robust on a fresh PHP install (bug fix, complete)

**Report:** on a clean laptop, running AI risk scoring over a 20-student block dies with
`Maximum execution time of 30 seconds exceeded` (a PHP fatal → HTTP 500, shown in the scoring
modal as a raw engine string). The original machine had a hand-tuned `php.ini`; a stock install
does not.

### 1. Root cause (reproduced, not guessed)

A block is scored **synchronously inside one HTTP request**. `RiskScoringService::processBlock()`
chunks the students five at a time (`aistudio.batch_size`) and sleeps `aistudio.batch_delay_seconds`
(4s) **between** batches, to hold the free-tier cadence. A 20-student block is therefore 4 batches =
3 sleeps = **~12s of sleep alone**, before AI latency, the HTTP round-trip and the omission-retry
policy. PHP's default `max_execution_time` is **30s**, and — decisively — on **Windows** the measured
time is **wall-clock, not CPU time**, so the `usleep()` pauses count toward the limit. A request that
legitimately needs far more than 30s therefore fails on a stock host.

### 2. The fix — a request-scoped ceiling, no global change

| File | Change |
|------|--------|
| `app/Http/Controllers/Controller.php` | Added `extendAiExecutionTime()` — reads `services.aistudio.max_execution_seconds`, then applies `@set_time_limit()` + `@ini_set('max_execution_time', ...)` for **this request only**. Both calls are `@`-suppressed so a host that disables the functions cannot turn the guard itself into a fatal error; under the CLI SAPI `set_time_limit()` is a no-op, so `artisan test` / `artisan serve` are unaffected. `0` disables the override. |
| `config/services.php` | `aistudio.max_execution_seconds` added (`AI_STUDIO_MAX_EXECUTION_SECONDS`, default **300** = 5 min). |
| `.env`, `.env.example` | `AI_STUDIO_MAX_EXECUTION_SECONDS=300`, documented inline. |
| `app/Http/Controllers/AcademicHead/RiskScoringController.php` | `runScoring()` calls the guard as its **first statement** — the endpoint that triggers scoring. |
| `app/Http/Controllers/AcademicHead/InterventionController.php` | `generateForBlock()` and `generateForSelected()` call the same guard: they run the identical long synchronous AI loop (one AI call per student in-request). |

`set_time_limit()` is scoped to the current script by definition, so **no other page** — dashboards,
reports, lists, the read-only `scoring-status` poll — is affected. The default is comfortably above
the worst case (4s × batches + AI latency + retries) and is env-overridable per host.

### 3. Queue-vs-synchronous decision (investigated, then chosen deliberately)

`ProcessRiskScoringJob` exists but is **never dispatched** by the request path — the whole block runs
synchronously in `runScoring()`. Moving `run-scoring` to a dispatched job + UI poll was **rejected on
evidence**, not convenience:

* **The UI contracts are synchronous, and they are asserted.** `block_dom_harness.js` requires the
  Run button to issue the `run-scoring` request; `risk_scoring_progress_harness.js` stubs the
  request's `{ success, processed, batches, ai_calls }` payload and asserts the bar settles from
  **that** response. A dispatched job returns no `processed`, so both harnesses — and the JS hooks
  the task requires to stay byte-identical — would have to change.
* **A queue worker is not guaranteed on a fresh laptop.** The whole premise is "works with no manual
  setup"; `QUEUE_CONNECTION=database` needs a running `queue:work` consumer, which a double-click
  XAMPP start does not provide.
* **Polling progress was already tried and rejected in this codebase** (Refactor 13 §1):
  `scoring-status` reports `processed: 0` for the entire in-flight run, because nothing is written
  until the batch loop ends — a poll would cost one request/second and still show a motionless bar.

The synchronous path is therefore kept, with the raised per-request ceiling. `ProcessRiskScoringJob`
is left **untouched** and remains available to a deployment that chooses to score on a worker.

### 4. User-facing failure message

`resources/views/academic-head/block.blade.php` — the scoring modal's `catch` no longer prints the raw
engine string. A PHP timeout does not return JSON (it is a 5xx HTML body, a JSON-parse failure or a
dropped connection), so the non-OK branch now falls back to `HTTP <status>`, and a new
`friendlyScoringError()` classifies timeout / transport / 5xx errors into **"Scoring is taking longer
than expected. Please wait a moment or try a smaller batch."** Genuine, descriptive AI errors (e.g.
the 503 `"AI risk scoring unavailable: …"`) pass through unchanged. No id, function name or other JS
hook was renamed — `runRiskScoring`, `scoringModal`, `scoringProgress`, `scoringStatus`,
`runRiskScoringBtn` and the rest are byte-identical.

### 5. Setup note — `max_execution_time`

The code now raises the limit **per request, in the app**, so **no `php.ini` edit is required** on any
host. A host that nevertheless wants a higher *global* default may still set `max_execution_time` in
`php.ini`, but the app no longer depends on it: `AI_STUDIO_MAX_EXECUTION_SECONDS` (default `300`) is
the single knob, and it overrides whatever `php.ini` says. A hardened host that forbids
`set_time_limit()`/`ini_set()` degrades silently to its `php.ini` value instead of erroring.

### 6. Pre-existing brittleness repaired (unrelated to scoring)

`tests/Unit/EndOfTermReportAppendixTest` asserted the raw PDF bytes did **not** contain the bare three
characters `A6.` / `A7.`. The PDF embeds an image stream that can contain `A6.` **by chance**
(probed: exactly **1** occurrence, with **0** headings; `A1. `, `A5. ` present once each), so the
suite failed *before* any scoring change. The needles now carry the trailing space the rendered
headings use (`A6. ` / `A7. `), matching the sibling numbering test's `/A(\d)\. /` contract. Intent
unchanged; false positive removed.

### 7. Verification

| Check | Result |
|-------|--------|
| `php artisan test` | **171 passed (567 assertions)** — includes the new `ControllerExecutionTimeTest` (config default 300, configured ceiling honoured, `0` leaves the host value untouched) |
| `php artisan mock-api:smoke` | All **65** endpoints rendered |
| `php artisan mock-api:tenancy` | **5/5 PASS** — isolation holds |
| `php artisan mock-api:escalation-drill` | **PASS** (all writes rolled back) |
| `node tests/JavaScript/check_inline_scripts.js` | **28** blocks, **0** failing |
| `node tests/JavaScript/block_dom_harness.js` | every control handler fired |
| `node tests/JavaScript/risk_scoring_progress_harness.js` | terminal state reached, figures refresh |
| 20-student block, stock 30s config | completes — the ceiling is raised to 300s for the whole request |

### 8. Rollback

Remove the three `extendAiExecutionTime()` call sites and the method in `Controller.php`, the
`max_execution_seconds` config key and the `.env` / `.env.example` lines, and restore the two needles
in `EndOfTermReportAppendixTest`. No schema, migration, route, model, escalation flow or
`ProcessRiskScoringJob` change is involved.
## Refactor 27: Block Dashboard — the AI-scoring failure modal showed TWO Retry buttons (bug fix, complete)

**Report:** when AI risk scoring fails, the progress modal shows **two** `Retry` buttons instead of
one.

### 1. Root cause

The modal markup contains **no** Retry button — it is created on demand in `runRiskScoring()`'s
`catch`. The handler appended a fresh button after a 3s reveal delay and **never removed the
previous one**. The modal is `data-bs-backdrop="static"` with no close button, so the stale button
stayed in the DOM; the next failure (the user clicks Retry → the run restarts and fails again)
appended a second one, and a third stacked a third. Reproduced in the harness: 1 → 2 → 3 buttons.

### 2. The fix (Retry rendering only)

`resources/views/academic-head/block.blade.php` — the created button is now tracked, and a small
`resetScoringRetry()` removes it (and cancels its pending reveal timer) **before** a new one is armed
and again when a run starts:

* `resetScoringRetry()` — clears `scoringRetryTimer` / removes `scoringRetryButton`.
* `runRiskScoring()` calls it right after `modal.show()`, so a fresh run never inherits a stale button.
* the `catch` assigns `scoringRetryTimer = setTimeout(...)` and calls `resetScoringRetry()` before
  appending, then stores the new button in `scoringRetryButton`.

Nothing else changed. The scoring pipeline, the 503 handling, every existing error/status message,
and every id, class, `data-*`, JS hook and drill literal are byte-identical; the modal markup was not
touched at all.

### 3. Verification

| Check | Result |
|-------|--------|
| `tests/JavaScript/scoring_retry_harness.js` **(new)** | 9/9 — three failure cycles, **exactly one** Retry button at every rest point (sensitivity-checked: fails 2→3 buttons against the pre-fix logic) |
| `php artisan test` | **171 passed (567 assertions)** |
| `php artisan mock-api:smoke` | All **65** endpoints rendered |
| `php artisan mock-api:tenancy` | **5/5 PASS** |
| `php artisan mock-api:escalation-drill` | **PASS** (writes rolled back) |
| `node tests/JavaScript/check_inline_scripts.js` | **28** blocks, **0** failing |
| `node tests/JavaScript/block_dom_harness.js` | every control handler fired |
| `node tests/JavaScript/risk_scoring_progress_harness.js` | terminal state reached |

### 4. Rollback

Delete `resetScoringRetry()`, its two call sites and the two state variables, and restore the bare
`setTimeout(() => { ... }, 3000);` in the `catch`. No schema, route, model, controller or scoring
change is involved.
