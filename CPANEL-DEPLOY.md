# IZZY Web — Git Version Control y Deploy de cPanel

Esta configuración sigue el modelo de despliegue manual de cPanel: **Update from Remote** actualiza el repositorio administrado por cPanel y **Deploy HEAD Commit** ejecuta `.cpanel.yml`.

## Archivos de despliegue incluidos

- `.cpanel.yml` — archivo requerido por cPanel en la raíz del repositorio.
- `deploy-cpanel.sh` — script seguro ejecutado por `.cpanel.yml`.
- `.gitignore` — excluye secretos, runtime, uploads y archivos generados por cPanel.
- `.gitattributes` — normaliza archivos de texto a LF para evitar cambios falsos por CRLF.

## Rutas

Document Root de producción:

```text
/home/esmultiservicios/public_html/izzycloud.app
```

Repositorio recomendado de cPanel (separado del Document Root):

```text
/home/esmultiservicios/repositories/izzy_web
```

> El repositorio y el Document Root deben estar separados para que `Deploy HEAD Commit` copie el código desde el repositorio hacia producción.

## Qué NO sobrescribe el deploy

El script no despliega `.git`, `.env`, secretos, `php.ini`, `.user.ini`, `.well-known`, `config/config.php`, `config/database.php`, `config/app.key`, `config/install.lock`, respaldos ni uploads dinámicos.

El `.htaccess` raíz solo se copia si aún no existe en producción. Esto evita destruir cambios legítimos de cPanel/MultiPHP/SSL.

## Comandos de verificación

```bash
cd /home/esmultiservicios/repositories/izzy_web
git status --short
git status
git ls-files .cpanel.yml
git ls-files deploy-cpanel.sh
```

El estado correcto es `nothing to commit, working tree clean`.

## Si `.htaccess` se modifica automáticamente dentro de un repositorio antiguo en el Document Root

Primero revisar el cambio:

```bash
cd /home/esmultiservicios/public_html/izzycloud.app
git diff -- .htaccess
```

Si el cambio pertenece realmente a cPanel/MultiPHP/SSL y deseas conservarlo fuera del flujo normal de Git:

```bash
git update-index --skip-worktree .htaccess
```

Para volver a administrarlo con Git:

```bash
git update-index --no-skip-worktree .htaccess
```

No usar `git reset --hard` ni `git clean -fd` sobre producción sin revisar primero los archivos afectados.

## v1.0.112 - Sincronización estable de `.htaccess`

- `.htaccess` del repositorio es la fuente de verdad e incluye permanentemente el handler `ea-php82` de producción.
- `deploy-cpanel.sh` ya no conserva una copia antigua del `.htaccess` del servidor.
- Si el repositorio cPanel y el DocumentRoot son la misma ruta, el deploy detecta ese modo y evita copiar archivos sobre sí mismos; `Update from Remote` ya realizó la actualización.
- Si en el futuro el repositorio y el DocumentRoot son rutas distintas, `.htaccess` se copia y se valida byte a byte al finalizar.
- No usar `skip-worktree` para `.htaccess`; ocultaría diferencias reales.
- Si se cambia la versión de PHP en MultiPHP Manager, debe actualizarse el handler versionado en `.htaccess` en el mismo commit.
