<?php

namespace App\DTOs;

use Carbon\Carbon;

/**
 * Immutable context object containing case-specific data.
 *
 * Provides template variables for document generation and string interpolation.
 * Loaded from config/legal-artillery.php case_context section.
 */
class CaseContext
{
    public function __construct(
        public readonly string $caseNumber,
        public readonly string $criminalCaseNumber,
        public readonly string $searchDate,
        public readonly string $archiveDate,
        public readonly string $addressSearched,
        public readonly string $warrantReference,
        public readonly string $policeRequestKlasa,
        public readonly string $policeRequestUrbroj,
        public readonly string $legalBasisWarrant,
        public readonly string $suspectedOffense,
        public readonly string $judge,
        public readonly string $denialDate,
        public readonly string $countyCourtResponseDate,
        public readonly SenderIdentity $sender,
        public readonly array $seizedItems = [],
        public readonly array $proceduralViolations = [],
    ) {}

    public static function fromConfig(): self
    {
        $case = config('legal-artillery.case_context');
        return new self(
            caseNumber: $case['case_number'],
            criminalCaseNumber: $case['criminal_case_number'] ?? '',
            searchDate: $case['search_date'],
            archiveDate: $case['archive_date'],
            addressSearched: $case['address_searched'],
            warrantReference: $case['warrant_reference'],
            policeRequestKlasa: $case['police_request_klasa'],
            policeRequestUrbroj: $case['police_request_urbroj'],
            legalBasisWarrant: $case['legal_basis_warrant'],
            suspectedOffense: $case['suspected_offense'],
            judge: $case['judge'],
            denialDate: $case['denial_date'],
            countyCourtResponseDate: $case['county_court_response_date'],
            sender: SenderIdentity::fromConfig(),
            seizedItems: $case['seized_items'] ?? [],
            proceduralViolations: $case['procedural_violations'] ?? [],
        );
    }

    public function toTemplateVars(): array
    {
        $now = Carbon::now();

        return [
            'case_number' => $this->caseNumber,
            'criminal_case_number' => $this->criminalCaseNumber,
            'search_date' => $this->searchDate,
            'archive_date' => $this->archiveDate,
            'address_searched' => $this->addressSearched,
            'warrant_reference' => $this->warrantReference,
            'police_klasa' => $this->policeRequestKlasa,
            'police_urbroj' => $this->policeRequestUrbroj,
            'legal_basis_warrant' => $this->legalBasisWarrant,
            'suspected_offense' => $this->suspectedOffense,
            'judge' => $this->judge,
            'denial_date' => $this->denialDate,
            'county_response_date' => $this->countyCourtResponseDate,
            'sender_name' => $this->sender->name,
            'sender_oib' => $this->sender->oib,
            'sender_address' => $this->sender->address,
            'sender_email' => $this->sender->email,
            'sender_phone' => $this->sender->phone,
            'today_date' => $now->format('d. F Y.'),
            'today_date_iso' => $now->toDateString(),
            'seized_items' => $this->seizedItems,
            'procedural_violations' => $this->proceduralViolations,
        ];
    }

    /**
     * Format seized items as a numbered list for the evidence exclusion request.
     */
    public function formatSeizedItemsList(): string
    {
        return collect($this->seizedItems)
            ->map(fn(array $item, int $i) => ($i + 1) . '. ' . $item['item'] . ' (' . $item['quantity'] . ')')
            ->implode("\n");
    }

    public function interpolate(string $template): string
    {
        $vars = $this->toTemplateVars();
        return preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($vars) {
            return $vars[$matches[1]] ?? $matches[0];
        }, $template);
    }
}
