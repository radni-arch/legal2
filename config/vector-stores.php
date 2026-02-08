<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Vector Store Configurations
    |--------------------------------------------------------------------------
    |
    | Named vector store configurations for different document collections.
    |
    */
    'stores' => [
        'case_files' => [
            'id' => env('VS_CASE_FILES', 'vs_68c89c6bb90081918cf07e4441f58ecc'),
            'name' => 'Case Files (KP-DO-731, Pp Prz-74)',
        ],
        'laws' => [
            'id' => env('VS_LAWS', 'vs_68c89c812d408191add94d47a2b749e8'),
            'name' => 'Zakoni (clanci)',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Vector Store
    |--------------------------------------------------------------------------
    |
    | The default vector store to use when none is specified.
    |
    */
    'default_store' => env('VS_DEFAULT', 'vs_68c89c6bb90081918cf07e4441f58ecc'),

    /*
    |--------------------------------------------------------------------------
    | Catalog Directory
    |--------------------------------------------------------------------------
    |
    | Where generated catalog JSON files are stored.
    |
    */
    'catalog_dir' => env('CATALOG_DIR', storage_path('app/catalog')),
];
