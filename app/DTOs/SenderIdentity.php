<?php

namespace App\DTOs;

/**
 * Immutable identity of the document sender/petitioner.
 *
 * Contains personal identification data (name, OIB, address, contact).
 * Loaded from config/legal-artillery.php sender section.
 */
class SenderIdentity
{
    public function __construct(
        public readonly string $name,
        public readonly string $oib,
        public readonly string $address,
        public readonly string $email,
        public readonly string $phone,
    ) {}

    public static function fromConfig(): self
    {
        $cfg = config('legal-artillery.sender');
        return new self(
            name: $cfg['name'],
            oib: $cfg['oib'],
            address: $cfg['address'],
            email: $cfg['email'],
            phone: $cfg['phone'],
        );
    }
}
