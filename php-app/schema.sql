-- TECHBISS — database schema + seed data
-- Import this file into a MySQL/MariaDB database via phpMyAdmin
-- (Import tab -> choose file -> Go), then point config.php at that
-- database, username and password.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------
-- staff — internal/admin users. Only staff can sign in at /admin/.
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS `staff`;
CREATE TABLE `staff` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` VARCHAR(60) NOT NULL DEFAULT 'Staff',
  `initials` VARCHAR(4) NOT NULL DEFAULT '',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed staff login: admin@techbiss.com / techbiss-admin-2026
-- CHANGE THIS PASSWORD before going live — see README.md.
INSERT INTO `staff` (`name`, `email`, `password_hash`, `role`, `initials`) VALUES
('Mara Aldous', 'mara@techbiss.com', '$2y$12$KqXcJRwo2G4x/oavDaIVPuv754B8wk0ECXzkK4BWlcq0lzqQDrTha', 'Founder & CEO', 'MA'),
('Devon Kwan', 'devon@techbiss.com', '$2y$12$KqXcJRwo2G4x/oavDaIVPuv754B8wk0ECXzkK4BWlcq0lzqQDrTha', 'Head of Engineering', 'DK'),
('Rhea Solano', 'rhea@techbiss.com', '$2y$12$KqXcJRwo2G4x/oavDaIVPuv754B8wk0ECXzkK4BWlcq0lzqQDrTha', 'Head of Design', 'RS'),
('Jonah Traeger', 'admin@techbiss.com', '$2y$12$KqXcJRwo2G4x/oavDaIVPuv754B8wk0ECXzkK4BWlcq0lzqQDrTha', 'VP Client Success', 'JT');

-- ---------------------------------------------------------------
-- customers — real accounts created via the public sign-up form.
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- businesses — client accounts shown on the admin "Business accounts" table.
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS `businesses`;
CREATE TABLE `businesses` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `sector` VARCHAR(80) NOT NULL,
  `plan` VARCHAR(60) NOT NULL,
  `mrr_cents` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('Active','Trial','Past due') NOT NULL DEFAULT 'Active',
  `last_activity_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `businesses` (`name`, `sector`, `plan`, `mrr_cents`, `status`, `last_activity_at`) VALUES
('Maple & Co. Bakery', 'Bakery', 'Growth', 14900, 'Active', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('Solstice Yoga Studio', 'Fitness', 'Starter', 7900, 'Active', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('Corner Hardware & Repair', 'Home services', 'Growth', 14900, 'Active', DATE_SUB(NOW(), INTERVAL 3 DAY)),
('Nomad Coffee Roasters', 'Creator', 'App + Web', 22900, 'Trial', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
('Kinship Pet Rescue', 'Nonprofit', 'Starter', 7900, 'Active', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('Bloom & Bramble Florist', 'Retail', 'Growth', 14900, 'Past due', DATE_SUB(NOW(), INTERVAL 11 DAY));

-- ---------------------------------------------------------------
-- tickets — cross-client support queue shown in the admin panel.
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS `tickets`;
CREATE TABLE `tickets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `priority` ENUM('Low','Normal','High') NOT NULL DEFAULT 'Normal',
  `status` ENUM('Open','In progress','Closed') NOT NULL DEFAULT 'Open',
  `assignee_staff_id` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assignee_staff_id`) REFERENCES `staff`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `tickets` (`business_id`, `title`, `priority`, `status`, `assignee_staff_id`) VALUES
(1, 'Checkout error on mobile Safari', 'High', 'Open', 2),
(6, 'Billing card declined — needs follow-up call', 'High', 'Open', 4),
(1, 'Add a holiday hours banner', 'Normal', 'In progress', 3),
(4, 'App Store review flagged a screenshot', 'Normal', 'Open', 2),
(5, 'Domain renewal reminder', 'Low', 'Open', 4);

-- ---------------------------------------------------------------
-- products — marketplace listings, rendered from the DB on the public site.
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` VARCHAR(20) PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `category` VARCHAR(60) NOT NULL,
  `icon` VARCHAR(40) NOT NULL,
  `price` DECIMAL(8,2) NOT NULL,
  `rating` DECIMAL(2,1) NOT NULL,
  `tagline` TEXT NOT NULL,
  `description` TEXT NOT NULL,
  `specs_json` TEXT NOT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `products` (`id`,`name`,`category`,`icon`,`price`,`rating`,`tagline`,`description`,`specs_json`,`sort_order`) VALUES
('p1','Orchard Storefront','Templates','cart',349,4.9,'A ready-made online store you can brand as your own in an afternoon.','A fully responsive storefront theme with cart, wishlist and a component library tuned for small retail. Drop in your logo and colors — no code needed. Ships with light and dark themes.','["42 ready-made sections","No-code branding","WCAG AA out of the box","Free updates for 12 months"]',1),
('p2','Signal Support Agent','AI Agents','chat',129,4.8,'An AI assistant that answers customers and books appointments while you''re busy.','A drop-in AI agent trained on your business info that answers common questions and books appointments, with a graceful hand-off to you when it''s unsure. Works on your website or as a chat widget.','["Trains on your business info","Books appointments automatically","Hands off to you when unsure","Works on any website"]',2),
('p3','Meridian Ops Dashboard','Dashboards','monitor',249,4.9,'One screen for orders, bookings and messages — no more checking five apps.','A simple dashboard that pulls orders, bookings and customer messages from your site and socials into one screen, so you''re not logging into five different apps every day.','["Orders & bookings in one place","Daily & weekly summaries","Works with most booking tools","Mobile-friendly"]',3),
('p4','Domain, Hosting & Email Starter Kit','Bundles','globe',89,4.7,'Domain, fast hosting, SSL and a business email — set up and handed to you.','Everything your website needs to actually go live: a domain name registered in your business''s name, fast and secure hosting with SSL included, and a professional email address ready to use from day one.','["Domain registration included","Hosting + SSL, fully managed","Business email on your domain","Renewal reminders, no surprise lapses"]',4),
('p5','Bramble Field Themes','Themes','spark',59,4.6,'Twelve ready-made themes for any small business website.','A theme pack built for shops, studios and service businesses — organic shapes, accessible contrast, and full light/dark parity. Brand any one of them as your own.','["12 curated themes","Light & dark parity","Fully brandable","Drop-in, no developer needed"]',5),
('p6','App Store Launch Kit','Bundles','compass',199,4.8,'Everything it takes to get your app live on the App Store and Play Store.','We handle the developer accounts, store listings, screenshots and the entire review process, so your app goes from finished to published without you touching App Store Connect or the Play Console.','["iOS & Android submission handled","Store listing & screenshots included","Review process managed for you","Post-launch update support"]',6),
('p7','Willow Client Portal','Templates','users',279,4.9,'A booking and client portal your customers will actually enjoy using.','A branded portal for appointments, files and updates, so "when''s my next appointment" never turns into a text-message thread. Built for salons, studios, clinics and consultants.','["Online booking & reminders","File & document sharing","Branded to your business","Client notification emails"]',7),
('p8','Nectar Ranking Agent','AI Agents','chart',159,4.7,'Tracks your search ranking and tells you, in plain language, what to fix.','A plain-language SEO agent that checks how your site ranks for the searches that matter to your business, and tells you exactly what to fix — no jargon, no dashboard full of numbers to decode.','["Plain-language ranking reports","Local search tracking","Actionable fix-it suggestions","Weekly email summary"]',8);

-- ---------------------------------------------------------------
-- contact_messages — every submission of the public contact form.
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE `contact_messages` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `company` VARCHAR(150) NULL,
  `need` VARCHAR(150) NULL,
  `message` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- newsletter_subscribers — Resources page "get the next field note" form.
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS `newsletter_subscribers`;
CREATE TABLE `newsletter_subscribers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(190) NOT NULL UNIQUE,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
