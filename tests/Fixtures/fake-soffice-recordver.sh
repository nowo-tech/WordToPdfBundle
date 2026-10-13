#!/bin/sh
# Copied into a temp dir by tests: records each invocation's arguments in ./args.log next to the script,
# creates a file inside the -env:UserInstallation profile, and fails while ./fail exists.
here=$(dirname "$0")
echo "$*" >> "$here/args.log"
for arg in "$@"; do
  case "$arg" in
    -env:UserInstallation=file://*)
      profile="${arg#-env:UserInstallation=file://}"
      mkdir -p "$profile/user" && : > "$profile/user/registrymodifications.xcu"
      ;;
  esac
done
if [ -f "$here/fail" ]; then
  exit 1
fi
echo "LibreOffice 24.8.1.2 40(Build:2)"
exit 0
