<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class HoneypotController extends Controller
{
    /**
     * Fake admin login endpoint.
     */
    public function adminLogin(Request $request)
    {
        return response()->json([
            'error' => 'Invalid credentials',
            'message' => 'The username or password is incorrect.',
            'attempts_remaining' => rand(1, 3),
        ], 401);
    }

    /**
     * Fake admin users list endpoint.
     */
    public function adminUsers()
    {
        return response()->json([
            'success' => true,
            'data' => [
                [
                    'id' => 1,
                    'username' => 'admin',
                    'email' => 'admin@example.com',
                    'role' => 'administrator',
                    'created_at' => '2024-01-15T10:30:00Z',
                ],
                [
                    'id' => 2,
                    'username' => 'superuser',
                    'email' => 'super@example.com',
                    'role' => 'superadmin',
                    'created_at' => '2024-02-20T14:22:00Z',
                ],
            ],
            'total' => 2,
        ]);
    }

    /**
     * Fake config/environment endpoint.
     */
    public function config()
    {
        return response()->json([
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
                'database' => 'production_db',
                'username' => 'db_user',
                'password' => '***********',
            ],
            'api_keys' => [
                'openai' => 'sk-fake***************************',
                'aws_access_key' => 'AKIA****************',
                'aws_secret' => '***********',
            ],
            'debug' => false,
            'environment' => 'production',
        ]);
    }

    /**
     * Fake .env file endpoint.
     */
    public function envFile()
    {
        $fakeEnv = <<<'ENV'
APP_NAME="AI Legal Platform"
APP_ENV=production
APP_KEY=base64:fakekeyforsecuritytesting123456789012345678901234567890
APP_DEBUG=false
APP_URL=https://api.example.com

DB_CONNECTION=mysql
DB_HOST=10.0.0.15
DB_PORT=3306
DB_DATABASE=legal_db
DB_USERNAME=legal_user
DB_PASSWORD=fake_password_12345

REDIS_HOST=10.0.0.20
REDIS_PASSWORD=fake_redis_pass
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=admin@example.com
MAIL_PASSWORD=fake_mail_password

AWS_ACCESS_KEY_ID=AKIAIOSFODNN7EXAMPLE
AWS_SECRET_ACCESS_KEY=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
AWS_DEFAULT_REGION=us-east-1

OPENAI_API_KEY=sk-proj-fakekey123456789012345678901234567890
ENV;

        return response($fakeEnv, 200, [
            'Content-Type' => 'text/plain',
        ]);
    }

    /**
     * Fake database backup endpoint.
     */
    public function databaseBackup()
    {
        return response()->json([
            'success' => true,
            'message' => 'Backup initiated',
            'backup_file' => 'db_backup_'.date('Y-m-d').'.sql.gz',
            'download_url' => '/api/admin/backups/download/db_backup_'.date('Y-m-d').'.sql.gz',
            'size' => '2.3 GB',
            'status' => 'processing',
        ]);
    }

    /**
     * Fake database dump endpoint.
     */
    public function databaseDump()
    {
        $fakeSql = <<<SQL
-- MySQL dump 10.13  Distrib 8.0.32
--
-- Database: legal_production
-- Table structure for table `users`

CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `api_token` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `users` VALUES
(1,'Admin User','admin@example.com','$2y$10$fakehashedpassword123456789',NULL),
(2,'Super Admin','super@example.com','$2y$10$anotherfakehashedpassword789',NULL);
SQL;

        return response($fakeSql, 200, [
            'Content-Type' => 'application/sql',
            'Content-Disposition' => 'attachment; filename="database_dump.sql"',
        ]);
    }

    /**
     * Fake debug info endpoint.
     */
    public function debugInfo()
    {
        return response()->json([
            'php_version' => '8.2.10',
            'laravel_version' => '11.0.0',
            'server_ip' => '192.168.1.100',
            'memory_usage' => '128MB',
            'disk_free' => '450GB',
            'loaded_extensions' => ['pdo', 'pdo_mysql', 'openssl', 'mbstring', 'redis'],
            'environment' => 'production',
            'debug_mode' => 'enabled',
            'sensitive_data' => [
                'database_password' => 'exposed_for_debugging',
                'api_keys' => ['key1', 'key2', 'key3'],
            ],
        ]);
    }

    /**
     * Fake phpinfo endpoint.
     */
    public function phpInfo()
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head><title>PHP Info - Production Server</title></head>
<body>
<h1>PHP Version 8.2.10</h1>
<h2>System Information</h2>
<p>System: Linux production-server 5.15.0-56-generic</p>
<p>Server API: Apache 2.4.52</p>
<p>Configuration File: /etc/php/8.2/apache2/php.ini</p>
<h2>Environment Variables</h2>
<pre>
DB_PASSWORD=fake_production_password
AWS_SECRET_KEY=fake_aws_secret
OPENAI_KEY=sk-fake-key-here
</pre>
</body>
</html>
HTML;

        return response($html, 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Fake user passwords endpoint.
     */
    public function userPasswords()
    {
        return response()->json([
            'success' => true,
            'users' => [
                ['id' => 1, 'username' => 'admin', 'password_hash' => Hash::make('fake_password')],
                ['id' => 2, 'username' => 'superadmin', 'password_hash' => Hash::make('another_fake')],
                ['id' => 3, 'username' => 'developer', 'password_hash' => Hash::make('dev_password')],
            ],
            'encryption' => 'bcrypt',
        ]);
    }

    /**
     * Fake API keys endpoint.
     */
    public function apiKeys()
    {
        return response()->json([
            'success' => true,
            'keys' => [
                [
                    'name' => 'OpenAI Production',
                    'key' => 'sk-proj-fake'.str_repeat('X', 40),
                    'permissions' => ['read', 'write', 'admin'],
                    'created_at' => '2024-01-15',
                ],
                [
                    'name' => 'AWS Production',
                    'access_key' => 'AKIA'.str_repeat('X', 16),
                    'secret_key' => str_repeat('x', 40),
                    'region' => 'us-east-1',
                ],
            ],
        ]);
    }

    /**
     * Fake git config endpoint.
     */
    public function gitConfig()
    {
        return response()->json([
            'repository' => 'https://github.com/company/legal-platform.git',
            'branch' => 'production',
            'last_commit' => 'a1b2c3d4e5f6',
            'credentials' => [
                'username' => 'deploy-bot',
                'token' => 'ghp_fake'.str_repeat('X', 30),
            ],
            'deploy_key' => "-----BEGIN OPENSSH PRIVATE KEY-----\nFAKE_KEY_DATA_HERE\n-----END OPENSSH PRIVATE KEY-----",
        ]);
    }

    /**
     * Fake SQL injection vulnerable endpoint.
     */
    public function sqlVulnerable(Request $request)
    {
        $userId = $request->input('user_id', '1');

        return response()->json([
            'query' => "SELECT * FROM users WHERE id = {$userId}",
            'result' => [
                'id' => 1,
                'username' => 'admin',
                'email' => 'admin@example.com',
                'role' => 'administrator',
            ],
            'note' => 'Query executed successfully',
        ]);
    }

    /**
     * Fake command execution endpoint.
     */
    public function execCommand(Request $request)
    {
        $command = $request->input('command', 'whoami');

        return response()->json([
            'command' => $command,
            'output' => "www-data\n/var/www/html\nLinux production-server 5.15.0-56-generic",
            'exit_code' => 0,
        ]);
    }

    /**
     * Fake upload to S3 endpoint with credentials.
     */
    public function s3Upload()
    {
        return response()->json([
            'success' => true,
            'bucket' => 'legal-docs-production',
            'region' => 'us-east-1',
            'access_key' => 'AKIA'.str_repeat('X', 16),
            'secret_key' => str_repeat('x', 40),
            'presigned_url' => 'https://s3.amazonaws.com/legal-docs/upload?signature=fakesignature',
        ]);
    }

    /**
     * Fake internal API documentation.
     */
    public function internalDocs()
    {
        return response()->json([
            'api_version' => '2.0',
            'endpoints' => [
                [
                    'path' => '/api/admin/users',
                    'method' => 'GET',
                    'auth' => 'admin_token_required',
                    'description' => 'Get all users including sensitive data',
                ],
                [
                    'path' => '/api/admin/config',
                    'method' => 'GET',
                    'auth' => 'root_access',
                    'description' => 'Get system configuration including passwords',
                ],
            ],
            'authentication' => [
                'type' => 'Bearer Token',
                'test_token' => 'fake_test_token_for_development',
            ],
        ]);
    }
}
