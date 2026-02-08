<?php

namespace App\DTOs;

use InvalidArgumentException;

/**
 * Immutable data transfer object representing a legal document profile.
 *
 * Each profile defines: recipient, legal basis, tone, structure, and formatting
 * for a specific type of legal correspondence (e.g., court motion, ombudsman complaint).
 *
 * Profiles are loaded from config/legal-artillery.php.
 */
class DocumentProfile
{
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly array $recipient,
        public readonly array $legalBasis,
        public readonly string $tone,
        public readonly array $structure,
        public readonly array $requiredSections,
        public readonly string $docxTemplate,
        public readonly bool $requiresAttachments = false,
        public readonly ?string $emailSubjectTemplate = null,
        public readonly array $metadata = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            key: $data['key'],
            name: $data['name'],
            recipient: $data['recipient'],
            legalBasis: $data['legal_basis'] ?? [],
            tone: $data['tone'] ?? 'formal',
            structure: $data['structure'] ?? [],
            requiredSections: $data['required_sections'] ?? [],
            docxTemplate: $data['docx_template'] ?? 'legal-formal',
            requiresAttachments: $data['requires_attachments'] ?? false,
            emailSubjectTemplate: $data['email_subject_template'] ?? null,
            metadata: $data['metadata'] ?? [],
        );
    }

    public static function fromConfig(string $key): self
    {
        $profiles = config('legal-artillery.profiles', []);

        if (!isset($profiles[$key])) {
            throw new InvalidArgumentException("Document profile '{$key}' not found in config.");
        }

        return self::fromArray(array_merge(['key' => $key], $profiles[$key]));
    }

    public static function all(): array
    {
        $profiles = config('legal-artillery.profiles', []);

        return array_map(
            fn(string $key, array $data) => self::fromArray(array_merge(['key' => $key], $data)),
            array_keys($profiles),
            array_values($profiles),
        );
    }

    public static function isEscalationProfile(?string $key): bool
    {
        if (!$key) {
            return false;
        }

        $escalationProfiles = config('legal-artillery.escalation_profiles', []);

        return in_array($key, $escalationProfiles, true);
    }

    public function requiresEscalationConfirmation(): bool
    {
        return self::isEscalationProfile($this->key);
    }

    public function recipientLine(): string
    {
        return implode("\n", array_filter([
            $this->recipient['title'] ?? null,
            $this->recipient['address'] ?? null,
        ]));
    }
}
