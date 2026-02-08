<?php
// config/informator.php

return [
    'base_url' => env('INFORMATOR_BASE_URL', 'https://informator.hr'),

    // Authentication credentials for PDF access
    'auth' => [
        'email' => env('INFORMATOR_EMAIL', ''),
        'password' => env('INFORMATOR_PASSWORD', ''),
        'sign_in_url' => env('INFORMATOR_SIGN_IN_URL', '/users/sign_in'),
    ],

    // Endpoints as configurable constants.
    'endpoints' => [
        // Listing endpoint (HTML)
        'list' => env('INFORMATOR_LIST_ENDPOINT', '/sudske-odluke'),

        // Single fetch endpoint (PDF) – must include "{id}" placeholder.
        // Example shape: "[SINGLE_ITEM_PDF_ENDPOINT_TEMPLATE]"
        'single_pdf' => env('INFORMATOR_SINGLE_PDF_ENDPOINT', 'https://informator.hr/court_decision/{id}?v=&format=pdf'),
    ],

    // HTML parsing configuration (selectors/regex kept configurable because markup changes over time).
    'parsing' => [
        // CSS selector that targets the listing links representing items
        // Matches: <div class="js-modal-container"> <a ... href="/sudske-odluke/1191214?hls="> ...
        'listing_item_link_selector' => env(
            'INFORMATOR_LISTING_LINK_SELECTOR',
            'div.js-modal-container a.table-court__item[href^="/sudske-odluke/"]'
        ),

        // Regex used to extract `{id}` from the link path.
        // Matches: /sudske-odluke/1191214 (query string is ignored by parse_url)
        'listing_item_href_regex' => env(
            'INFORMATOR_LISTING_ID_REGEX',
            '#^/sudske-odluke/(?P<id>\d+)$#'
        ),
    ],

    // Preconfigured request templates.
    // Keep sensitive values in env vars; never hardcode them.
    'headers' => [
        'common' => [
            'User-Agent' => env('INFORMATOR_UA', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'),
            'Accept-Language' => env('INFORMATOR_ACCEPT_LANGUAGE', 'en-US,en;q=0.9'),
        ],

        'listing' => [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'Priority' => 'u=0, i',
            'Referer' => env('INFORMATOR_REFERER', 'https://informator.hr/sudske-odluke'),
            'Sec-CH-UA' => '"Chromium";v="142", "Google Chrome";v="142", "Not_A Brand";v="99"',
            'Sec-CH-UA-Mobile' => '?0',
            'Sec-CH-UA-Platform' => '"Linux"',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'same-origin',
            'Sec-Fetch-User' => '?1',
            'Upgrade-Insecure-Requests' => '1',

            // If the listing requires authentication/session:
            // Provide a full cookie string via INFORMATOR_COOKIE.
            'Cookie' => env('INFORMATOR_COOKIE', ''),
        ],

        'pdf' => [
            // Match the proven-working curl headers for the PDF endpoint.
            // (Yes, Accept is HTML here – that's what Informator serves/accepts in practice.)
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'Priority' => 'u=0, i',
            'Sec-CH-UA' => '"Chromium";v="142", "Google Chrome";v="142", "Not_A Brand";v="99"',
            'Sec-CH-UA-Mobile' => '?0',
            'Sec-CH-UA-Platform' => '"Linux"',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Sec-Fetch-User' => '?1',
            'Upgrade-Insecure-Requests' => '1',

            // Provide cookies via env (same as listing by default).
            'Cookie' => env('INFORMATOR_COOKIE', ''),
        ],
    ],

    'http' => [
        'timeout' => env('INFORMATOR_TIMEOUT', 30),
        'retry' => [
            'times' => env('INFORMATOR_RETRY_TIMES', 2),
            'sleep' => env('INFORMATOR_RETRY_SLEEP_MS', 250),
        ],
    ],
];
