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
    
    print("✅ VS Code configuration created!")


def setup_pycharm():
    """Creates PyCharm configuration file"""
    print("🔧 Creating PyCharm configuration...")
    
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
# Alternative: Install Poetry Plugin
# 1. File → Settings → Plugins
# 2. Search for "Poetry"
# 3. Install "Poetry" plugin
# 4. Restart PyCharm
"""
    
    with open(".pycharmrc", "w") as f:
        f.write(config)
    
    print("✅ PyCharm configuration created!")


def show_manual_steps():
    """Shows manual steps for other IDEs"""
    env_path = get_poetry_env_path()
    python_path = f"{env_path}/bin/python"
    
    print("\n📋 Manual steps for other IDEs:")
    print(f"Python Interpreter: {python_path}")
    print("\nFor Vim/Neovim:")
    print("1. Install coc-pyright or ALE")
    print("2. Set g:python3_host_prog to the path above")
    print("\nFor Emacs:")
    print("1. Install lsp-mode or elpy")
    print("2. Configure the Python interpreter path")


def main():
    """Main function"""
    print("🚀 Poetry IDE Setup")
    print("=" * 50)
    
    # Check if Poetry is installed
    if not run_command("poetry --version"):
        print("❌ Poetry is not installed!")
        print("Install Poetry with: curl -sSL https://install.python-poetry.org | python3 -")
        sys.exit(1)
    
    # Check if we're in a Poetry project
    if not Path("pyproject.toml").exists():
        print("❌ No Poetry project found!")
        print("Run 'poetry init' to create a new project.")
        sys.exit(1)
    
    # Install dependencies
    print("📦 Installing dependencies...")
    run_command("poetry install")
    
    # Setup IDEs
    setup_vscode()
    setup_pycharm()
    show_manual_steps()
    
    print("\n🎉 Setup completed!")
    print("\nNext steps:")
    print("1. Restart your IDE")
    print("2. Select the Python interpreter from the Poetry environment")
    print("3. The imports should now work!")


if __name__ == "__main__":
    main() 