<?php

namespace App\Console\Commands\LegalArtillery;

use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GmailAuthCommand extends Command
{
    protected $signature = 'legal:gmail-auth';
    protected $description = 'Authenticate with Gmail API for Legal Artillery dispatch';

    public function handle(): int
    {
        $client = new GoogleClient();
        $client->setApplicationName('Legal Artillery');
        $client->setScopes([Gmail::GMAIL_COMPOSE, Gmail::GMAIL_SEND]);
        $client->setAuthConfig(config('legal-artillery.gmail.credentials_path'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $authUrl = $client->createAuthUrl();
        $this->info("Otvori ovaj URL u pregledniku:");
        $this->line($authUrl);

        $code = $this->ask('Unesi autorizacijski kod');

        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            $this->error("Greska: {$token['error']}");
            return self::FAILURE;
        }

        $tokenPath = config('legal-artillery.gmail.token_path');
        File::ensureDirectoryExists(dirname($tokenPath), 0700);

        file_put_contents($tokenPath, json_encode($token));
        $this->info("Token spremljen: {$tokenPath}");

        return self::SUCCESS;
    }
}
