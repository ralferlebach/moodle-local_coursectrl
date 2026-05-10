MOODLE_ROOT := /var/www/html/moodle45_aliseadele
PLUGIN_PATH  := local/coursectrl
PHP          := php

.PHONY: all fix check fix-phpdoc lint-php lint-js lint-phpdoc lint-mustache

all: clear fix-phpdoc lint-php fix-lint-php lint-js lint-phpdoc lint-mustache
	@echo ""
	@echo "=== All checks complete. Review output above for errors. ==="

fix: clear fix-phpdoc fix-lint-php lint-js
	@echo ""
	@echo "=== All fixes complete. ==="

check: clear lint-php lint-js lint-phpdoc lint-mustache
	@echo ""
	@echo "=== All checks complete. Review output above for errors. ==="

clear:
	clear

fix-phpdoc:
	@echo ""
	@echo "=== fix_phpdoc (auto-correct @param counts) ==="
	-$(PHP) $(MOODLE_ROOT)/$(PLUGIN_PATH)/tools/fix_phpdoc.php \
	    $(MOODLE_ROOT)/$(PLUGIN_PATH)

lint-php:
	@echo ""
	@echo "=== phpcs (reads phpcs.xml, excludes tools/) ==="
	-cd $(MOODLE_ROOT)/$(PLUGIN_PATH) && phpcs --standard=moodle --extensions=php --severity=1 --no-cache .

fix-lint-php:
	@echo ""
	@echo "=== phpcbf (reads phpcs.xml, excludes tools/) ==="
	-cd $(MOODLE_ROOT)/$(PLUGIN_PATH) && phpcbf .

lint-js:
	@echo ""
	@echo "=== ESLint ==="
	-npx grunt amd --force
	-cd $(MOODLE_ROOT) && npx grunt eslint --root=. \
	    --files=$(PLUGIN_PATH)/amd/src/

lint-phpdoc:
	@echo ""
	@echo "=== PHPDoc (local_moodlecheck, excludes tools/) ==="
	-cd $(MOODLE_ROOT) && php local/moodlecheck/cli/moodlecheck.php \
	    --path=$(PLUGIN_PATH) \
	    --exclude=$(PLUGIN_PATH)/tools \
	    --format=text 2>&1 | grep -B1 '    Line' | grep -v '^--$$' || true

lint-mustache:
	@echo ""
	@echo "=== Mustache syntax check ==="
	-$(PHP) $(MOODLE_ROOT)/$(PLUGIN_PATH)/tools/mustache_check.php \
	    $(MOODLE_ROOT)/$(PLUGIN_PATH)/templates 2>&1 | grep -v '^OK:' || true
