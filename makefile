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
# all: auto-fix everything, then re-run full check suite
# All steps run regardless of individual failures.
# ---------------------------------------------------------------------------
all: clear fix check
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
check: clear lint-php lint-phpdoc lint-js lint-mustache lint-gherkin amd phpunit
	@echo ""
	@echo "=== All checks complete. Review output above for errors. ==="

# ---------------------------------------------------------------------------
# clear: clear screen
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
		--format=text 2>&1 | grep -B1 '    Line' | grep -v '^--$$' || true

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
# amd: rebuild AMD files for this plugin only.
# find builds a comma-separated list of actual .js file paths so rollup
# receives entry points, not a directory (which would cause E_RESOLVE).
# ---------------------------------------------------------------------------
amd:
	@echo "=== Rebuilding AMD (plugin only, grunt amd --files) ==="
	-cd $(MOODLE_ROOT) && files=$$(find local/coursectrl/amd/src -name '*.js' \
	    | tr '\n' ',' | sed 's/,$$//'); \
	    $(NPX) grunt amd --root=. --force --files="$$files"

# ---------------------------------------------------------------------------
# phpunit: run PHPUnit testsuite for this plugin
#
# One-time setup required — add to config.php, then run init:
#   $CFG->phpunit_dataroot = '/var/www/html/moodle45_aliseadele/phpunit_data';
#   $CFG->phpunit_prefix   = 'phpu_';
#   php admin/tool/phpunit/cli/init.php
# ---------------------------------------------------------------------------
phpunit:
	@echo "=== PHPUnit ==="
	@if ! $(PHP) -r "define('CLI_SCRIPT',1); require '$(MOODLE_ROOT)/config.php'; "\
		"exit(empty(\$$CFG->phpunit_dataroot) ? 1 : 0);" 2>/dev/null; then \
		echo "SKIP: phpunit_dataroot not set in config.php."; \
		echo "      Add to config.php and run: php admin/tool/phpunit/cli/init.php"; \
	else \
		cd $(MOODLE_ROOT) && $(PHP) vendor/bin/phpunit \
			--testsuite local_coursectrl_testsuite \
			--testdox 2>&1 | grep -vE '^ ✔ |^$$' || true; \
	fi
