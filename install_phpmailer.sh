#!/bin/bash

set -e

DEST_DIR="webhooks/phpmailer"
PHPMailer_REPO="https://github.com/PHPMailer/PHPMailer.git"
PHPMailer_VERSION="v6.9.1"

if [ -d "$DEST_DIR" ]; then
    echo "Removing existing $DEST_DIR"
    rm -rf "$DEST_DIR"
fi

echo "Cloning PHPMailer $PHPMailer_VERSION into $DEST_DIR..."
git clone --depth 1 --branch "$PHPMailer_VERSION" "$PHPMailer_REPO" "$DEST_DIR"

echo "Cleaning up unnecessary files..."
cd "$DEST_DIR"
rm -rf .git docs test composer* .github changelog.md extras CONTRIBUTING.md .gitattributes .gitignore SECURITY.md CODE_OF_CONDUCT.md

echo "PHPMailer installed in $DEST_DIR"
