#!/usr/bin/env bash

set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
release_dir="${1:-"$project_root/dist/yopay"}"
archive_path="${2:-"$project_root/dist/yopay-release.zip"}"

if [[ ! -f "$project_root/vendor/autoload.php" ]]; then
    echo "vendor/autoload.php 不存在，请先执行 composer install --no-dev --optimize-autoloader。" >&2
    exit 1
fi

if [[ ! -f "$project_root/public/build/manifest.json" ]]; then
    echo "public/build/manifest.json 不存在，请先完成前端构建。" >&2
    exit 1
fi

rm -rf "$release_dir"
mkdir -p "$release_dir" "$(dirname "$archive_path")"

rsync -a \
    --exclude='/.git/' \
    --exclude='/.github/' \
    --exclude='/.agents/' \
    --exclude='/.editorconfig' \
    --exclude='/.gitattributes' \
    --exclude='/.gitignore' \
    --exclude='/.npmrc' \
    --exclude='/.env' \
    --exclude='/.env.backup' \
    --exclude='/.env.production' \
    --exclude='/AGENTS.md' \
    --exclude='/FRONTEND_RULES.md' \
    --exclude='/dist/' \
    --exclude='/docs/' \
    --exclude='/node_modules/' \
    --exclude='/package.json' \
    --exclude='/pnpm-lock.yaml' \
    --exclude='/resources/css/' \
    --exclude='/resources/js/' \
    --exclude='/scripts/' \
    --exclude='/tests-js/' \
    --exclude='/tests/' \
    --exclude='/vite.config.js' \
    --exclude='/public/hot' \
    --exclude='/public/storage' \
    --exclude='/bootstrap/cache/*.php' \
    --exclude='/storage/*.key' \
    --exclude='/storage/app/private/installed.lock' \
    --exclude='/storage/logs/*.log' \
    "$project_root/" "$release_dir/"

rm -f "$archive_path"
(
    cd "$(dirname "$release_dir")"
    zip -qr "$archive_path" "$(basename "$release_dir")"
)

echo "发布包已生成：$archive_path"
