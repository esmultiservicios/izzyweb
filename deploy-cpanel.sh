#!/bin/bash
set -euo pipefail

DEPLOYPATH="${DEPLOYPATH:-/home/esmultiservicios/public_html/izzycloud.app}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"

log() {
  printf '[IZZY deploy] %s\n' "$*"
}

fail() {
  printf '[IZZY deploy] ERROR: %s\n' "$*" >&2
  exit 1
}

# Resolve a path even when its destination does not exist yet.
resolve_path() {
  local path="$1"
  if [ -e "$path" ]; then
    /usr/bin/realpath "$path"
  else
    local parent
    parent="$(/usr/bin/dirname "$path")"
    printf '%s/%s\n' "$(/usr/bin/realpath "$parent")" "$(/usr/bin/basename "$path")"
  fi
}

[ -f "$SCRIPT_DIR/.htaccess" ] || fail "No se encontró .htaccess versionado en la raíz del repositorio."
[ -f "$SCRIPT_DIR/index.php" ] || fail "No se encontró index.php en la raíz del repositorio."
[ -f "$SCRIPT_DIR/robots.txt" ] || fail "No se encontró robots.txt en la raíz del repositorio."
[ -f "$SCRIPT_DIR/sitemap.xml" ] || fail "No se encontró sitemap.xml en la raíz del repositorio."

# El handler que cPanel/MultiPHP usa en producción debe vivir también en Git.
# Esto evita que cPanel lo agregue después y deje el working tree como modified.
if ! /bin/grep -q 'application/x-httpd-ea-php82' "$SCRIPT_DIR/.htaccess"; then
  fail ".htaccess no contiene el handler versionado ea-php82 requerido por producción."
fi

/bin/mkdir -p "$DEPLOYPATH"
SOURCE_ROOT="$(resolve_path "$SCRIPT_DIR")"
TARGET_ROOT="$(resolve_path "$DEPLOYPATH")"

copy_dir() {
  local source_dir="$1"
  local destination_dir="$2"

  [ -d "$source_dir" ] || return 0

  local source_real destination_real
  source_real="$(resolve_path "$source_dir")"
  destination_real="$(resolve_path "$destination_dir")"

  # El repositorio de IZZY actualmente vive directamente en el DocumentRoot.
  # En ese modo Update from Remote ya actualizó los archivos y no debemos
  # copiar un directorio sobre sí mismo.
  if [ "$source_real" = "$destination_real" ]; then
    return 0
  fi

  /bin/mkdir -p "$destination_dir"
  /bin/cp -a "$source_dir"/. "$destination_dir"/
}

copy_file() {
  local source_file="$1"
  local destination_file="$2"

  [ -f "$source_file" ] || return 0

  local source_real destination_real
  source_real="$(resolve_path "$source_file")"
  destination_real="$(resolve_path "$destination_file")"

  # Evita cp "same file" cuando el repositorio ya es el sitio publicado.
  if [ "$source_real" = "$destination_real" ]; then
    return 0
  fi

  /bin/mkdir -p "$(/usr/bin/dirname "$destination_file")"

  # No reescribir archivos idénticos: reduce cambios de mtime innecesarios.
  if [ -f "$destination_file" ] && /usr/bin/cmp -s "$source_file" "$destination_file"; then
    return 0
  fi

  /bin/cp -f "$source_file" "$destination_file"
}

log "Fuente: $SOURCE_ROOT"
log "Destino: $TARGET_ROOT"

# Código de aplicación versionado.
copy_dir "$SCRIPT_DIR/admin" "$DEPLOYPATH/admin"
copy_dir "$SCRIPT_DIR/assets" "$DEPLOYPATH/assets"
copy_dir "$SCRIPT_DIR/core" "$DEPLOYPATH/core"
copy_dir "$SCRIPT_DIR/install" "$DEPLOYPATH/install"

# Configuración segura/versionada. Nunca sobrescribir secretos reales de producción.
copy_file "$SCRIPT_DIR/config/bootstrap.php" "$DEPLOYPATH/config/bootstrap.php"
copy_file "$SCRIPT_DIR/config/disposable-email-domains.php" "$DEPLOYPATH/config/disposable-email-domains.php"
copy_file "$SCRIPT_DIR/config/config.example.php" "$DEPLOYPATH/config/config.example.php"
copy_file "$SCRIPT_DIR/config/database.example.php" "$DEPLOYPATH/config/database.example.php"
copy_file "$SCRIPT_DIR/config/.htaccess" "$DEPLOYPATH/config/.htaccess"

# Archivos raíz de la aplicación.
copy_file "$SCRIPT_DIR/index.php" "$DEPLOYPATH/index.php"
copy_file "$SCRIPT_DIR/estimate-submit.php" "$DEPLOYPATH/estimate-submit.php"
copy_file "$SCRIPT_DIR/email-validate.php" "$DEPLOYPATH/email-validate.php"
copy_file "$SCRIPT_DIR/robots.txt" "$DEPLOYPATH/robots.txt"
copy_file "$SCRIPT_DIR/sitemap.xml" "$DEPLOYPATH/sitemap.xml"
copy_file "$SCRIPT_DIR/schema.sql" "$DEPLOYPATH/schema.sql"
copy_file "$SCRIPT_DIR/database.sql" "$DEPLOYPATH/database.sql"
copy_file "$SCRIPT_DIR/database-update.sql" "$DEPLOYPATH/database-update.sql"

# .htaccess ahora es fuente de verdad del repositorio, incluido el handler ea-php82.
# Si fuente y destino son diferentes, se sincroniza siempre. Ya no se conserva
# silenciosamente una copia vieja del servidor.
copy_file "$SCRIPT_DIR/.htaccess" "$DEPLOYPATH/.htaccess"

# Conservar uploads existentes. Solo asegurar estructura/protección.
for dir in about-artworks backups estimates gallery media service-badges videos; do
  /bin/mkdir -p "$DEPLOYPATH/uploads/$dir"
done
copy_file "$SCRIPT_DIR/uploads/.htaccess" "$DEPLOYPATH/uploads/.htaccess"

# Verificación posterior: si el repositorio y DocumentRoot son ubicaciones
# distintas, .htaccess debe quedar byte-a-byte igual al versionado.
if [ "$SOURCE_ROOT" != "$TARGET_ROOT" ]; then
  /usr/bin/cmp -s "$SCRIPT_DIR/.htaccess" "$DEPLOYPATH/.htaccess" \
    || fail ".htaccess de producción no coincide con el archivo versionado después del deploy."
fi

log "Deploy finalizado correctamente en: $DEPLOYPATH"
