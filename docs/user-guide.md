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
   - 4.6 [Checks — Solutions Tab (Risk Assessment)](#46-checks--solutions-tab-risk-assessment)
   - 4.7 [Checks — Simulation Tab](#47-checks--simulation-tab)
   - 4.8 [Bulk Date Shift](#48-bulk-date-shift)
   - 4.9 [Text Review](#49-text-review)
   - 4.10 [History & Rollback](#410-history--rollback)
5. [Concepts](#5-concepts)
   - 5.1 [Risk Severity Levels](#51-risk-severity-levels)
   - 5.2 [How the Journey Simulation Works](#52-how-the-journey-simulation-works)
   - 5.3 [Subplugin Adapters](#53-subplugin-adapters)
6. [Troubleshooting](#6-troubleshooting)

---

## 1. Introduction

Course Control Hub gives teachers a single place to inspect, fix, and future-proof their
Moodle courses. It detects configuration problems that are otherwise invisible — date
inversions, circular completion dependencies, activities that can never be reached —
and provides bulk-editing tools to correct them efficiently.

**What it does not do.** The plugin does not change any data without an explicit preview
and confirmation step. It never acts automatically, autonomously, or on behalf of learners.
All bulk actions are logged and can be rolled back.

---

## 2. Installation

### Requirements

| Component | Minimum |
|---|---|
| Moodle | 4.5 |
| PHP | 8.2 |
| Database | MariaDB 10.6 · PostgreSQL 14 |

Tested: Moodle 4.5, 5.0, 5.1, 5.2 · PHP 8.2–8.4 · MariaDB 10.11 · PostgreSQL 16.

### Steps

1. Extract the archive into `<moodleroot>/local/coursectrl/`.
   The directory must be named exactly `coursectrl`.

2. Log in as administrator and navigate to
   **Site administration → Notifications**.
   Moodle will run the database installer automatically.

3. Assign capabilities (see [§ 3.1](#31-capabilities)).

4. Optionally install the companion **Course Control Hub Block**
   (`block_coursectrl`) to give teachers a visible entry point in the course.

### Upgrade

Replace the `local/coursectrl/` directory with the new version and visit
**Site administration → Notifications**.  
Database migrations are applied automatically. No manual SQL is required.

---

## 3. Administrator Reference

### 3.1 Capabilities

Navigate to **Site administration → Users → Permissions → Define roles** to assign
capabilities per role.

| Capability | Suggested default | Description |
|---|---|---|
| `local/coursectrl:view` | `editingteacher`, `manager` | Open the plugin and view all read-only pages |
| `local/coursectrl:bulkaction` | `editingteacher` | Execute bulk date shifts and text changes |
| `local/coursectrl:rollback` | `manager` | Roll back a previously executed bulk action |

> **Note.** A user who has `view` but not `bulkaction` can use all analysis features
> (Dashboard, Timeline, Graph, Checks, Simulation) but cannot modify course content.

### 3.2 Plugin Settings

Navigate to **Site administration → Plugins → Local plugins → Course Control Hub**.

#### Dashboard

| Setting | Default | Description |
|---|---|---|
| `dashboard_inventory` | `show` | Whether to display the full activity inventory on the Dashboard. `admin_only` restricts it to managers; `hide` removes it entirely. |
| `dashboard_upcoming_count` | `7` | Number of upcoming dates shown in the Cockpit tile. |
| `dashboard_warning_cap` | `0` | Maximum warnings shown in the problem summary (0 = auto). |
| `dashboard_textfind_count` | `0` | Maximum free-text date references shown (0 = auto). |

#### History

| Setting | Default | Description |
|---|---|---|
| `history_maxcount` | `100` | Maximum number of batch records retained per course. Older batches are removed by the scheduled task. |
| `history_maxdays` | `365` | Maximum age of batch records in days. |

#### Risk Assessment — Rules R0 to R7

Each rule has a **severity** selector: `off`, `notice`, `warning`, `error`.

| Rule | What it checks |
|---|---|
| **R0** | Activity dates outside the course start/end window; deadlines in the past |
| **R1** | Hidden activities with completion-tracking enabled |
| **R2** | Gap between activity deadline and `completionexpected` date |
| **R3** | Date inversions within an activity (e.g. open date after due date) |
| **R4** | Minimum gap between two sequenced dates (configurable threshold in days) |
| **R5** | Activities with availability conditions but no completion tracking |
| **R6** | Availability conditions referencing deleted or non-existent groups/groupings |
| **R7** | Adapter-specific checks (per activity type, configured per subplugin) |

#### Dynamic Journey Simulation

| Setting | Default | Description |
|---|---|---|
| `risk_min_activity_minutes` | `30` | Minutes assumed per activity when the simulation advances the clock. Used to evaluate time-based availability conditions. |
| `risk_max_group_combinations` | `32` | Maximum number of group-membership combinations simulated per run. Covers all combinations for up to 5 groups; larger courses may need a higher value (performance impact). |

### 3.3 Subplugins

Activity-type-specific logic is encapsulated in `coursectrlmod_*` subplugins
located in `local/coursectrl/mod/`. Each subplugin handles one Moodle activity
type and provides date field mappings, validation, and adapter-specific checks.

| Subplugin | Activity type |
|---|---|
| `coursectrlmod_assign` | Assignment |
| `coursectrlmod_quiz` | Quiz |
| `coursectrlmod_feedback` | Feedback |
| `coursectrlmod_forum` | Forum |
| `coursectrlmod_lesson` | Lesson |
| `coursectrlmod_page` | Page |
| `coursectrlmod_h5pactivity` | H5P Activity |
| `coursectrlmod_workshop` | Workshop |

A subplugin for an activity type that is not installed on the Moodle instance is
silently ignored. No settings for that adapter are shown in the admin panel.

To add support for a new activity type, create a new `coursectrlmod_*` subplugin
following the interface defined in
`classes/local/contract/activity_adapter.php`.

### 3.4 Scheduled Tasks

| Task class | Default schedule | Purpose |
|---|---|---|
| `purge_old_batches` | Daily at 03:00 | Removes batch records and their snapshots beyond the configured retention limits (`history_maxcount`, `history_maxdays`). |

Configure the schedule at **Site administration → Server → Scheduled tasks**.

### 3.5 Privacy & Data Retention

The plugin stores the following data in the Moodle database:

| Table | Contains | Linked to user |
|---|---|---|
| `local_coursectrl_batch` | Bulk action header records | Yes — `userid` |
| `local_coursectrl_batch_item` | Per-CM action results | Via batch |
| `local_coursectrl_snapshot` | Pre-action state for rollback | Via batch |
| `local_coursectrl_preset` | Saved action presets | Yes — `userid` |
| `local_coursectrl_risk` | Risk assessment results | No |
| `local_coursectrl_text_hit` | Detected date references in texts | No |

The plugin implements Moodle's Privacy API:

- **Export:** All batches, snapshots, and presets owned by a user are included in
  their GDPR data export.
- **Deletion:** Deleting a user's data removes all their batch and preset records.
  Risk and text-hit records are not personal and are unaffected.

Risk assessment results and text hit records contain no personal data and are
scoped to course context. They are purged when a new analysis run is triggered for
the same course.

---

## 4. Teacher Reference

### 4.1 Opening the Plugin

**Via the block:** If the **Course Control Hub Block** is added to your course,
click the block's link.

**Via the course navigation:** In the course view, look for **Course Control Hub**
in the course-level navigation.

**Direct URL:**
```
/local/coursectrl/index.php?courseid=<id>
```

You must be enrolled in the course with a role that has `local/coursectrl:view`.

---

### 4.2 Dashboard (Cockpit)

The Dashboard is the starting point. It shows a compact overview of your course
without running any deep analysis.

**Cockpit tiles** (top row):

| Tile | What it shows |
|---|---|
| Sections | Total number of course sections |
| Activities | Total number of course modules |
| Open problems | Errors + warnings found in the last scan |
| Dates in texts | Free-text fields containing date references |

**Problem summary.** Below the tiles, errors and warnings are listed with a link
directly to the affected activity and a one-click action button where applicable.

**Upcoming dates.** A chronological list of the next scheduled dates across all
activities. Click any date to jump to the Timeline.

**Calendar.** A monthly calendar that highlights days with scheduled dates.
Click a day to filter the Timeline to that day.

> **Tip.** The Dashboard does not run a full risk analysis automatically.
> Use **Checks → Solutions** to run the risk scanner.

---

### 4.3 Timeline & Gantt

Navigate to **Timeline** from the top navigation.

The Timeline lists all structured date fields across all activities in
chronological order. Each entry shows:

- Activity name and type icon
- Field name (e.g. "Due date", "Close date")
- Date and time
- A **Shift** button to move that single date

**Bulk shift.** Use the **Shift all dates** button at the top to open the
three-step bulk shift workflow:

1. **Configure.** Set the delta (days, hours, minutes), select which field types
   to include, and optionally skip weekends or public holidays.
2. **Preview.** Review old and new values for every affected field. Conflicts and
   warnings are highlighted.
3. **Confirm.** The changes are applied and logged as a batch. You can roll back
   from the History page.

**Gantt tab.** Switch to the Gantt view for a horizontal timeline showing
overlapping date windows across all activities.

**Filters.** Filter by activity type or date field type using the dropdowns at the top.

---

### 4.4 Dependency Graph

Navigate to **Graph** from the top navigation.

The graph shows all activities as nodes connected by completion-dependency edges.
An arrow from A to B means "B requires A to be completed before it becomes
accessible."

**Reading the graph:**

| Visual element | Meaning |
|---|---|
| Arrow A → B | B has a completion condition on A |
| Red node | Involved in a structural problem (circular dep or dead end) |
| Yellow node | Has a warning-level issue |
| Red arrow | Part of a circular dependency cycle |

**Group filter.** Use the group dropdown to restrict the graph to activities
accessible to a specific group.

**Simulation overlay.** After running a simulation (see [§ 4.7](#47-checks--simulation-tab)),
the graph highlights:

- **Red nodes** — activities blocked in the simulated state
- **Green nodes** — the next accessible but incomplete activities (suggested next steps)

---

### 4.5 Checks — Problems Tab

Navigate to **Checks** from the top navigation, then select the **Problems** tab.

Problems are live consistency checks that run every time you open the tab. They
do not persist to the database and are always current.

**What is checked:**

| Check category | Examples |
|---|---|
| Temporal conflicts | Due date before open date; gradingdue before duedate |
| Date/course-frame conflicts | Activity date after course end; deadline in the past |
| Dangling prerequisites | Completion condition referencing a non-existent or deleted activity |
| Impossible prerequisites | Completion condition on an activity with no completion tracking |
| Dangling groups/groupings | Availability condition referencing a deleted group |
| Adapter checks (R7) | Activity-type-specific rule violations |
| Completion date mismatch | `completionexpected` more than N days after the activity deadline |

Each problem row shows:

- **Severity icon** (❗ error / ⚠ warning / ℹ notice)
- **Activity name** with a link to the activity settings
- **What the problem is** and **what to do**
- A **Fix** button where a one-click correction is possible (e.g. open the
  activity settings, jump to the Timeline)
- A **▶ Play** button that opens the Simulation tab pre-loaded with the
  relevant date and state

---

### 4.6 Checks — Solutions Tab (Risk Assessment)

The Solutions tab runs a deeper, persistent risk analysis. Results are stored
and shown immediately on subsequent visits until you run a new scan.

**Running a scan.** Click **Run now**. The scanner may take a few seconds for
large courses. The timestamp of the last scan is shown next to the button.

**What the scanner checks in addition to the Problems tab:**

- Structural dead ends (activities no learner can ever reach)
- Circular dependency cycles
- Long dependency chains
- Completion conditions on hidden activities
- **Journey simulation** (see below)

**Journey simulation findings.** The scanner simulates learner journeys through
the course for every group-membership combination and for two grade scenarios
(best case: all activities passed; worst case: all grade-gated activities failed).
If an activity is unreachable in any scenario, a finding is generated.

Each finding shows:

- The affected activity and severity
- Whether the block occurs in the **best case** or **worst case** scenario
- **Show journey steps** — a collapsible list of every activity visited before
  the simulation stopped, with timestamps and outcomes (completed / passed /
  failed / failed – attempts exhausted)
- A **▶ Replay in Simulation** button that opens the Simulation tab with the
  exact group membership, dates, completion states, and grades that caused the block

> **Understanding the scenarios.**
> *Best case* means the simulation assumed every learner passes all grade-gated
> activities. If an activity is still unreachable, it is structurally blocked
> regardless of learner performance.
> *Worst case* means the simulation assumed every learner fails every grade-gated
> activity. Findings in this scenario indicate a conditional block that can be
> resolved by learners who do pass.

---

### 4.7 Checks — Simulation Tab

The Simulation tab lets you inspect the course from the perspective of a specific
learner state.

**Parameters:**

| Field | Description |
|---|---|
| Date / Time | The simulated point in time. All time-based availability conditions are evaluated as if it were this moment. |
| Groups | Which groups the hypothetical learner belongs to. |
| Completed activities | Activities the learner has already completed (tick the checkbox). |
| Passed activities | Activities the learner has passed (requires a passing grade). |
| Grade | A percentage grade for grade-gated activities. |

Click **Run simulation** to evaluate all activities. The result table shows:

| Column | Meaning |
|---|---|
| Activity | Name and type of the activity |
| Accessible | ✓ if the learner can open the activity at the given date/time |
| Reason | Why the activity is blocked (if applicable) |
| Status | Completed, passed, failed, or not yet attempted |
| Next step | ★ marks the recommended next activity (accessible and not yet completed) |

The simulation result also feeds into the **Dependency Graph overlay**
(see [§ 4.4](#44-dependency-graph)).

**Using simulation links from the Solutions tab.** When you click **▶ Replay in Simulation**
on a risk finding, all parameters are pre-filled. This lets you immediately reproduce
the exact scenario in which the activity was found to be unreachable.

---

### 4.8 Bulk Date Shift

Bulk date shifting is available from both the Timeline (all dates or filtered)
and from individual activity rows.

#### The three-step workflow

**Step 1 — Configure**

| Option | Description |
|---|---|
| Delta days / hours / minutes | Positive = move forward, negative = move backward |
| Field types | Which date fields to include (e.g. due dates only, all dates, …) |
| Activities | Limit to specific activities or apply to the whole course |
| Skip weekends | Move dates that would land on Saturday or Sunday to the next Monday |
| Skip holidays | Requires a calendar subplugin; moves dates landing on public holidays |

**Step 2 — Preview**

Every affected field is listed with its old and new value. Conflicts are
highlighted in amber. Entries that cannot be shifted (e.g. already at the
minimum/maximum allowed value) are shown as skipped with a reason.

Review the preview carefully before confirming. The preview does not modify
any data.

**Step 3 — Confirm**

Click **Apply** to execute the shift. The action is logged as a batch entry
(visible in History) and pre-action snapshots are stored for rollback.

---

### 4.9 Text Review

Many courses contain dates written in free text — in activity descriptions,
section summaries, labels, or the course description itself. The Text Review
tab finds these references and helps you update them when shifting the course.

Navigate to **Timeline → Text Review** tab.

**Detection levels:**

| Level | Meaning |
|---|---|
| Safe | Date format is unambiguous and includes a year; the rewriter can transform it reliably |
| Ambiguous | Date format is ambiguous (e.g. numeric month/day) or missing the year; manual review required |
| Informational | A date-like string was found but cannot be reliably extracted for editing; shown for awareness |

For each detected date reference, the table shows:

- Where it was found (activity, field)
- The detected text snippet
- The normalised date value
- Confidence level

Tick the checkboxes for the references you want to update, enter a date delta,
and click **Apply selected**. Ambiguous references require you to confirm the
intended date manually before the rewriter acts on them.

---

### 4.10 History & Rollback

Navigate to **History** from the top navigation.

The History page lists all bulk actions executed in this course, newest first.

Each batch entry shows:

- Date and time of execution
- Action type (e.g. shift\_dates)
- Number of affected activities
- Status (executed, partially executed, rolled back)
- A **Roll back** button (if the batch is eligible)

**Rolling back.** Click **Roll back** on a batch entry. The plugin restores the
pre-action state for each affected activity from the stored snapshot and marks
the batch as rolled back. Only users with `local/coursectrl:rollback` can
perform this action.

**Eligibility.** A batch can be rolled back if:

- It was fully executed (not just previewed)
- It has not already been rolled back
- The activity still exists in the course (deleted activities cannot be restored)

**Retention.** Batch records are kept according to the administrator's retention
settings (`history_maxcount`, `history_maxdays`). After the retention period,
rollback is no longer possible.

---

## 5. Concepts

### 5.1 Risk Severity Levels

| Level | Icon | What it means |
|---|---|---|
| **Error** | ❗ | The configuration is broken. Learners are definitively blocked or cannot complete the course. Immediate action required. |
| **Warning** | ⚠ | The configuration is suspicious and likely to cause confusion or partial blockage. Review recommended. |
| **Notice** | ℹ | A best-practice deviation. Not immediately harmful but worth noting. |

Severity levels for individual rules can be adjusted or disabled by an
administrator (see [§ 3.2](#32-plugin-settings)). A finding with severity
`error` is automatically escalated when the affected activity is required
for course completion.

---

### 5.2 How the Journey Simulation Works

The **Solutions tab** risk scanner includes a dynamic journey simulator that goes
beyond static structural analysis. It models how a real learner would progress
through the course.

**Algorithm:**

1. Start with all initially accessible activities (those with no conditions, or
   whose conditions are met at the simulation start time with no completed activities).
2. "Complete" the first accessible activity. The simulated clock advances by the
   configured minimum activity duration (default: 30 minutes).
3. Re-evaluate all remaining activities with the updated completion state and time.
   Newly accessible activities are added to the queue.
4. Repeat until no more activities become accessible.
5. Activities never added to the queue are **unreachable**.

**Scenarios.** The simulation runs this BFS twice per group combination:

- **All-pass:** Every grade-gated activity is completed with a passing grade.
  Unreachable activities in this scenario are structurally blocked, regardless
  of learner ability.
- **All-fail:** Every grade-gated activity is completed with a failing grade.
  Additional unreachable activities in this scenario are conditionally blocked
  (learners who fail can no longer progress past a certain point).

**Group combinations.** The simulation runs once per unique combination of group
memberships. For a course with 3 groups, there are 8 combinations (2³). The
administrator can limit the maximum number of combinations to control
performance.

**Trial limits.** For activities with a maximum number of allowed attempts
(Quiz, Lesson), the simulation marks an activity as "attempts exhausted" in the
all-fail scenario. This is reflected in the journey step log.

---

### 5.3 Subplugin Adapters

Each `coursectrlmod_*` subplugin provides:

- **Field map** — which database columns represent date fields, and in what order
  they must occur (e.g. for Assignment: `allowsubmissionsfromdate` < `duedate`
  < `cutoffdate` < `gradingduedate`)
- **Validation** — what payloads are acceptable for each action
- **Preview** — how proposed changes look before execution
- **Execution** — how to apply the change to the database
- **Snapshot / restore** — how to capture and restore the pre-change state
- **Checks** — adapter-specific rule violations (R7)

If a subplugin for a given activity type is not installed, that activity type
is silently omitted from all bulk actions and adapter-specific checks. Its dates
can still be shifted if the date fields are registered in the generic field map.

---

## 6. Troubleshooting

### The plugin is not visible in a course

1. Confirm the plugin is installed: **Site administration → Plugins → Local plugins**.
2. Check that the user's role has `local/coursectrl:view`.
3. If using the block: confirm the block is added to the course and is visible.

### The Timeline or Graph shows no data

The course must have at least one activity with structured date fields (assign,
quiz, etc.) for Timeline data to appear. The Graph requires at least one activity
with a completion condition.

### The Risk Scanner takes a very long time

For courses with many groups, the journey simulation tries up to
`risk_max_group_combinations` combinations. Reduce this value in
**Site administration → Course Control Hub → Dynamic journey simulation**
to speed up the scan.

### A rollback failed partially

If a rollback reports that some activities could not be restored, the most
common cause is that an activity was deleted after the batch was executed.
Deleted activities cannot be restored. The successfully restored activities
are listed in the rollback result.

### "Subplugin not found" errors in the Moodle upgrade log

This usually means a `coursectrlmod_*` directory exists but the subplugin was
not correctly registered. Ensure `db/subplugins.json` contains both the
`subplugintypes` and `plugintypes` keys (both are required as of MDL-83705).

### PHPCS or CI failures after extending the plugin

Follow the coding standards documented in `docs/coding-standards-prompt.md`.
The most common pitfalls are:

- Multi-line function calls must have one argument per continuation line
- Class properties must not contain underscores
- Every class constant needs its own docblock
- Inline comments must start with a capital letter

---

*Course Control Hub is free software: you can redistribute it and/or modify it under the
terms of the GNU General Public License version 3 or later.*

*Copyright 2026 Ralf Erlebach.*
