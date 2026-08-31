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


-- ---------------------------------------------------------------------
-- Positioning: the site now leads with both promises — taking an offline
-- business online, and building the app you have in mind.
-- ---------------------------------------------------------------------

UPDATE `page_sections` SET `eyebrow` = 'One partner. Websites and apps.'
  WHERE `section_key` = 'hero' AND `eyebrow` = 'One partner. Everything digital.';

UPDATE `page_sections` SET `subheading` = 'We take offline businesses online — domain, website, email, branding, SEO — and build the custom app you have in mind.'
  WHERE `section_key` = 'hero' AND `subheading` = 'Everything your business needs online — domain, website, apps, email, branding and SEO — from one partner.';

UPDATE `page_sections` SET `subheading` = 'Ten services — your domain and website, custom web and mobile apps, and the work that keeps them running.'
  WHERE `section_key` = 'services' AND `subheading` = 'Ten services, from infrastructure to the work that keeps it running.';

UPDATE `settings` SET `value` = 'Offline business to online, plus the app you have in mind.'
  WHERE `key_name` = 'brand_promise' AND `value` = 'Take your business online, properly.';

UPDATE `services` SET `tagline` = 'The app you have in mind, built for Android and iOS.'
  WHERE `slug` = 'mobile-applications' AND `tagline` = 'Professional Android and iOS applications.';

UPDATE `services` SET `short_description` = 'The app you have in mind — for your customers, or for your team in the field.'
  WHERE `slug` = 'mobile-applications' AND `short_description` = 'Native-quality mobile apps for customers or for the team in the field.';

UPDATE `services` SET `description` = '<p>Bring us the idea. We tell you what it takes to build, what it will do on day one, and what can wait for later. Then we build it for Android and iOS, ship it to both stores, and handle the releases and updates after that.</p><p>Apps we build most: ordering and booking, loyalty, field data capture, and internal tools for a team that works away from a desk.</p>'
  WHERE `slug` = 'mobile-applications' AND `description` = '<p>We build mobile apps when a phone is genuinely the right place for the job — field data capture, loyalty, ordering, bookings. Shipped to both stores, with the release and update process handled.</p>';

UPDATE `settings` SET `value` = 'We take offline businesses online and build the app you have in mind. Websites, mobile apps, hosting, business email, branding and SEO from one partner.'
  WHERE `key_name` = 'seo_default_description' AND `value` = 'TECHBISS turns offline businesses into premium digital brands. Domain, hosting, websites, apps, business email, branding, SEO and ongoing support from one partner.';

-- Show mobile apps on the homepage, next to the website service.
UPDATE `services` SET `is_featured` = 1, `sort_order` = 3 WHERE `slug` = 'mobile-applications' AND `is_featured` = 0;
UPDATE `services` SET `sort_order` = 4 WHERE `slug` = 'web-applications' AND `sort_order` = 3;

-- ---------------------------------------------------------------------
-- Remove the copy that still promised published prices. The site shows no
-- figures; these sentences told visitors to look for them.
-- ---------------------------------------------------------------------

UPDATE `page_sections` SET `heading` = 'Pick what you need. We price it with you.'
  WHERE `section_key` = 'packages' AND `heading` = 'Pay upfront. Save more. Build better.';

UPDATE `page_sections` SET `subheading` = 'Complete setups. Tell us what you want included and we send the figure.'
  WHERE `section_key` = 'packages' AND `subheading` = 'Complete setups with published prices. Where a prepaid discount applies, you see the exact saving.';

UPDATE `process_steps` SET `description` = 'Select a package that fits, or ask for a custom scope. You see exactly what is included, and we send the price before you decide.'
  WHERE `step_number` = '02' AND `description` = 'Select a package that fits, or ask for a custom scope. You see the regular price, the prepaid price and exactly what is included before deciding.';

UPDATE `faqs` SET `answer` = 'Sometimes settling upfront rather than in stages reduces the total. We tell you both figures in writing when we quote.'
  WHERE `question` = 'What does the prepaid price mean?' AND `answer` = 'Some packages have a lower price when the package is paid upfront rather than in stages. Where that applies, both prices and the exact saving are shown on the package. If no prepaid price is shown, there is no prepaid discount on that package.';

UPDATE `faqs` SET `answer` = 'Yes. Staged payment is the standard arrangement. If you would rather settle upfront, say so and we will quote that way too.'
  WHERE `question` = 'Can I pay in stages?' AND `answer` = 'Yes. Staged payment is the standard arrangement. The prepaid price is the alternative for businesses that prefer to settle upfront.';

UPDATE `navigation` SET `description` = 'Complete setups, priced with you'
  WHERE `url` = '/packages' AND `description` = 'Prepaid setups with clear pricing';

UPDATE `faqs` SET `question` = 'Can I pay less by paying upfront?'
  WHERE `question` = 'What does the prepaid price mean?';

UPDATE `pages` SET `content` = REPLACE(`content`, '<h2>Clear pricing</h2><p>Package prices are published. Where a prepaid discount applies, both prices and the exact saving are shown. Custom work is scoped and quoted in writing before it starts.</p>', '<h2>Priced in conversation</h2><p>We do not publish prices, because the work changes with what you actually need. Tell us the list and we send a written figure, usually within one business day.</p>')
  WHERE `slug` = 'why-techbiss';

UPDATE `pages` SET `seo_description` = 'One partner for domain, hosting, website, apps, email, branding and SEO — with full ownership and a written price before you commit.'
  WHERE `slug` = 'why-techbiss' AND `seo_description` = 'One partner for domain, hosting, website, apps, email, branding and SEO — with full ownership and clear pricing.';

UPDATE `pages` SET `content` = REPLACE(`content`, '<h2>Pricing</h2><p>Prices published on this website are indicative and may change. The price that applies to your project is the one stated in your written proposal. Where a prepaid price is shown, the conditions attached to it are stated in the proposal.</p>', '<h2>Pricing</h2><p>We do not publish prices on this website. The price that applies to your project is the one stated in your written proposal.</p>')
  WHERE `slug` = 'terms-and-conditions';
-- The cache holds rendered settings and navigation, so clear it afterwards:
--     rm -f storage/cache/*.cache
-- or use Admin → System → Tools → Clear cache.
