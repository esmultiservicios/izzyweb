# IZZY Web v1.0.101

- Refuerza el ciclo de vida de autenticación administrativa con 60 minutos de inactividad y 12 horas de duración absoluta.
- Endurece cookies PHP con HttpOnly, Secure bajo HTTPS, SameSite=Lax y session.use_strict_mode.
- Evita que Recordarme reactive una sesión vencida por inactividad o reinicie artificialmente el límite máximo de 12 horas.
- Revoca sesiones persistidas, tokens Recordarme y cookies cuando una sesión vence, se cierra manualmente, cambia la contraseña o se fuerza una reautenticación.
- Agrega soporte `?fresh=1` para solicitar una autenticación administrativa nueva de forma explícita.
- Las solicitudes AJAX/JSON sin sesión válida reciben HTTP 401 con una respuesta JSON en lugar de HTML de login.
- Agrega expiración de 10 minutos al paso pendiente de autenticación de dos factores.
- No requiere cambios de base de datos.

# IZZY Web v1.0.99

- Actualiza el botón flotante de WhatsApp al formato premium tipo píldora, con icono y texto visibles.
- Mantiene fondo verde sólido, borde blanco, halo sutil y sombra ligera sin degradados.
- Ajusta dimensiones y tipografía para escritorio y móvil sin afectar NIVO ni las redes sociales flotantes.
- No requiere cambios de base de datos.

## v1.0.98 — validación avanzada de correo y protección antispam

- Valida el correo en frontend y backend antes de aceptar el formulario público.
- Detecta errores comunes de dominios y ofrece sugerencias sin modificar el correo automáticamente.
- Verifica registros MX/DNS y bloquea dominios que no parecen recibir correo.
- Bloquea proveedores de correo temporal/desechable mediante una lista local mantenible.
- Agrega una capa opcional de validación externa de entregabilidad con fallback seguro si el proveedor no responde.
- Refuerza el honeypot, el tiempo mínimo del formulario, el rate limit por sesión y por IP anonimizada, y la detección de contenido automatizado.
- Mantiene soporte opcional para Cloudflare Turnstile.
- El formulario ya no envía correos de confirmación al visitante; solo notifica al administrador.
- Agrega estado visual discreto para correo válido, inválido o con sugerencia.
- No requiere cambios de base de datos.

## v1.0.95
- Simplifica `.cpanel.yml` para seguir el modelo oficial de cPanel y ejecutar un script de despliegue dedicado.
- Agrega `deploy-cpanel.sh` con copias explícitas y seguras hacia `/home/esmultiservicios/public_html/izzycloud.app`.
- Protege configuración real de producción, uploads, `.env`, secretos, `php.ini`, `.user.ini`, `.well-known` y archivos runtime.
- Agrega `.gitattributes` para normalizar LF y reducir cambios falsos por CRLF en cPanel/Linux.
- Documenta claramente la separación recomendada entre repositorio administrado por cPanel y Document Root.
- No requiere cambios de base de datos.

## v1.0.94
- Prepara el proyecto para Git Version Control de cPanel con `.cpanel.yml` en la raíz.
- Define despliegue seguro hacia `/home/esmultiservicios/public_html/izzycloud.app/` sin copiar `.git` ni usar comodines sobre la raíz del repositorio.
- Protege configuración de producción, secretos, `.env`, `php.ini`, `.user.ini`, `install.lock`, respaldos y contenido dinámico de `uploads/`.
- Mantiene `.htaccess` versionado como base, pero el deploy solo lo crea si no existe para no sobrescribir cambios de cPanel/MultiPHP/SSL.
- Amplía `.gitignore` para runtime, logs, caché, temporales, uploads, backups y archivos generados por cPanel/instalador.
- Agrega `CPANEL-DEPLOY.md` con la arquitectura y comandos seguros de operación.
- No requiere cambios de base de datos.

## v1.0.93
- Corrige la captura de **Facturación móvil** para que muestre siempre el mismo botón azul de lupa que el resto de imágenes ampliables.
- La imagen mantiene cursor `zoom-in`; sobre el botón de lupa el cursor cambia a `pointer`.
- El botón y la imagen abren el mismo lightbox y se añade activación por teclado con Enter/Espacio.
- Se elimina la dependencia del indicador antiguo `Ampliar` oculto en escritorio y se unifica el comportamiento visual.

# v1.0.92

- Unifica el comportamiento de zoom de todas las imágenes ampliables del sitio público.
- La imagen usa cursor de lupa (`zoom-in`) y el botón flotante de lupa queda siempre visible.
- Al colocar el cursor sobre el botón de lupa cambia a cursor de mano (`pointer`).
- Elimina la lupa decorativa antigua para evitar iconos duplicados.
- Las imágenes ampliables que no tenían botón reciben automáticamente un botón real y funcional.

## izzy_web v1.0.91 - Actualización DB compatible y unificada

- Corregido `database-update.sql` para no utilizar `ADD COLUMN IF NOT EXISTS`, sintaxis no soportada por algunos servidores MySQL/MariaDB de hosting compartido.
- Reutilizado el helper `cms_core_safe_alter()` para agregar `izzy_plans.show_image` de forma segura e idempotente.
- El helper de migración ahora permanece disponible hasta finalizar todas las alteraciones y se elimina únicamente al final del script.
- Eliminado `IZZY_UPDATE_DB_COMPLETO.sql`; desde esta versión `database-update.sql` es el único archivo acumulativo para actualizar instalaciones existentes.
- Actualizada la documentación para evitar ejecutar dos actualizadores diferentes.

# v1.0.90

- Corrige el resaltado automático del menú al desplazarse: ahora sigue estrictamente Inicio → Soluciones → Modalidades → Planes → El sistema → Ubicación y el mismo orden en reversa.
- Sustituye el cálculo por porcentaje visible que podía saltar secciones por un seguimiento determinista basado en la posición real de cada sección.
- Conecta el menú público y el orden físico de las secciones con Admin > Section Manager.
- El orden, visibilidad, texto del menú y estilo CTA definidos por el administrador se reflejan en la landing sin hardcodear el flujo.
- Migra las secciones genéricas heredadas del CMS a las secciones reales de IZZY, conservando un orden inicial limpio y coherente.

# v1.0.89

- Corrige la lupa de las capturas del showcase: ahora es un botón real y funcional que abre el lightbox.
- Unifica el zoom de las tarjetas principales y adicionales, con soporte de teclado y móvil.
- Integra las cinco capturas IZZY como registros administrables en Projects / Case Studies mediante actualización idempotente de base de datos.
- Mantiene títulos, descripciones, imágenes, orden y visibilidad configurables desde el administrador.
- Conserva compatibilidad con instalaciones sin registros mediante los fallbacks públicos existentes.

## v1.0.88
- Replaced the previous IZZY login showcase image with the new premium login experience, including the DEMO state and updated responsive access design.
- Added the password-recovery screen to the public-site default system gallery so visitors can see the complete access recovery experience.
- Added the new account-creation screen to the public-site default system gallery, including password confirmation and security guidance.
- Kept the existing CMS gallery behavior intact: administrator-managed gallery content still takes priority over the built-in showcase defaults.

## v1.0.87
- Corregido el campo de contraseña del login administrativo para mantener el ojito IZZY perfectamente centrado dentro del input, alineado a la derecha y sin alterar el tamaño del campo.
- Se conserva oculto el control nativo del navegador y no se modifica la lógica de autenticación ni Recordarme.

## v1.0.86 / Installer 4.7.2
- Fixed installer Back/Next navigation so revalidating an earlier step no longer clears data already captured in later steps.
- Database step now preserves Administrator and Email drafts, including all password/secret values stored for the active installer session.
- Administrator step now preserves Email completion/state when revisited.
- Draft data remains available across all four steps and is only cleared through the explicit reload/reset confirmation flow.

## v1.0.85 / Installer 4.7.1
- Preserva los valores sensibles del asistente al navegar hacia Atrás/Adelante entre pasos: contraseña MySQL, contraseña y confirmación del administrador, SMTP password y Client Secret VALUE de Microsoft Graph.
- Los secretos permanecen únicamente durante la sesión activa del instalador y se eliminan cuando el usuario confirma reiniciar/perder datos desde la recarga del asistente.
- Mantiene intacta la configuración de correo ya probada al regresar desde Confirmación a Correo, evitando volver a escribir Client Secret VALUE.

## v1.0.84 / Installer 4.7.0
- Hardened the installer email test so it always sends using the values currently entered in Step 3; saving the configuration first is not required.
- Added a unique timestamped subject for each test, exact sender/recipient feedback, Graph request IDs, cURL error handling and Microsoft Graph error details.
- When `Guardar una copia en Elementos enviados` is enabled and the application has read permission, the test also checks Sent Items immediately and reports whether the exact test message was found.
- Changed the success wording so HTTP 202 is reported accurately as Microsoft Graph accepting the message for delivery instead of claiming final recipient delivery before Exchange completes processing.

## v1.0.83
- Security Center now provides one protected "Instalación desde cero" action for the Owner.
- Requires the current Owner password plus the exact confirmation phrase `BORRAR IZZY`.
- Sends the security alert email before any destructive change; if delivery fails, nothing is removed.
- After the email succeeds, active configuration files and `install.lock` are moved to a protected backup, the configured IZZY database is dropped completely, browser/session access is cleared, and the user is redirected to `/install/` as a new installation.
- If the database cannot be dropped, the active configuration files are restored automatically.

## v1.0.82 / Installer 4.6.9
- Closed direct access to `/install/` whenever `config/install.lock` and an active database configuration are present.
- Installed sites now redirect `/install/` back to the public site instead of exposing the installer or redirecting to the admin login.
- The protected Security Center fresh-install flow remains compatible because it moves the active configuration and lock before opening the installer.

## v1.0.81 / Installer 4.6.8
- Fixed a redirect loop between `/install/` and `/admin/login.php` when an installation lock existed but the CMS was not actually in a complete, usable installed state.
- The installer now redirects to the admin login only when the lock, database configuration, `admin_users` table and at least one administrator are all present.
- The admin bootstrap now treats a missing active database configuration as an incomplete installation and sends the user to the four-step installer instead of entering an inconsistent state.

## v1.0.80 — Unified installation flow
- Removed the legacy `RESET ADMIN` / Owner recovery flow from Profile & Security so there is no second setup experience.
- Retired the standalone `admin/setup.php` UI; when no administrator exists it now redirects directly to the approved four-step `/install/` assistant.
- Updated admin login, password recovery, password reset, and admin index fallback routes to send administrator-less installations to `/install/` instead of the old setup screen.
- Kept the single protected Owner action in Security Center: **Preparar instalación nueva**, including password verification, email security alert, confirmation phrase, session/token revocation, protected config backup, and redirect to the four-step installer.

## v1.0.79
- Kept confirmation instructions such as `Type RESET ADMIN` on a single line with the required phrase highlighted cleanly.
- Standardized the custom password-eye control so ownership reset fields use the same centered, integrated style as Two-factor authentication.
- Preserved responsive behavior without horizontal overflow.

## v1.0.78
- Added the project-wide IZZY password visibility control to every password field that did not already have the custom eye control, while suppressing browser-native reveal buttons.
- Kept existing installer password controls intact to avoid duplicate buttons or logic changes.
- Highlighted required typed confirmation phrases across the admin, including RESET ADMIN, REINSTALAR IZZY and permanent-delete confirmation phrases.

## v1.0.77
- Fixed Admin Login “Recordarme” so the persistent 30-day login token is created reliably, including self-healing token storage on existing installations.
- Improved secure cookie detection for HTTPS/proxy environments and rotate remembered-login tokens after use.
- The login now also remembers only the username/email preference in local storage (never the password), keeping the checkbox state consistent after returning to the login screen.

## v1.0.76
- Security Center now sends a mandatory Owner security alert before preparing a fresh installation; if delivery fails, no sessions are revoked and no active configuration files are moved.
- The alert records the administrator, date/time, IP address and the security impact of the action.
- Removed link underlines globally from the published site and admin panel, including hover, focus, visited and active states, while preserving colors and interaction behavior.

## v1.0.75 / Installer 4.6.7
- Added an Owner-only Security Center action to prepare a truly fresh installer run without manually deleting files.
- The action requires the current Owner password, the exact confirmation phrase `REINSTALAR IZZY`, and a SweetAlert confirmation before proceeding.
- The active installer lock and database configuration files are moved to protected timestamped backups under `config/installer-backups/`, allowing `/install/` to open as a new installation instead of reinstall mode.
- Existing administrator sessions and remember-me tokens are invalidated before redirecting to the installer.
- The database itself is not modified by this preparation action; schema reset still occurs only when the final installer step is confirmed.

## v1.0.74 / Installer 4.6.6
- Centralized SweetAlert2 positioning so every Swal.fire dialog is centered horizontally and vertically against the real viewport.
- Added safe viewport bounds for desktop, tablet and mobile so dialogs remain fully visible without horizontal overflow.
- Preserved existing SweetAlert2 content, buttons, confirmation logic and page behavior; only the shared dialog positioning was normalized.

## v1.0.73 / Installer 4.6.5
- Improved SweetAlert2 button readability project-wide with clearer font size, stronger weight, sharper rendering and better line-height.
- Added safe wrapping and responsive button sizing so longer Swal actions remain readable without overflowing on desktop, tablet or mobile.
- Preserved the established IZZY colors, icons and compact modal proportions.

## v1.0.72 / Installer 4.6.4
- Reinstallation now always finishes at the administrator login, never inside an already authenticated panel session.
- The installer destroys the current PHP session and removes the persistent administrator remember cookie before redirecting to login.
- Added an installation session generation token so administrator sessions created before a reinstall are invalidated automatically when they next access the CMS.
- Login, remember-me and two-factor sessions are now bound to the current installation generation.

## v1.0.71 / Installer 4.6.3
- Reorganized the Administrator step so Username and Email share one row on desktop while remaining stacked and responsive on small screens.
- Kept SMTP User / sender email and Password / App Password on one clean row, with responsive stacking only when needed.
- Refined local Select2 option states so options stay neutral by default; only hover/focus receives a subtle readable highlight and the selected option no longer uses a permanent filled background.

## v1.0.70 / Installer 4.6.2
- Preserved administrator password fields when the confirmation does not match so the installer no longer clears them on validation errors.
- Reordered the SMTP layout in the installer to: server + port + security, then sender email + password, improving visual order without increasing the overall shell height.
- Increased legibility across installer steps with darker helper text, clearer labels, and sharper step/sidebar descriptions while keeping the responsive layout compact.

## v1.0.68
- Corrige pantalla completa para que NO se cierre al navegar entre páginas del administrador.
- El modo fullscreen ahora usa un shell persistente en iframe del mismo dominio; los enlaces del menú navegan dentro del shell sin abandonar fullscreen.
- `Expandir` entra a pantalla completa real y permanece activo hasta presionar `Restaurar` o ESC.
- Header, sidebar, contenido y navegación conservan su comportamiento normal dentro del modo fullscreen.
- Mantiene showNotify como fallback si el navegador rechaza Fullscreen API.

## v1.0.67
- Corrige el botón `Expandir` del administrador para usar pantalla completa real del navegador mediante Fullscreen API.
- Ya no simula expansión ocultando el menú lateral.
- Al presionar `Expandir`, el navegador entra realmente en fullscreen; al volver a presionarlo o usar ESC, restaura la vista normal.
- El header, sidebar y contenido permanecen visibles y ordenados dentro de pantalla completa.
- El botón cambia visualmente a `Restaurar` mientras fullscreen está activo.
- Mantiene showNotify para cualquier fallo del navegador al solicitar fullscreen.

## v1.0.66
- Restaura movimiento visible al pasar el mouse sobre las redes sociales flotantes y las redes del footer.
- El movimiento se aplica únicamente al contenedor con desplazamientos de píxeles enteros para evitar desenfoque.
- Los SVG de Facebook, TikTok y demás redes ya no escalan, rotan ni usan filtros, por lo que permanecen nítidos.
- Redes flotantes: movimiento lateral + elevación sutil.
- Footer: elevación sutil con sombra premium.
- Mantiene los nombres ocultos y conserva accesibilidad mediante aria-label.

## v1.0.65
- Mejora la legibilidad del texto `IZZY · Tecnología preparada para crecer contigo` dentro de los planes.
- Sustituye el gris tenue por un azul corporativo más nítido y aumenta ligeramente peso/tamaño tipográfico.
- Cambia las líneas degradadas por líneas sólidas suaves para evitar sensación borrosa.
- Mantiene un hover discreto y coherente con el resto del sitio.

## v1.0.64
- Mantiene la corrección de redes sociales de v1.0.63: iconos nítidos, sin escala, rotación ni desenfoque.
- Agrega al botón `Quiero IZZY` un efecto sutil premium: ligera elevación, sombra limpia y barrido de luz discreto.
- El botón conserva siempre su azul y texto blanco; nunca cambia a fondo blanco.
- Respeta `prefers-reduced-motion` para accesibilidad.

## v1.0.63
- Elimina por completo escala, rotación, filtros y movimiento del icono de redes sociales al hacer hover.
- El hover ahora usa únicamente sombra, borde y color de plataforma para mantener Facebook, TikTok y demás iconos totalmente nítidos.
- Quita la animación flotante continua de las redes para evitar cualquier sensación de desenfoque.
- Mantiene el diseño compacto, limpio y sin nombres visibles de las redes.

## v1.0.62
- Elimina por completo los tooltips visibles con el nombre de Facebook, TikTok u otra red social.
- Elimina también el tooltip nativo del navegador quitando `title`; se conserva `aria-label` para accesibilidad.
- Mantiene las redes como iconos únicamente tanto en el dock flotante como en el footer.
- Ajusta el hover para usar desplazamientos limpios y evitar sensación borrosa en iconos y animaciones.
- Conserva el movimiento premium agregado en v1.0.61 sin mostrar texto de la red.

## v1.0.61
- Mejora las redes sociales flotantes con microanimación suave, hover premium y movimiento del icono sin alterar su tamaño ni posición.
- Mantiene el dock flotante siempre como iconos compactos: nunca expande el nombre lateralmente.
- Agrega tooltip discreto con el nombre de la red al pasar el cursor.
- Rediseña las redes del footer como iconos compactos sin texto permanente para escalar limpiamente cuando haya 3, 4 o 5 redes.
- El footer muestra el nombre mediante tooltip y aplica el color propio de cada plataforma solo al interactuar.
- Añade estilos preparados para Instagram, Facebook, TikTok, YouTube y LinkedIn.
- Conserva accesibilidad mediante aria-label/title, feedback táctil y prefers-reduced-motion.

## v1.0.60
- Mejora el botón flotante de WhatsApp con el isotipo correcto y proporciones limpias.
- Elimina el icono anterior y cualquier fondo interno extraño.
- Ajusta tamaño, borde, sombra y hover para mantener el estilo premium del sitio.
- Conserva posición y comportamiento responsive existentes.

## v1.0.59
- Agrega un sistema uniforme de microinteracciones al sitio público para evitar secciones estáticas.
- Facturación Móvil ahora responde al cursor: elevación de tarjeta, zoom sutil de la imagen, movimiento del contenido, chips interactivos y callout con profundidad.
- Extiende hover premium a modalidades, soluciones, galería, proyectos, videos, planes, servicios y tarjetas informativas.
- Las imágenes ganan zoom suave sin recortes ni saltos de layout.
- Los botones públicos ganan movimiento y sombra, manteniendo siempre su color; ningún CTA se vuelve blanco.
- Agrega feedback táctil en móviles y respeta `prefers-reduced-motion` por accesibilidad.
- No modifica la lógica del administrador ni las funcionalidades ya validadas.

## v1.0.58
- Corrige definitivamente la altura del bloque superior del SEO Manager.
- Configuración principal y la columna completa de Vista previa + Salud SEO terminan exactamente a la misma altura.
- Vista previa de Google y Salud SEO se distribuyen en dos mitades limpias dentro de la columna derecha.
- Salud SEO continúa completamente estático, sin movimiento al pasar el cursor.
- Las dos tarjetas inferiores siguen conservando la misma altura en escritorio.
- En tablet y móvil las tarjetas vuelven a altura natural para evitar espacios forzados.

## v1.0.57
- Corrige Salud SEO para que permanezca completamente estático: sin desplazamiento, elevación ni cambio de geometría al pasar el cursor.
- Separa visualmente Vista previa de Google y Salud SEO con espaciado fijo y limpio.
- Igual altura para Vista previa al compartir y SEO Técnico en escritorio.
- Mantiene el logo social contenido y sin deformaciones.
- Conserva toda la lógica SEO, robots.txt, sitemap.xml y configuraciones existentes.

## v1.0.56
- Corrige el warning público `Undefined array key chat_widget_mode` cuando la instalación todavía no tiene esa clave.
- Reorganiza el formulario de contacto: Nombre o empresa 100%, Teléfono + Plan 50/50, Correo 100% y mensaje con editor Rich Text.
- El backend sanitiza y conserva formato seguro del mensaje del formulario público; admin y correo lo muestran correctamente.
- Corrige hover de `Quiero IZZY` y demás CTAs para que ningún botón visible se vuelva blanco.
- SEO Manager deja de estirar tarjetas: Google Preview + Salud SEO se apilan a la derecha y Social + SEO Técnico usan el ancho completo inferior.
- Agrega `Expandir / Restaurar` al admin con modo expandido persistente entre navegaciones usando `localStorage`.
- El instalador se alinea visualmente con el sitio público: tipografía nítida, superficies limpias y jerarquía uniforme.
- Microsoft Graph: Tenant ID + Client ID 50/50, Client Secret + Mailbox 50/50, campos individuales al 100% y tarjetas informativas premium.
- Mantiene las reglas de mensajes: showNotify para estados y SweetAlert2 para diálogos; sin alert/confirm nativos.

## v1.0.55
- Reorganiza SEO Manager para eliminar por completo el espacio blanco muerto.
- Configuración principal y Vista previa de Google ocupan la primera franja completa.
- Salud SEO, Vista previa social y SEO Técnico pasan a una segunda fila que usa todo el ancho disponible.
- Se conservan robots.txt, sitemap.xml, score SEO, preview social y toda la lógica existente.

## v1.0.54
- Corrige los iconos negros de Facturación Móvil y los controles de zoom usando iconos de línea limpios.
- En escritorio se elimina el texto redundante `Ampliar`; la imagen completa sigue siendo clicable y muestra una lupa sutil al pasar el cursor.
- En móvil se muestra un botón `Ampliar` premium y entendible; tocar la imagen también abre el zoom.
- Lightbox adaptado a escritorio, tablet y móvil con imagen contenida y botón de cierre accesible.
- SEO Manager: corrige la vista previa social para que el logo de IZZY no quede gigante ni recortado.
- SEO Manager agrega administración visible de `robots.txt` y `sitemap.xml`, con estado, URL, fecha y botones Generar/Regenerar.
- Incluye `robots.txt` y `sitemap.xml` iniciales para `https://izzycloud.app/` y agrega descubrimiento de sitemap en el HTML público.
- Mantiene intacta la lógica existente del sitio y del administrador.

## v1.0.53
- Corrige el botón `Ver pantalla completa`: icono compacto, texto en una sola línea y sin círculo negro.
- Widget flotante reconstruido para NIVO o cualquier proveedor: URL/iframe o código embed, nombre del proveedor, textos y posición.
- El widget nunca comparte lado con WhatsApp: la posición se resuelve automáticamente al lado contrario.
- SEO Manager rediseñado para eliminar espacio muerto: score SEO, preview Google, checklist y preview social.
- Appearance / Temas y colores reorganizado: primero muestra el estilo actual de IZZY y después paletas IZZY recomendadas.
- Nuevas paletas IZZY Original, Navy, Cian, Fresh y Soft, además del editor manual de colores.
- Mantiene compatibilidad con las configuraciones NIVO anteriores.

## v1.0.52
- Integra la nueva visual aprobada de **Facturación Móvil** dentro de `El sistema`.
- Agrega un bloque premium propio, separado de Modalidades, para evitar repetir imágenes o conceptos.
- Explica apertura/cierre de caja, cliente y vendedor, productos y ventas de contado/crédito.
- La imagen de Facturación Móvil puede ampliarse con el lightbox existente.
- Dashboard administrativo refinado: textos principales en español, jerarquía más clara, tarjetas más uniformes y espaciado más limpio.
- Conserva estructura, permisos y lógica existente del administrador sin alterar funcionalidades.

## v1.0.51
- Elimina la repetición de la misma imagen en Modalidades.
- La tarjeta `IZZY Punto de Venta Visual` ahora muestra un recorrido móvil real con las tres capturas compartidas: Mesas, Productos y Pedido.
- El bloque grande `Así se ve IZZY trabajando` conserva únicamente la captura real de escritorio del POS.
- Se elimina del proyecto la imagen generada que se estaba repitiendo.
- Se limpia el CSS anterior de esas secciones y se reemplaza por un único bloque coherente, responsive y sin estilos duplicados.
- `Ver pantalla completa` sigue funcional y abre la captura real de escritorio.

## v1.0.50
- Corrige los iconos de la cabecera premium del Dashboard administrativo.
- El problema era un selector CSS demasiado amplio que aplicaba el estilo de la píldora también al `span.ui-icon` interno.
- Ahora Contenido, Métricas y Seguridad muestran iconos reales, compactos, alineados y con color uniforme.
- Se refuerza el estilo para evitar que reglas globales vuelvan a deformar estos iconos.

## v1.0.49
- Sustituye la visual anterior de IZZY Punto de Venta por una composición limpia y premium basada en la experiencia real de escritorio y móvil.
- Usa la nueva visual tanto en la tarjeta de modalidad como en el bloque grande explicativo para mantener coherencia visual.
- El botón `Ver pantalla completa` ahora abre realmente la imagen en el lightbox existente.
- Mantiene fondos claros, sin saturaciones ni degradados agresivos en la sección de Modalidades.
- Se auditó el proyecto para detectar usos de `alert()` / `confirm()` nativos.

## v1.0.48
- Modalidades: evita repetir la misma captura real del POS dos veces; la tarjeta resumen usa una visual diferente y la pantalla real queda en el bloque explicativo grande.
- Galería pública: Dashboard IZZY, Acceso seguro e IZZY en móvil quedan con el mismo alto, marcos de imagen uniformes y CTA alineado abajo.
- Ubicación y contacto: refuerza la identidad IZZY con logo, tratamiento de marca y jerarquía premium.
- Contacto: agrega un bloque de marca IZZY visible antes de los datos de contacto.
- Dashboard administrativo: agrega una cabecera IZZY CMS premium y alinea KPIs, tráfico y herramientas con tarjetas de altura consistente.

## v1.0.47
- Integra una captura real de IZZY Punto de Venta Visual en la sección Modalidades.
- Explica que el modo de mesas es opcional y que el mismo sistema puede trabajar sin mesas para comercios y otros negocios.
- Agrega un bloque premium “Así se ve IZZY trabajando” con zoom de imagen, modos de operación y ejemplos de negocios.
- Deja preparado el espacio conceptual para sumar posteriormente la pantalla real de Cocina / Comanda.
- Refuerza el mensaje de que IZZY se adapta a restaurantes, tiendas, cafeterías, ferreterías, farmacias, repuestos, servicios y PYMES.

## v1.0.46
- Corrige definitivamente la igualdad visual de las tarjetas de planes.
- Ya no se iguala únicamente el borde exterior: también se sincronizan encabezado, precio, bloque de valor, beneficios, compatibilidad y CTAs.
- El plan destacado conserva su diseño premium sin alterar el alto ni la línea base del resto.
- El comportamiento responsive móvil conserva alturas automáticas.

## v1.0.45
- Corrige definitivamente la altura de las tarjetas públicas de planes: todas las visibles se sincronizan al mismo alto en escritorio.
- El plan destacado conserva borde, cinta y jerarquía premium, pero ya no se desplaza hacia arriba ni rompe la alineación.
- El espacio sobrante de planes cortos se absorbe de forma intencional antes de compatibilidad y CTAs.
- Elimina botones blancos visibles en el sitio público: botones secundarios, “Ver todos los planes” y cierre del lightbox usan superficies premium.
- Refuerza el mismo criterio en botones secundarios y toolbars del administrador.
- Hero público mejorado para explicar qué es IZZY, para qué negocios sirve y qué problemas ayuda a ordenar sin sobrecargar la pantalla.
- Actualiza el contenido inicial del hero y migra solo el texto legacy, sin sobrescribir contenido personalizado.

## v1.0.44
- Planes IZZY: agrega un botón global visible justo encima del listado para **Mostrar fotos en TODOS** / **Ocultar fotos en TODOS** con una sola acción.
- Se mantiene el control global Sí/No superior y el control individual de cada plan.
- Landing pública: las tarjetas de planes quedan con el mismo alto por fila.
- El espacio variable se balancea con una franja visual discreta de marca para evitar huecos desordenados sin inventar beneficios del plan.
- Compatibilidad y llamados a la acción quedan alineados en la parte inferior de todas las tarjetas.

## v1.0.43
- Rehace el menú `Acciones` global: opciones sin fondos, iconos con color y eliminar solo en rojo, sin bloque rojo.
- Carga `admin.css` al final para que las reglas administrativas sean realmente autoritativas y no las pisen estilos globales.
- Planes IZZY: agrega panel visible Sí/No para mostrar u ocultar globalmente todas las fotos y corrige el guardado del valor `No`.
- Redes sociales: elimina la barra/mensaje fijo y lo reemplaza por un bloque normal, legible y dentro del flujo.
- NIVO Web Chat: mantiene su módulo y refuerza carriles flotantes para WhatsApp izquierda / NIVO derecha sin superposición.
- Select2: cerrado siempre blanco; hover y selección solo se colorean dentro del desplegable.
- SEO Manager: vista previa reconstruida con simulación Google, URL, resultado y contadores de caracteres.
- Sidebar: conserva de forma robusta la posición usando almacenamiento local y re-aplica la posición después del layout.

## v1.0.42
- Unifica el menú **Acciones** en todo el administrador: dropdown blanco, opciones sin fondo, iconos con color y estados hover limpios.
- Planes IZZY: hace visible el interruptor global **Mostrar fotos en TODOS los planes**, manteniendo además el control individual por plan.
- Redes sociales: reemplaza la barra flotante que tapaba el preview por una tarjeta de guardado integrada al flujo.
- Agrega el módulo administrativo **Widget flotante** para NIVO Web Chat. WhatsApp queda a la izquierda y NIVO a la derecha para evitar superposición.
- Select2: campo cerrado neutro; en el desplegable solo hover y selección reciben color.
- SEO Manager: nueva vista previa premium estilo resultado de Google y actualización en vivo al escribir.
- Sidebar: conserva la posición vertical entre páginas usando sessionStorage.
- Revisión responsive y de consistencia visual para los módulos tocados.

## v1.0.41
- Planes IZZY: agrega control global para mostrar/ocultar fotos en el listado público y conserva el control individual por plan.
- Instalaciones nuevas dejan las fotos globales apagadas por defecto; instalaciones existentes quedan encendidas una vez para revisión.
- Planes, Servicios y Videos: `Editar` pasa al menú `Acciones`.
- Menús `Acciones`: opciones limpias, sin fondos de color; eliminar conserva únicamente el énfasis de texto/icono.
- Servicios: imágenes con lupa al pasar el cursor y vista ampliada en el modal global.
- Servicios, Videos y Project Manager: campos de descripción preparados con editor rich text seguro.
- Videos y demás selectores multimedia: zonas de carga al 100% del ancho disponible.
- Web pública: la foto de cada plan solo aparece cuando están activos tanto el control global como el individual.
- Web pública: descripciones enriquecidas de servicios y proyectos se renderizan con una lista segura de etiquetas HTML.

## v1.0.40
- Planes IZZY: zoom con lupa sobre imágenes del listado y vista ampliada usando el modal premium existente.
- Planes IZZY: nueva opción por plan para mostrar/ocultar la imagen promocional en la web pública; nuevos planes quedan apagados por defecto.
- Planes IZZY: planes existentes con imagen quedan visibles inicialmente para revisión.
- Web pública: integra la imagen promocional dentro de cada tarjeta de precios cuando está habilitada y permite ampliarla.
- Project Manager: mejora visual del flujo, agrupación de campos, panel de media/publicación, estados y tarjetas existentes.
- Base de datos: agrega `izzy_plans.show_image` al esquema y al paquete acumulativo de actualización.

## v1.0.39
- Corrige específicamente el selector de archivos en **Servicios** y **Planes IZZY**.
- Evita que el botón `Seleccionar archivo` se comprima y muestre el texto en vertical.
- Mantiene el área de arrastrar/soltar/pegar archivos limpia y alineada en escritorio.
- En móvil el selector pasa a una fila completa, conservando diseño responsive.

## v1.0.38
- Corrige el renderizado roto de iconos de acción en todo el administrador.
- Carga globalmente `action-icons.css` desde el header administrativo con cache-busting.
- Restaura tamaños y alineación normales en Section Manager, Services, Planes IZZY y demás módulos que usan botones/iconos dinámicos.
- Evita SVG sin estilos que podían ocupar cientos de píxeles, deformar tarjetas, selects y zonas de carga.


## v1.0.30
- Corregida la carga del CSS y JavaScript del sitio público: las rutas versionadas ahora apuntan a `assets/izzy-site.css` y `assets/izzy-site.js`.
- Restaurada la landing page pública con su diseño premium y responsive.
- Los SweetAlert2 del administrador cargan la capa local de iconos antes de abrirse, incluyendo cerrar sesión y cancelar.
- El control “Recordarme” usa un indicador visual propio con el punto blanco perfectamente centrado.
## v1.0.29

- Canonicalized the admin login URL so `?installed=1` is never kept in the address bar.
- Installation completion remains a one-time session notice on the clean `admin/login.php` URL.
- SweetAlert2 confirmation buttons are now explicitly rescanned for local SVG action icons after each modal opens, including administrator logout.
- Installer display version updated to 4.5.6.

## v1.0.28
- Corrige alineación visual de Recordarme en el login.
- El aviso de instalación completada ahora se muestra una sola vez y no reaparece tras un intento de inicio de sesión.
- El instalador redirige al login sin parámetros persistentes de instalación.
- Mejora el mensaje de error de autenticación y conserva el prellenado de la cuenta creada.

# v1.0.18
- Installer constrained to 95% of the viewport with internal vertical scrolling.
- Footer actions remain visible and centered at all times.
- Removed duplicate close icon in showNotify/SweetAlert close controls while retaining action icons on Swal buttons.

## 4.3.6 / izzy_web v1.0.16
- El botón Atrás del instalador conserva los datos escritos en la pestaña actual mediante borrador de sesión; al regresar al paso se restauran los valores.
- F5 / Ctrl+R / Cmd+R usan confirmación premium local con `Swal.fire` antes de descartar el borrador. Si se recarga desde el botón del navegador, el asistente detecta el borrador al volver y permite restaurarlo o descartarlo sin `confirm()` nativo.
- El paso de base de datos ya no intenta abrir una base inexistente: valida únicamente servidor/credenciales y crea la base + esquema al confirmar el paso final.
- Se añadieron mensajes de avance del instalador con `showNotify` local y un mensaje final de instalación orientado a comenzar a personalizar y dar a conocer IZZY.
- Se consolidó el estándar UI local para instalador, sitio público y admin: ShowNotify (success/error/danger/warning/info), Swal.fire, Select2, botones con iconos, uploads drag/drop/paste/selector, modales sin cierre por backdrop y KPI con hover premium.
- Los scrollbars internos del instalador usan `overflow-y:auto`: solo aparecen cuando el contenido realmente excede el espacio disponible.

## 4.3.4 / izzy_web v1.0.15
- El instalador ocupa el 95% de la altura disponible del viewport y se adapta a desktop, laptop, tablet y móvil sin scroll horizontal.
- El contenido de cada paso usa scroll vertical interno cuando excede el espacio disponible, manteniendo siempre visibles los botones Atrás/Siguiente/Continuar en un footer fijo dentro del asistente.
- Los formularios, métodos de correo, pruebas y revisión final se alinean sobre una cuadrícula responsive de 12 columnas; en pantallas estrechas se reorganizan verticalmente.
- El paso de correo mueve “Guardar y continuar” al footer fijo para que la navegación no desaparezca al hacer scroll.
- El instalador continúa sin tablas HTML: toda la estructura visual usa CSS Grid/Flex.

## 4.3.3 / izzy_web v1.0.14
- Se restauró la misma altura visual entre el panel de pasos y el panel principal en escritorio/laptop.
- En pantallas de poca altura, el instalador ya no se comprime: la página usa scroll vertical natural del navegador.
- Se eliminó cualquier posibilidad de scroll horizontal; cuando falta ancho, los bloques se reorganizan hacia abajo.
- En móvil/tablet estrecha, pasos, formularios y acciones fluyen en una sola columna ordenada.

## 4.3.2 / izzy_web v1.0.13
- Se incrementó el tamaño visual del instalador para mejorar la lectura en desktop y laptop, aprovechando mejor el ancho disponible.
- Se ajustaron tipografías, logo, botones, campos y panel lateral para un look más claro sin perder responsividad ni altura útil.
- Se redujeron márgenes exteriores y paddings verticales para que el contenido se distinga más sin desbordarse en pantallas normales.

## 4.3.1 / izzy_web v1.0.12
- Corregida la detección de URL del instalador: ahora toma la URL visible real del navegador (por ejemplo `http://izzy_web.test/install/`) incluso en entornos locales con virtual host.
- El valor guardado para la configuración se normaliza automáticamente al sitio base, evitando conservar `/install/` como URL pública final.
- Se mantiene compatibilidad con proxies mediante `X-Forwarded-Host` y `X-Forwarded-Proto`.

## 4.3.0 / izzy_web v1.0.11
- Rediseño completo del instalador inspirado en una interfaz corporativa limpia: cabecera superior, navegación lateral clara y panel principal sin saturación.
- Eliminados degradados decorativos del instalador; se usan colores planos IZZY, bordes suaves, sombras discretas y más aire visual.
- Responsive reforzado para escritorio, laptop, tablet y móvil sin scroll interno innecesario.
- Sitio público refinado con una presentación más sobria: menos gradientes, sombras más ligeras y contraste más controlado.

## 4.2.4 / izzy_web v1.0.10
- Eliminados los scrollbars internos horizontal y vertical del asistente en pantallas normales.
- El instalador ahora crece según su contenido; en pantallas de poca altura el scroll vertical pertenece a la página/navegador, no a paneles internos.
- Reforzado overflow-x:hidden y min-width:0 para evitar desbordes horizontales en sidebar, contenido, stepper y formularios.
- Se conserva el responsive móvil: una sola columna, sin scroll horizontal y con desplazamiento vertical natural cuando el contenido lo requiera.

## 4.2.3 / izzy_web v1.0.9
- El instalador detecta y muestra automáticamente la URL real del sitio; no requiere escribirla manualmente.
- Se propone `izzy_web` como nombre inicial de base de datos, manteniéndolo editable para compatibilidad con hosting/cPanel.
- Se reforzó la presentación del paso de base de datos sin alterar la lógica de instalación.


## izzy_web v1.0.8
- Corrected IZZY branding: complete white wordmark is used on dark surfaces and black wordmark on light surfaces.
- Enlarged the installer logo without distortion or decorative recoloring.
- Updated fresh-install defaults and safe update SQL so legacy logo-mark settings migrate to the full logo while preserving custom logos.
- Reinforced responsive behavior for public navigation, mobile hamburger menu, buttons, cards, images and administrator layouts.
- Added lightweight lazy loading for non-critical public images.
- Kept premium hover motion on dashboard/public cards with reduced-motion accessibility support.


## izzy_web v1.0.2
- Corregido el alineado visual del instalador en todos los pasos.
- Los asteriscos de campos obligatorios ahora permanecen en la misma línea del label.
- Añadido favicon IZZY por defecto en instalador, sitio público y accesos administrativos.
- Favicon del instalador con versión de caché para evitar que el navegador conserve el icono anterior.
- Instalador actualizado a 4.1.2.

## 2026-09-30 — Installer alignment + default favicon
- Corrected installer field layout so labels, required asterisks and inputs stay aligned on the same baseline.
- Preserved responsive behavior and checkbox card layout.
- Added the IZZY mark as the default browser-tab favicon in installer, public site and admin when no custom favicon is configured.
- Bumped installer version to 4.1.1.
# Changelog

## Social Networks & Installation Confirmation — 2026-09-29

- Added the neutral, reusable Social Networks administration module with five configurable platforms, local SVG icons, permissions, live preview, responsive placement and public rendering.
- Added synchronized fresh-install and cumulative database definitions for `social_links`, its display settings and `social.manage` permission.
- Corrected premium selects so the closed control remains neutral and the arrow stays vertically centered; blue is reserved for the selected dropdown option and option hover.
- Added a responsive, accessible final installation confirmation using the Core local modal component; no native `confirm()` is used.

## Administrator Onboarding & Local UI Dialogs — 2026-09-28

- Added username-or-email login and secure one-time login prefill after installation or Owner setup.
- Restyled Remember me as a premium accessible selector while preserving the existing 30-day server-side token flow.
- Preserved the complete password-recovery flow and now carries a valid entered email into the recovery screen.
- Added a neutral executive administrator welcome template and non-blocking welcome delivery after installation, Owner recovery and administrator creation.
- Added clear welcome-delivery status without ever blocking account creation or installation when email delivery is unavailable.
- Hardened the bundled local SweetAlert-compatible confirmation layer with focus trapping, Escape handling, viewport-safe sizing and focus restoration.
- Added a reusable local `CMSModal` component with info, success, warning and danger variants, responsive actions and an accessible JavaScript API.
- Kept ShowNotify success, error, information and warning feedback fully local and unchanged.

## Executive Email System & Installer Alignment — 2026-09-28

- Rebuilt the shared HTML email foundation as a neutral executive template using email-safe tables, inline styles, navy/blue hierarchy and responsive mobile rules.
- Applied the same premium wrapper to delivery tests, estimate notifications, customer confirmations, administrator responses, password resets and ownership security notices.
- Added a clear verified-delivery panel to SMTP and Microsoft Graph test emails.
- Aligned the installer test-recipient field and **Probar configuración** button in one responsive row instead of leaving the action visually detached below the input.
- Added local ShowNotify success/error feedback while preserving the visible inline test result for accessibility.
- Kept the complete four-step installation and clean-reinstallation flow unchanged.

## Installation Lock, Clean Reinstallation & User Profile — 2026-09-28

- Made `config/install.lock` the authoritative installation state and ensured it is created only after successful finalization.
- Added automatic clean-reinstallation mode when configuration exists but the lock is removed.
- Reused saved MySQL host, port, database, user and unchanged password without requiring manual database deletion.
- Added a protected schema reset that drops only the 34 tables declared by the CMS, brackets the operation with foreign-key checks and never drops the database.
- Added `schema.sql` as the installer source while retaining synchronized `database.sql` compatibility.
- Rebuilt the assistant as Database → Administrator → Email → Confirmation, with one readable step at a time and internal scrolling on short screens.
- Added automatic root/subfolder site URL detection, atomic `config/config.php` writing, JSON lock metadata and rollback of prior configuration when finalization fails.
- Added the `admin/login.php?installed=1` completion message.
- Expanded the topbar profile menu with user identity, role, public-site access and permission-aware Email/Settings links.
- Hardened profile updates with duplicate username/email checks and optional password changes requiring the current password and an eight-character minimum.

## Optional Email Setup in Installer — 2026-09-28

- Expanded the guided installation from three to four real steps: server requirements, database, email delivery and Owner account.
- Added a live pre-install PHP requirements check with Available/Missing states and a cache-busting recheck action.
- Rebuilt Email delivery as Step 3 of 4 with selectable Configure later, SMTP and Microsoft Graph cards plus Back and Next navigation.
- Rebuilt the complete four-step installation experience as a professional guided assistant with a navy progress panel, contextual workspace and consistent Owner setup.
- Added dedicated tablet and small-mobile layouts that preserve step progress, readable fields and full-width actions without horizontal overflow.
- Added an optional email-delivery step between database installation and Owner creation.
- Added mutually exclusive SMTP and Microsoft Graph panels so only the selected method's fields are loaded and submitted.
- Added a real connection test that preserves the entered form values while reporting transport success or failure.
- Added multi-purpose connection assignment using the existing encrypted `correo` storage and EmailService implementation.
- Added a safe **Configure email later** route that completes installation without creating an incomplete connection.
- Delayed creation of `config/install.lock` until email configuration is saved or explicitly postponed.
- Kept the installer fully responsive and made no database schema changes.

## Mobile Navigation & Multilayer Form Protection — 2026-09-23

- Rebuilt the public mobile navigation as a fixed, scrollable panel below the sticky header without moving the Hero.
- Added an accessible hamburger-to-close control, active states, outside-click/Escape/link/resize/orientation closing and reliable body-scroll restoration.
- Added a server-validated honeypot, server-issued minimum-time token, per-session cooldown and hourly submission limit without storing IP addresses.
- Added a conservative multi-signal filter for obvious unsolicited sales promotions.
- Added optional Cloudflare Turnstile explicit rendering and mandatory server-side Siteverify validation before persistence or email delivery.
- Added encrypted Turnstile secret storage, keep/remove behavior and incomplete-configuration protection in Settings.
- Added neutral defaults for fresh installations without introducing a new table or schema migration.

## Configurable Lead Capture & Private Traffic — 2026-09-16

- Added administrator-controlled required fields for the public request form while keeping Email permanently required.
- Added English/Spanish referral catalogs, conditional Other/Otro details and dedicated lead-source persistence.
- Added meaningful-message validation using configurable useful-character and useful-word thresholds on both client and server.
- Added a lightweight private visit counter backed by `settings`, a daily first-party HttpOnly cookie and transactional row locking.
- Excluded administrator previews, common bots, crawlers and known uptime monitors without storing IP addresses or personal identifiers.
- Added clearly labeled Website Traffic cards to the Dashboard and Private website visits controls to Settings.
- Added automatic file modification versioning for public and administrator CSS/JavaScript.
- Corrected the public request layout so informational columns keep content-based height and become single-column on smaller screens.
- Added cumulative, duplicate-safe lead-source columns and neutral defaults for fresh installations.

## Complete Email Configuration — 2026-09-15

- Removed the duplicated general Sender email field from the administrator.
- Made SMTP user / sender email and Graph User / mailbox the authoritative method-specific senders.
- Added optional Internal destination with automatic fallback to the selected method sender.
- Added optional multi-address CC with comma/semicolon parsing for SMTP and Microsoft Graph.
- Added Reply-To support for public estimate notifications while preserving database-first submission handling.
- Added activation validation, real test fallback, controlled delivery errors and administrative failure notifications.
- Extended Copy configuration to include routing fields and encrypted secrets without browser-native selection errors.
- Added strict SMTP/Graph panel separation and dynamic method help.
- Improved all administrator custom selects with neutral states and complete keyboard navigation.
- Added cumulative idempotent `destinatario` and `copia` migration calls and updated the fresh-install schema.

## Automatic PHP Requirements Diagnostic — 2026-09-15

- Added live PHP 8+, PDO, PDO MySQL, OpenSSL, cURL, Fileinfo, ZIP/ZipArchive, JSON, Session, Filter and Hash checks to Website Health.
- Added Available/Missing states, capability descriptions and requirement-specific WHM enablement instructions.
- Added a global missing-requirements alert and a cache-busting **↻ Recheck server** action.
- Added secure Fileinfo availability guards so uploads return an understandable message instead of a `Class finfo not found` fatal error.
- Kept real MIME validation mandatory and made no database changes.

## Reusable Email Connections — 2026-09-14

- Added one-click copying of an SMTP or Microsoft Graph connection to multiple email purposes.
- Preserved encrypted SMTP passwords and Microsoft Graph client secrets without exposing them in the browser.
- Added multi-select destinations, Select all, Clear and active/inactive copy options.
- Added SweetAlert confirmation before replacing active purpose connections.
- Reused existing `correo` and `correo_tipo` structures without database changes.
- Enforced one active connection per email purpose when copying, saving or manually activating a configuration.
- Added Activity Center records for create, update, copy, delete, status and test operations.
- Added a responsive copy dialog with icon-and-text controls and no outside-click dismissal.
- Replaced browser-native destination validation with controlled CMS validation to prevent invalid empty-selection prompts.

## Generic Projects / Case Studies Manager — 2026-09-12

- Promoted the existing Gallery storage layer into a complete Projects / Case Studies administrator.
- Added first-class project creation without manual SQL or preloaded records.
- Reused one validated save flow for both insert and update operations.
- Added optional English/default and Spanish project names, categories and descriptions.
- Added project URL, cover selection, order and published/unpublished status.
- Preserved drag and drop, clipboard paste, file chooser, Media Library reuse and real image previews.
- Added SweetAlert deletion confirmation with no native confirmation fallback.
- Added responsive project forms and uniform administrator/public cards.
- Added cumulative, duplicate-safe MySQL/MariaDB schema updates and the complete fresh-install schema.
- Preserved the existing `gallery.manage` permission and `gallery.php` route for backward compatibility.

## Admin Base Premium + Complete Section Manager — 2026-09-03

- Established the official neutral navy-and-blue administrator token system.
- Improved header control sizing, contrast, responsive behavior and logo-free fallback.
- Reserved green for positive states instead of primary administrator branding.
- Added complete Section Manager controls for visibility, public-menu inclusion and menu style.
- Added desktop drag and drop, touch/pointer reordering and accessible Move up/Move down controls.
- Added transactional automatic `sort_order` renumbering.
- Replaced the hardcoded public navigation and footer links with the ordered section registry.
- Updated active-section tracking to follow the configured public menu.
- Added idempotent section-navigation schema updates for MySQL/MariaDB and fresh installations.
- Removed bundled external stock-image fallbacks and remaining industry-specific public copy.
- Preserved all existing CMS, media, approval, security, request, integration and preview capabilities.

## Phase 1 Stable V2 — 2026-09-02

- Added optional service badges/icons.
- Added flexible company-artwork management with Mission/Vision text fallback.
- Added multi-video management for YouTube, YouTube Shorts, Vimeo and MP4/WEBM.
- Added drag/drop, clipboard paste and file chooser media controls where supported.
- Added administrator video previews.
- Standardized public video cards to equal responsive media frames.
- Added Videos to permissions, Page Content, navigation and Section Manager.
- Updated the first-time installer to a responsive three-step wizard.
- Consolidated `database-update.sql` as the cumulative existing-install migration.
- Updated `database.sql` as the complete neutral fresh-install schema.
- Removed project-specific branding, media, credentials and developer identity from the reusable core.
## v1.0.4
- Reemplacé el botón textual Ver/Ocultar por iconos de ojo y ojo tachado en todos los campos de contraseña del instalador.
- Oculté el control nativo de revelado de contraseña de Edge para evitar iconos duplicados.
- Amplié el campo Contraseña MySQL para que el texto de ayuda tenga mejor lectura y mantenga una composición limpia.
- Ajusté el comportamiento responsive del campo de contraseña y actualicé el instalador a 4.1.4.


## izzy_web v1.0.5 - Instalador Premium
- Rediseño visual completo del asistente de instalación con estética premium IZZY.
- Branding IZZY integrado en el panel lateral.
- Mejoras en jerarquía visual, pasos, progreso, tarjetas, formularios, inputs, botones y estados.
- Responsive reforzado para escritorio, tablet y móvil.
- Hover, focus, sombras, microinteracciones y transiciones más modernas.
- Conserva el flujo funcional de 4 pasos y controles de seguridad existentes.
- Instalador actualizado a versión 4.2.0.

## 2026-10-01 — Instalador compacto y responsive
- Paso 1 reorganizado en dos filas: Servidor + Puerto y Base de datos + Usuario MySQL.
- Pasos 1–3 compactados para aprovechar el 95% del viewport sin mostrar scroll cuando el contenido cabe.
- Scroll vertical queda en modo automático y solo aparece cuando existe overflow real, incluyendo el paso 4.
- Footer de acciones permanece siempre visible y el diseño conserva adaptación móvil de una columna.

## v1.0.24
- El recorrido con Tab ignora los botones de mostrar/ocultar contraseña y continúa entre campos.
- SweetAlert2 queda centrado de forma estricta respecto al viewport, sin desplazamientos acumulados.
- showNotify queda aislado de estilos/iconos globales para evitar deformaciones y conservar su distribución premium.


## v1.0.25
- Recargar el instalador reinicia por completo los 4 pasos y vuelve al paso 1.
- Se eliminan borradores de sessionStorage al recargar; ya no se restauran datos descartados.
- El estado PHP temporal del instalador se elimina al reiniciar, incluyendo administrador y correo.
- Se conservan únicamente los valores por defecto de una instalación nueva (por ejemplo localhost, 3306 y nombre sugerido de BD).

## v1.0.26
- Paso Administrador: correo ocupa 100% del ancho.
- Contraseña y Confirmar contraseña quedan en una sola fila al 50% cada una en escritorio y se apilan de forma responsive en móvil/tablet.
- Se agregó el bloque informativo de seguridad en el espacio disponible: hash seguro, Rol Owner, acceso por usuario/correo y credencial protegida.
- Se mantiene la navegación por Tab saltando los botones de mostrar contraseña.

## v1.0.27
- Restaurada la confirmación premium al recargar/F5 cuando hay datos.
- Opción explícita para conservar datos o reiniciar el asistente.
- Confirmación final de instalación rediseñada con estilo formal y centrado estable.
- Conservación temporal de campos durante recarga mediante sessionStorage del tab.

## v1.0.32
- Rediseño premium de landing pública.
- Planes compactos con expansión bajo demanda.
- Navegación activa por sección.
- Rotador animado de capacidades IZZY.
- Corrección de doble X en lightbox.
- Corrección de espacios vacíos en galería.
- Tratamiento visual de marca IZZY reforzado.

## v1.0.34
- Public social dock is now fixed icon-only with no expanding hover labels.
- Admin profile control readability corrected with strong contrast and truncation.
- Admin sidebar refined with premium grouped navigation, icon capsules, active indicator, counters, hover and responsive behavior.

## v1.0.36
- Added a configurable **Ingresar a IZZY** link to the public navigation.
- The application link is visible in desktop and mobile hamburger navigation.
- Added admin settings for link visibility, label, destination URL and new-tab behavior.
- Default destination: `https://sistema.izzycloud.app/`.

## v1.0.37
- Updated the default public **Ingresar a IZZY** destination to `https://sistema.izzycloud.app/`.
- Existing installations using the previous default `https://app.izzycloud.app/` are migrated automatically by the cumulative database update.

## v1.0.69
- Instalador: retirada la confirmación visible de recreación de tablas; el comportamiento de reinstalación queda implícito dentro del flujo existente.
- Los checkbox del instalador mantienen siempre el texto alineado a la derecha del control, incluso cuando el texto ocupa más de una línea.
- Ajuste compacto sin aumentar la altura general del asistente y conservando el flujo funcional de 4 pasos.
