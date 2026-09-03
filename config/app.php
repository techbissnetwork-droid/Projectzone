<?php
declare(strict_types=1);

return [
    'name' => 'TECHBISS',
    'tagline' => 'Digital transformation, engineered.',
    'legal_name' => 'TECHBISS Global Technologies',
    'version' => '1.0.0',

    // Overwritten per request when detect_url is true. The installer pins a
    // value here if the operator chooses a fixed canonical URL.
    'url' => 'http://localhost:8000',
    'detect_url' => true,
    'base_path' => '',

    'env' => 'production',
    'debug' => false,
    'timezone' => 'UTC',
    'locale' => 'en',
    'locale_tag' => 'en-US',
    'currency' => 'USD',

    // Public marketing pages are served with a shared CDN cache window.
    'page_cache_ttl' => 600,
];
