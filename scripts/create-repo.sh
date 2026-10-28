#!/usr/bin/env bash
# Create the public GitHub repo for Plogins Assets and push main.
# Run once, from the plugin dir. Force-push is not used; this is a first push.
set -euo pipefail

cd "$(dirname "$0")/.."

REPO="wppoland/plogins-assets"
DESC="Conditional script & style loading for WordPress - dequeue assets per page for a lighter, faster front end."
HOME_URL="https://plogins.com/pagelean/"

# Create the repo (public) if it does not exist yet.
if gh repo view "$REPO" >/dev/null 2>&1; then
  echo "repo $REPO already exists - skipping create"
else
  gh repo create "$REPO" --public --description "$DESC" --homepage "$HOME_URL" --disable-wiki
fi

git remote get-url origin >/dev/null 2>&1 || git remote add origin "git@github.com:${REPO}.git"
git branch -M main
git push -u origin main

# Backlink for SEO (repo homepage -> plugin page).
gh repo edit "$REPO" --homepage "$HOME_URL" || true

echo "✓ pushed $REPO"
