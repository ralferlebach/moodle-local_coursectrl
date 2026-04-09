# Subplugin directory – coursectrlmod_*

This directory contains activity-type adapter subplugins.
Each subdirectory follows the naming scheme: `coursectrlmod_{modname}`

The subplugin type is registered as `coursectrlmod` (no underscore)
in `db/subplugins.json`. Moodle requires subplugin type names to be
lowercase alphanumeric without underscores.

First adapters scheduled for Phase 3:
- `coursectrlmod_assign`
- `coursectrlmod_quiz`
- `coursectrlmod_feedback`
