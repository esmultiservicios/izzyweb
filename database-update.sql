-- CMS CORE - CUMULATIVE DATABASE UPDATE (SAFE TO RE-RUN)
-- Designed for the MariaDB/current-MySQL environments used by the CMS hosting.
-- CREATE TABLE IF NOT EXISTS, duplicate-safe ALTER calls, INSERT IGNORE and
-- NOT EXISTS guards prevent duplicate schema/content when re-run.
-- Do NOT use database.sql on an existing production database.

SET NAMES utf8mb4;

-- Shared-hosting compatible ALTER helper. Numeric handlers 1060 and 1061
-- safely ignore columns or indexes already present without INFORMATION_SCHEMA
-- or ADD COLUMN/INDEX IF NOT EXISTS syntax.
DELIMITER $$
DROP PROCEDURE IF EXISTS cms_core_safe_alter$$
CREATE PROCEDURE cms_core_safe_alter(IN alter_statement TEXT)
BEGIN
  DECLARE CONTINUE HANDLER FOR 1060 SET @cms_core_duplicate_column = 1;
  DECLARE CONTINUE HANDLER FOR 1061 SET @cms_core_duplicate_index = 1;

  SET @cms_core_alter_statement = alter_statement;
  PREPARE cms_core_alter FROM @cms_core_alter_statement;
  EXECUTE cms_core_alter;
  DEALLOCATE PREPARE cms_core_alter;
END$$
DELIMITER ;

-- EMAIL DESTINATION AND OPTIONAL CC
-- Duplicate column errors are handled by cms_core_safe_alter, so this remains safe to re-run.
CALL cms_core_safe_alter('ALTER TABLE correo ADD COLUMN destinatario VARCHAR(180) NULL AFTER graph_user');
CALL cms_core_safe_alter('ALTER TABLE correo ADD COLUMN copia TEXT NULL AFTER destinatario');

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

CALL cms_core_safe_alter('ALTER TABLE site_sections ADD COLUMN anchor_id VARCHAR(120) NULL AFTER label');
CALL cms_core_safe_alter('ALTER TABLE site_sections ADD COLUMN navigation_label VARCHAR(120) NULL AFTER anchor_id');
CALL cms_core_safe_alter('ALTER TABLE site_sections ADD COLUMN show_in_navigation TINYINT(1) NOT NULL DEFAULT 0 AFTER navigation_label');
CALL cms_core_safe_alter('ALTER TABLE site_sections ADD COLUMN navigation_style VARCHAR(20) NOT NULL DEFAULT ''link'' AFTER show_in_navigation');
CALL cms_core_safe_alter('ALTER TABLE site_sections ADD INDEX idx_site_sections_public_order(active,sort_order)');
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
('home','Home / Hero','home','Home',1,'link',10,1),
('intro','Introduction','intro','Introduction',0,'link',20,1),
('about','About','about','About',1,'link',30,1),
('services','Services','services','Services',1,'link',40,1),
('videos','Videos','videos','Videos',1,'link',50,1),
('gallery','Projects / Case Studies','gallery','Projects',1,'link',60,1),
('areas','Service Areas','areas','Service Areas',1,'link',70,1),
('tips','Tips','tips','Tips',0,'link',80,1),
('estimate','Request Form','estimate','Request',1,'cta',90,1),
('contact','Contact','contact','Contact',1,'link',100,1)
ON DUPLICATE KEY UPDATE section_key=VALUES(section_key);

UPDATE site_sections SET anchor_id='home',navigation_label='Home',show_in_navigation=1,navigation_style='link' WHERE section_key='home' AND navigation_label IS NULL;
UPDATE site_sections SET anchor_id='intro',navigation_label='Introduction',show_in_navigation=0,navigation_style='link' WHERE section_key='intro' AND navigation_label IS NULL;
UPDATE site_sections SET anchor_id='about',navigation_label='About',show_in_navigation=1,navigation_style='link' WHERE section_key='about' AND navigation_label IS NULL;
UPDATE site_sections SET anchor_id='services',navigation_label='Services',show_in_navigation=1,navigation_style='link' WHERE section_key='services' AND navigation_label IS NULL;
UPDATE site_sections SET anchor_id='videos',navigation_label='Videos',show_in_navigation=1,navigation_style='link' WHERE section_key='videos' AND navigation_label IS NULL;
UPDATE site_sections SET anchor_id='gallery',navigation_label='Projects',show_in_navigation=1,navigation_style='link' WHERE section_key='gallery' AND navigation_label IS NULL;
UPDATE site_sections SET anchor_id='areas',navigation_label='Service Areas',show_in_navigation=1,navigation_style='link' WHERE section_key='areas' AND navigation_label IS NULL;
UPDATE site_sections SET anchor_id='tips',navigation_label='Tips',show_in_navigation=0,navigation_style='link' WHERE section_key='tips' AND navigation_label IS NULL;
UPDATE site_sections SET anchor_id='estimate',navigation_label='Request',show_in_navigation=1,navigation_style='cta' WHERE section_key='estimate' AND navigation_label IS NULL;
UPDATE site_sections SET anchor_id='contact',navigation_label='Contact',show_in_navigation=1,navigation_style='link' WHERE section_key='contact' AND navigation_label IS NULL;
INSERT INTO settings(setting_key,setting_value) VALUES
('site_language','en'),
('seo_title','Website'),
('seo_description',''),
('seo_social_image',''),('seo_robots','index,follow'),
('service_map_enabled','0'),('service_map_query',''),('service_map_label','Service Area Map'),
('developer_credit_enabled','0'),('developer_credit_text','')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

-- USERS, ROLES, APPROVALS, SALES TRACKING & SECURITY CENTER

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
('areas.manage','Manage service areas','Content'),
('tips.manage','Manage home tips','Content'),
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
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='areas.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='tips.manage' WHERE r.role_key='administrator';
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
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='areas.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='tips.manage' WHERE r.role_key='editor';
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

CALL cms_core_safe_alter('ALTER TABLE admin_users ADD COLUMN role_id INT UNSIGNED NULL AFTER password_hash');
CALL cms_core_safe_alter('ALTER TABLE admin_users ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER role_id');
CALL cms_core_safe_alter('ALTER TABLE admin_users ADD COLUMN created_by INT UNSIGNED NULL AFTER active');
CALL cms_core_safe_alter('ALTER TABLE admin_users ADD COLUMN last_login_at DATETIME NULL AFTER created_by');
CALL cms_core_safe_alter('ALTER TABLE admin_users ADD COLUMN last_login_ip VARCHAR(64) NULL AFTER last_login_at');
CALL cms_core_safe_alter('ALTER TABLE admin_users ADD COLUMN last_user_agent VARCHAR(500) NULL AFTER last_login_ip');
CALL cms_core_safe_alter('ALTER TABLE admin_users ADD COLUMN two_factor_secret_enc TEXT NULL AFTER last_user_agent');
CALL cms_core_safe_alter('ALTER TABLE admin_users ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER two_factor_secret_enc');
CALL cms_core_safe_alter('ALTER TABLE admin_users ADD INDEX idx_admin_role_active(role_id,active)');

ALTER TABLE estimate_requests
  MODIFY COLUMN status ENUM('new','contacted','in_progress','won','lost','closed') NOT NULL DEFAULT 'new';
CALL cms_core_safe_alter('ALTER TABLE estimate_requests ADD COLUMN assigned_to INT UNSIGNED NULL AFTER status');
CALL cms_core_safe_alter('ALTER TABLE estimate_requests ADD COLUMN priority ENUM(''low'',''normal'',''high'',''urgent'') NOT NULL DEFAULT ''normal'' AFTER assigned_to');
CALL cms_core_safe_alter('ALTER TABLE estimate_requests ADD COLUMN follow_up_date DATE NULL AFTER priority');
CALL cms_core_safe_alter('ALTER TABLE estimate_requests ADD COLUMN internal_notes TEXT NULL AFTER follow_up_date');
CALL cms_core_safe_alter('ALTER TABLE estimate_requests ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
CALL cms_core_safe_alter('ALTER TABLE estimate_requests ADD INDEX idx_estimate_assigned(assigned_to,status,follow_up_date)');

UPDATE admin_users
SET role_id=(SELECT id FROM admin_roles WHERE role_key='owner' LIMIT 1)
WHERE role_id IS NULL;


-- ============================================================
-- REUSABLE MEDIA UPDATE: SERVICE BADGES + COMPANY ARTWORK + VIDEOS
-- Safe to re-run: existing structures/content are preserved and duplicate seeds are skipped.
-- ============================================================
CALL cms_core_safe_alter('ALTER TABLE services ADD COLUMN icon_path VARCHAR(500) NULL AFTER details');

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
  PRIMARY KEY (id),
  KEY idx_videos_active_order (active,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO admin_permissions(permission_key,permission_name,permission_group) VALUES
('videos.manage','Manage website videos','Content')
ON DUPLICATE KEY UPDATE permission_name=VALUES(permission_name),permission_group=VALUES(permission_group);

INSERT IGNORE INTO admin_role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='videos.manage'
WHERE r.role_key IN ('administrator','editor');


-- ============================================================
-- REUSABLE MEDIA FLEXIBILITY: ABOUT ARTWORK GALLERY + VIDEOS
-- ============================================================
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

-- GENERIC PROJECTS / CASE STUDIES
-- The legacy gallery table remains the storage layer for backward compatibility.
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

CALL cms_core_safe_alter('ALTER TABLE gallery ADD COLUMN title_es VARCHAR(150) NULL AFTER title');
CALL cms_core_safe_alter('ALTER TABLE gallery ADD COLUMN category VARCHAR(120) NULL AFTER title_es');
CALL cms_core_safe_alter('ALTER TABLE gallery ADD COLUMN category_es VARCHAR(120) NULL AFTER category');
CALL cms_core_safe_alter('ALTER TABLE gallery ADD COLUMN description TEXT NULL AFTER category_es');
CALL cms_core_safe_alter('ALTER TABLE gallery ADD COLUMN description_es TEXT NULL AFTER description');
CALL cms_core_safe_alter('ALTER TABLE gallery ADD COLUMN project_url VARCHAR(700) NULL AFTER image_path');
CALL cms_core_safe_alter('ALTER TABLE gallery ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER active');
CALL cms_core_safe_alter('ALTER TABLE gallery ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
CALL cms_core_safe_alter('ALTER TABLE gallery ADD INDEX idx_gallery_public_order(active,sort_order,id)');

-- CONFIGURABLE PUBLIC FORM, LEAD SOURCE AND PRIVATE VISIT ANALYTICS
-- Nullable lead-source columns preserve all existing requests during deployment.
CALL cms_core_safe_alter('ALTER TABLE estimate_requests ADD COLUMN lead_source VARCHAR(120) NULL AFTER internal_notes');
CALL cms_core_safe_alter('ALTER TABLE estimate_requests ADD COLUMN lead_source_detail VARCHAR(255) NULL AFTER lead_source');

INSERT INTO settings(setting_key,setting_value) VALUES
('form_required_name','1'),
('form_required_phone','0'),
('form_required_service','0'),
('form_required_referral','1'),
('form_required_message','1'),
('form_message_min_characters','30'),
('form_message_min_words','5'),
('form_referral_options_en','Google or another search engine\nFacebook\nInstagram\nTikTok\nWhatsApp\nRecommendation from a person or company\nI already knew the company\nOther'),
('form_referral_options_es','Google u otro buscador\nFacebook\nInstagram\nTikTok\nWhatsApp\nRecomendación de una persona o empresa\nYa conocía la empresa\nOtro'),
('analytics_tracking_enabled','1'),
('analytics_total_visits','0'),
('analytics_today_date',''),
('analytics_today_visits','0'),
('analytics_last_visit_at','')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

-- ============================================================
-- ADMINISTRABLE SOCIAL NETWORKS
-- Safe to re-run: existing links and display preferences remain unchanged.
-- ============================================================
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

INSERT INTO settings(setting_key,setting_value) VALUES
('social_size','medium'),
('social_style','icon_name'),
('social_location','footer'),
('social_show_desktop','1'),
('social_show_mobile','1')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

INSERT INTO social_links(platform,url,sort_order,active) VALUES
('instagram',NULL,10,0),
('facebook',NULL,20,0),
('tiktok',NULL,30,0),
('youtube',NULL,40,0),
('linkedin',NULL,50,0)
ON DUPLICATE KEY UPDATE platform=VALUES(platform);

INSERT INTO admin_permissions(permission_key,permission_name,permission_group) VALUES
('social.manage','Manage social networks','Content')
ON DUPLICATE KEY UPDATE permission_name=VALUES(permission_name),permission_group=VALUES(permission_group);

INSERT IGNORE INTO admin_role_permissions(role_id,permission_id)
SELECT r.id,p.id
FROM admin_roles r
JOIN admin_permissions p ON p.permission_key='social.manage'
WHERE r.role_key IN ('owner','administrator','editor');

-- IZZY cumulative update continues below.
-- Keep cms_core_safe_alter available until the end of the migration so
-- duplicate-safe ALTER operations work on shared-hosting MySQL/MariaDB.
CREATE TABLE IF NOT EXISTS izzy_plans (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  tagline VARCHAR(220) NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  billing_label VARCHAR(40) NOT NULL DEFAULT '/mes',
  features TEXT NOT NULL,
  image_path VARCHAR(500) NULL,
  show_image TINYINT(1) NOT NULL DEFAULT 0,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(id), UNIQUE KEY uq_izzy_plan_name(name), KEY idx_izzy_plans_public(active,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO izzy_plans(name,tagline,price,billing_label,features,image_path,show_image,featured,sort_order,active)
SELECT 'Plan Emprendedor','Ideal para comenzar',599,'/mes','Facturación electrónica con el SAR\nControl de caja\nFormatos de factura: ticket y carta\nReporte de ventas\nRegistro de productos\n1 punto de venta\n1 usuario administrador\n2 usuarios adicionales\nSoporte técnico','assets/izzy/plan-emprendedor.jpeg',1,0,10,1 WHERE NOT EXISTS(SELECT 1 FROM izzy_plans WHERE name='Plan Emprendedor');
INSERT INTO izzy_plans(name,tagline,price,billing_label,features,image_path,show_image,featured,sort_order,active)
SELECT 'Plan Básico','Más control para tu negocio',1099,'/mes','Facturación electrónica con el SAR\nControl de caja\nInventario\nCuentas por cobrar\nReportes de ventas\n1 punto de venta\n1 usuario administrador\n2 usuarios adicionales\nSoporte técnico','assets/izzy/plan-basico.jpeg',1,0,20,1 WHERE NOT EXISTS(SELECT 1 FROM izzy_plans WHERE name='Plan Básico');
INSERT INTO izzy_plans(name,tagline,price,billing_label,features,image_path,show_image,featured,sort_order,active)
SELECT 'Plan Regular','Más capacidad para crecer',1610,'/mes','Facturación electrónica con el SAR\nControl de caja\nInventario y compras\nCuentas por cobrar y pagar\n2 puntos de venta\n1 administrador + 3 usuarios\nFacturas recurrentes automáticas\nSoporte técnico','assets/izzy/plan-regular.jpeg',1,1,30,1 WHERE NOT EXISTS(SELECT 1 FROM izzy_plans WHERE name='Plan Regular');
INSERT INTO izzy_plans(name,tagline,price,billing_label,features,image_path,show_image,featured,sort_order,active)
SELECT 'Plan Estándar','Operación integral para tu negocio',2499,'/mes','Facturación electrónica con el SAR\nControl de caja\nInventario\nCompras y cotizaciones\nCuentas por cobrar y pagar\n3 puntos de venta\n1 administrador + 4 usuarios\nFacturas recurrentes automáticas','assets/izzy/plan-estandar.jpeg',1,0,40,1 WHERE NOT EXISTS(SELECT 1 FROM izzy_plans WHERE name='Plan Estándar');
INSERT INTO izzy_plans(name,tagline,price,billing_label,features,image_path,show_image,featured,sort_order,active)
SELECT 'Plan Premium','Máximo control para tu empresa',3499,'/mes','Facturación electrónica con el SAR\nMúltiples bodegas y transferencias\nCompras y cotizaciones\nReportes avanzados\nNómina y contratos\nControl de asistencia\n4 puntos de venta\n1 administrador + 10 usuarios','assets/izzy/plan-premium.jpeg',1,0,50,1 WHERE NOT EXISTS(SELECT 1 FROM izzy_plans WHERE name='Plan Premium');
INSERT INTO izzy_plans(name,tagline,price,billing_label,features,image_path,show_image,featured,sort_order,active)
SELECT 'Plan Restaurantes','Solución premium',2499,'/mes','Mesas y reservaciones\nComandas y pantalla de cocina\nVenta visual por iconos\nCuentas abiertas\nPromociones y combos\nFacturación electrónica con el SAR\n1 punto de venta\n4 usuarios adicionales','assets/izzy/plan-restaurantes.jpeg',1,1,60,1 WHERE NOT EXISTS(SELECT 1 FROM izzy_plans WHERE name='Plan Restaurantes');

INSERT INTO settings(setting_key,setting_value) VALUES
('google_site_verification',''),('public_logo_path','assets/izzy/logo-full-dark.png')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

INSERT INTO settings(setting_key,setting_value) VALUES
('company_name','IZZY'),('site_language','es'),('admin_brand_name','IZZY CMS'),
('phone','+504 8913-6844'),('phone_digits','50489136844'),
('whatsapp_enabled','1'),('whatsapp_message','Hola, quiero conocer más sobre IZZY y sus planes.'),
('service_map_enabled','1'),('service_map_query','San Pedro Sula, Cortés, Honduras'),('service_map_label','Ubicación y cobertura IZZY'),
('seo_title','IZZY | Sistema de facturación y gestión empresarial'),
('seo_description','IZZY simplifica facturación electrónica, inventario, compras, cuentas por cobrar y pagar, recursos humanos, reportes y operación de restaurantes.')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

INSERT INTO site_content(content_key,content_value) VALUES
('hero_eyebrow','Facturación + gestión para negocios reales'),('hero_title','Factura, controla y haz crecer tu negocio con'),
('hero_text','IZZY reúne facturación electrónica, inventario, compras, cuentas, personal y reportes en un solo lugar. Ideal para negocios que quieren trabajar con más orden, rapidez y control.'),
('intro_title','Lo que tu negocio necesita, conectado.'),('intro_text','IZZY reúne las funciones clave de tu operación para que reduzcas pasos, tengas mayor control y tomes decisiones con información clara.'),
('areas_title','IZZY cerca de tu negocio.'),('areas_text','Atendemos empresas que buscan simplificar su operación con una plataforma práctica, escalable y acompañada por soporte técnico.'),
('estimate_title','¿Quieres implementar IZZY en tu negocio?'),('estimate_text','Cuéntanos qué necesitas y te ayudamos a identificar la modalidad y el plan que mejor se adapte a tu operación.'),('contact_title','Estamos listos para ayudarte.')
ON DUPLICATE KEY UPDATE content_value=VALUES(content_value);

-- IZZY full logo defaults: keep user-customized logos intact; only replace the legacy default mark.
INSERT INTO settings(setting_key,setting_value) VALUES ('admin_logo_path','assets/izzy/logo-full-dark.png')
ON DUPLICATE KEY UPDATE setting_value=IF(setting_value='' OR setting_value='assets/izzy/logo-mark.png',VALUES(setting_value),setting_value);
INSERT INTO settings(setting_key,setting_value) VALUES ('public_logo_path','assets/izzy/logo-full-dark.png')
ON DUPLICATE KEY UPDATE setting_value=IF(setting_value='' OR setting_value='assets/izzy/logo-mark.png',VALUES(setting_value),setting_value);

-- IZZY v1.0.36 — configurable public link to the production application
INSERT INTO settings(setting_key,setting_value) VALUES
('system_access_enabled','1'),
('system_access_url','https://sistema.izzycloud.app/'),
('system_access_label','Ingresar a IZZY'),
('system_access_new_tab','1')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

-- v1.0.37: update legacy default public system URL when it still uses the previous value.
UPDATE settings
SET setting_value = 'https://sistema.izzycloud.app/'
WHERE setting_key = 'system_access_url'
  AND setting_value = 'https://app.izzycloud.app/';

-- v1.0.40: control de visibilidad de imagen promocional por plan.
-- Solo activa las fotos existentes la primera vez que se agrega la columna;
-- futuras ejecuciones respetan lo que el administrador haya apagado manualmente.
SET @izzy_show_image_existed := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'izzy_plans' AND COLUMN_NAME = 'show_image'
);
CALL cms_core_safe_alter('ALTER TABLE izzy_plans ADD COLUMN show_image TINYINT(1) NOT NULL DEFAULT 0 AFTER image_path');
UPDATE izzy_plans
SET show_image=1
WHERE @izzy_show_image_existed=0 AND image_path IS NOT NULL AND image_path<>'';


-- v1.0.41: control global de fotos en el listado público de planes.
-- Instalaciones existentes quedan encendidas una sola vez para revisión.
-- Instalaciones nuevas usan el valor OFF sembrado en schema/database.sql.
INSERT INTO settings(setting_key,setting_value)
SELECT 'plans_show_images','1'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='plans_show_images');


-- v1.0.42 · NIVO Web Chat floating widget
INSERT INTO settings(setting_key,setting_value) VALUES
('nivo_widget_enabled','0'),
('nivo_widget_url',''),
('nivo_widget_title','NIVO Web Chat'),
('nivo_widget_greeting','¿Necesitas ayuda?')
ON DUPLICATE KEY UPDATE setting_value=setting_value;
INSERT INTO settings(setting_key,setting_value) VALUES ('whatsapp_position','left')
ON DUPLICATE KEY UPDATE setting_value='left';


-- v1.0.45 · mejora del mensaje principal sin pisar contenido personalizado.
UPDATE site_content SET content_value='Facturación + gestión para negocios reales'
WHERE content_key='hero_eyebrow' AND content_value='Simplifica · Controla · Crece';
UPDATE site_content SET content_value='Factura, controla y haz crecer tu negocio con'
WHERE content_key='hero_title' AND content_value='Todo tu negocio, en un solo sistema.';
UPDATE site_content SET content_value='IZZY reúne facturación electrónica, inventario, compras, cuentas, personal y reportes en un solo lugar. Ideal para negocios que quieren trabajar con más orden, rapidez y control.'
WHERE content_key='hero_text' AND content_value='Facturación electrónica, inventario, compras, cuentas, recursos humanos y una experiencia visual para restaurantes. IZZY se adapta a la forma en que trabaja tu negocio.';

-- v1.0.53 · widget de chat flotante genérico.
INSERT INTO settings(setting_key,setting_value) VALUES ('chat_widget_enabled','0') ON DUPLICATE KEY UPDATE setting_value=setting_value;
INSERT INTO settings(setting_key,setting_value) VALUES ('chat_widget_provider','NIVO Web Chat') ON DUPLICATE KEY UPDATE setting_value=setting_value;
INSERT INTO settings(setting_key,setting_value) VALUES ('chat_widget_title','¿Necesitas ayuda?') ON DUPLICATE KEY UPDATE setting_value=setting_value;
INSERT INTO settings(setting_key,setting_value) VALUES ('chat_widget_subtitle','Chatea con nosotros') ON DUPLICATE KEY UPDATE setting_value=setting_value;
INSERT INTO settings(setting_key,setting_value) VALUES ('chat_widget_mode','url') ON DUPLICATE KEY UPDATE setting_value=setting_value;
INSERT INTO settings(setting_key,setting_value) VALUES ('chat_widget_url','') ON DUPLICATE KEY UPDATE setting_value=setting_value;
INSERT INTO settings(setting_key,setting_value) VALUES ('chat_widget_embed_code','') ON DUPLICATE KEY UPDATE setting_value=setting_value;
INSERT INTO settings(setting_key,setting_value) VALUES ('chat_widget_position','auto') ON DUPLICATE KEY UPDATE setting_value=setting_value;
INSERT INTO settings(setting_key,setting_value) VALUES ('chat_widget_resolved_position','right') ON DUPLICATE KEY UPDATE setting_value=setting_value;


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

-- v1.0.90 · IZZY public section order and navigation synchronized with Admin > Section Manager.
-- Legacy generic CMS section rows are replaced by the real IZZY landing sections.
DELETE FROM site_sections
WHERE section_key IN ('home','intro','about','services','videos','gallery','areas','tips','estimate','contact');

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
ON DUPLICATE KEY UPDATE
  label=VALUES(label),
  anchor_id=VALUES(anchor_id),
  navigation_label=VALUES(navigation_label),
  show_in_navigation=VALUES(show_in_navigation),
  navigation_style=VALUES(navigation_style);

-- Remove the temporary migration helper after all guarded ALTER operations finish.
DROP PROCEDURE IF EXISTS cms_core_safe_alter;
