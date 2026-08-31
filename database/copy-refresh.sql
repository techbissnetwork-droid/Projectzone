-- =====================================================================
-- TECHBISS — shorten the seeded copy on a site that is already installed
--
-- database/seed.sql only runs at install time, so an existing site keeps
-- whatever copy it was set up with. This script rewrites the seeded text
-- to the shorter wording.
--
-- Safe to run more than once, and safe to run on a site you have edited:
-- each statement only updates a row whose text is still exactly the
-- original. Anything you have rewritten yourself is left alone.
--
--     mysql -u USER -p DATABASE < database/copy-refresh.sql
-- =====================================================================
SET NAMES utf8mb4;

UPDATE `page_sections` SET `subheading` = 'Everything your business needs online — domain, website, apps, email, branding and SEO — from one partner.'
  WHERE `section_key` = 'hero' AND `subheading` = 'Transform your offline business into a premium, trusted digital brand. TECHBISS provides everything you need — from your own domain and hosting to websites, apps, business email, branding, SEO and ongoing support.';

UPDATE `page_sections` SET `subheading` = 'You have the customers and the reputation. What is missing is the part that makes you easy to find and easy to buy from.'
  WHERE `section_key` = 'problem' AND `subheading` = 'You have customers, a reputation and something that works. What you do not have is the digital foundation that makes it visible, credible and easy to buy from.';

UPDATE `page_sections` SET `subheading` = 'Each piece depends on the one before. We build them in order.'
  WHERE `section_key` = 'chain' AND `subheading` = 'Each piece depends on the one before it. We build them in order, and we build all of them.';

UPDATE `page_sections` SET `subheading` = 'Ten services, from infrastructure to the work that keeps it running.'
  WHERE `section_key` = 'services' AND `subheading` = 'Ten services that cover the entire digital foundation — infrastructure, presence, applications and the work that keeps them performing.';

UPDATE `page_sections` SET `heading` = 'What this actually changes.'
  WHERE `section_key` = 'trust' AND `heading` = 'What a professional digital presence actually changes.';

UPDATE `page_sections` SET `subheading` = 'Not guaranteed sales. How established you look, how easily customers find you, and how far past your front door you reach.'
  WHERE `section_key` = 'trust' AND `subheading` = 'Not guaranteed sales. Something more fundamental: how established your business looks, how easily customers find what they need, and how far beyond your physical location you can reach.';

UPDATE `page_sections` SET `heading` = 'Six stages, first call to ongoing support.'
  WHERE `section_key` = 'process' AND `heading` = 'Six stages from first conversation to ongoing growth.';

UPDATE `page_sections` SET `subheading` = 'Complete setups with published prices. Where a prepaid discount applies, you see the exact saving.'
  WHERE `section_key` = 'packages' AND `subheading` = 'Complete setups with published pricing. Where a prepaid discount applies, you see the regular price, the prepaid price and the exact saving.';

UPDATE `page_sections` SET `heading` = 'Built around your sector.'
  WHERE `section_key` = 'industries' AND `heading` = 'Built around how your sector actually works.';

UPDATE `page_sections` SET `subheading` = 'A restaurant and a law firm need different things. We start from your sector, not a template.'
  WHERE `section_key` = 'industries' AND `subheading` = 'A restaurant, a law firm and a manufacturer need different things. We start from the sector, not from a template.';

UPDATE `page_sections` SET `subheading` = 'Tell us about the business. You get a scope, a schedule and a price. No obligation.'
  WHERE `section_key` = 'cta' AND `subheading` = 'Tell us about the business. We will come back with a clear scope, a schedule and a price — no obligation.';

UPDATE `page_sections` SET `heading` = 'Built the same way yours will be.'
  WHERE `section_key` = 'work' AND `heading` = 'Projects built the same way yours will be.';

UPDATE `settings` SET `value` = 'Take your business online, properly.'
  WHERE `key_name` = 'brand_promise' AND `value` = 'Transform your offline business into a premium digital brand.';

UPDATE `settings` SET `value` = 'One partner for everything your business needs online: domain, hosting, website, apps, email, branding, SEO and support.'
  WHERE `key_name` = 'footer_text' AND `value` = 'TECHBISS is a complete digital transformation partner. We take businesses from offline to a premium, trusted digital presence — domain, hosting, website, apps, email, branding, SEO and ongoing support.';

-- The cache holds rendered settings and navigation, so clear it afterwards:
--     rm -f storage/cache/*.cache
-- or use Admin → System → Tools → Clear cache.
