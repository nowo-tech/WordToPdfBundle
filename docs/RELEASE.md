# Release process

1. Update [CHANGELOG.md](CHANGELOG.md): move entries from `[Unreleased]` to a new `[X.Y.Z] - YYYY-MM-DD` section. (This project does not store version in `composer.json`; Packagist uses the git tag.)
2. Update [UPGRADING.md](UPGRADING.md) if the release has upgrade notes.
3. Update README badges / coverage notes if needed.
4. Run pre-release checks: `make release-check` (includes `check-no-cursor-coauthor`, `composer-sync`, cs-fix, cs-check, rector-dry, phpstan, test-coverage, and optionally demo healthchecks).
5. Commit all changes, create an annotated tag (e.g. `v1.0.0`), and push branch and tag. The [release workflow](../.github/workflows/release.yml) creates the GitHub Release from the tag message and changelog section.
6. Publish / verify Packagist (usually automatic via webhook when the tag is pushed; first release may need a one-time Packagist submit).

After creating the release commit and tag, run `make check-no-cursor-coauthor` again **before** `git push` (REQ-GIT-001). The release commit itself is not covered by an earlier `release-check` run.

## Table of contents

- [Example for v1.2.2](#example-for-v122)
- [Example for v1.2.1](#example-for-v121)
- [Example for v1.2.0](#example-for-v120)
- [Example for v1.1.1](#example-for-v111)
- [Example for v1.1.0](#example-for-v110)
- [Example for v1.0.0](#example-for-v100)
- [Sync missing releases](#sync-missing-releases)

## Example for v1.2.2

```bash
git add -A
git status   # review
make release-check
git -c core.hooksPath=.githooks commit -m "$(cat <<'EOF'
Release 1.2.2: FrankenPHP banner, release-check gates, LibreOffice locator PHPStan.

EOF
)"
git tag -a v1.2.2 -m "Release v1.2.2"
make check-no-cursor-coauthor
git push origin main
git push origin v1.2.2
```

## Example for v1.2.1

```bash
git add -A
git status   # review
make release-check
git commit -m "$(cat <<'EOF'
Release 1.2.1: fix PHP 8.5 finfo_close() deprecation in mime checks.

EOF
)"
git tag -a v1.2.1 -m "Release v1.2.1"
make check-no-cursor-coauthor
git push origin main
git push origin v1.2.1
```

## Example for v1.2.0

```bash
git add -A
git status   # review
make release-check
git commit -m "$(cat <<'EOF'
Release 1.2.0: convertMany + PdfNaming, PROCESS_TIMEOUT 180, demo multi-upload.

EOF
)"
git tag -a v1.2.0 -m "Release v1.2.0"
make check-no-cursor-coauthor
git push origin main
git push origin v1.2.0
```

## Example for v1.1.1

```bash
git add -A
git status   # review
make release-check
git commit -m "$(cat <<'EOF'
Release 1.1.1: sync demo composer.lock to PHP 8.5 platform.

EOF
)"
git tag -a v1.1.1 -m "Release v1.1.1"
make check-no-cursor-coauthor
git push origin main
git push origin v1.1.1
```

## Example for v1.1.0

```bash
git add -A
git status   # review
make release-check
git commit -m "$(cat <<'EOF'
Release 1.1.0: FrankenPHP-safe timeouts, demo worker mode, Spec Kit.

EOF
)"
git tag -a v1.1.0 -m "Release v1.1.0"
make check-no-cursor-coauthor
git push origin main
git push origin v1.1.0
```

## Example for v1.0.0

```bash
git add -A
git status   # review
make release-check
git commit -m "$(cat <<'EOF'
Release 1.0.0: initial stable WordToPdfBundle.

EOF
)"
git tag -a v1.0.0 -m "Release v1.0.0"
make check-no-cursor-coauthor
git push origin main
git push origin v1.0.0
```

## Sync missing releases

See [GITHUB_CI.md](GITHUB_CI.md) and the `sync-releases.yml` workflow if a tag exists without a GitHub Release.
