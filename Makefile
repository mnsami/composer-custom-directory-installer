.PHONY: composer-install shell format build-docker-image

IMAGE = composer-custom-directory-installer
DOCKER = docker run --rm -it -v "$(PWD)":/app -w /app

composer-install:
	$(DOCKER) $(IMAGE) composer install

shell:
	$(DOCKER) $(IMAGE) sh

format:
	$(DOCKER) $(IMAGE) vendor/bin/php-cs-fixer fix


build-docker-image:
	docker build -t $(IMAGE) .
