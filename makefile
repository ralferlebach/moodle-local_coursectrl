MOODLE_ROOT := /var/www/html/moodle45_aliseadele
PLUGIN_PATH  := local/coursectrl

.PHONY: all lint-php lint-js lint-phpdoc lint-mustache

all: lint-php lint-js lint-phpdoc lint-mustache
	@echo ""
	@echo "=== All checks complete. Review output above for errors. ==="

lint-php:
	@echo ""
	@echo "=== phpcs ==="
	-cd $(MOODLE_ROOT)/$(PLUGIN_PATH) && phpcs --standard=moodle .

lint-js:
	@echo ""
	@echo "=== ESLint ==="
	-cd $(MOODLE_ROOT) && npx grunt eslint --root=. \
	    --files=$(PLUGIN_PATH)/amd/src/

lint-phpdoc:
	@echo ""
	@echo "=== PHPDoc (local_moodlecheck) ==="
	-cd $(MOODLE_ROOT) && php local/moodlecheck/cli/moodlecheck.php \
	    --path=$(PLUGIN_PATH) --format=text

lint-mustache:
	@echo ""
	@echo "=== Mustache syntax check ==="
	-php $(MOODLE_ROOT)/$(PLUGIN_PATH)/tools/mustache_check.php \
	    $(MOODLE_ROOT)/$(PLUGIN_PATH)/templates
