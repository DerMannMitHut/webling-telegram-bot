.PHONY: install run test format lint clean help

# Standardziel
help:
	@echo "Verfügbare Befehle:"
	@echo "  install  - Dependencies installieren"
	@echo "  run      - Bot ausführen"
	@echo "  test     - Tests ausführen"
	@echo "  format   - Code formatieren"
	@echo "  lint     - Code linting"
	@echo "  clean    - Cache aufräumen"
	@echo "  shell    - Poetry Shell starten"

# Dependencies installieren
install:
	poetry install

# Bot ausführen
run:
	poetry run python main.py

# Tests ausführen
test:
	poetry run pytest

# Code formatieren
format:
	poetry run black .
	poetry run isort .

# Code linting
lint:
	poetry run flake8 .
	poetry run mypy .

# Cache aufräumen
clean:
	poetry cache clear . --all
	find . -type d -name "__pycache__" -exec rm -rf {} +
	find . -type f -name "*.pyc" -delete

# Poetry Shell starten
shell:
	poetry env activate

# Dependencies aktualisieren
update:
	poetry update

# Neue Dependency hinzufügen
add:
	@read -p "Package name: " package; \
	poetry add $$package

# Dev Dependency hinzufügen
add-dev:
	@read -p "Package name: " package; \
	poetry add --group dev $$package 