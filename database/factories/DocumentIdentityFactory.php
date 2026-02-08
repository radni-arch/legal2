<?php

namespace Database\Factories;

use App\Models\DocumentIdentity;
use App\Models\CaseDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * DocumentIdentity Factory
 *
 * Sprint 7 - Task 38: Factory for DocumentIdentity model
 */
class DocumentIdentityFactory extends Factory
{
    protected $model = DocumentIdentity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $suffixes = [1, 2, 3, 4, 5];
        $prefixes = ['K', 'KO', 'Kv', 'Pp Prz', 'Kis', 'KP-DO'];
        $prefix = $this->faker->randomElement($prefixes);
        $seq = $this->faker->numberBetween(1, 999);
        $year = $this->faker->numberBetween(2020, 2025);
        $suffix = $this->faker->optional(0.8)->randomElement($suffixes);

        $caseNumber = "{$prefix}-{$seq}/{$year}";
        $caseNumberFull = $suffix ? "{$caseNumber}-{$suffix}" : $caseNumber;

        return [
            'case_id' => (string) $this->faker->uuid(),
            'case_document_id' => null,
            'case_number' => $caseNumber,
            'case_prefix' => $prefix,
            'case_seq_number' => $seq,
            'case_year' => $year,
            'case_suffix' => $suffix,
            'case_number_full' => $caseNumberFull,
            'klasa' => $this->faker->optional(0.7)->regexify('UP/I-\d{3}-\d{2}/\d{2}-\d{2}/\d{1,3}'),
            'urbroj' => $this->faker->optional(0.7)->regexify('\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{1,3}'),
            'urbroj_institution_code' => $this->faker->optional(0.5)->randomElement(['511', '2158', '2168', '2170', '2181']),
            'urbroj_suffix' => $this->faker->optional(0.5)->numberBetween(1, 10),
            'broj' => null,
            'broj_type' => null,
            'document_date' => $this->faker->optional(0.6)->dateTimeBetween('-2 years', 'now'),
            'document_type' => $this->faker->optional(0.5)->randomElement([
                'rjesenje', 'zapisnik', 'naredba', 'prijedlog', 'potvrda', 'izvjesce',
            ]),
            'issuing_institution' => $this->faker->optional(0.5)->randomElement([
                'Opcinski sud u Osijeku',
                'Opcinski sud u Zagrebu',
                'Zupanijski sud u Osijeku',
                'Drzavno odvjetnistvo',
                'MUP - Policija',
            ]),
            'metacase_role' => $this->faker->optional(0.6)->randomElement([
                'main_criminal', 'search_warrant', 'detention', 'detention_appeal',
                'investigation_opening', 'investigative_action', 'appeal', 'prosecution',
            ]),
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
            'discovery_source' => DocumentIdentity::SOURCE_EXTRACTION,
            'referenced_in_documents' => [],
            'reference_count' => 0,
            'notes' => null,
        ];
    }

    /**
     * Indicate the document is present.
     */
    public function present(): static
    {
        return $this->state(fn (array $attributes) => [
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
        ]);
    }

    /**
     * Indicate the document is missing.
     */
    public function missing(): static
    {
        return $this->state(fn (array $attributes) => [
            'presence_status' => DocumentIdentity::STATUS_MISSING,
            'case_document_id' => null,
        ]);
    }

    /**
     * Indicate the document is partial (OCR failed, corrupted).
     */
    public function partial(): static
    {
        return $this->state(fn (array $attributes) => [
            'presence_status' => DocumentIdentity::STATUS_PARTIAL,
        ]);
    }

    /**
     * Set discovery source to extraction.
     */
    public function fromExtraction(): static
    {
        return $this->state(fn (array $attributes) => [
            'discovery_source' => DocumentIdentity::SOURCE_EXTRACTION,
        ]);
    }

    /**
     * Set discovery source to inference.
     */
    public function fromInference(): static
    {
        return $this->state(fn (array $attributes) => [
            'discovery_source' => DocumentIdentity::SOURCE_INFERENCE,
            'notes' => 'Inferred from sequence gap',
        ]);
    }

    /**
     * Set discovery source to manual entry.
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'discovery_source' => DocumentIdentity::SOURCE_MANUAL,
        ]);
    }

    /**
     * Associate with a case document.
     */
    public function forCaseDocument(CaseDocument $document): static
    {
        return $this->state(fn (array $attributes) => [
            'case_document_id' => $document->id,
            'case_id' => $document->case_id,
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
        ]);
    }

    /**
     * Set specific case role.
     */
    public function withRole(string $role): static
    {
        return $this->state(fn (array $attributes) => [
            'metacase_role' => $role,
        ]);
    }

    /**
     * Set specific KLASA.
     */
    public function withKlasa(string $klasa): static
    {
        return $this->state(fn (array $attributes) => [
            'klasa' => $klasa,
        ]);
    }
}
