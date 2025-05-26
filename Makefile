.PHONY: composer-install shell format build-docker-image

IMAGE = composer-custom-directory-installer
DOCKER = docker run --rm -it -v "$(CURDIR)":/app -w /app

composer-install: build-docker-image
	$(DOCKER) $(IMAGE) composer install

shell:
	$(DOCKER) $(IMAGE) sh

format:
	$(DOCKER) $(IMAGE) vendor/bin/php-cs-fixer fix


build-docker-image:
	docker build -t $(IMAGE) .
