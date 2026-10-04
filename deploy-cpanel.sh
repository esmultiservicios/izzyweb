#!/bin/bash
set -euo pipefail

DEPLOYPATH="/home/esmultiservicios/public_html/izzycloud.app"

if [ ! -d "$DEPLOYPATH" ]; then
  /bin/mkdir -p "$DEPLOYPATH"
fi

copy_dir() {
  local source_dir="$1"
  local destination_dir="$2"

  if [ -d "$source_dir" ]; then
    /bin/mkdir -p "$destination_dir"
    /bin/cp -a "$source_dir"/. "$destination_dir"/
  fi
}

copy_file() {
  local source_file="$1"
  local destination_file="$2"

  if [ -f "$source_file" ]; then
    /bin/mkdir -p "$(/usr/bin/dirname "$destination_file")"
    /bin/cp -f "$source_file" "$destination_file"
  fi
}

# Código de aplicación versionado.
copy_dir "admin" "$DEPLOYPATH/admin"
copy_dir "assets" "$DEPLOYPATH/assets"
copy_dir "core" "$DEPLOYPATH/core"
copy_dir "install" "$DEPLOYPATH/install"

# Configuración segura/versionada. Nunca sobrescribir configuración real de producción.
copy_file "config/bootstrap.php" "$DEPLOYPATH/config/bootstrap.php"
copy_file "config/disposable-email-domains.php" "$DEPLOYPATH/config/disposable-email-domains.php"
copy_file "config/config.example.php" "$DEPLOYPATH/config/config.example.php"
copy_file "config/database.example.php" "$DEPLOYPATH/config/database.example.php"
copy_file "config/.htaccess" "$DEPLOYPATH/config/.htaccess"

# Archivos raíz de la aplicación.
copy_file "index.php" "$DEPLOYPATH/index.php"
copy_file "estimate-submit.php" "$DEPLOYPATH/estimate-submit.php"
copy_file "email-validate.php" "$DEPLOYPATH/email-validate.php"
copy_file "robots.txt" "$DEPLOYPATH/robots.txt"
copy_file "sitemap.xml" "$DEPLOYPATH/sitemap.xml"
copy_file "schema.sql" "$DEPLOYPATH/schema.sql"
copy_file "database.sql" "$DEPLOYPATH/database.sql"
copy_file "database-update.sql" "$DEPLOYPATH/database-update.sql"

# .htaccess puede ser alterado por cPanel/MultiPHP/SSL.
# Se instala solo si producción aún no tiene uno.
if [ ! -f "$DEPLOYPATH/.htaccess" ]; then
  copy_file ".htaccess" "$DEPLOYPATH/.htaccess"
fi

# Conservar uploads existentes. Solo asegurar estructura/protección.
for dir in about-artworks backups estimates gallery media service-badges videos; do
  /bin/mkdir -p "$DEPLOYPATH/uploads/$dir"
done

if [ ! -f "$DEPLOYPATH/uploads/.htaccess" ] && [ -f "uploads/.htaccess" ]; then
  copy_file "uploads/.htaccess" "$DEPLOYPATH/uploads/.htaccess"
fi

printf '%s\n' "IZZY Web desplegado correctamente en: $DEPLOYPATH"
