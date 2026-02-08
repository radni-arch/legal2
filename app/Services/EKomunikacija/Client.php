<?php

namespace App\Services\EKomunikacija;

/**
 * Interface for e-komunikacija court submission API client.
 *
 * Handles communication with the Croatian court e-filing system (e-komunikacija).
 * Implementations should handle authentication, payload serialization, and HTTP transport.
 */
interface Client
{
    /**
     * Submit a document to the e-komunikacija system.
     *
     * @param array $payload Structured submission payload containing case_number, court_id, document_type, attachments, etc.
     * @return array Response with at least 'success' (bool), 'submission_id' (string), and 'timestamp' (string) keys.
     */
    public function submitDocument(array $payload): array;

    /**
     * Check the status of a previously submitted document.
     *
     * @param string $submissionId The submission ID returned from submitDocument()
     * @return array Status information including current state and any updates.
     */
    public function getSubmissionStatus(string $submissionId): array;

    /**
     * Get submission history for a given case number.
     *
     * @param string $caseNumber The case number to query history for
     * @return array List of submissions for the case.
     */
    public function getSubmissionHistory(string $caseNumber): array;
}
