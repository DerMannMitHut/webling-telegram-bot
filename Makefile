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

# Run bot
run:
	poetry run python main.py

# Run tests
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

# Clean cache
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

# Add new dependency
add:
	@read -p "Package name: " package; \
	poetry add $$package

# Add dev dependency
add-dev:
	@read -p "Package name: " package; \
	poetry add --group dev $$package 