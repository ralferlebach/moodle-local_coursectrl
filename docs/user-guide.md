# Course Control Hub — User Guide

**Plugin:** `local_coursectrl` | **Version:** 1.0.0 | **Audience:** Administrators · Teachers

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Installation](#2-installation)
3. [Administrator Reference](#3-administrator-reference)
   - 3.1 [Capabilities](#31-capabilities)
   - 3.2 [Plugin Settings](#32-plugin-settings)
   - 3.3 [Subplugins](#33-subplugins)
   - 3.4 [Scheduled Tasks](#34-scheduled-tasks)
   - 3.5 [Privacy & Data Retention](#35-privacy--data-retention)
4. [Teacher Reference](#4-teacher-reference)
   - 4.1 [Opening the Plugin](#41-opening-the-plugin)
   - 4.2 [Dashboard (Cockpit)](#42-dashboard-cockpit)
   - 4.3 [Timeline & Gantt](#43-timeline--gantt)
   - 4.4 [Dependency Graph](#44-dependency-graph)
   - 4.5 [Checks — Problems Tab](#45-checks--problems-tab)
   - 4.6 [Checks — Solutions Tab](#46-checks--solutions-tab)
   - 4.7 [Checks — Simulation Tab](#47-checks--simulation-tab)
   - 4.8 [Bulk Date Shift](#48-bulk-date-shift)
   - 4.9 [Text Review](#49-text-review)
   - 4.10 [History & Rollback](#410-history--rollback)
5. [Concepts](#5-concepts)
   - 5.1 [Risk Severity Levels](#51-risk-severity-levels)
   - 5.2 [Consistency Check Rules (R0–R7)](#52-consistency-check-rules-r0r7)
   - 5.3 [How the Journey Simulation Works](#53-how-the-journey-simulation-works)
   - 5.4 [Subplugin Adapters](#54-subplugin-adapters)
6. [Troubleshooting](#6-troubleshooting)

---

## 1. Introduction

Course Control Hub gives teachers a single place to inspect, fix, and future-proof their
Moodle courses. It surfaces configuration problems that are otherwise invisible — date
inversions, circular completion dependencies, activities that can never be reached — and
provides bulk-editing tools to correct them efficiently and safely.

**Core principles:**

- **Preview first.** No data changes without an explicit preview and confirmation step.
- **Logged always.** Every bulk action is recorded in the audit log.
- **Reversible.** Bulk actions can be rolled back from the History page.
- **Non-intrusive.** The plugin never acts automatically or on behalf of learners.

---

## 2. Installation

### Requirements

| Component | Minimum | Tested up to |
|---|---|---|
| Moodle | 4.5 | 5.2 |
| PHP | 8.2 | 8.4 |
| Database | MariaDB 10.6 / PostgreSQL 14 | MariaDB 10.11 / PostgreSQL 16 |

### Steps

1. Extract the archive into `<moodleroot>/local/coursectrl/`.
   The directory must be named exactly `coursectrl`.

2. Log in as administrator and navigate to
   **Site administration → Notifications**.
   Moodle will detect the plugin and run the database installer automatically.

3. Assign capabilities to the appropriate roles (see [§ 3.1](#31-capabilities)).

4. Optionally install the companion **Course Control Hub Block** (`block_coursectrl`)
   to give teachers a visible entry point inside each course.

### Upgrade

Replace the `local/coursectrl/` directory with the new version and visit
**Site administration → Notifications**.
Database migrations run automatically. No manual SQL is required.

---

## 3. Administrator Reference

### 3.1 Capabilities

Capabilities are defined per course context. Assign them via
**Site administration → Users → Permissions → Define roles**.

| Capability | Default roles | Description |
|---|---|---|
| `local/coursectrl:view` | `editingteacher`, `manager` | View all analysis pages (read-only) |
| `local/coursectrl:bulkaction` | `editingteacher` | Execute bulk date shifts and text changes |
| `local/coursectrl:rollback` | `manager` | Roll back a previously executed bulk action |

### 3.2 Plugin Settings

**Site administration → Plugins → Local plugins → Course Control Hub**

#### History and data retention

| Setting | Default | Description |
|---|---|---|
| `history_maxcount` | 100 | Maximum number of batch records retained per course |
| `history_maxdays` | 365 | Maximum age of batch records in days |

A scheduled task runs nightly to remove records older than these limits.

#### Consistency check rules (R0–R7)

Each rule can be set to **disabled**, **info**, **warning**, or **error** independently.
This allows administrators to tune the noise level for their specific Moodle installation.

| Rule | Default | Description |
|---|---|---|
| R0 — Date inversion | `warning` | A close/end date is set before the open/start date |
| R1 — Hidden tracked | `warning` | A completion-tracked activity is not visible to learners |
| R2 — Circular lock | `error` | A completion dependency cycle is detected |
| R3 — Stale group condition | `warning` | An availability condition references a group that no longer exists |
| R4 — Missing completion | `info` | An activity has availability conditions but no completion tracking |
| R5 — Unreachable activity | `warning` | An activity can never be accessed given the current condition chain |
| R6 — Section dead end | `warning` | A section has no reachable onward path for learners |
| R7 — No next step | `info` | A learner who completes the last available activity has no explicit next step |

#### Simulation settings

| Setting | Default | Description |
|---|---|---|
| `risk_max_group_combinations` | 32 | Maximum number of group-membership combinations the simulator evaluates per run |

Increasing this limit improves simulation completeness for courses with many groups but
increases server load.

### 3.3 Subplugins

Activity-type-specific logic is encapsulated in `coursectrlmod_*` subplugins located in
`local/coursectrl/mod/<modname>/`. Each subplugin contains an adapter class that
implements the `activity_adapter` interface: it declares which date fields are editable,
validates changes, and provides rollback state snapshots.

Subplugins bundled with version 1.0.0:

| Subplugin | Activity | Editable date fields |
|---|---|---|
| `coursectrlmod_assign` | Assignment | `duedate`, `allowsubmissionsfromdate`, `cutoffdate`, `gradingduedate` |
| `coursectrlmod_capquiz` | CAPQuiz | `timedue` |
| `coursectrlmod_choice` | Choice | `timeopen`, `timeclose` |
| `coursectrlmod_choicegroup` | Group Choice | `timeopen`, `timeclose` |
| `coursectrlmod_feedback` | Feedback | `timeopen`, `timeclose` |
| `coursectrlmod_forum` | Forum | `duedate`, `cutoffdate`, `assesstimestart`, `assesstimefinish` |
| `coursectrlmod_glossary` | Glossary | `assesstimestart`, `assesstimefinish` |
| `coursectrlmod_h5pactivity` | H5P Activity | no date fields (date-less adapter) |
| `coursectrlmod_lesson` | Lesson | `available`, `deadline` |
| `coursectrlmod_page` | Page | no date fields (date-less adapter) |
| `coursectrlmod_questionnaire` | Questionnaire | `opendate`, `closedate` |
| `coursectrlmod_quiz` | Quiz | `timeopen`, `timeclose` |
| `coursectrlmod_scorm` | SCORM Package | `timeopen`, `timeclose` |
| `coursectrlmod_studentquiz` | StudentQuiz | `opensubmissionsat`, `closesubmissionsat`, `openansweringat`, `closeansweringat` |
| `coursectrlmod_workshop` | Workshop | `submissionstart`, `submissionend`, `assessmentstart`, `assessmentend` |

Activities without a registered adapter can still be shifted at the availability-condition
level (completion-expected dates in `course_modules`).

To add support for a new activity type, create a `coursectrlmod_<modname>` subplugin
following the structure in any existing adapter.

### 3.4 Scheduled Tasks

| Task | Default schedule | Purpose |
|---|---|---|
| `\local_coursectrl\task\purge_old_batches` | Daily at 02:30 | Removes audit log records older than `history_maxdays` or beyond `history_maxcount` per course |

Configure via **Site administration → Server → Scheduled tasks**.

### 3.5 Privacy & Data Retention

Course Control Hub stores the following data:

| Table | Content | Personal data | Retention |
|---|---|---|---|
| `local_coursectrl_batch` | Audit log header; references `userid` of the person who executed the action | Yes — userid | Deleted when course is deleted; purged by scheduled task |
| `local_coursectrl_batch_item` | Per-activity result linked to a batch | No direct personal data | Deleted with parent batch |
| `local_coursectrl_snapshot` | State snapshot for rollback, linked to batch | No direct personal data | Deleted with parent batch |
| `local_coursectrl_text_hit` | Date references found in course texts; course-level data | No — course content only | Deleted when course is deleted |
| `local_coursectrl_risk` | Detected consistency issues; course-level data | No — course content only | Deleted when course is deleted |

The Privacy API (`classes/privacy/provider.php`) exports batch records and user
preferences when a GDPR subject-access request is made, and deletes personal data
when a deletion request is received.

---

## 4. Teacher Reference

### 4.1 Opening the Plugin

**From the course page:**
- If the **Course Control Hub Block** is installed, click the link in the block.
- Otherwise, navigate directly:
  `<moodleroot>/local/coursectrl/index.php?courseid=<id>`

The plugin opens to the **Dashboard**. Use the navigation tabs at the top to switch
between views.

### 4.2 Dashboard (Cockpit)

The Dashboard provides a quick overview of the course's health:

- **Upcoming dates** — a chronological list of the next activity open/close events.
- **Open issues** — a count of problems detected by the last consistency scan.
- **Recent actions** — the five most recent bulk actions executed in this course.

The Dashboard auto-refreshes its date list from the current course state. It does not
cache results across browser sessions.

### 4.3 Timeline & Gantt

The Timeline page shows all activity date fields in two complementary views:

**Schedule view** (default) lists every activity with its open and close dates. Use the
filter controls at the top to narrow by section, activity type, or date range.

**Gantt view** renders the same data as horizontal bars on a time axis. Hover over any
bar to see the exact dates in a tooltip. The Gantt supports scrolling and zooming for
large courses.

#### Bulk Date Shift from the Timeline

Any row in the Schedule view has a shift icon (⇄) that opens the **Shift workflow modal**
for that activity. To shift multiple activities at once, select them using the row
checkboxes and click **Shift selected** in the toolbar.

See [§ 4.8](#48-bulk-date-shift) for the full shift workflow.

### 4.4 Dependency Graph

The Dependency Graph renders the course's completion and availability structure as an
interactive directed graph.

**Navigation:**
- Drag to pan; scroll to zoom.
- Click any node to highlight its incoming and outgoing edges.
- Use the **Group filter** dropdown to restrict the graph to a specific group context.

**Node colours:**
- Blue — normal, reachable activity.
- Orange — available to the group but blocked by a condition.
- Red — activity detected as a dead end or circular lock.

**Simulation overlay:**
When a simulation result is active (from the Simulation tab), the graph applies a
second colour layer:
- Red border — activity is blocked in the simulated learner state.
- Green border — activity is the recommended next step for the simulated learner.

### 4.5 Checks — Problems Tab

The Problems tab runs all enabled consistency rules (R0–R7) against the current course
and lists each finding with its severity, affected activity, and a short explanation.

Rules are evaluated server-side on demand. Click **Run checks** to trigger a fresh scan.
Results are not cached between page loads.

Each finding includes a direct link to the affected activity in Moodle's normal editing
interface for immediate correction.

### 4.6 Checks — Solutions Tab

The Solutions tab groups the findings from the Problems tab by issue type and suggests
concrete fixes. Each suggestion links back to the relevant problem entry and, where
possible, provides a pre-configured action link (e.g., opening the shift modal with the
correct activity pre-selected).

### 4.7 Checks — Simulation Tab

The Simulation tab lets you evaluate the course from the perspective of a specific
learner state.

**Simulation parameters:**

| Parameter | Description |
|---|---|
| **Simulated date and time** | The point in time the simulation assumes; defaults to now |
| **Group membership** | One or more groups the simulated learner belongs to |
| **Completion assumptions** | Which activities the simulated learner has already completed |
| **Grade assumptions** | Pass/fail assumptions for graded activities with grade conditions |

Click **Run simulation** to evaluate the course. The result shows:

- A list of all activities that are **accessible** at the simulated state.
- A list of **blocked** activities with the specific conditions that block them.
- The **next recommended step(s)** — activities the learner should work on now.

The simulation result is also applied as an overlay to the Dependency Graph (see
[§ 4.4](#44-dependency-graph)).

**Scope:** The simulation evaluates date conditions, group-membership conditions,
completion conditions, and visibility rules. Grade conditions are simulated using your
assumed pass/fail values. Restrictions set at the section level cascade to all activities
within the section.

### 4.8 Bulk Date Shift

The Bulk Date Shift workflow moves one or more date fields by a fixed offset
(days + hours + minutes, positive or negative).

**Workflow steps:**

1. **Configuration** — enter the delta, optionally tick *Shift dependants* to cascade
   the shift to downstream completion-dependent activities, and click **Preview**.

2. **Preview** — the modal shows every activity that will be changed, with each date
   field listed by its resolved label (e.g. "Due date") along with the old and new values
   in locale-aware format. Fields that have no date set are shown as `–` and are skipped.
   Confirm by clicking **Apply shift**.

3. **Text review** (optional) — if you ticked *Show text review after shift*, the modal
   proceeds to the Text Review step after the shift completes, allowing you to update any
   date references found in free-text fields in the same workflow.

The shift is recorded in the audit log and can be rolled back from the History page.

**Availability-condition dates** (dates stored in `course_modules.availability`) are also
shifted, even for activity types that do not have a registered subplugin adapter.

### 4.9 Text Review

The Text Review step scans the following free-text fields for embedded date and time
references:

- Activity intro / description
- Section name and summary
- Course summary
- Labels (the Label activity type)

Each found reference is classified:

| Classification | Meaning |
|---|---|
| **Safe** | Unambiguous date that can be shifted automatically |
| **Ambiguous** | Date without a year, or a pattern that could be interpreted in multiple ways |
| **Informational** | Relative expression (e.g. "next week") — shown for awareness only; not shifted |

**Safe** references are pre-selected for update. **Ambiguous** references are shown but
not pre-selected; you can inspect the surrounding context and manually select them.
**Informational** entries cannot be automatically updated and are shown read-only.

Click **Apply selected text changes** to update the selected references. Changes are
recorded in the audit log alongside the corresponding date shift batch.

### 4.10 History & Rollback

The History page lists all bulk actions executed in this course, most recent first.

Each entry shows:
- Date and time of the action.
- Action type (e.g. *shift_dates*).
- Number of activities affected.
- Current status (completed / rolled back).

Click **Roll back** on any completed entry to restore the affected activities to their
state immediately before that action. The rollback itself is also recorded as a new
entry in the log.

**Note:** Rollback restores the field values captured at the time of the action. Changes
made by other means (e.g. direct editing of an activity) between the original action and
the rollback are not affected.

---

## 5. Concepts

### 5.1 Risk Severity Levels

| Level | Visual | Meaning |
|---|---|---|
| **Error** | 🔴 Red | Critical misconfiguration; learners are likely to be blocked |
| **Warning** | 🟡 Yellow | Probable problem; review recommended |
| **Info** | 🔵 Blue | Observation; not necessarily a problem |
| **Disabled** | — | Rule is turned off globally by the administrator |

Severity levels are configurable per rule by the administrator (see [§ 3.2](#32-plugin-settings)).

### 5.2 Consistency Check Rules (R0–R7)

| Rule | Name | Description |
|---|---|---|
| R0 | Date inversion | An activity's close/end date is set before its open/start date |
| R1 | Hidden tracked | A completion-tracked activity is hidden — learners cannot complete it |
| R2 | Circular lock | Two or more activities form a completion dependency cycle |
| R3 | Stale group condition | An availability condition references a group or grouping that no longer exists |
| R4 | Missing completion | An activity is required by an availability condition but has no completion tracking enabled |
| R5 | Unreachable activity | The activity's conditions can never be satisfied given the current course state |
| R6 | Section dead end | A section contains no onward path for learners who complete all activities within it |
| R7 | No next step | A learner at the end of their path has no clear next activity |

### 5.3 How the Journey Simulation Works

The simulation uses a breadth-first traversal of the course dependency graph, evaluating
all reachable group-membership combinations up to the configured limit.

For each combination, it evaluates:

1. **Date conditions** — compared against the simulated timestamp.
2. **Group and grouping conditions** — checked against the simulated membership.
3. **Completion conditions** — checked against the assumed completion state you provided.
4. **Grade conditions** — evaluated using your assumed pass/fail values (if provided); if
   no assumption is given, the condition is treated as unknown.
5. **Section-level restrictions** — propagated to all activities within the section.
6. **Teacher-hidden flag** — any activity hidden by a teacher is always blocked regardless
   of other conditions.

An activity is **accessible** only when all conditions on both its own entry and its
parent section evaluate to pass.

The **next step** is the set of accessible activities that the simulated learner has not
yet completed and that have no unsatisfied prerequisites.

### 5.4 Subplugin Adapters

Each `coursectrlmod_*` subplugin implements the `activity_adapter` interface defined in
`classes/local/contract/activity_adapter.php`. The interface requires:

- `component()` — returns the Moodle component name (e.g. `mod_assign`).
- `get_supported_fields()` — returns the list of date fields the adapter handles.
- `preview_action()` — builds a preview of what would change for given cmids and payload.
- `execute_action()` — applies the change and returns a result record.
- `export_state()` / `restore_state()` — capture and restore field values for rollback.
- `run_checks()` — runs activity-type-specific consistency checks.

Adapters are discovered automatically at runtime via Moodle's plugin registry. No
configuration is required after installation.

---

## 6. Troubleshooting

### "Text analysis not available" after a bulk shift

The text-analysis step calls the `local_coursectrl_get_text_hits` web service. If this
message appears, check the Moodle server error log for a PHP exception from this
external function. The most common causes are:

- A database connection error during the CM bulk-lookup phase.
- A permission error if the user lost the `local/coursectrl:view` capability between the
  shift and the text-scan step.

### Dates in the preview appear as `–` (dash)

A dash means the field's stored value is 0 (disabled/not set). The shift workflow skips
these fields; they are shown for transparency only. Enable the date field in the
activity's settings to make it available for shifting.

### Dependency Graph is empty or missing edges

The graph is built from the availability conditions stored in `course_modules.availability`.
If a course has no availability or completion conditions configured, the graph will show
nodes only, with no edges. This is expected — it means the course has no dependency
structure.

### A rolled-back batch did not restore all fields

Rollback restores the values captured in the snapshot at the time the original action was
executed. If you edited an activity's dates between the original action and the rollback
(e.g. manually in the activity settings), the manual changes are overwritten by the
rollback. Fields that were changed by means other than Course Control Hub are not
tracked and cannot be restored.

### Scheduled task not running

Check **Site administration → Server → Scheduled tasks** and confirm the
`purge_old_batches` task is enabled and its last run time is recent. If the task is
failing, check the Moodle cron log and server PHP error log for details.

### A subplugin activity type is not detected

If an activity type is not listed in the bulk action preview, either:

1. No `coursectrlmod_<modname>` adapter is installed for that type, or
2. The adapter is installed but not yet visible to Moodle — visit
   **Site administration → Notifications** to complete the installation.

Activities without an adapter can still be shifted at the availability-condition level.
