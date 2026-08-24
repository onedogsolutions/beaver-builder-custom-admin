#!/usr/bin/env bash
#
# Packages the plugin for distribution or testing.
#
# Produces dist/beaver-builder-custom-admin-<version>.zip containing a
# single top-level beaver-builder-custom-admin/ directory, so the archive
# can be uploaded directly via Plugins → Add New → Upload Plugin.
#
# Runtime files only: source, build tooling, and STATE.md are excluded.
# Run `npm run build` first if src/ has changed — this script packages
# build/ as it stands and does not compile.

set -euo pipefail

SLUG="beaver-builder-custom-admin"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST="${ROOT}/dist"
STAGE="${DIST}/${SLUG}"

cd "${ROOT}"

VERSION="$(sed -n "s/^define( 'BBCA_VER', '\(.*\)' );/\1/p" "${SLUG}.php")"

if [ -z "${VERSION}" ]; then
	echo "error: could not read BBCA_VER from ${SLUG}.php" >&2
	exit 1
fi

if [ ! -f "build/index.js" ] || [ ! -f "build/index.asset.php" ]; then
	echo "error: build/ is missing or incomplete — run 'npm run build' first" >&2
	exit 1
fi

rm -rf "${STAGE}"
mkdir -p "${STAGE}"

# Runtime files only.
cp "${SLUG}.php" readme.txt LICENSE "${STAGE}/"
cp -R assets build classes includes "${STAGE}/"

# Strip anything that should never ship, in case it was left in a copied tree.
find "${STAGE}" \( -name '.DS_Store' -o -name '*.map' -o -name '*.log' \) -delete

ARCHIVE="${DIST}/${SLUG}-${VERSION}.zip"
rm -f "${ARCHIVE}"

( cd "${DIST}" && zip -rq "${ARCHIVE}" "${SLUG}" -x '*.DS_Store' )

rm -rf "${STAGE}"

echo "Packaged ${SLUG} ${VERSION}"
echo "  ${ARCHIVE}"
echo "  $(unzip -l "${ARCHIVE}" | tail -1 | awk '{print $2}') files, $(du -h "${ARCHIVE}" | cut -f1)"
