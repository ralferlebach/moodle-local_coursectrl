# Course Control Hub — Check Rule Reference

**Version:** 0.1.57 — 2026-04-19
**Status:** authoritative, updated each patch

---

## Rule Overview

| ID  | Name                        | Scope          | Responsibility            |
|-----|-----------------------------|----------------|---------------------------|
| R0  | Course-frame plausibility   | All activities | `local_coursectrl` central|
| R1  | Accessibility               | All activities | `local_coursectrl` central|
| R2  | completionexpected window   | Per CM         | `temporal_conflict_detector` + adapter config |
| R3  | Process logic               | Per adapter    | Subplugin adapter         |
| R4  | Date coupling               | Per adapter    | Subplugin adapter (configurable) |
| R7  | Missing counterparts        | Per adapter    | Subplugin adapter (configurable, default varies) |

---

## R0 — Course-Frame Plausibility

Checked centrally before any other rule. Only meaningful when course start/end are defined.

| Sub-rule | Condition                                    | Severity | Notes                                  |
|----------|----------------------------------------------|----------|----------------------------------------|
| R0a      | Activity date after course end               | error    | Always blocks                          |
| R0b      | Activity date before course start            | error    | Link to course settings in message     |
| R0c      | Deadline in the past (completion tracking on)| warning  | May be intentional for template courses|

---

## R1 — Accessibility

Checks whether all relevant learners have uninterrupted access to an activity during its
active period. Considers availability conditions on the activity, its section, and
prerequisites.

| Condition                                                        | Severity |
|------------------------------------------------------------------|----------|
| No learner has continuous access                                  | warning  |
| Only a defined group subset has continuous access                 | notice   |
| A date shift changes the group that has access                    | warning  |

**Configuration (plugin-level setting `local_coursectrl`):**
- `accessibility_check_mode`: `simulation` (default) / `static` / `off`

---

## R2 — completionexpected Window

`completionexpected` triggers a calendar reminder. It should sit within a reasonable
window before the primary deadline of the activity.

| Condition                                                          | Severity  | Notes                                        |
|--------------------------------------------------------------------|-----------|----------------------------------------------|
| completionexpected after primary deadline                          | warning   | Exception: multiphase activities (see below) |
| completionexpected before deadline, gap > configured threshold     | notice    | Default threshold: 3 days before deadline    |
| For multiphase activities (workshop, studentquiz): completionexpected ≤ last deadline | notice | Not warning, since phases span longer periods |

**Configuration (adapter-level, instanz-wide settable):**
- `completionexpected_warning_offset_days`: days before deadline that trigger notice (default: 3)

**Primary deadline per adapter:**

| Adapter         | Primary deadline field    | Multi-phase? |
|-----------------|---------------------------|--------------|
| mod_assign      | duedate                   | no           |
| mod_quiz        | timeclose                 | no           |
| mod_feedback    | timeclose                 | no           |
| mod_forum       | duedate                   | no           |
| mod_lesson      | deadline                  | no           |
| mod_workshop    | assessmentend             | yes          |
| mod_choice      | timeclose                 | no           |
| mod_scorm       | timeclose                 | no           |
| mod_capquiz     | timedue                   | no           |
| mod_choicegroup | timeclose                 | no           |
| mod_questionnaire | closedate               | no           |
| mod_studentquiz | closeansweringfrom        | yes          |

---

## R3 — Process Logic (per adapter, error severity)

These checks detect conditions that make activity completion logically impossible.
Violations are always `error`. Implemented in each subplugin adapter's `run_checks()`.

| Adapter         | Rule                                                            |
|-----------------|-----------------------------------------------------------------|
| mod_assign      | allowsubmissionsfromdate > duedate                              |
| mod_quiz        | timeopen > timeclose                                            |
| mod_quiz        | timelimit > (timeclose − timeopen) when both dates are set      |
| mod_feedback    | timeopen > timeclose                                            |
| mod_forum       | assesstimestart > assesstimefinish (when ratings enabled)       |
| mod_lesson      | available > deadline                                            |
| mod_workshop    | submissionstart > submissionend                                 |
| mod_workshop    | assessmentstart > assessmentend                                 |
| mod_workshop    | submissionend > assessmentstart                                 |
| mod_choice      | timeopen > timeclose                                            |
| mod_scorm       | timeopen > timeclose                                            |
| mod_choicegroup | timeopen > timeclose                                            |
| mod_questionnaire | opendate > closedate                                          |
| mod_studentquiz | opensubmissionfrom > closesubmissionfrom                        |
| mod_studentquiz | openansweringfrom > closeansweringfrom                          |
| mod_studentquiz | closesubmissionfrom > openansweringfrom                         |

---

## R4 — Date Coupling (per adapter, configurable)

When the primary deadline of an activity is shifted, secondary dates that are logically
coupled to it are shifted by the same delta automatically.

**Configuration (adapter-level):**
- `coupling_mode`: `days` (default) / `seconds` / `off`
  - `days`: delta rounded to full days, preserves time-of-day
  - `seconds`: exact same delta in seconds
  - `off`: no automatic coupling

| Adapter    | Primary field  | Coupled secondary fields              |
|------------|---------------|---------------------------------------|
| mod_assign | duedate       | cutoffdate (+Δ), gradingduedate (+Δ)  |
| mod_forum  | duedate       | cutoffdate (+Δ)                       |
| mod_workshop | submissionend | assessmentstart (+Δ)                |

---

## R7 — Missing Counterparts (per adapter, configurable per site)

These checks flag when one field of a logical pair is set but the other is missing.
All R7 checks are **configurable per subplugin** as a Moodle admin setting with three
possible values: `off` / `notice` / `warning`. Defaults are listed below.

### mod_assign

| Condition                                    | Default  |
|----------------------------------------------|----------|
| allowsubmissionsfromdate set, duedate not set | notice  |
| cutoffdate set, duedate not set               | warning  |
| gradingduedate set, duedate or cutoffdate not set | warning |
| duedate set, allowsubmissionsfromdate not set | off     |

### mod_quiz

| Condition                        | Default |
|----------------------------------|---------|
| timeopen set, timeclose not set  | notice  |
| timeclose set, timeopen not set  | off     |

### mod_feedback

All R7 checks: `off` (default).

### mod_forum

| Condition                              | Default |
|----------------------------------------|---------|
| duedate set, cutoffdate not set        | warning |
| cutoffdate set, duedate not set        | notice  |
| assesstimestart without assesstimefinish | notice |
| assesstimefinish without assesstimestart | notice |

### mod_lesson

| Condition                        | Default |
|----------------------------------|---------|
| available set, deadline not set  | notice  |
| deadline set, available not set  | off     |

### mod_workshop

| Condition                                         | Default |
|---------------------------------------------------|---------|
| assessmentstart set, assessmentend not set        | warning |
| assessmentend set, assessmentstart not set        | notice  |
| assessmentstart or assessmentend, submissionend not set | warning |

### mod_choice, mod_scorm, mod_choicegroup, mod_questionnaire, mod_studentquiz, mod_capquiz

All R7 checks: `off` (default).

---

## Forum/Glossary: assesstimestart / assesstimefinish

These fields exist in the Moodle DB tables `{forum}` and `{glossary}`. They control
the time window during which learners may *rate* posts/entries (Moodle Ratings feature).
They are only visible in the activity UI when ratings are enabled.

- `mod_forum`: fields included in adapter (relevant-mods.md), R3 check for ordering,
  R7 notice for incomplete pairs (default: notice).
- `mod_glossary`: adapter **removed** — fields are purely internal ratings metadata,
  not typically configured by teaching staff, and the module exposes no other
  schedulable content.

---

## Decisions Log

| Date       | Decision                                                              |
|------------|-----------------------------------------------------------------------|
| 2026-04-18 | Severity system introduced: error / warning / notice                  |
| 2026-04-18 | completionexpected checks moved from adapter to temporal_conflict_detector |
| 2026-04-19 | R0 (course-frame) elevated to error for both before-start and after-end |
| 2026-04-19 | R7 checks made configurable per adapter as Moodle admin settings      |
| 2026-04-19 | glossary adapter removed (ratings-only fields, no practical scheduling use) |
| 2026-04-19 | quiz.timelimit added to R3 (error when exceeds open window)            |
| 2026-04-19 | forum.assesstimestart/finish added to field_map and R3                 |
| 2026-04-19 | assign: completionexpected duplicate checks removed from adapter        |
