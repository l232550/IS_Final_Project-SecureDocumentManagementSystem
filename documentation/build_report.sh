#!/bin/bash
# Builds the Word report from the Markdown chapters. Requires pandoc + python-docx.  Usage: bash documentation/build_report.sh "Roll#01_Roll#02_Roll#03_IS_Project.docx"
set -e
cd "$(dirname "$0")"
OUT="${1:-Report.docx}"
python3 build_testing_report.py >/dev/null
TMP=$(mktemp -d)
n=0
for f in 00_introduction 01_research_answers 02_architecture 03_threat_model 04_database_storage 05_cryptographic_design 06_access_control 09_ddrs 08_security_testing 10_backup_recovery 12_limitations 13_references; do
  cat "$f.md" > "$TMP/$(printf '%02d' $n).md"; echo >> "$TMP/$(printf '%02d' $n).md"; n=$((n+1))
done
# Setup chapter taken from README (setup + operations)
{ echo "# Setup and Installation Instructions"; echo; sed -n '/^## Requirements/,/^## Project structure/p' ../README.md | sed '$d' | sed 's/^## /## /' ; } > "$TMP/90_setup.md"
mv "$TMP/90_setup.md" "$TMP/11_setup.md"
# strip hand-typed heading numbers (pandoc numbers headings automatically)
sed -i -E 's/^(##+) [0-9]+\. /\1 /' "$TMP"/*.md
# cross references: chapter numbers in the report
sed -i 's/(Chapter 9)/(Chapter 8)/' "$TMP"/*.md
sed -i 's/diagram in Chapter 2/diagram in Chapter 3/' "$TMP"/*.md
cat > "$TMP/meta.yaml" <<'YAML'
---
title: "Secure Document Management System"
subtitle: "CS3002 – Information Security – Semester Project Report"
author: "Roll#01, Roll#02, Roll#03 (replace with group members)"
date: "National University of Computer & Emerging Sciences, Chiniot-Faisalabad Campus – November 2026"
---
YAML
pandoc "$TMP/meta.yaml" $(ls "$TMP"/[0-9]*.md | sort) -o "$TMP/raw.docx" --toc --toc-depth=2 --number-sections --resource-path=. -f markdown+pipe_tables
python3 postprocess_docx.py "$TMP/raw.docx" "$OUT"
rm -rf "$TMP"; echo "Report written: $OUT"
