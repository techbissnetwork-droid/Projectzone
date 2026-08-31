-- =====================================================================
-- TECHBISS — remove the optional demo content
--
-- Deletes everything database/demo-content.sql inserted, and nothing else.
-- Your own projects, testimonials, posts and statistics are untouched.
--
--     mysql -u USER -p DATABASE < database/demo-content-remove.sql
-- =====================================================================
SET NAMES utf8mb4;

-- Portfolio (cascades to images, technologies and service links)
DELETE FROM `portfolio` WHERE `slug` IN
  ('meridian-bakehouse','northgate-dental','harbour-supply-co','stonebridge-construction','alloy-fabrication','verdant-wellness');

-- Testimonials attributed to the fictional demo companies
DELETE FROM `testimonials` WHERE `company` LIKE '%(fictional)%';

-- Demo blog posts (cascades to their tag links)
DELETE FROM `blog_posts` WHERE `slug` IN
  ('who-actually-owns-your-domain','business-email-deliverability','what-a-website-costs');

-- Demo statistics
DELETE FROM `stats` WHERE `label` IN
  ('Services under one partner','Industries we build for','Ownership stays with you');

-- Demo premade projects (cascades to their features, images and technologies)
DELETE FROM `premade_projects` WHERE `slug` IN
  ('demo-clinic-booking','demo-restaurant-menu','demo-shop-starter');

-- Media rows pointing at the generated demo images, if they were imported
DELETE FROM `media` WHERE `path` LIKE 'uploads/media/demo/%';

-- Finally, remove the image files themselves from the server:
--     rm -rf uploads/media/demo
