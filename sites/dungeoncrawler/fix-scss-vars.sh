#!/bin/bash
# Fix all $forseti-* variable references in SCSS component files
SCSS_DIR="/home/keithaumiller/forseti.life/sites/dungeoncrawler/web/themes/custom/dungeoncrawler/src/scss"

find "$SCSS_DIR" -name "*.scss" ! -name "_variables.scss" -print0 | xargs -0 sed -i \
  -e 's/\$forseti-guardian-blue/$dc-mystic-purple/g' \
  -e 's/\$forseti-safety-green/$dc-health-green/g' \
  -e 's/\$forseti-alert-amber/$dc-torch-gold/g' \
  -e 's/\$forseti-danger-red/$dc-blood-red/g' \
  -e 's/\$forseti-calm-slate/$dc-shadow-slate/g' \
  -e 's/\$forseti-sky-light/$dc-parchment/g' \
  -e 's/\$forseti-forest-dark/$dc-deep-cavern/g' \
  -e 's/\$forseti-white-pure/$dc-bone-white/g' \
  -e 's/\$forseti-night-dark/$dc-abyss-dark/g' \
  -e 's/\$forseti-info-blue/$dc-mana-blue/g' \
  -e 's/\$forseti-success-green/$dc-health-green/g' \
  -e 's/\$forseti-warning-orange/$dc-fire-orange/g' \
  -e 's/\$forseti-error-red/$dc-poison-red/g' \
  -e 's/\$forseti-text-primary/$dc-text-primary/g' \
  -e 's/\$forseti-text-secondary/$dc-text-secondary/g' \
  -e 's/\$forseti-text-muted/$dc-text-muted/g' \
  -e 's/\$forseti-text-inverse/$dc-text-inverse/g' \
  -e 's/Forseti Theme/Dungeon Crawler/g' \
  -e 's/Forseti theme/Dungeon Crawler/g' \
  -e 's/card-forseti/card-dungeoncrawler/g' \
  -e 's/\.forseti-/\.dc-/g'

echo "Variable replacements complete."
echo "Remaining forseti references in SCSS (excluding _variables.scss):"
grep -rn "forseti" "$SCSS_DIR" --include="*.scss" | grep -v "_variables.scss" || echo "None found!"
