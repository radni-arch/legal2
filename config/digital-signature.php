<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PKCS#11 Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the PKCS#11 module used to communicate with the
    | Croatian electronic ID card (eOI) via a smart card reader. Requires
    | the OpenSC library to be installed on the host system.
    |
    */
    'pkcs11' => [
        // Path to PKCS#11 module for Croatian eID
        'module_path' => env('PKCS11_MODULE', '/usr/lib/opensc-pkcs11.so'),

        // Slot number (usually 0 for first card reader)
        'slot' => env('PKCS11_SLOT', 0),

        // Certificate label on card
        'cert_label' => env('PKCS11_CERT_LABEL', 'Signature'),

        // PIN (should be entered interactively, but for automation...)
        'pin' => env('PKCS11_PIN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Signing Options
    |--------------------------------------------------------------------------
    |
    | Options controlling how the digital signature is embedded in signed
    | PDF documents, including field name, reason, location, and visible
    | signature placement.
    |
    */
    'pdf' => [
        'signature_field_name' => 'LegalArtillerySignature',
        'signature_reason' => 'Potpisano sustavom Pravna Artiljerija',
        'signature_location' => 'Osijek, Hrvatska',
        'visible_signature' => true,
        'signature_page' => 'last', // first, last, all
        'signature_rect' => [50, 50, 200, 100], // x, y, width, height in points
    ],

    /*
    |--------------------------------------------------------------------------
    | LibreOffice for DOCX to PDF Conversion
    |--------------------------------------------------------------------------
    |
    | LibreOffice is used in headless mode to convert DOCX documents to PDF
    | before signing. Configure the path to the soffice binary and the
    | maximum execution timeout.
    |
    */
    'libreoffice' => [
        'binary' => env('LIBREOFFICE_PATH', '/usr/bin/soffice'),
        'timeout' => 60,
    ],
];
