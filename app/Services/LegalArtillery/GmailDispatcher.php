<?php

namespace App\Services\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Illuminate\Support\Facades\Log;
use App\Services\LegalArtillery\PiiRedactor;

class GmailDispatcher
{
    private ?GoogleClient $client = null;

    public function __construct(
        private readonly PiiRedactor $piiRedactor,
    ) {}

    /**
     * Preview what would be sent without actually sending.
     * Returns the complete payload for review.
     */
    public function preview(DocumentProfile $profile, CaseContext $context, string $docxPath, ?string $toEmail = null): array
    {
        $to = $toEmail ?? $profile->recipient['email'] ?? '';
        $subject = $this->resolveSubject($profile, $context);
        $body = $this->buildEmailBody($profile, $context);

        $payload = [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'attachment' => basename($docxPath),
            'attachment_path' => $docxPath,
            'attachment_exists' => file_exists($docxPath),
            'from' => config('legal-artillery.gmail.from_email', ''),
            'cc' => config('legal-artillery.gmail.cc'),
        ];

        return $this->piiRedactor->redactArray($payload);
    }

    public function send(
        DocumentProfile $profile,
        CaseContext $context,
        string $docxPath,
        ?string $toEmail = null,
        bool $asDraft = false,
    ): array {
        $gmail = $this->getGmailService();

        $to = $toEmail ?? $profile->recipient['email'] ?? null;
        if (!$to && !$asDraft) {
            Log::warning('GmailDispatcher: No email for recipient, saving as draft');
            $asDraft = true;
        }

        $subject = $this->resolveSubject($profile, $context);
        $body = $this->buildEmailBody($profile, $context);
        $mimeRaw = $this->buildMimeMessage($to ?? '', $subject, $body, $docxPath);

        $message = new Message();
        $message->setRaw(rtrim(strtr(base64_encode($mimeRaw), '+/', '-_'), '='));

        if ($asDraft) {
            $draft = new Gmail\Draft();
            $draft->setMessage($message);
            $result = $gmail->users_drafts->create('me', $draft);
            Log::info('GmailDispatcher: Draft saved', ['id' => $result->getId()]);
            return ['status' => 'draft', 'id' => $result->getId()];
        }

        $result = $gmail->users_messages->send('me', $message);
        Log::info('GmailDispatcher: Sent', [
            'id' => $result->getId(),
            'to' => $to ? $this->piiRedactor->redact($to) : null,
        ]);

        return ['status' => 'sent', 'id' => $result->getId(), 'to' => $to];
    }

    public function resolveSubject(DocumentProfile $profile, CaseContext $context): string
    {
        $template = $profile->emailSubjectTemplate ?? $profile->name;
        return $context->interpolate($template);
    }

    public function buildMimeMessage(string $to, string $subject, string $body, ?string $attachmentPath = null): string
    {
        $boundary = md5(uniqid((string) rand(), true));
        $fromEmail = $this->sanitizeHeaderValue(config('legal-artillery.gmail.from_email', ''));
        $fromName = $this->sanitizeHeaderValue(config('legal-artillery.gmail.from_name', ''));
        $cc = config('legal-artillery.gmail.cc');

        $headers = [];
        $headers[] = "From: {$fromName} <{$fromEmail}>";
        if ($to) {
            $headers[] = "To: " . $this->sanitizeHeaderValue($to);
        }
        if ($cc) {
            $headers[] = "Cc: " . $this->sanitizeHeaderValue($cc);
        }
        $headers[] = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";

        $mime = implode("\r\n", $headers) . "\r\n\r\n";

        // Body part
        $mime .= "--{$boundary}\r\n";
        $mime .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $mime .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $mime .= chunk_split(base64_encode($body)) . "\r\n";

        // Attachment
        if ($attachmentPath && file_exists($attachmentPath)) {
            $filename = basename($attachmentPath);
            $fileData = file_get_contents($attachmentPath);
            $mimeType = $this->resolveMimeType($attachmentPath);

            $mime .= "--{$boundary}\r\n";
            $mime .= "Content-Type: {$mimeType}; name=\"{$filename}\"\r\n";
            $mime .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n";
            $mime .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $mime .= chunk_split(base64_encode($fileData)) . "\r\n";
        }

        $mime .= "--{$boundary}--";

        return $mime;
    }

    private function buildEmailBody(DocumentProfile $profile, CaseContext $context): string
    {
        $vars = $context->toTemplateVars();
        return implode("\n", [
            "Postovani,",
            "",
            "U prilogu dostavljam: {$profile->name}",
            "Predmet: {$vars['case_number']}",
            "",
            "S postovanjem,",
            $vars['sender_name'],
            $vars['sender_address'],
            "OIB: {$vars['sender_oib']}",
            "E-mail: {$vars['sender_email']}",
        ]);
    }

    private function getGmailService(): Gmail
    {
        if (!$this->client) {
            $this->client = new GoogleClient();
            $this->client->setApplicationName('Legal Artillery');
            $this->client->setScopes([Gmail::GMAIL_COMPOSE, Gmail::GMAIL_SEND]);

            $credPath = config('legal-artillery.gmail.credentials_path');
            $tokenPath = config('legal-artillery.gmail.token_path');

            $this->client->setAuthConfig($credPath);
            $this->client->setAccessType('offline');

            if (file_exists($tokenPath)) {
                $token = json_decode(file_get_contents($tokenPath), true);
                $this->client->setAccessToken($token);

                if ($this->client->isAccessTokenExpired()) {
                    $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                    file_put_contents($tokenPath, json_encode($this->client->getAccessToken()));
                }
            } else {
                throw new \RuntimeException(
                    "Gmail token not found. Run: php artisan legal:gmail-auth"
                );
            }
        }

        return new Gmail($this->client);
    }

    /**
     * Sanitize header value to prevent CRLF injection attacks.
     *
     * Removes carriage returns, newlines, and null bytes that could be used
     * to inject additional email headers.
     */
    private function sanitizeHeaderValue(string $value): string
    {
        return str_replace(["\r", "\n", "\0"], '', $value);
    }

    private function resolveMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };
    }
}
