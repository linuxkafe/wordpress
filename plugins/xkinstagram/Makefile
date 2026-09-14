.PHONY: setup test lint format check doctor metrics clean

setup:
	php composer.phar install --no-interaction

test:
	vendor/bin/phpunit --testdox

lint:
	find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \;

format:
	@echo "PHP formatting not configured (use PHP-CS-Fixer if needed)"

check: test lint

doctor:
	@echo "PHP: $$(php -r 'echo PHP_VERSION;')"
	@echo "Composer: $$(php composer.phar --version 2>/dev/null || echo 'not installed')"
	@echo "PHPUnit: $$(vendor/bin/phpunit --version 2>/dev/null || echo 'not installed')"

metrics:
	@echo "Lines of code (src): $$(find src -name '*.php' -exec wc -l {} + | tail -1 | awk '{print $$1}')"
	@echo "Test count: $$(vendor/bin/phpunit --list-tests 2>/dev/null | grep -c '^.*Test' || echo 0)"

clean:
	rm -rf vendor/ composer.phar composer.lock