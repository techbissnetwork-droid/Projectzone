<?php
declare(strict_types=1);

/**
 * Marketing content that is structural rather than editorial. Keeping it in
 * config means the marketing pages render with zero database round-trips —
 * the single biggest TTFB win on the public site.
 */
return [
    'brand' => [
        'name' => 'TECHBISS',
        'promise' => 'Digital transformation, engineered.',
        'positioning' => 'TECHBISS designs, builds and operates the digital platforms that regulated enterprises depend on — from architecture through to the deployment that goes live.',
        'founded' => 2013,
        'email' => 'hello@techbiss.com',
        'sales_email' => 'sales@techbiss.com',
        'support_email' => 'support@techbiss.com',
        'phone' => '+1 (415) 555-0142',
        'phone_href' => '+14155550142',
        'social' => [
            ['label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/company/techbiss', 'icon' => 'linkedin'],
            ['label' => 'GitHub', 'url' => 'https://github.com/techbiss', 'icon' => 'github'],
            ['label' => 'X', 'url' => 'https://x.com/techbiss', 'icon' => 'x'],
            ['label' => 'YouTube', 'url' => 'https://www.youtube.com/@techbiss', 'icon' => 'youtube'],
        ],
    ],

    'stats' => [
        ['value' => '412', 'suffix' => '', 'label' => 'Platforms shipped', 'detail' => 'Across 31 countries since 2013'],
        ['value' => '99.98', 'suffix' => '%', 'label' => 'Fleet uptime', 'detail' => 'Rolling twelve-month average'],
        ['value' => '18', 'suffix' => 'wks', 'label' => 'Median time to launch', 'detail' => 'Discovery to production'],
        ['value' => '4', 'suffix' => '', 'label' => 'Delivery regions', 'detail' => 'One standard, follow-the-sun'],
    ],

    'trustbar' => [
        'label' => 'Trusted by teams operating at national scale',
        'logos' => ['Northwind Bank', 'Meridian Health', 'Vantage Logistics', 'Helix Energy', 'Civica', 'Arclight Retail'],
    ],

    'certifications' => [
        ['label' => 'SOC 2 Type II', 'detail' => 'Audited annually'],
        ['label' => 'ISO/IEC 27001', 'detail' => 'Certified ISMS'],
        ['label' => 'GDPR & UK DPA', 'detail' => 'DPO on staff'],
        ['label' => 'WCAG 2.2 AA', 'detail' => 'Verified per release'],
        ['label' => 'PCI DSS 4.0', 'detail' => 'SAQ-D environments'],
    ],

    'offices' => [
        [
            'city' => 'San Francisco',
            'role' => 'Global HQ',
            'address' => '535 Mission Street, Suite 1400, San Francisco, CA 94105',
            'region' => 'Americas',
            'timezone' => 'UTC−8',
            'phone' => '+1 (415) 555-0142',
        ],
        [
            'city' => 'London',
            'role' => 'EMEA delivery',
            'address' => '20 Ropemaker Street, London EC2Y 9AR, United Kingdom',
            'region' => 'Europe',
            'timezone' => 'UTC+0',
            'phone' => '+44 20 7946 0812',
        ],
        [
            'city' => 'Dubai',
            'role' => 'Middle East',
            'address' => 'One Central, Office Tower 3, Dubai World Trade Centre, Dubai, UAE',
            'region' => 'Middle East',
            'timezone' => 'UTC+4',
            'phone' => '+971 4 555 0198',
        ],
        [
            'city' => 'Singapore',
            'role' => 'APAC delivery',
            'address' => '9 Straits View, Marina One West Tower, Singapore 018937',
            'region' => 'Asia Pacific',
            'timezone' => 'UTC+8',
            'phone' => '+65 6555 0177',
        ],
    ],

    'services' => [
        [
            'slug' => 'platform-engineering',
            'name' => 'Platform Engineering',
            'lede' => 'Cloud-native systems that survive contact with real traffic, real auditors and real deadlines.',
            'body' => 'We design event-driven, service-oriented architectures with explicit contracts, then build the delivery platform around them: golden paths, environments, observability and rollback that engineers actually use.',
            'icon' => 'layers',
            'accent' => 'blue',
            'outcomes' => ['Sub-200ms p95 at national scale', 'Zero-downtime releases', 'Cost per transaction cut 30–60%'],
            'capabilities' => ['Domain-driven design', 'Event streaming & CQRS', 'API gateways and BFF layers', 'Service mesh and traffic policy', 'Legacy strangler migrations', 'Load and chaos testing'],
            'starting_at' => 'From $180k',
            'duration' => '12–24 weeks',
        ],
        [
            'slug' => 'product-design',
            'name' => 'Product Design',
            'lede' => 'Research-led interface design that turns a complex operation into an obvious one.',
            'body' => 'Discovery, service blueprints, prototypes tested with real operators, then a versioned design system your engineers can consume as code — not a folder of screenshots.',
            'icon' => 'compass',
            'accent' => 'violet',
            'outcomes' => ['Task completion up 40%+', 'Support contacts down 25%', 'Design system adopted in one sprint'],
            'capabilities' => ['Generative and evaluative research', 'Service blueprints', 'Interaction and motion design', 'Design systems as code', 'Accessibility to WCAG 2.2 AA', 'Usability testing programmes'],
            'starting_at' => 'From $95k',
            'duration' => '8–16 weeks',
        ],
        [
            'slug' => 'web-commerce',
            'name' => 'Web & Commerce',
            'lede' => 'Storefronts and marketing platforms measured on revenue per session, not on awards.',
            'body' => 'Composable commerce, headless content and edge-rendered marketing sites engineered against Core Web Vitals budgets that are enforced in CI, not hoped for.',
            'icon' => 'cart',
            'accent' => 'teal',
            'outcomes' => ['LCP under 1.8s on 4G', 'Conversion up 18–34%', 'Organic sessions up 2–3×'],
            'capabilities' => ['Composable commerce architecture', 'Headless CMS integration', 'Checkout and payments', 'Technical SEO at scale', 'Experimentation platforms', 'Edge rendering and caching'],
            'starting_at' => 'From $70k',
            'duration' => '6–14 weeks',
        ],
        [
            'slug' => 'data-ai',
            'name' => 'Data & AI',
            'lede' => 'Warehouses, pipelines and applied models that answer questions the business actually asks.',
            'body' => 'We build the semantic layer before the dashboards and the evaluation harness before the model. Assistants ship with retrieval, guardrails and a measurable accuracy baseline.',
            'icon' => 'spark',
            'accent' => 'amber',
            'outcomes' => ['Single trusted metric layer', 'Reporting latency minutes, not days', 'Assistant deflection above 40%'],
            'capabilities' => ['Lakehouse and warehouse design', 'Streaming and batch pipelines', 'Semantic and metrics layers', 'Applied ML and forecasting', 'Retrieval-augmented assistants', 'Model evaluation and monitoring'],
            'starting_at' => 'From $140k',
            'duration' => '10–20 weeks',
        ],
        [
            'slug' => 'cloud-devsecops',
            'name' => 'Cloud & DevSecOps',
            'lede' => 'Infrastructure as code, security as a control plane, reliability as a published number.',
            'body' => 'Landing zones, policy-as-code, supply-chain attestation and SLO-driven operations — delivered with the runbooks and on-call structure to keep them true after we hand over.',
            'icon' => 'shield',
            'accent' => 'blue',
            'outcomes' => ['Change failure rate under 5%', 'Mean time to recovery under 20 min', 'Audit evidence generated automatically'],
            'capabilities' => ['Multi-account landing zones', 'Terraform and policy-as-code', 'CI/CD and progressive delivery', 'Zero-trust identity', 'SLOs, error budgets, on-call', 'FinOps and cost governance'],
            'starting_at' => 'From $120k',
            'duration' => '10–18 weeks',
        ],
        [
            'slug' => 'growth-engineering',
            'name' => 'Growth Engineering',
            'lede' => 'Acquisition, activation and retention treated as an engineering system with a scoreboard.',
            'body' => 'Technical SEO, structured data, lifecycle messaging and a controlled experiment programme running on your own analytics — instrumented so every claim is checkable.',
            'icon' => 'trend',
            'accent' => 'teal',
            'outcomes' => ['Qualified pipeline up 45%', 'CAC down 20%+', 'Experiment velocity 4× higher'],
            'capabilities' => ['Technical SEO and Core Web Vitals', 'Structured data and entity SEO', 'Lifecycle and CRM automation', 'Experimentation infrastructure', 'Attribution modelling', 'Conversion research'],
            'starting_at' => 'From $45k',
            'duration' => 'Ongoing retainer',
        ],
    ],

    'process' => [
        [
            'phase' => '01',
            'name' => 'Align',
            'duration' => 'Week 1–2',
            'promise' => 'A decision, not a deck.',
            'body' => 'We map the commercial outcome, the constraints and the failure modes with the people who own them, then write down the measurable definition of done.',
            'deliverables' => ['Outcome and success metrics', 'Constraint and risk register', 'Stakeholder decision map', 'Commercial business case'],
        ],
        [
            'phase' => '02',
            'name' => 'Architect',
            'duration' => 'Week 2–5',
            'promise' => 'The hard problems, solved first.',
            'body' => 'Target architecture, data model, security posture and delivery plan — with the riskiest assumption proven by a running spike before anyone commits a budget.',
            'deliverables' => ['Target architecture and ADRs', 'Threat model and controls', 'Delivery plan and pod shape', 'Technical spike on the top risk'],
        ],
        [
            'phase' => '03',
            'name' => 'Build',
            'duration' => 'Week 5–16',
            'promise' => 'Production from day one.',
            'body' => 'Two-week increments into a production-grade pipeline. Every increment is deployable, observable and reviewed against the same quality gates as launch.',
            'deliverables' => ['Fortnightly production increments', 'Automated test and quality gates', 'Live observability dashboards', 'Design system in code'],
        ],
        [
            'phase' => '04',
            'name' => 'Harden',
            'duration' => 'Week 14–18',
            'promise' => 'Proven before it is promised.',
            'body' => 'Load, failover, penetration and accessibility testing against published budgets. Nothing launches on an unproven assumption.',
            'deliverables' => ['Load and soak test results', 'Penetration test and remediation', 'WCAG 2.2 AA audit', 'Disaster recovery rehearsal'],
        ],
        [
            'phase' => '05',
            'name' => 'Launch',
            'duration' => 'Week 18',
            'promise' => 'A boring go-live.',
            'body' => 'Progressive rollout behind flags, with a rehearsed rollback, a staffed war room and the switch-over checklist signed off in advance.',
            'deliverables' => ['Cutover and rollback runbook', 'Progressive rollout plan', 'Launch war room', 'Day-one support rota'],
        ],
        [
            'phase' => '06',
            'name' => 'Operate',
            'duration' => 'Ongoing',
            'promise' => 'Accountability that outlasts the invoice.',
            'body' => 'SLO reporting, cost governance, a quarterly roadmap review and a capability transfer plan that ends with your team owning it.',
            'deliverables' => ['SLO and cost reporting', 'Quarterly roadmap reviews', 'Runbooks and training', 'Capability transfer plan'],
        ],
    ],

    'principles' => [
        ['title' => 'Evidence over opinion', 'body' => 'Every recommendation carries the measurement that justifies it. If we cannot measure it, we say so plainly.'],
        ['title' => 'Senior hands on keys', 'body' => 'The architects who scope the work write the code. No bait-and-switch staffing after the statement of work is signed.'],
        ['title' => 'Own the outcome', 'body' => 'We are accountable to the business result, not to a ticket count. Scope moves when the evidence moves.'],
        ['title' => 'Leave it better', 'body' => 'Documentation, runbooks and training are deliverables. Handover is a milestone with a date, not a gesture.'],
        ['title' => 'Secure by construction', 'body' => 'Threat modelling starts at architecture. Security is a design input, never a late audit.'],
        ['title' => 'Performance is a feature', 'body' => 'Speed budgets are written into the definition of done and enforced by the pipeline on every commit.'],
    ],

    'differentiators' => [
        ['title' => 'Fixed-scope discovery', 'body' => 'A two-week paid discovery ends with an architecture, a plan and a fixed price. If you walk away, you keep all of it.', 'metric' => '2 weeks'],
        ['title' => 'One accountable lead', 'body' => 'A named principal owns your account end to end and stays through operations. You never re-explain your business.', 'metric' => '1 owner'],
        ['title' => 'Published reliability', 'body' => 'Fleet SLOs are reported to every client monthly, including the months we miss them.', 'metric' => '99.98%'],
    ],

    'faqs' => [
        ['q' => 'How quickly can a team start?', 'a' => 'A discovery pod typically starts within ten business days of signature. Full delivery pods staff in three to four weeks, and we publish the named team before you commit.'],
        ['q' => 'Do you work fixed-price or time and materials?', 'a' => 'Discovery is always fixed-price. Delivery is fixed-price per increment once architecture is settled, with a published change process for scope that moves.'],
        ['q' => 'Who owns the intellectual property?', 'a' => 'You do — source, infrastructure definitions, designs and documentation, assigned on payment. Marketplace products are separately licensed under their stated tier.'],
        ['q' => 'Can you work with our existing team?', 'a' => 'Yes. Roughly half our engagements are embedded pods working alongside in-house engineers, with capability transfer written into the plan from week one.'],
        ['q' => 'What happens after launch?', 'a' => 'You choose: full operations under an SLA, a co-managed period with your team taking the pager, or a clean handover with training and runbooks.'],
        ['q' => 'How is the Marketplace different from the services business?', 'a' => 'Marketplace products are productised, self-serve builds you deploy yourself with the Advanced Installer. Services engagements are bespoke. Many clients start with a product and grow into an engagement.'],
    ],

    'testimonials' => [
        [
            'quote' => 'They rebuilt our settlement platform without a single unplanned outage, and left our engineers able to run it. That second part is what nobody else delivered.',
            'name' => 'Adaeze Okonkwo',
            'role' => 'Chief Technology Officer',
            'company' => 'Northwind Bank',
            'metric' => '99.99% settlement uptime',
        ],
        [
            'quote' => 'The discovery was two weeks and the estimate held to within four percent across nine months. In this industry that is close to unheard of.',
            'name' => 'Marcus Feld',
            'role' => 'VP Digital',
            'company' => 'Vantage Logistics',
            'metric' => '4% estimate variance',
        ],
        [
            'quote' => 'Our storefront went from a two and a half second LCP to under one second, and revenue per session moved with it inside the first month.',
            'name' => 'Priya Raman',
            'role' => 'Head of Ecommerce',
            'company' => 'Arclight Retail',
            'metric' => '+31% revenue per session',
        ],
    ],

    'leadership' => [
        ['name' => 'Elena Vasquez', 'role' => 'Chief Executive Officer', 'bio' => 'Twenty years building platforms for regulated markets. Previously led engineering for a top-five European payments processor.', 'location' => 'San Francisco', 'accent' => 'blue'],
        ['name' => 'Daniel Osei', 'role' => 'Chief Technology Officer', 'bio' => 'Distributed systems architect. Designed the settlement rails now clearing eleven billion dollars a year across three continents.', 'location' => 'London', 'accent' => 'teal'],
        ['name' => 'Mei Lin Chen', 'role' => 'Chief Design Officer', 'bio' => 'Built design practices at two public companies. Advocates for accessibility as an engineering constraint rather than a compliance chore.', 'location' => 'Singapore', 'accent' => 'violet'],
        ['name' => 'Rashid Al-Amin', 'role' => 'Chief Delivery Officer', 'bio' => 'Runs the global delivery standard. Owns the published reliability numbers and the quality gates every engagement passes through.', 'location' => 'Dubai', 'accent' => 'amber'],
        ['name' => 'Sofia Ricci', 'role' => 'Chief Information Security Officer', 'bio' => 'Leads the SOC 2 and ISO 27001 programmes in-house, and the threat modelling practice embedded in every architecture review.', 'location' => 'London', 'accent' => 'blue'],
        ['name' => 'James Whitfield', 'role' => 'Managing Director, Americas', 'bio' => 'Commercial lead for the Americas region. Previously built the enterprise practice at a global systems integrator.', 'location' => 'San Francisco', 'accent' => 'teal'],
    ],

    'timeline' => [
        ['year' => '2013', 'title' => 'Founded in San Francisco', 'body' => 'Three engineers, one conviction: transformation programmes fail because nobody owns the outcome.'],
        ['year' => '2016', 'title' => 'London opens', 'body' => 'First regulated financial services engagement; the compliance-by-construction practice is formed.'],
        ['year' => '2019', 'title' => 'ISO 27001 certified', 'body' => 'Security programme brought fully in-house, with a dedicated CISO and a DPO on staff.'],
        ['year' => '2021', 'title' => 'Singapore and Dubai', 'body' => 'Follow-the-sun delivery goes live across four regions under a single quality standard.'],
        ['year' => '2023', 'title' => 'Marketplace launches', 'body' => 'Productised builds and the Advanced Installer put deploy-ready platforms in customers\' own hands.'],
        ['year' => '2026', 'title' => '412 platforms live', 'body' => 'Fleet uptime holds at 99.98% across thirty-one countries and four regulated sectors.'],
    ],

    'careers' => [
        ['role' => 'Principal Platform Engineer', 'location' => 'London / Remote EMEA', 'type' => 'Full-time', 'team' => 'Engineering'],
        ['role' => 'Senior Product Designer', 'location' => 'Singapore / Remote APAC', 'type' => 'Full-time', 'team' => 'Design'],
        ['role' => 'Staff Data Engineer', 'location' => 'San Francisco / Remote US', 'type' => 'Full-time', 'team' => 'Data & AI'],
        ['role' => 'Delivery Lead', 'location' => 'Dubai', 'type' => 'Full-time', 'team' => 'Delivery'],
        ['role' => 'Security Engineer', 'location' => 'Remote EMEA', 'type' => 'Full-time', 'team' => 'Security'],
    ],

    'contact_topics' => [
        'new-project' => 'Start a new project',
        'marketplace' => 'Marketplace or licensing',
        'migration' => 'Migration or installation help',
        'partnership' => 'Partnership or vendor enquiry',
        'careers' => 'Careers',
        'support' => 'Existing client support',
    ],

    'budget_bands' => [
        'under-50k' => 'Under $50k',
        '50k-150k' => '$50k – $150k',
        '150k-500k' => '$150k – $500k',
        '500k-plus' => '$500k+',
        'not-sure' => 'Not sure yet',
    ],
];
