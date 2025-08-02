#!/usr/bin/env python3
"""
IDE Setup Script für Poetry-Umgebung
Führt automatisch die notwendigen Schritte aus, um die Poetry-Umgebung
in verschiedenen IDEs zu konfigurieren.
"""

import os
import subprocess
import sys
from pathlib import Path


def run_command(cmd: str) -> str:
    """Führt einen Befehl aus und gibt die Ausgabe zurück"""
    try:
        result = subprocess.run(
            cmd, shell=True, capture_output=True, text=True, check=True
        )
        return result.stdout.strip()
    except subprocess.CalledProcessError as e:
        print(f"Fehler beim Ausführen von '{cmd}': {e}")
        return ""


def get_poetry_env_path() -> str:
    """Ermittelt den Pfad zur Poetry-Umgebung"""
    return run_command("poetry env info --path")


def setup_vscode():
    """Konfiguriert VS Code für Poetry"""
    print("🔧 Konfiguriere VS Code...")
    
    # Erstelle .vscode Verzeichnis
    vscode_dir = Path(".vscode")
    vscode_dir.mkdir(exist_ok=True)
    
    # Python-Interpreter-Pfad
    env_path = get_poetry_env_path()
    python_path = f"{env_path}/bin/python"
    
    settings = {
        "python.defaultInterpreterPath": python_path,
        "python.terminal.activateEnvironment": True,
        "python.analysis.extraPaths": [f"{env_path}/lib/python3.13/site-packages"],
        "python.linting.enabled": True,
        "python.linting.flake8Enabled": True,
        "python.linting.mypyEnabled": True,
        "python.formatting.provider": "black",
        "python.sortImports.args": ["--profile", "black"],
        "editor.formatOnSave": True,
        "editor.codeActionsOnSave": {
            "source.organizeImports": True
        }
    }
    
    import json
    with open(vscode_dir / "settings.json", "w") as f:
        json.dump(settings, f, indent=4)
    
    print("✅ VS Code Konfiguration erstellt!")


def setup_pycharm():
    """Erstellt PyCharm-Konfigurationsdatei"""
    print("🔧 Erstelle PyCharm-Konfiguration...")
    
    env_path = get_poetry_env_path()
    python_path = f"{env_path}/bin/python"
    
    config = f"""# PyCharm Poetry Integration
# 
# In PyCharm:
# 1. File → Settings → Project → Python Interpreter
# 2. Add Interpreter → Existing Environment
# 3. Interpreter: {python_path}
# 4. Apply and OK
#
# Alternative: Poetry Plugin installieren
# 1. File → Settings → Plugins
# 2. Search for "Poetry"
# 3. Install "Poetry" plugin
# 4. Restart PyCharm
"""
    
    with open(".pycharmrc", "w") as f:
        f.write(config)
    
    print("✅ PyCharm-Konfiguration erstellt!")


def show_manual_steps():
    """Zeigt manuelle Schritte für andere IDEs"""
    env_path = get_poetry_env_path()
    python_path = f"{env_path}/bin/python"
    
    print("\n📋 Manuelle Schritte für andere IDEs:")
    print(f"Python-Interpreter: {python_path}")
    print("\nFür Vim/Neovim:")
    print("1. Installiere coc-pyright oder ALE")
    print("2. Setze g:python3_host_prog auf den obigen Pfad")
    print("\nFür Emacs:")
    print("1. Installiere lsp-mode oder elpy")
    print("2. Konfiguriere den Python-Interpreter-Pfad")


def main():
    """Hauptfunktion"""
    print("🚀 Poetry IDE Setup")
    print("=" * 50)
    
    # Prüfe ob Poetry installiert ist
    if not run_command("poetry --version"):
        print("❌ Poetry ist nicht installiert!")
        print("Installiere Poetry mit: curl -sSL https://install.python-poetry.org | python3 -")
        sys.exit(1)
    
    # Prüfe ob wir in einem Poetry-Projekt sind
    if not Path("pyproject.toml").exists():
        print("❌ Kein Poetry-Projekt gefunden!")
        print("Führe 'poetry init' aus, um ein neues Projekt zu erstellen.")
        sys.exit(1)
    
    # Installiere Dependencies
    print("📦 Installiere Dependencies...")
    run_command("poetry install")
    
    # Setup IDEs
    setup_vscode()
    setup_pycharm()
    show_manual_steps()
    
    print("\n🎉 Setup abgeschlossen!")
    print("\nNächste Schritte:")
    print("1. Starte deine IDE neu")
    print("2. Wähle den Python-Interpreter aus der Poetry-Umgebung")
    print("3. Die Imports sollten jetzt funktionieren!")


if __name__ == "__main__":
    main() 