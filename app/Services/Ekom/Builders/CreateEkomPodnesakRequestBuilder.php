<?php

namespace App\Services\Ekom\Builders;

use InvalidArgumentException;

/**
 * Fluent builder for CreateEkomPodnesakRequest.
 *
 * Two modes:
 * 1. New Proceeding: requires vrstaPostupkaId, ulogaPodnositeljaId, stranke
 * 2. Existing Case: requires predmetId/predmetOznaka, no stranke/protustranke
 */
class CreateEkomPodnesakRequestBuilder
{
    private ?int $sudId = null;

    private ?string $sudOznaka = null;

    private ?int $predmetId = null;

    private ?string $predmetOznaka = null;

    private ?int $vrstaPodneskaId = null;

    private ?int $vrstaPostupkaId = null;

    private ?int $ulogaPodnositeljaId = null;

    private array $sadrzaj = [];

    private array $stranke = [];

    private array $protustranke = [];

    private array $prilozi = [];

    private array $pristojbaDodatniPodaci = [];

    private bool $isNewProceeding = true;

    // S4-9: External references
    private ?string $vanjskaUstrojstvenaJedinica = null;

    private ?string $vanjskiPredmet = null;

    // S4-10: Submitter identification
    private ?int $podnositeljRbr = null;

    private ?string $podnositeljSlobodanUnos = null;

    // S4-11: Dispute value
    private ?float $vrijednostPredmetaSpora = null;

    // S4-12: Not my case flag
    private bool $potvrdaPredmetaKojiNijeMoj = false;

    /**
     * Set court by ID.
     */
    public function forCourt(int $sudId): self
    {
        $this->sudId = $sudId;
        $this->sudOznaka = null;

        return $this;
    }

    /**
     * Set court by oznaka (code).
     */
    public function forCourtByOznaka(string $sudOznaka): self
    {
        $this->sudOznaka = $sudOznaka;
        $this->sudId = null;

        return $this;
    }

    /**
     * Configure as new proceeding (default).
     */
    public function asNewProceeding(int $vrstaPostupkaId, int $ulogaPodnositeljaId): self
    {
        $this->isNewProceeding = true;
        $this->vrstaPostupkaId = $vrstaPostupkaId;
        $this->ulogaPodnositeljaId = $ulogaPodnositeljaId;
        $this->predmetId = null;
        $this->predmetOznaka = null;

        return $this;
    }

    /**
     * Configure for existing case by ID.
     */
    public function forExistingCase(int $predmetId): self
    {
        $this->isNewProceeding = false;
        $this->predmetId = $predmetId;
        $this->predmetOznaka = null;
        $this->vrstaPostupkaId = null;
        $this->ulogaPodnositeljaId = null;

        return $this;
    }

    /**
     * Configure for existing case by oznaka.
     */
    public function forExistingCaseByOznaka(string $predmetOznaka): self
    {
        $this->isNewProceeding = false;
        $this->predmetOznaka = $predmetOznaka;
        $this->predmetId = null;
        $this->vrstaPostupkaId = null;
        $this->ulogaPodnositeljaId = null;

        return $this;
    }

    /**
     * Set submission type.
     */
    public function withSubmissionType(int $vrstaPodneskaId): self
    {
        $this->vrstaPodneskaId = $vrstaPodneskaId;

        return $this;
    }

    /**
     * Set the main document content.
     *
     * @param string $naziv Filename
     * @param string $base64Content Base64 encoded file content
     * @param int $brojStranica Number of pages
     * @param bool $ignorirajUpozorenja Ignore format warnings
     */
    public function withContent(string $naziv, string $base64Content, int $brojStranica = 1, bool $ignorirajUpozorenja = false): self
    {
        $this->sadrzaj = [
            'naziv' => $naziv,
            'sadrzaj' => $base64Content,
            'brojStranica' => $brojStranica,
            'ignorirajUpozorenja' => $ignorirajUpozorenja,
        ];

        return $this;
    }

    /**
     * Set the main document content from file path.
     *
     * @param string $filePath Path to the file
     * @param int $brojStranica Number of pages
     * @param bool $ignorirajUpozorenja Ignore format warnings
     */
    public function withContentFromFile(string $filePath, int $brojStranica = 1, bool $ignorirajUpozorenja = false): self
    {
        if (! file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        $base64 = base64_encode($content);
        $naziv = basename($filePath);

        return $this->withContent($naziv, $base64, $brojStranica, $ignorirajUpozorenja);
    }

    /**
     * Add a party (stranka) - for new proceedings only.
     *
     * @param array $strankaData CreateStrankaRequest data
     */
    public function addStranka(array $strankaData): self
    {
        $this->stranke[] = $strankaData;

        return $this;
    }

    /**
     * Add opposing party (protustranka) - for new proceedings only.
     *
     * @param array $protustrankaData CreateStrankaRequest data
     */
    public function addProtustranka(array $protustrankaData): self
    {
        $this->protustranke[] = $protustrankaData;

        return $this;
    }

    /**
     * Add attachment.
     *
     * @param string $opis Description
     * @param string $naziv Filename
     * @param string $base64Content Base64 encoded content
     * @param int $brojStranica Number of pages
     * @param string|null $primjedba Note
     * @param bool $ignorirajUpozorenja Ignore format warnings
     */
    public function addPrilog(
        string $opis,
        string $naziv,
        string $base64Content,
        int $brojStranica = 1,
        ?string $primjedba = null,
        bool $ignorirajUpozorenja = false
    ): self {
        $prilog = [
            'opis' => $opis,
            'sadrzaj' => [
                'naziv' => $naziv,
                'sadrzaj' => $base64Content,
                'brojStranica' => $brojStranica,
                'ignorirajUpozorenja' => $ignorirajUpozorenja,
            ],
        ];

        if ($primjedba !== null) {
            $prilog['primjedba'] = $primjedba;
        }

        $this->prilozi[] = $prilog;

        return $this;
    }

    /**
     * Set additional fee data.
     */
    public function withFeeData(array $pristojbaDodatniPodaci): self
    {
        $this->pristojbaDodatniPodaci = $pristojbaDodatniPodaci;

        return $this;
    }

    /**
     * Set external references (S4-9).
     *
     * @param string $ustrojstvenaJedinica External organizational unit
     * @param string $predmet External case reference
     */
    public function withExternalRef(string $ustrojstvenaJedinica, string $predmet): self
    {
        $this->vanjskaUstrojstvenaJedinica = $ustrojstvenaJedinica;
        $this->vanjskiPredmet = $predmet;

        return $this;
    }

    /**
     * Set submitter by ordinal - for new proceedings only (S4-10).
     *
     * References a party in the stranke array by 1-indexed ordinal.
     *
     * @param int $rbr 1-indexed ordinal referencing stranke array
     */
    public function withSubmitterOrdinal(int $rbr): self
    {
        $this->podnositeljRbr = $rbr;
        $this->podnositeljSlobodanUnos = null;

        return $this;
    }

    /**
     * Set submitter by free-text name - for existing cases only (S4-10).
     *
     * @param string $name Submitter name in free-text format
     */
    public function withSubmitterName(string $name): self
    {
        $this->podnositeljSlobodanUnos = $name;
        $this->podnositeljRbr = null;

        return $this;
    }

    /**
     * Set the dispute value (S4-11).
     *
     * @param float $value Value of the dispute
     */
    public function withDisputeValue(float $value): self
    {
        $this->vrijednostPredmetaSpora = $value;

        return $this;
    }

    /**
     * Confirm that this submission is for a case not assigned to the submitter (S4-12).
     */
    public function confirmNotMyCase(): self
    {
        $this->potvrdaPredmetaKojiNijeMoj = true;

        return $this;
    }

    /**
     * Build the request payload.
     *
     * @throws InvalidArgumentException if required fields are missing
     */
    public function build(): array
    {
        $this->validate();

        $payload = [
            'vrstaPodneskaId' => $this->vrstaPodneskaId,
            'sadrzaj' => $this->sadrzaj,
        ];

        // Court identification
        if ($this->sudId !== null) {
            $payload['sudId'] = $this->sudId;
        } elseif ($this->sudOznaka !== null) {
            $payload['sudOznaka'] = $this->sudOznaka;
        }

        // Mode-specific fields
        if ($this->isNewProceeding) {
            $payload['vrstaPostupkaId'] = $this->vrstaPostupkaId;
            $payload['ulogaPodnositeljaId'] = $this->ulogaPodnositeljaId;

            if (! empty($this->stranke)) {
                $payload['stranke'] = $this->stranke;
            }

            if (! empty($this->protustranke)) {
                $payload['protustranke'] = $this->protustranke;
            }

            // S4-10: Submitter ordinal for new proceedings
            if ($this->podnositeljRbr !== null) {
                $payload['podnositeljRbr'] = $this->podnositeljRbr;
            }
        } else {
            // Existing case
            if ($this->predmetId !== null) {
                $payload['predmetId'] = $this->predmetId;
            } elseif ($this->predmetOznaka !== null) {
                $payload['predmetOznaka'] = $this->predmetOznaka;
            }

            // S4-15: Explicitly serialize empty arrays for existing case
            $payload['stranke'] = [];
            $payload['protustranke'] = [];
            $payload['prilozi'] = [];

            // S4-10: Submitter free-text name for existing case
            if ($this->podnositeljSlobodanUnos !== null) {
                $payload['podnositeljSlobodanUnos'] = $this->podnositeljSlobodanUnos;
            }
        }

        // Optional fields (for new proceedings)
        if ($this->isNewProceeding && ! empty($this->prilozi)) {
            $payload['prilozi'] = $this->prilozi;
        }

        if (! empty($this->pristojbaDodatniPodaci)) {
            $payload['pristojbaDodatniPodaci'] = $this->pristojbaDodatniPodaci;
        }

        // S4-9: External references
        if ($this->vanjskaUstrojstvenaJedinica !== null) {
            $payload['vanjskaUstrojstvenaJedinica'] = $this->vanjskaUstrojstvenaJedinica;
            $payload['vanjskiPredmet'] = $this->vanjskiPredmet;
        }

        // S4-11: Dispute value
        if ($this->vrijednostPredmetaSpora !== null) {
            $payload['vrijednostPredmetaSpora'] = $this->vrijednostPredmetaSpora;
        }

        // S4-12: Not my case flag
        if ($this->potvrdaPredmetaKojiNijeMoj) {
            $payload['potvrdaPredmetaKojiNijeMoj'] = true;
        }

        return $payload;
    }

    /**
     * Validate required fields are set.
     */
    private function validate(): void
    {
        $errors = [];

        // Court required
        if ($this->sudId === null && $this->sudOznaka === null) {
            $errors[] = 'Court must be specified (sudId or sudOznaka)';
        }

        // Submission type required
        if ($this->vrstaPodneskaId === null) {
            $errors[] = 'Submission type (vrstaPodneskaId) is required';
        }

        // Content required
        if (empty($this->sadrzaj)) {
            $errors[] = 'Content (sadrzaj) is required';
        }

        // Mode-specific validation
        if ($this->isNewProceeding) {
            if ($this->vrstaPostupkaId === null) {
                $errors[] = 'Procedure type (vrstaPostupkaId) is required for new proceeding';
            }
            if ($this->ulogaPodnositeljaId === null) {
                $errors[] = 'Submitter role (ulogaPodnositeljaId) is required for new proceeding';
            }
            if (empty($this->stranke)) {
                $errors[] = 'At least one party (stranka) is required for new proceeding';
            }

            // S4-10: Submitter name cannot be used for new proceeding
            if ($this->podnositeljSlobodanUnos !== null) {
                $errors[] = 'Submitter free-text name (podnositeljSlobodanUnos) cannot be used for new proceeding';
            }

            // S4-10: Validate submitter ordinal is positive
            if ($this->podnositeljRbr !== null && $this->podnositeljRbr < 1) {
                $errors[] = 'Submitter ordinal (podnositeljRbr) must be 1 or greater';
            }
        } else {
            if ($this->predmetId === null && $this->predmetOznaka === null) {
                $errors[] = 'Case must be specified (predmetId or predmetOznaka) for existing case';
            }

            // S4-15: Existing case cannot have parties
            if (! empty($this->stranke)) {
                $errors[] = 'Existing case cannot have parties (stranke)';
            }

            // S4-15: Existing case cannot have opposing parties
            if (! empty($this->protustranke)) {
                $errors[] = 'Existing case cannot have opposing parties (protustranke)';
            }

            // S4-10: Submitter ordinal cannot be used for existing case
            if ($this->podnositeljRbr !== null) {
                $errors[] = 'Submitter ordinal (podnositeljRbr) cannot be used for existing case';
            }
        }

        if (! empty($errors)) {
            throw new InvalidArgumentException(implode('; ', $errors));
        }
    }
}
