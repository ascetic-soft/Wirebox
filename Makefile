.PHONY: test fix stan check install

## Запуск всех проверок (fixer dry-run + phpstan + tests)
check: fix-dry stan test

## PHPUnit тесты
test:
	vendor/bin/phpunit

## PHP CS Fixer — исправить код
fix:
	vendor/bin/php-cs-fixer fix --verbose --diff

## PHP CS Fixer — проверка без изменений
fix-dry:
	vendor/bin/php-cs-fixer fix --dry-run --diff

## PHPStan level 9
stan:
	vendor/bin/phpstan analyse

## Установка зависимостей
install:
	composer install
