<div align="center">

# CMS Core

CMS Core includes an independent, permission-aware **Social Networks** module. Administrators can configure Instagram, Facebook, TikTok, YouTube and LinkedIn without hardcoded public links, choose their order, size, icon presentation, placement and responsive visibility. All public icons are inline local SVG and require no external icon library.

The installation wizard ends with an accessible premium confirmation dialog before creating the final configuration and `config/install.lock`. New installations and clean reinstallations use distinct confirmation copy.

### Reusable PHP/MySQL content-management foundation for professional websites

**Content · Media · Security · SEO · Design Controls · Requests · Backups · Integrations**

</div>

---

## Overview

CMS Core is a **clean, reusable single-site CMS foundation** designed for professional website projects that need a friendly administrator without requiring end users to edit source code.

The package is intentionally neutral: it contains **no client name, logo, banner, contact information, videos, service artwork, credentials, or project-specific branding**. New implementations start from this core and add their own identity through the administrator or a dedicated project branch.

> [!IMPORTANT]
> This repository is the reusable foundation. Keep project-specific assets and business data out of the core so improvements can be reused safely across future implementations.

---

## Core capabilities

### Content & publishing

| Capability | What it provides |
|---|---|
| 📝 **Page Content** | Section-focused editing with draft, preview and publish workflow. |
| 🧩 **Section Manager** | Show, hide and reorder real public sections and their navigation items from desktop or touch devices. |
| ✅ **Approval Queue** | Review submitted drafts before publication when approval is required. |
| 🕘 **Version History** | Preserve and restore previous content versions. |
| 👁️ **Focused Preview** | Preview the section being edited before publishing. |

### Media & presentation

| Capability | What it provides |
|---|---|
| 🖼️ **Media Library** | Upload, search, preview and reuse website files. |
| 🛠️ **Services** | Manage services, details, order, visibility and optional custom badges/icons. |
| 📁 **Projects / Case Studies** | Create, edit, order, publish and remove projects with bilingual-ready copy, URL and reusable cover media. |
| 🎬 **Videos** | YouTube, YouTube Shorts, Vimeo, MP4 and WEBM support. |
| ▶️ **Admin Video Preview** | Preview saved videos directly inside the administrator. |
| 🖼️ **Video Covers** | Optional poster/cover image with drag-and-drop, paste and file chooser. |
| 🏷️ **Company Artwork** | Publish one or multiple Mission, Vision, values or general brand artworks. |
| 🔁 **Artwork Fallback** | If no artwork is published, editable Mission/Vision text remains available. |
| 📍 **Service Areas** | Manage coverage locations and a configurable public map. |
| 💡 **Tips** | Publish short educational or helpful links. |
| 🔗 **Social Networks** | Configure five ordered channels, local SVG icons, presentation, placement and responsive visibility without hardcoded URLs. |

### Design & appearance

- 🎨 Theme presets and custom brand colors.
- 🔤 Configurable typography with desktop, tablet and mobile sizing.
- 🖥️ Header/banner support for image, uploaded video, YouTube or Vimeo.
- 🧭 Navigation position, alignment, logo visibility and sticky behavior.
- 📱 Responsive public website and responsive administrator.
- ♿ Reduced-motion support.
- 🚫 No dependency on gradients; visual identity remains fully configurable.

### Official administrator theme tokens

The administrator uses a neutral navy-and-blue base. Derived projects can change its identity from the token block at the top of `admin/admin.css` without rewriting components:

```css
--admin-primary
--admin-primary-hover
--admin-primary-dark
--admin-sidebar
--admin-sidebar-secondary
--admin-accent
--admin-background
--admin-surface
--admin-text
--admin-muted
--admin-border
--admin-success
--admin-warning
--admin-danger
```

Green is reserved for positive status feedback. The base administrator contains no gradients or white action buttons.

### Section ordering contract

Each public section is registered in `site_sections` with a stable `section_key`, anchor, administrator label, navigation label, public visibility, navigation visibility, navigation style and `sort_order`. Section Manager renumbers records in one transaction, and the public landing page and menus read the same ordered registry.

### Projects / Case Studies contract

The project manager uses the existing `gallery` storage layer and `gallery.manage` permission for backward compatibility. One shared save handler creates and updates records, while the administrator provides project name, optional Spanish translation, category, description, project URL, cover image, order and publication status. Images support drag and drop, clipboard paste, file chooser, Media Library reuse, preview and replacement.

### Email delivery contract

Each Email Purpose can have one active SMTP or Microsoft Graph configuration. SMTP uses **SMTP user / sender email** as both its login and sender; Microsoft Graph uses **Graph User / mailbox** as its sender and never asks for the mailbox password. Passwords and client secrets are encrypted at rest.

### Configurable lead capture and private visits

Settings controls whether Name, Phone, Request type / Service, How did you hear about us? and Message are required. Email remains permanently required. Referral options are maintained independently for English and Spanish; Other/Otro reveals a required detail field. Meaningful-message limits are enforced again on the server, where spaces and punctuation do not count toward the configured character and word thresholds.

The private website counter uses the existing `settings` key/value storage. It records only estimated totals, today's date/count and the last counted time. A first-party HttpOnly, SameSite=Lax daily cookie prevents repeated refreshes from the same browser from incrementing the counter. It stores no IP address, fingerprint, email, name or precise location. Cookie removal, different browsers and different devices can produce additional counts, so the values are operational estimates rather than unique-person analytics.

### Public form protection

The public request form includes configurable local defenses: a honeypot, a server-issued minimum-time token, a per-session cooldown, an hourly successful-submission limit and a conservative multi-signal filter for obvious unsolicited sales offers. These controls store no IP address, fingerprint or precise location.

Cloudflare Turnstile is optional. Configure a Managed widget in Cloudflare, then save its Site Key and Secret Key under **Settings → Public form protection**. The secret is encrypted and is never returned to the browser or displayed again. When enabled, every token is verified server-side with the `contact_inquiry` action before any request, attachment, notification or email is created. If either key is missing, submissions remain blocked until the configuration is completed or Turnstile is disabled.

Anti-spam is a defense-in-depth measure that considerably reduces automated and promotional submissions; it cannot guarantee removal of all spam.

`Internal destination` and `Optional copy / CC` are optional. When the internal destination is empty, the CMS falls back to the sender for the selected method. CC accepts one or several comma- or semicolon-separated addresses and travels through the same SMTP or Graph connection. Public request notifications include the visitor address as Reply-To, while the request remains stored even when delivery fails.

### Administration & security

- 👥 Multiple administrator accounts.
- 🛡️ Owner, Administrator, Editor, Sales and Viewer system roles.
- 🔐 Granular permissions and custom roles.
- 🔑 Secure password reset.
- 👋 Executive welcome email for newly created administrator accounts when an email transport is available.
- ✅ Username-or-email login, post-install email prefill and a secure 30-day Remember me option.
- 🧱 CSRF protection.
- 📲 Optional TOTP two-factor authentication.
- 🖥️ Active-session management and login history.
- 🔔 Notifications and Activity Center.
- 🧰 Website Health checks with live PHP requirement diagnostics and one-click server recheck.
- 💾 Backup & Restore with server-readiness validation.
- ✉️ SMTP and Microsoft Graph email configuration.
- 📋 Reuse one encrypted SMTP or Graph connection across several email purposes without entering credentials repeatedly.
- 🔌 Encrypted integration/API credentials.
- 🔎 SEO title, description, robots and social-sharing image controls.
- 🚧 Maintenance mode with private administrator preview.
- 💬 Configurable floating WhatsApp contact button.

---

## Installation

### Requirements

- PHP 8.0 or newer.
- MySQL or MariaDB with `utf8mb4` support.
- PDO and PDO MySQL.
- OpenSSL, cURL, Fileinfo and ZIP/ZipArchive.
- JSON, Session, Filter and Hash.
- Writable `uploads/` directory.

The administrator's **Website Health** page checks these PHP capabilities directly on every request. After enabling an extension in WHM, use **↻ Recheck server** to refresh its state without clearing the browser cache manually.

Uploads require Fileinfo for real MIME inspection. If it is unavailable, the CMS stops the upload with a clear configuration message instead of trusting the browser-provided file type.

### 4-step setup wizard

1. Upload or extract the project into the web root.
2. Create an empty MySQL database and database user.
3. Open `/install/` in the browser.
4. Complete the responsive professional setup assistant:

| Step | Purpose |
|---|---|
| **1 · Database** | Validate MySQL/MariaDB, install `schema.sql` and generate the application encryption key. |
| **2 · Administrator** | Create the first protected Owner account with duplicate-safe username and email validation. |
| **3 · Email** | Choose Configure later, SMTP or Microsoft Graph; run a real test and copy one connection to every available email purpose. |
| **4 · Confirmation** | Review the automatic site URL, database, Owner and email method before finalizing. |

After setup, sign in through `/admin/` and configure the site identity, content and modules. When email setup was postponed, the same complete configuration remains available under **Email Configuration**.

Every outgoing message uses the shared executive HTML template in `core/emailTemplates.php`. This includes connection tests, administrator welcome messages, estimate notifications, customer confirmations, administrator replies and account-security messages. The template uses neutral CMS branding, an email-client-safe table layout, inline styling and a responsive mobile fallback.

The installer prefills the new administrator email on the first login screen. Authentication accepts either username or email. Selecting **Remember me** creates a revocable server-side token and an HttpOnly SameSite cookie; passwords are never stored in the browser.

ShowNotify, the SweetAlert-compatible confirmation layer and the reusable `CMSModal` dialog are bundled under `assets/vendor/`. They require no CDN or external JavaScript/CSS service.

The wizard creates these environment-specific files automatically:

```text
config/config.php
config/app.key
config/install.lock
```

> [!WARNING]
> Never copy those files from one installation to another and never commit real credentials to the repository. `config/install.lock` is never included in a release and is created only after every installation step succeeds.

### Clean reinstallation

To reinstall without deleting the database or connection file manually, remove only `config/install.lock` and open `/install/`.

The installer detects **Clean reinstallation**, preloads host, port, database and user from `config/config.php`, and reuses the stored MySQL password when the password field remains empty. It never executes `DROP DATABASE`; it temporarily disables foreign-key checks and recreates only the tables explicitly declared by `schema.sql`. Unrelated tables in the same database remain untouched.

If finalization fails, the prior `config/config.php` is restored and no installation lock is left behind. The lock is a JSON file containing the installation date, detected site URL and installer version, and is always the final file created.

---

## Database strategy

The project keeps two fresh-install schema files plus one single cumulative updater for existing installations:

| File | Purpose |
|---|---|
| `schema.sql` | Authoritative schema and neutral starter data used by the installer. |
| `database.sql` | Synchronized complete database for manual fresh installations and compatibility. |
| `database-update.sql` | **Only cumulative upgrade script** for existing compatible installations. Safe to re-run. |

### Fresh installation

Use the browser installer. It imports `schema.sql` automatically.

### Existing installation

Back up the site and database first, then execute `database-update.sql`.

The complete Email Configuration upgrade adds the optional `correo.destinatario` and `correo.copia` columns. Existing installations must run the cumulative update once before saving or copying configurations. Existing records and encrypted secrets are preserved.

The cumulative update is designed to be safe to re-run by using guarded operations such as:

```sql
CREATE TABLE IF NOT EXISTS
CALL cms_core_safe_alter(...)
INSERT IGNORE
ON DUPLICATE KEY UPDATE
WHERE NOT EXISTS
```

The temporary `cms_core_safe_alter` procedure catches duplicate-column and duplicate-index errors. This keeps schema updates idempotent on shared-hosting MySQL/MariaDB versions without relying on `ADD COLUMN IF NOT EXISTS` or `INFORMATION_SCHEMA`.

> [!CAUTION]
> **Never import `database.sql` over an existing production database.** It is the full fresh-install schema, not an upgrade script.

---

## Media upload experience

Where file uploads are exposed by the current module, the preferred CMS interaction pattern is:

- drag and drop;
- paste from the clipboard;
- native file chooser;
- selected-file preview;
- clear/replace action;
- responsive layout without overlapping controls.

This keeps media management usable for non-technical administrators on desktop, tablet and mobile.

---

## Video workflow

The Videos module supports an unlimited reusable collection rather than a fixed number of videos.

### Supported sources

- YouTube videos.
- YouTube Shorts.
- Vimeo.
- Uploaded MP4.
- Uploaded WEBM.

### Public presentation

- two equal media cards per row on sufficiently wide screens;
- one column on smaller screens;
- consistent visual frame regardless of horizontal or vertical source;
- no automatic playback;
- responsive containment so vertical videos do not stretch the page.

### Administrator

Saved videos can be previewed directly in the administrator before or after publication. Direct uploads can use an optional poster image for a cleaner presentation.

---

## Flexible company artwork

The core does not force a website to use exactly two Mission/Vision images.

A project may publish:

- one combined poster;
- separate Mission and Vision artwork;
- multiple approved company/value graphics;
- no artwork at all.

When no artwork is published, the public site falls back to editable Mission and Vision text instead of leaving an empty section.

---

## Project structure

```text
/admin/                 Administrator interface
/assets/                Public CSS, JavaScript and local UI libraries
/config/                Bootstrap and environment configuration
/core/                  Shared application services
/install/               First-time setup wizard
/uploads/               User-generated media and backups
  about-artworks/
  backups/
  estimates/
  gallery/               Project and case-study covers
  media/
  service-badges/
  videos/
database.sql            Complete fresh-install database
database-update.sql     Cumulative upgrade database
estimate-submit.php     Public request handler
index.php               Public website
```

---

## Repository hygiene

The reusable core should remain free of installation secrets and project-specific content.

Recommended `.gitignore` exclusions include:

```text
config/config.php
config/app.key
config/install.lock
```

Uploaded production media should also be handled according to the deployment strategy rather than committed as reusable core assets.

---

## Recommended branching model

```text
cms-core-main
│
├── project-a
├── project-b
├── project-c
└── phase2-multitenant
```

### Rule of thumb

**Project branch → business-specific implementation**  
**Core branch → only reusable functionality**

Promote a change back into the core only when it works without relying on a specific business name, logo, dataset, workflow or media file.

---

## Release checklist

Before declaring a core build stable:

- [ ] Run PHP syntax validation across every PHP file.
- [ ] Validate administrator JavaScript.
- [ ] Validate public JavaScript.
- [ ] Confirm `database.sql` supports a clean installation.
- [ ] Confirm `database-update.sql` is safe for the intended upgrade path.
- [ ] Verify login, logout and password reset.
- [ ] Verify Owner setup and 2FA flow.
- [ ] Verify roles and permissions.
- [ ] Verify Page Content draft/preview/publish workflow.
- [ ] Verify Section Manager ordering and visibility.
- [ ] Verify drag/drop, clipboard paste and file chooser uploads.
- [ ] Verify service badge upload/replacement.
- [ ] Verify company-artwork fallback behavior.
- [ ] Verify YouTube, Shorts, Vimeo and uploaded video playback.
- [ ] Verify administrator video previews.
- [ ] Verify project create, edit, publish, unpublish and SweetAlert delete flows.
- [ ] Verify project cover upload, clipboard paste, preview, replacement and Media Library reuse.
- [ ] Verify Projects / Case Studies, Media Library and Service Areas.
- [ ] Verify SEO, Website Health and maintenance mode.
- [ ] Verify Backup & Restore readiness checks.
- [ ] Test desktop, tablet and mobile layouts.
- [ ] Confirm there are no project-specific assets, credentials or names in the core.

---

## Current release

### Phase 1 · Stable reusable core with Projects / Case Studies

This package is intended to be the starting point for new single-site implementations. It can be branded and populated through the CMS while the reusable source remains neutral.

Future architectural work such as multitenancy, multiple independent pages, page building and tenant isolation should evolve from this neutral core rather than from an individual project implementation.
