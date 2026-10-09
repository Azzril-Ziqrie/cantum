-- Cantum Studio — MySQL / MariaDB schema + data
-- Generated 2026-10-05 16:10
-- phpMyAdmin: create an empty database, select it, then Import this file.
-- The users table is created but the admin row is NOT exported (it holds your password hash):
-- on first visit the app seeds  admin / admin123  — log in and change it in Settings.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS projects (
      id INT AUTO_INCREMENT PRIMARY KEY,
      title VARCHAR(255) NOT NULL,
      slug VARCHAR(191) NOT NULL UNIQUE,
      category VARCHAR(100) NOT NULL DEFAULT 'Other',
      `year` VARCHAR(20) NULL,
      location VARCHAR(255) NULL,
      description TEXT NULL,
      cover_id INT NULL,
      sort_order INT NOT NULL DEFAULT 0,
      published TINYINT NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS images (
      id INT AUTO_INCREMENT PRIMARY KEY,
      project_id INT NOT NULL,
      filename VARCHAR(255) NOT NULL,
      sort_order INT NOT NULL DEFAULT 0,
      CONSTRAINT fk_images_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (k VARCHAR(100) PRIMARY KEY, v TEXT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
      id INT AUTO_INCREMENT PRIMARY KEY,
      username VARCHAR(100) NOT NULL UNIQUE,
      password VARCHAR(255) NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
      id INT AUTO_INCREMENT PRIMARY KEY,
      number VARCHAR(100) NOT NULL UNIQUE,
      token VARCHAR(64) NOT NULL UNIQUE,
      client_name VARCHAR(255) NOT NULL,
      client_email VARCHAR(255) NULL,
      client_phone VARCHAR(50) NULL,
      client_address TEXT NULL,
      issue_date VARCHAR(10) NOT NULL,
      due_date VARCHAR(10) NULL,
      tax_rate DOUBLE NOT NULL DEFAULT 0,
      discount BIGINT NOT NULL DEFAULT 0,
      notes TEXT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'draft',
      paid_at VARCHAR(10) NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
      id INT AUTO_INCREMENT PRIMARY KEY,
      invoice_id INT NOT NULL,
      description TEXT NOT NULL,
      qty DOUBLE NOT NULL DEFAULT 1,
      price BIGINT NOT NULL DEFAULT 0,
      sort_order INT NOT NULL DEFAULT 0,
      CONSTRAINT fk_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------- data ----------------

-- projects (3)
INSERT INTO `projects` (`id`, `title`, `slug`, `category`, `year`, `location`, `description`, `cover_id`, `sort_order`, `published`, `created_at`) VALUES ('2', 'HARDPREAZE', 'jnjlnjlnjl', 'Web', '2026', '-', 'End-to-end website development focused on translating ideas into intuitive, responsive, and functional digital experiences. Includes custom UI/UX implementation, responsive development, CMS integration, essential functionality, and production-ready deployment.', NULL, '2', '1', '2026-10-05 11:55:13');
INSERT INTO `projects` (`id`, `title`, `slug`, `category`, `year`, `location`, `description`, `cover_id`, `sort_order`, `published`, `created_at`) VALUES ('3', 'DEEPDIMES', 'deepdimes', 'Web', '2026', '', 'End-to-end website development focused on translating ideas into intuitive, responsive, and functional digital experiences. Includes custom UI/UX implementation, responsive development, CMS integration, essential functionality, and production-ready deployment.', NULL, '1', '1', '2026-10-05 12:42:32');
INSERT INTO `projects` (`id`, `title`, `slug`, `category`, `year`, `location`, `description`, `cover_id`, `sort_order`, `published`, `created_at`) VALUES ('4', 'PASTPRESENTFURTHER', 'pastpresentfurther', 'Web', '2026', '-', 'End-to-end website development focused on translating ideas into intuitive, responsive, and functional digital experiences. Includes custom UI/UX implementation, responsive development, CMS integration, essential functionality, and production-ready deployment.', NULL, '0', '1', '2026-10-05 12:56:33');

-- images (18)
INSERT INTO `images` (`id`, `project_id`, `filename`, `sort_order`) VALUES ('3', '2', '9d393533ba8ef9bb.png', '1');
INSERT INTO `images` (`id`, `project_id`, `filename`, `sort_order`) VALUES ('4', '2', 'fe3b0d46279e4b6e.png', '2');
INSERT INTO `images` (`id`, `project_id`, `filename`, `sort_order`) VALUES ('5', '2', '2c1bcfb2f1261a4d.png', '3');
INSERT INTO `images` (`id`, `project_id`, `filename`, `sort_order`) VALUES ('6', '2', '03665a1cc90da593.png', '4');
INSERT INTO `images` (`id`, `project_id`, `filename`, `sort_order`) VALUES ('7', '2', '4ba33db385b47dc8.png', '5');
INSERT INTO `images` (`id`, `project_id`, `filename`, `sort_order`) VALUES ('8', '3', '63bfc95929d7196f.png', '1');
INSERT INTO `images` (`id`, `project_id`, `filename`, `sort_order`) VALUES ('9', '3', '38f3f6239dce0c99.png', '2');
INSERT INTO `images` (`id`, `project_id`, `filename`, `sort_order`) VALUES ('10', '3', 'd43646fec134ba5b.png', '3');
INSERT INTO `images` (`id`, `project_id`, `filename`, `sort_order`) VALUES ('11', '3', 'f7571d52e8e82d17.png', '4');
INSERT INTO `images` (`id`, `project_id`, `filename`, `sort_order`) VALUES ('12', '3', 'ef412eb2997cf065.png', '5');
INSERT INTO `images` (`id`, `project_id`, `filename`, `sort_order`) VALUES ('13', '4', 'be621f9bb94f6352.png', '1');
INSERT INTO `images` (`id`, `project_id`, `filename`, `sort_order`) VALUES ('14', '4', 'f0c0d76261ca26d9.png', '2');
INSERT INTO `images` (`id`, `project_id`, `filename`, `sort_order`) VALUES ('15', '4', '72dd6fd5ac897516.png', '3');
INSERT INTO `images` (`id`, `project_id`, `filename`, `sort_order`) VALUES ('16', '4', 'f9d8a146597127fa.png', '4');
INSERT INTO `images` (`id`, `project_id`, `filename`, `sort_order`) VALUES ('17', '4', 'cc97ec8e62c86f87.png', '5');
INSERT INTO `images` (`id`, `project_id`, `filename`, `sort_order`) VALUES ('18', '4', '79ddd82433f184ea.png', '6');
INSERT INTO `images` (`id`, `project_id`, `filename`, `sort_order`) VALUES ('19', '4', '2b4e07dc61a3dcea.png', '7');
INSERT INTO `images` (`id`, `project_id`, `filename`, `sort_order`) VALUES ('20', '4', '5c8127921bfbee49.png', '8');

-- settings (8)
INSERT INTO `settings` (`k`, `v`) VALUES ('about_text', 'CANTUM STUDIO is an end-to-end creative practice translating ideas into clear, intuitive visual experiences. Guided by conceptual thinking, the studio works across disciplines to shape brand worlds that are intentional, distinct, and alive.

We resist the rigidity of standardized branding processes. Instead, we examine the essence of each brand, build a conceptual platform around it, and translate that thinking into a distinct visual language. Through deliberate expression, we create identities that capture attention, build recognition, and give each brand a presence of its own.');
INSERT INTO `settings` (`k`, `v`) VALUES ('about_title', '');
INSERT INTO `settings` (`k`, `v`) VALUES ('address', '');
INSERT INTO `settings` (`k`, `v`) VALUES ('email', 'info@cantum-studio.site');
INSERT INTO `settings` (`k`, `v`) VALUES ('headline', 'CANTUM STUDIO is an end-to-end creative practice translating ideas into clear, intuitive visual experiences.');
INSERT INTO `settings` (`k`, `v`) VALUES ('instagram', '');
INSERT INTO `settings` (`k`, `v`) VALUES ('phone', '');
INSERT INTO `settings` (`k`, `v`) VALUES ('tagline', '');

-- invoices (1)
INSERT INTO `invoices` (`id`, `number`, `token`, `client_name`, `client_email`, `client_phone`, `client_address`, `issue_date`, `due_date`, `tax_rate`, `discount`, `notes`, `status`, `paid_at`, `created_at`) VALUES ('2', 'INV-2026-001', 'f524eca11a721ec2d17c5f40', 'noir', 'noir@gmail.com', '0895386847552', 'dsad
sadas', '2026-10-05', '2026-10-19', '0.0', '0', 'gak ada', 'draft', NULL, '2026-10-05 12:59:14');

-- invoice_items (1)
INSERT INTO `invoice_items` (`id`, `invoice_id`, `description`, `qty`, `price`, `sort_order`) VALUES ('3', '2', 'website', '1.0', '1500000', '0');

SET FOREIGN_KEY_CHECKS = 1;
