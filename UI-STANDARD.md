# IZZY UI Standard

This file is the implementation contract for the installer, published site and administrator UI.

## Local UI libraries
- Notifications: `assets/vendor/show-notify/` (`success`, `error`, `danger`, `warning`, `info`).
- Confirmations/dialogs: `assets/vendor/sweetalert2/` through `Swal.fire`.
- Selects: local Select2-compatible assets in `assets/vendor/select2/`, initialized globally.
- Local jQuery dependency: `assets/vendor/jquery/jquery.min.js`.
- Shared rules: `assets/ui-standards.css` and `assets/ui-standards.js`.

No CDN may be introduced for those UI libraries.

## Interaction rules
- Do not use native `alert()` or `confirm()`.
- Action buttons must use a meaningful icon and must not use a white action surface.
- Hover states must remain readable and fully opaque.
- Dialog/modal backdrop clicks never close the dialog. Close only by explicit Close/Cancel/X controls or Escape.
- File inputs must support browse/select, drag/drop and paste when the browser supplies files.
- Admin KPI cards use readable high-contrast values and subtle hover movement.

## Installer
- Wizard shell targets 95% of the available viewport.
- The action footer stays visible while only the step content scrolls vertically when required.
- Layout follows a 12-column responsive grid and does not use HTML tables for layout.
- Back navigation preserves values within the current browser tab.
- Reload/F5 uses the local `Swal.fire` flow to choose whether to keep or discard the captured draft.
- Step 1 validates only MySQL/MariaDB server access. The selected database and project schema are created only after final confirmation.

### Reload behavior
- F5 / Ctrl+R / Cmd+R are intercepted with the local `Swal.fire` confirmation.
- Browser-toolbar reload cannot be stopped with a custom modal without falling back to the browser native dialog; instead the draft is retained in `sessionStorage` and, immediately after reload, IZZY asks with local `Swal.fire` whether to restore or discard it.
- No native `beforeunload`/`confirm()` prompt is used.
