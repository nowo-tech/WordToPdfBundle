#!/bin/sh
# Fake LibreOffice soffice for unit tests: creates a PDF in --outdir.
set -e
outdir=""
input=""
while [ "$#" -gt 0 ]; do
  case "$1" in
    --outdir)
      outdir="$2"
      shift 2
      ;;
    --version)
      echo "LibreOffice 24.2.0.0 40(Build:1)"
      exit 0
      ;;
    --headless|--nologo|--nolockcheck|--nodefault|--nofirststartwizard|--convert-to)
      # skip flag; convert-to takes next arg
      if [ "$1" = "--convert-to" ]; then
        shift 2
      else
        shift
      fi
      ;;
    -env:UserInstallation=*)
      shift
      ;;
    *)
      input="$1"
      shift
      ;;
  esac
done

if [ -z "$outdir" ] || [ -z "$input" ]; then
  echo "fake soffice: missing outdir or input" >&2
  exit 1
fi

base=$(basename "$input")
name="${base%.*}"
mkdir -p "$outdir"
printf '%%PDF-1.4\nfake\n' > "$outdir/${name}.pdf"
exit 0
