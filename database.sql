-- ============================================================
-- CMS CORE - COMPLETE FRESH INSTALL DATABASE
-- Current schema includes: core CMS, roles/security, media,
-- service badges, videos and flexible organization artwork.
-- Use ONLY for a new/fresh installation.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(80) NOT NULL,
  full_name VARCHAR(150) NULL,
  email VARCHAR(180) NULL,
  avatar_path VARCHAR(500) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role_id INT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL,
  last_login_at DATETIME NULL,
  last_login_ip VARCHAR(64) NULL,
  last_user_agent VARCHAR(500) NULL,
  two_factor_secret_enc TEXT NULL,
  two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_admin_username (username), KEY idx_admin_email (email), KEY idx_admin_role_active(role_id,active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_password_resets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_reset_token (token_hash),
  KEY idx_admin_reset_user (admin_id),
  KEY idx_admin_reset_expiry (expires_at),
  CONSTRAINT fk_admin_reset_user FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_remember_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NOT NULL,
  selector CHAR(18) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_remember_selector (selector),
  KEY idx_admin_remember_user (admin_id),
  KEY idx_admin_remember_expiry (expires_at),
  CONSTRAINT fk_admin_remember_user FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_content (
  content_key VARCHAR(100) NOT NULL,
  content_value TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (content_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_links (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  platform VARCHAR(40) NOT NULL,
  url VARCHAR(700) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_social_platform (platform),
  KEY idx_social_public_order (active,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(150) NOT NULL,
  details TEXT NOT NULL,
  icon_path VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS videos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  video_type ENUM('youtube','vimeo','upload') NOT NULL DEFAULT 'youtube',
  video_url VARCHAR(700) NULL,
  file_path VARCHAR(500) NULL,
  poster_path VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_videos_active_order (active,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS about_artworks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(180) NULL,
  image_path VARCHAR(500) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_about_artworks_active_order (active,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS gallery (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(150) NOT NULL,
  title_es VARCHAR(150) NULL,
  category VARCHAR(120) NULL,
  category_es VARCHAR(120) NULL,
  description TEXT NULL,
  description_es TEXT NULL,
  image_path VARCHAR(500) NULL,
  project_url VARCHAR(700) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_gallery_public_order (active,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_areas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  area_name VARCHAR(180) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tips (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(220) NOT NULL,
  url VARCHAR(500) NOT NULL DEFAULT '#',
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estimate_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(150) NULL,
  phone VARCHAR(80) NULL,
  email VARCHAR(180) NULL,
  address VARCHAR(255) NULL,
  service_needed VARCHAR(150) NULL,
  desired_date DATE NULL,
  message TEXT NULL,
  photo_path VARCHAR(500) NULL,
  status ENUM('new','contacted','in_progress','won','lost','closed') NOT NULL DEFAULT 'new',
  assigned_to INT UNSIGNED NULL,
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  follow_up_date DATE NULL,
  internal_notes TEXT NULL,
  lead_source VARCHAR(120) NULL,
  lead_source_detail VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_estimate_status_created(status,created_at), KEY idx_estimate_assigned(assigned_to,status,follow_up_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estimate_request_flags (
  estimate_id BIGINT UNSIGNED NOT NULL,
  is_spam TINYINT(1) NOT NULL DEFAULT 0,
  archived_at DATETIME NULL,
  updated_by INT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (estimate_id),
  KEY idx_estimate_flags_state (is_spam,archived_at),
  KEY idx_estimate_flags_updated_by (updated_by),
  CONSTRAINT fk_estimate_flags_request FOREIGN KEY (estimate_id) REFERENCES estimate_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_estimate_flags_admin FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estimate_replies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  estimate_id BIGINT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED NULL,
  recipient_email VARCHAR(180) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_estimate_replies_request (estimate_id,created_at),
  KEY idx_estimate_replies_admin (admin_id,created_at),
  CONSTRAINT fk_estimate_replies_request FOREIGN KEY (estimate_id) REFERENCES estimate_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_estimate_replies_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estimate_reply_attachments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reply_id BIGINT UNSIGNED NOT NULL,
  estimate_id BIGINT UNSIGNED NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_estimate_reply_attachments_reply (reply_id),
  KEY idx_estimate_reply_attachments_estimate (estimate_id),
  CONSTRAINT fk_estimate_reply_attachments_reply FOREIGN KEY (reply_id) REFERENCES estimate_replies(id) ON DELETE CASCADE,
  CONSTRAINT fk_estimate_reply_attachments_request FOREIGN KEY (estimate_id) REFERENCES estimate_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estimate_attachments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  estimate_id BIGINT UNSIGNED NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  original_name VARCHAR(255) NULL,
  mime_type VARCHAR(100) NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_estimate_attachment_estimate (estimate_id),
  CONSTRAINT fk_estimate_attachment_request FOREIGN KEY (estimate_id) REFERENCES estimate_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS correo_tipo (
  correo_tipo_id INT NOT NULL,
  nombre VARCHAR(30) NOT NULL,
  PRIMARY KEY (correo_tipo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS correo (
  correo_id INT NOT NULL AUTO_INCREMENT COMMENT 'Identificador unico de la configuracion de correo',
  correo_tipo_id INT NOT NULL COMMENT 'Tipo de correo',
  metodo_envio ENUM('SMTP','GRAPH') NOT NULL DEFAULT 'SMTP' COMMENT 'SMTP o Microsoft Graph',
  server VARCHAR(150) NOT NULL DEFAULT '' COMMENT 'Servidor SMTP o graph.microsoft.com',
  correo VARCHAR(180) NOT NULL COMMENT 'Correo emisor',
  password TEXT NULL COMMENT 'Contrasena SMTP cifrada',
  port INT NOT NULL DEFAULT 587 COMMENT 'Puerto SMTP; Graph usa 0',
  smtp_secure VARCHAR(10) NOT NULL DEFAULT 'tls' COMMENT 'tls o ssl',
  tenant_id VARCHAR(150) DEFAULT NULL,
  client_id VARCHAR(150) DEFAULT NULL,
  client_secret TEXT NULL COMMENT 'Client secret cifrado',
  graph_user VARCHAR(180) DEFAULT NULL,
  destinatario VARCHAR(180) DEFAULT NULL COMMENT 'Optional internal destination; falls back to method sender',
  copia TEXT NULL COMMENT 'Optional comma-separated CC addresses',
  save_to_sent_items TINYINT(1) NOT NULL DEFAULT 1,
  estado TINYINT NOT NULL DEFAULT 1 COMMENT '1 Activo, 2 Inactivo',
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (correo_id),
  KEY idx_correo_tipo_estado (correo_tipo_id,estado),
  CONSTRAINT fk_correo_tipo FOREIGN KEY (correo_tipo_id) REFERENCES correo_tipo(correo_tipo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_integrations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  provider_name VARCHAR(120) NOT NULL,
  api_type VARCHAR(40) NOT NULL DEFAULT 'custom',
  category VARCHAR(80) NOT NULL DEFAULT 'General',
  environment ENUM('sandbox','live') NOT NULL DEFAULT 'sandbox',
  auth_type VARCHAR(40) NOT NULL DEFAULT 'api_key',
  base_url VARCHAR(500) NULL,
  public_key VARCHAR(500) NULL,
  secret_key TEXT NULL,
  webhook_secret TEXT NULL,
  notes TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO correo_tipo (correo_tipo_id,nombre) VALUES
(1,'Website Alerts'),(2,'Admin Security'),(3,'Estimate Requests'),(4,'Auto Replies')
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre);

INSERT INTO site_content(content_key,content_value) VALUES
('hero_eyebrow',''),
('hero_title','Page heading'),
('hero_text','Add the primary page message from Page Content.'),
('intro_title','Introduction'),
('intro_text','Add introductory content from Page Content.'),
('about_title','About'),
('about_text','Add the first paragraph for this section.'),
('about_text_2','Add an optional supporting paragraph.'),
('mission','Add mission text from Page Content.'),
('vision','Add vision text from Page Content.'),
('areas_title','Locations or coverage'),
('areas_text','Add locations or coverage information from the administrator.'),
('estimate_title','Request information'),
('estimate_text','Complete the form with the available details. Attachments are optional.'),
('contact_title','Contact')
ON DUPLICATE KEY UPDATE content_value=VALUES(content_value);

INSERT INTO settings(setting_key,setting_value) VALUES
('company_name','Website'),
('site_language','en'),
('phone',''),
('phone_digits',''),
('email',''),
('youtube',''),('facebook',''),('tiktok',''),('website',''),('business_hours',''),
('admin_brand_name','CMS Core Admin'),('admin_logo_path','assets/izzy/logo-full-dark.png'),('favicon_path','assets/izzy/logo-mark.png'),
('maintenance_mode','0'),('maintenance_title','Website maintenance'),('maintenance_text','This website is temporarily unavailable.'),('maintenance_image_path',''),
('whatsapp_enabled','0'),('whatsapp_message','Hello, I would like more information.'),('whatsapp_position','right'),
('social_size','medium'),('social_style','icon_name'),('social_location','footer'),('social_show_desktop','1'),('social_show_mobile','1'),
('service_map_enabled','0'),('service_map_query',''),('service_map_label','Service Area Map'),
('plans_show_images','0'),
('form_required_name','1'),('form_required_phone','0'),('form_required_service','0'),('form_required_referral','1'),('form_required_message','1'),
('form_message_min_characters','30'),('form_message_min_words','5'),
('form_referral_options_en','Google or another search engine\nFacebook\nInstagram\nTikTok\nWhatsApp\nRecommendation from a person or company\nI already knew the company\nOther'),
('form_referral_options_es','Google u otro buscador\nFacebook\nInstagram\nTikTok\nWhatsApp\nRecomendación de una persona o empresa\nYa conocía la empresa\nOtro'),
('form_antispam_enabled','1'),('form_block_sales_solicitation','1'),
('form_minimum_seconds','3'),('form_cooldown_seconds','60'),('form_maximum_per_hour','5'),
('turnstile_enabled','0'),('turnstile_site_key',''),('turnstile_secret_key',''),
('analytics_tracking_enabled','1'),('analytics_total_visits','0'),('analytics_today_date',''),('analytics_today_visits','0'),('analytics_last_visit_at','')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

INSERT INTO social_links(platform,url,sort_order,active) VALUES
('instagram',NULL,10,0),
('facebook',NULL,20,0),
('tiktok',NULL,30,0),
('youtube',NULL,40,0),
('linkedin',NULL,50,0)
ON DUPLICATE KEY UPDATE platform=VALUES(platform);

CREATE TABLE IF NOT EXISTS content_drafts (
  content_key VARCHAR(100) NOT NULL,
  content_value TEXT NULL,
  updated_by INT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (content_key), KEY idx_content_drafts_user (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS content_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  snapshot_json LONGTEXT NOT NULL,
  note VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_content_versions_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS site_sections (
  section_key VARCHAR(60) NOT NULL,
  label VARCHAR(120) NOT NULL,
  anchor_id VARCHAR(120) NULL,
  navigation_label VARCHAR(120) NULL,
  show_in_navigation TINYINT(1) NOT NULL DEFAULT 0,
  navigation_style VARCHAR(20) NOT NULL DEFAULT 'link',
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (section_key),
  KEY idx_site_sections_public_order (active,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS media_library (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(180) NULL,
  file_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(100) NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_media_path (file_path), KEY idx_media_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS activity_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NULL,
  action_type VARCHAR(80) NOT NULL,
  description VARCHAR(500) NOT NULL,
  metadata_json TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_activity_created (created_at), KEY idx_activity_admin (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS admin_notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  notification_type VARCHAR(40) NOT NULL DEFAULT 'info',
  title VARCHAR(180) NOT NULL,
  message VARCHAR(500) NOT NULL,
  action_url VARCHAR(500) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_notifications_read_created (is_read,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS site_backups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  backup_name VARCHAR(180) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_backups_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO site_sections(
  section_key,label,anchor_id,navigation_label,show_in_navigation,navigation_style,sort_order,active
) VALUES
('inicio','Inicio','inicio','Inicio',1,'link',10,1),
('soluciones','Soluciones','soluciones','Soluciones',1,'link',20,1),
('modalidades','Modalidades','modalidades','Modalidades',1,'link',30,1),
('planes','Planes','planes','Planes',1,'link',40,1),
('sistema','El sistema','sistema','El sistema',1,'link',50,1),
('ubicacion','Ubicación','ubicacion','Ubicación',1,'link',60,1),
('contacto','Quiero IZZY','contacto','Quiero IZZY',1,'cta',70,1)
ON DUPLICATE KEY UPDATE label=VALUES(label);
INSERT INTO settings(setting_key,setting_value) VALUES
('site_language','en'),
('seo_title','Website'),
('seo_description',''),
('seo_social_image',''),('seo_robots','index,follow'),

('developer_credit_enabled','0'),('developer_credit_text','')
ON DUPLICATE KEY UPDATE setting_value=setting_value;



CREATE TABLE IF NOT EXISTS admin_roles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_key VARCHAR(60) NOT NULL,
  role_name VARCHAR(120) NOT NULL,
  description VARCHAR(300) NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(id), UNIQUE KEY uq_admin_role_key(role_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_permissions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  permission_key VARCHAR(100) NOT NULL,
  permission_name VARCHAR(160) NOT NULL,
  permission_group VARCHAR(80) NOT NULL DEFAULT 'General',
  PRIMARY KEY(id), UNIQUE KEY uq_admin_permission_key(permission_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY(role_id,permission_id), KEY idx_role_permission_permission(permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_approvals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  submitted_by INT UNSIGNED NOT NULL,
  status ENUM('pending','approved','changes_requested','cancelled') NOT NULL DEFAULT 'pending',
  note TEXT NULL,
  reviewer_note TEXT NULL,
  reviewed_by INT UNSIGNED NULL,
  submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  PRIMARY KEY(id), KEY idx_content_approval_status(status,submitted_at), KEY idx_content_approval_submitter(submitted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estimate_notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  estimate_id BIGINT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED NULL,
  note TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(id), KEY idx_estimate_notes_request(estimate_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NOT NULL,
  session_hash CHAR(64) NOT NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(500) NULL,
  last_seen_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  PRIMARY KEY(id), UNIQUE KEY uq_admin_session_hash(session_hash), KEY idx_admin_sessions_user(admin_id,last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_login_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NULL,
  username_attempt VARCHAR(80) NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(id), KEY idx_login_events_user(admin_id,created_at), KEY idx_login_events_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_notification_reads (
  notification_id BIGINT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED NOT NULL,
  read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(notification_id,admin_id), KEY idx_notification_reads_admin(admin_id,read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO admin_roles(role_key,role_name,description,is_system,active) VALUES
('owner','Owner','Full protected ownership of the website.',1,1),
('administrator','Administrator','Full day-to-day administration except protected owner controls.',1,1),
('editor','Editor','Creates and edits website content; publishing requires approval.',1,1),
('sales','Sales','Works with estimate requests and customer follow-ups.',1,1),
('viewer','Viewer','Read-only operational access.',1,1)
ON DUPLICATE KEY UPDATE role_name=VALUES(role_name),description=VALUES(description),active=1;

INSERT INTO admin_permissions(permission_key,permission_name,permission_group) VALUES
('dashboard.view','View dashboard','General'),
('content.view','View page content','Content'),
('content.edit','Edit drafts','Content'),
('content.publish','Publish content','Content'),
('content.approve','Approve submitted content','Content'),
('sections.manage','Manage landing sections','Content'),
('media.manage','Manage Media Library','Content'),
('services.manage','Manage services','Content'),
('gallery.manage','Manage projects and case studies','Content'),
('videos.manage','Manage website videos','Content'),
('areas.manage','Manage service areas','Content'),
('tips.manage','Manage home tips','Content'),
('social.manage','Manage social networks','Content'),
('estimates.view','View estimate requests','Business'),
('estimates.manage_assigned','Manage assigned estimates','Business'),
('estimates.manage_all','Manage all estimates and assignments','Business'),
('email.manage','Manage email configuration','System'),
('integrations.manage','Manage integrations & APIs','System'),
('seo.manage','Manage SEO','Content'),
('health.view','View Website Health','General'),
('notifications.view','View notifications','General'),
('activity.view','View activity log','General'),
('backups.manage','Manage backups','System'),
('settings.manage','Manage site settings','System'),
('users.manage','Manage administrator users','Administration'),
('roles.manage','Manage roles & permissions','Administration'),
('security.manage','Manage active sessions and security','Administration')
ON DUPLICATE KEY UPDATE permission_name=VALUES(permission_name),permission_group=VALUES(permission_group);

INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='dashboard.view' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.view' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.edit' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.publish' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.approve' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='sections.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='media.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='services.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='gallery.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='videos.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='areas.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='tips.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='social.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='estimates.view' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='estimates.manage_assigned' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='estimates.manage_all' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='email.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='integrations.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='seo.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='health.view' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='notifications.view' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='activity.view' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='backups.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='settings.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='users.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='security.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='dashboard.view' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.view' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.edit' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='sections.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='media.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='services.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='gallery.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='videos.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='areas.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='tips.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='social.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='seo.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='health.view' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='notifications.view' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='dashboard.view' WHERE r.role_key='sales';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='estimates.view' WHERE r.role_key='sales';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='estimates.manage_assigned' WHERE r.role_key='sales';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='notifications.view' WHERE r.role_key='sales';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='dashboard.view' WHERE r.role_key='viewer';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.view' WHERE r.role_key='viewer';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='estimates.view' WHERE r.role_key='viewer';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='health.view' WHERE r.role_key='viewer';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='notifications.view' WHERE r.role_key='viewer';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r CROSS JOIN admin_permissions p WHERE r.role_key='owner';

SET FOREIGN_KEY_CHECKS=1;

-- IZZY v1.0.36 — configurable public link to the production application
INSERT INTO settings(setting_key,setting_value) VALUES
('system_access_enabled','1'),
('system_access_url','https://sistema.izzycloud.app/'),
('system_access_label','Ingresar a IZZY'),
('system_access_new_tab','1')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);


-- v1.0.42 NIVO floating widget defaults
INSERT INTO settings(setting_key,setting_value) VALUES
('nivo_widget_enabled','0'),
('nivo_widget_url',''),
('nivo_widget_title','NIVO Web Chat'),
('nivo_widget_greeting','¿Necesitas ayuda?')
ON DUPLICATE KEY UPDATE setting_value=setting_value;


-- v1.0.89 · IZZY public showcase defaults managed from Admin > Projects / Case Studies.
-- Existing customized records are preserved; bundled defaults are only added when their image path does not exist.
INSERT INTO gallery (title, title_es, category, category_es, description, description_es, image_path, project_url, sort_order, active)
SELECT 'Dashboard IZZY','Dashboard IZZY','IZZY','IZZY','Indicadores, reportes y control operativo desde computadora.','Indicadores, reportes y control operativo desde computadora.','assets/izzy/dashboard-desktop.png','',10,1
WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE image_path='assets/izzy/dashboard-desktop.png');

INSERT INTO gallery (title, title_es, category, category_es, description, description_es, image_path, project_url, sort_order, active)
SELECT 'Acceso seguro','Acceso seguro','IZZY','IZZY','Nuevo acceso IZZY con experiencia premium, responsive y modo demo identificado.','Nuevo acceso IZZY con experiencia premium, responsive y modo demo identificado.','assets/izzy/login-desktop.png','',20,1
WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE image_path='assets/izzy/login-desktop.png');

INSERT INTO gallery (title, title_es, category, category_es, description, description_es, image_path, project_url, sort_order, active)
SELECT 'IZZY en móvil','IZZY en móvil','IZZY','IZZY','Consulta el negocio desde tu teléfono con una interfaz adaptada.','Consulta el negocio desde tu teléfono con una interfaz adaptada.','assets/izzy/mobile-dashboard.jpeg','',30,1
WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE image_path='assets/izzy/mobile-dashboard.jpeg');

INSERT INTO gallery (title, title_es, category, category_es, description, description_es, image_path, project_url, sort_order, active)
SELECT 'Recuperación de acceso','Recuperación de acceso','IZZY','IZZY','Flujo claro y seguro para restablecer la contraseña y recuperar el acceso a IZZY.','Flujo claro y seguro para restablecer la contraseña y recuperar el acceso a IZZY.','assets/izzy/password-reset.png','',40,1
WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE image_path='assets/izzy/password-reset.png');

INSERT INTO gallery (title, title_es, category, category_es, description, description_es, image_path, project_url, sort_order, active)
SELECT 'Crear cuenta IZZY','Crear cuenta IZZY','IZZY','IZZY','Registro guiado con datos esenciales, confirmación de contraseña y recomendaciones de seguridad.','Registro guiado con datos esenciales, confirmación de contraseña y recomendaciones de seguridad.','assets/izzy/create-account.png','',50,1
WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE image_path='assets/izzy/create-account.png');
