<?php
declare(strict_types=1);

/**
 * Single source of truth for header, mega-menu, mobile drawer, footer and
 * sitemap generation — one edit updates every surface.
 */
return [
    'primary' => [
        ['label' => 'Services', 'path' => '/services', 'summary' => 'Engineering, design and growth practices.', 'mega' => 'services'],
        ['label' => 'Solutions', 'path' => '/solutions', 'summary' => 'Outcome-built platforms per industry.', 'mega' => 'solutions'],
        ['label' => 'Marketplace', 'path' => '/marketplace', 'summary' => 'Deploy-ready websites, themes and templates.', 'mega' => 'marketplace'],
        ['label' => 'Work', 'path' => '/work', 'summary' => 'Case studies and measured outcomes.'],
        ['label' => 'Process', 'path' => '/process', 'summary' => 'How delivery actually runs.'],
        ['label' => 'Company', 'path' => '/about', 'summary' => 'Who we are and how we operate.', 'mega' => 'company'],
    ],

    'mega' => [
        'services' => [
            'heading' => 'Practices',
            'blurb' => 'Six practices that ship one outcome. Staffed by senior engineers, designers and strategists who stay on the account.',
            'columns' => [
                [
                    'title' => 'Build',
                    'links' => [
                        ['label' => 'Platform Engineering', 'path' => '/services#platform-engineering', 'meta' => 'Cloud-native systems'],
                        ['label' => 'Product Design', 'path' => '/services#product-design', 'meta' => 'Research to design system'],
                        ['label' => 'Web & Commerce', 'path' => '/services#web-commerce', 'meta' => 'Storefronts that convert'],
                    ],
                ],
                [
                    'title' => 'Scale',
                    'links' => [
                        ['label' => 'Data & AI', 'path' => '/services#data-ai', 'meta' => 'Warehouses, ML, assistants'],
                        ['label' => 'Cloud & DevSecOps', 'path' => '/services#cloud-devsecops', 'meta' => 'Reliability and compliance'],
                        ['label' => 'Growth Engineering', 'path' => '/services#growth-engineering', 'meta' => 'SEO, CRO, lifecycle'],
                    ],
                ],
            ],
            'feature' => [
                'eyebrow' => 'Engagement model',
                'title' => 'Pods, not headcount',
                'body' => 'A cross-functional pod owns discovery through operations, with one accountable lead and a fixed weekly cadence.',
                'cta' => ['label' => 'See how we work', 'path' => '/process'],
            ],
        ],

        'solutions' => [
            'heading' => 'Industries',
            'blurb' => 'Reference architectures and accelerators tuned to sector-specific regulation, scale and buying behaviour.',
            'columns' => [
                [
                    'title' => 'Regulated',
                    'links' => [
                        ['label' => 'Financial Services', 'path' => '/solutions/financial-services', 'meta' => 'Payments, lending, KYC'],
                        ['label' => 'Healthcare', 'path' => '/solutions/healthcare', 'meta' => 'HIPAA-ready platforms'],
                        ['label' => 'Public Sector', 'path' => '/solutions/public-sector', 'meta' => 'Accessible citizen services'],
                    ],
                ],
                [
                    'title' => 'Commercial',
                    'links' => [
                        ['label' => 'Retail & Commerce', 'path' => '/solutions/retail-commerce', 'meta' => 'Composable storefronts'],
                        ['label' => 'Logistics', 'path' => '/solutions/logistics', 'meta' => 'Fleet and visibility'],
                        ['label' => 'SaaS & Technology', 'path' => '/solutions/saas-technology', 'meta' => 'Multi-tenant platforms'],
                    ],
                ],
            ],
            'feature' => [
                'eyebrow' => 'Accelerators',
                'title' => 'Start at week six',
                'body' => 'Every solution ships with pre-hardened infrastructure, design system and CI/CD so discovery ends with running software.',
                'cta' => ['label' => 'Browse solutions', 'path' => '/solutions'],
            ],
        ],

        'marketplace' => [
            'heading' => 'Marketplace',
            'blurb' => 'Production-grade websites, themes and templates. Preview live, purchase, then deploy with the Advanced Installer.',
            'columns' => [
                [
                    'title' => 'Browse',
                    'links' => [
                        ['label' => 'All products', 'path' => '/marketplace', 'meta' => 'Full catalogue'],
                        ['label' => 'Websites', 'path' => '/marketplace?category=websites', 'meta' => 'Complete sites'],
                        ['label' => 'Themes', 'path' => '/marketplace?category=themes', 'meta' => 'Design systems'],
                    ],
                ],
                [
                    'title' => 'Deploy',
                    'links' => [
                        ['label' => 'Advanced Installer', 'path' => '/marketplace/installer', 'meta' => 'One-click deployment'],
                        ['label' => 'Migration & import', 'path' => '/marketplace/installer#migration', 'meta' => 'Move an existing site'],
                        ['label' => 'Licensing', 'path' => '/marketplace/licensing', 'meta' => 'Terms and tiers'],
                    ],
                ],
            ],
            'feature' => [
                'eyebrow' => 'Included free',
                'title' => 'Advanced Installer',
                'body' => 'URL auto-detection, existing-site detection, clean install, migration and configuration in one guided flow.',
                'cta' => ['label' => 'See the installer', 'path' => '/marketplace/installer'],
            ],
        ],

        'company' => [
            'heading' => 'Company',
            'blurb' => 'A distributed engineering firm operating across four regions with a single delivery standard.',
            'columns' => [
                [
                    'title' => 'About',
                    'links' => [
                        ['label' => 'Who we are', 'path' => '/about', 'meta' => 'Story and principles'],
                        ['label' => 'Leadership', 'path' => '/about#leadership', 'meta' => 'The people accountable'],
                        ['label' => 'Careers', 'path' => '/about#careers', 'meta' => 'Open roles'],
                    ],
                ],
                [
                    'title' => 'Resources',
                    'links' => [
                        ['label' => 'Insights', 'path' => '/resources', 'meta' => 'Field notes and playbooks'],
                        ['label' => 'Case studies', 'path' => '/work', 'meta' => 'Measured outcomes'],
                        ['label' => 'Contact', 'path' => '/contact', 'meta' => 'Talk to an architect'],
                    ],
                ],
            ],
            'feature' => [
                'eyebrow' => 'Trust',
                'title' => 'Audited and accountable',
                'body' => 'SOC 2 Type II, ISO 27001 and GDPR programmes maintained in-house, not outsourced to a badge vendor.',
                'cta' => ['label' => 'Read our principles', 'path' => '/about#principles'],
            ],
        ],
    ],

    'portals' => [
        ['label' => 'Client Login', 'path' => '/client/login', 'icon' => 'briefcase', 'summary' => 'Projects, licences and deployments'],
        ['label' => 'Staff Login', 'path' => '/staff/login', 'icon' => 'users', 'summary' => 'Pipeline, tasks and support queue'],
        ['label' => 'Admin Login', 'path' => '/admin/login', 'icon' => 'shield', 'summary' => 'Platform administration'],
    ],

    'footer' => [
        [
            'title' => 'Services',
            'links' => [
                ['label' => 'Platform Engineering', 'path' => '/services#platform-engineering'],
                ['label' => 'Product Design', 'path' => '/services#product-design'],
                ['label' => 'Web & Commerce', 'path' => '/services#web-commerce'],
                ['label' => 'Data & AI', 'path' => '/services#data-ai'],
                ['label' => 'Cloud & DevSecOps', 'path' => '/services#cloud-devsecops'],
                ['label' => 'Growth Engineering', 'path' => '/services#growth-engineering'],
            ],
        ],
        [
            'title' => 'Solutions',
            'links' => [
                ['label' => 'Financial Services', 'path' => '/solutions/financial-services'],
                ['label' => 'Healthcare', 'path' => '/solutions/healthcare'],
                ['label' => 'Retail & Commerce', 'path' => '/solutions/retail-commerce'],
                ['label' => 'Logistics', 'path' => '/solutions/logistics'],
                ['label' => 'SaaS & Technology', 'path' => '/solutions/saas-technology'],
                ['label' => 'Public Sector', 'path' => '/solutions/public-sector'],
            ],
        ],
        [
            'title' => 'Marketplace',
            'links' => [
                ['label' => 'Browse catalogue', 'path' => '/marketplace'],
                ['label' => 'Advanced Installer', 'path' => '/marketplace/installer'],
                ['label' => 'Licensing', 'path' => '/marketplace/licensing'],
                ['label' => 'Deployment guide', 'path' => '/resources/deployment-playbook'],
            ],
        ],
        [
            'title' => 'Company',
            'links' => [
                ['label' => 'About', 'path' => '/about'],
                ['label' => 'Work', 'path' => '/work'],
                ['label' => 'Process', 'path' => '/process'],
                ['label' => 'Resources', 'path' => '/resources'],
                ['label' => 'Contact', 'path' => '/contact'],
            ],
        ],
    ],

    'legal' => [
        ['label' => 'Privacy', 'path' => '/legal/privacy'],
        ['label' => 'Terms', 'path' => '/legal/terms'],
        ['label' => 'Security', 'path' => '/legal/security'],
        ['label' => 'Accessibility', 'path' => '/legal/accessibility'],
    ],
];
