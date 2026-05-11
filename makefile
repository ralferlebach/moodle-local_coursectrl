# Makefile for local_coursectrl
# Mirrors the moodle-plugin-ci check suite used in GitHub Actions.
# All checks run to completion even if individual steps report errors.
#
# Targets:
#   make all          — fix + full check suite (default)
#   make fix          — auto-fix PHP style, PHPDoc, rebuild AMD
#   make check        — check-only (no auto-fix)
#   make clear        — clear terminal
#
# Individual checks (all continue on error):
#   make lint-php       — PHP CodeSniffer
#   make lint-phpdoc    — Moodle PHPDoc checker (CI-equivalent, no || true)
#   make lint-js        — ESLint on AMD source files
#   make lint-gherkin   — Gherkin feature-file lint
#   make lint-mustache  — Mustache template syntax
#
# Auto-fixers:
#   make fix-lint-php   — phpcbf PHP code-style auto-fix
#   make fix-phpdoc     — moodlecheck PHPDoc report
#   make amd            — rebuild AMD minified files
#
# Tests:
#   make phpunit        — PHPUnit testsuite for this plugin

MOODLE_ROOT   ?= /var/www/html/moodle45_aliseadele
PLUGIN_DIR    ?= $(MOODLE_ROOT)/local/coursectrl
PHP           ?= /usr/bin/php
PHPCS         ?= phpcs
PHPCBF        ?= phpcbf
NPX           ?= npx

.PHONY: all fix check clear \
        lint-php lint-phpdoc lint-js lint-gherkin lint-mustache \
        fix-lint-php fix-phpdoc amd phpunit

# ---------------------------------------------------------------------------
# all: auto-fix everything, then run full check suite
# All steps run regardless of individual failures.
# ---------------------------------------------------------------------------
all: clear fix-phpdoc lint-php fix-lint-php lint-js lint-phpdoc lint-mustache lint-gherkin
	@echo ""
	@echo "=== All checks complete. Review output above for errors. ==="

# ---------------------------------------------------------------------------
# fix: run all auto-fixers (PHP style, PHPDoc, AMD rebuild)
# ---------------------------------------------------------------------------
fix: clear fix-phpdoc fix-lint-php amd lint-js
	@echo ""
	@echo "=== All fixes complete. ==="

# ---------------------------------------------------------------------------
# check: check-only run (no auto-fix)
# All checks run even if individual ones fail.
# ---------------------------------------------------------------------------
check: clear lint-php lint-js lint-phpdoc lint-mustache lint-gherkin
	@echo ""
	@echo "=== All checks complete. Review output above for errors. ==="

# ---------------------------------------------------------------------------
# clear
# ---------------------------------------------------------------------------
clear:
	clear

# ---------------------------------------------------------------------------
# lint-php: PHP CodeSniffer — severity 1
# ---------------------------------------------------------------------------
lint-php:
	@echo "=== phpcs (reads phpcs.xml, excludes tools/) ==="
	-cd $(PLUGIN_DIR) && $(PHPCS) \
		--standard=moodle \
		--extensions=php \
		--severity=1 \
		--no-cache \
		.

# ---------------------------------------------------------------------------
# fix-lint-php: phpcbf auto-fix
# ---------------------------------------------------------------------------
fix-lint-php:
	@echo "=== phpcbf (auto-fix) ==="
	-cd $(PLUGIN_DIR) && $(PHPCBF) \
		--standard=moodle \
		--extensions=php \
		.

# ---------------------------------------------------------------------------
# lint-phpdoc: Moodle PHPDoc checker
# Shows errors (no || true suppression) but continues the build.
# ---------------------------------------------------------------------------
lint-phpdoc:
	@echo "=== PHPDoc (local_moodlecheck, excludes tools/) ==="
	-cd $(MOODLE_ROOT) && $(PHP) local/moodlecheck/cli/moodlecheck.php \
		--path=local/coursectrl \
		--exclude=local/coursectrl/tools \
		--format=text 2>&1 | grep -B1 '    Line' | grep -v '^--$$' ; \
	cd $(MOODLE_ROOT) && $(PHP) local/moodlecheck/cli/moodlecheck.php \
		--path=local/coursectrl \
		--exclude=local/coursectrl/tools \
		--format=text > /dev/null

# ---------------------------------------------------------------------------
# fix-phpdoc: tools/fix_phpdoc.php — auto-fixes PHPDoc in plugin source
# ---------------------------------------------------------------------------
fix-phpdoc:
	@echo "=== fix_phpdoc (tools/fix_phpdoc.php) ==="
	-$(PHP) $(PLUGIN_DIR)/tools/fix_phpdoc.php $(PLUGIN_DIR)

# ---------------------------------------------------------------------------
# lint-mustache: Mustache template syntax
# ---------------------------------------------------------------------------
lint-mustache:
	@echo "=== Mustache syntax check ==="
	-$(PHP) $(PLUGIN_DIR)/tools/mustache_check.php \
		$(PLUGIN_DIR)/templates 2>&1 | grep -v '^OK:' || true

# ---------------------------------------------------------------------------
# lint-js: ESLint on AMD source (0 warnings = CI standard)
# ---------------------------------------------------------------------------
lint-js:
	@echo "=== ESLint ==="
	-cd $(MOODLE_ROOT) && $(NPX) grunt eslint --root=. \
		--files=local/coursectrl/amd/src/ \
		--show-lint-warnings

# ---------------------------------------------------------------------------
# lint-gherkin: Gherkin feature-file lint
# ---------------------------------------------------------------------------
lint-gherkin:
	@echo "=== Gherkin lint ==="
	-cd $(MOODLE_ROOT) && $(NPX) grunt gherkinlint --root=.

# ---------------------------------------------------------------------------
# amd: rebuild minified AMD files
# ---------------------------------------------------------------------------
amd:
	@echo "=== Rebuilding AMD (grunt amd) ==="
	cd $(MOODLE_ROOT) && $(NPX) grunt amd --root=. 2>/dev/null || \
	( echo "grunt unavailable — falling back to terser for shift_workflow" && \
	  cd $(PLUGIN_DIR) && terser amd/src/shift_workflow.js \
	      --compress passes=2 --mangle \
	      --source-map "filename=amd/build/shift_workflow.min.js.map,url=shift_workflow.min.js.map" \
	      --output amd/build/shift_workflow.min.js )

# ---------------------------------------------------------------------------
# phpunit: run PHPUnit testsuite for this plugin
# ---------------------------------------------------------------------------
phpunit:
	@echo "=== PHPUnit ==="
	-cd $(MOODLE_ROOT) && $(PHP) vendor/bin/phpunit \
		--testsuite local_coursectrl_testsuite \
		--testdox
