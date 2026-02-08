<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\UsesTestDatabase;

class HoneypotControllerTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_returns_fake_admin_login_error(): void
    {
        $response = $this->postJson('/api/admin/login', [
            'username' => 'admin',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJsonStructure([
                'error',
                'message',
                'attempts_remaining',
            ])
            ->assertJson([
                'error' => 'Invalid credentials',
            ]);
    }

    /** @test */
    public function it_returns_fake_admin_users_list(): void
    {
        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'username',
                        'email',
                        'role',
                        'created_at',
                    ],
                ],
                'total',
            ])
            ->assertJson([
                'success' => true,
            ]);

        $this->assertIsArray($response->json('data'));
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    /** @test */
    public function it_returns_fake_config_data(): void
    {
        $response = $this->getJson('/api/admin/config');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'database' => [
                    'host',
                    'port',
                    'database',
                    'username',
                    'password',
                ],
                'api_keys',
                'debug',
                'environment',
            ]);

        // Verify it contains fake/masked data
        $this->assertStringContainsString('***', $response->json('database.password'));
    }

    /** @test */
    public function it_returns_fake_env_file_content(): void
    {
        $response = $this->get('/api/.env');

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('APP_NAME', $content);
        $this->assertStringContainsString('DB_PASSWORD', $content);
        $this->assertStringContainsString('AWS_ACCESS_KEY_ID', $content);
        $this->assertStringContainsString('OPENAI_API_KEY', $content);

        // Verify passwords are fake
        $this->assertStringContainsString('fake', $content);
    }

    /** @test */
    public function it_serves_fake_env_file_from_multiple_routes(): void
    {
        $response1 = $this->get('/api/.env');
        $response2 = $this->get('/api/env');

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        $this->assertEquals($response1->getContent(), $response2->getContent());
    }

    /** @test */
    public function it_returns_fake_database_backup_info(): void
    {
        $response = $this->getJson('/api/admin/backup');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'backup_file',
                'download_url',
                'size',
                'status',
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Backup initiated',
            ]);
    }

    /** @test */
    public function it_returns_fake_database_dump(): void
    {
        $response = $this->get('/api/database/dump');

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('MySQL dump', $content);
        $this->assertStringContainsString('CREATE TABLE', $content);
        $this->assertStringContainsString('users', $content);
        $this->assertStringContainsString('fakehashedpassword', $content);
    }

    /** @test */
    public function it_returns_fake_debug_info(): void
    {
        $response = $this->getJson('/api/debug');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'php_version',
                'laravel_version',
                'server_ip',
                'memory_usage',
                'disk_free',
                'loaded_extensions',
                'environment',
                'debug_mode',
                'sensitive_data',
            ]);

        $this->assertEquals('production', $response->json('environment'));
    }

    /** @test */
    public function it_serves_debug_info_from_multiple_routes(): void
    {
        $response1 = $this->getJson('/api/debug');
        $response2 = $this->getJson('/api/debug/info');

        $response1->assertStatus(200);
        $response2->assertStatus(200);
        $this->assertEquals($response1->json(), $response2->json());
    }

    /** @test */
    public function it_returns_fake_phpinfo_html(): void
    {
        $response = $this->get('/api/phpinfo');

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('PHP Version', $content);
        $this->assertStringContainsString('System Information', $content);
        $this->assertStringContainsString('DB_PASSWORD', $content);
        $this->assertStringContainsString('fake', $content);
    }

    /** @test */
    public function it_serves_phpinfo_from_multiple_routes(): void
    {
        $response1 = $this->get('/api/phpinfo');
        $response2 = $this->get('/api/phpinfo.php');
        $response3 = $this->get('/api/info.php');

        $response1->assertStatus(200);
        $response2->assertStatus(200);
        $response3->assertStatus(200);

        $this->assertEquals($response1->getContent(), $response2->getContent());
        $this->assertEquals($response1->getContent(), $response3->getContent());
    }

    /** @test */
    public function it_returns_fake_user_passwords(): void
    {
        $response = $this->getJson('/api/users/passwords');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'users' => [
                    '*' => [
                        'id',
                        'username',
                        'password_hash',
                    ],
                ],
                'encryption',
            ])
            ->assertJson([
                'success' => true,
                'encryption' => 'bcrypt',
            ]);

        $this->assertIsArray($response->json('users'));
        $this->assertGreaterThan(0, count($response->json('users')));
    }

    /** @test */
    public function it_returns_fake_api_keys(): void
    {
        $response = $this->getJson('/api/api/keys');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'keys' => [
                    '*' => [
                        'name',
                        'key',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $keys = $response->json('keys');
        $this->assertIsArray($keys);
        $this->assertGreaterThan(0, count($keys));

        // Verify keys contain fake data
        $firstKey = $keys[0];
        $this->assertStringContainsString('fake', strtolower($firstKey['key']));
    }

    /** @test */
    public function it_serves_api_keys_from_multiple_routes(): void
    {
        $response1 = $this->getJson('/api/api/keys');
        $response2 = $this->getJson('/api/api/tokens');
        $response3 = $this->getJson('/api/admin/keys');

        $response1->assertStatus(200);
        $response2->assertStatus(200);
        $response3->assertStatus(200);

        $this->assertEquals($response1->json(), $response2->json());
        $this->assertEquals($response1->json(), $response3->json());
    }

    /** @test */
    public function it_returns_fake_git_config(): void
    {
        $response = $this->getJson('/api/.git/config');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'repository',
                'branch',
                'last_commit',
                'credentials' => [
                    'username',
                    'token',
                ],
                'deploy_key',
            ]);

        $this->assertStringContainsString('fake', strtolower($response->json('credentials.token')));
        $this->assertStringContainsString('FAKE_KEY_DATA', $response->json('deploy_key'));
    }

    /** @test */
    public function it_handles_sql_injection_attempts(): void
    {
        $response = $this->getJson('/api/user?user_id=1 OR 1=1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'query',
                'result',
                'note',
            ]);

        $this->assertStringContainsString('SELECT * FROM users', $response->json('query'));
    }

    /** @test */
    public function it_handles_sql_injection_via_post(): void
    {
        $response = $this->postJson('/api/user', [
            'user_id' => "1' OR '1'='1",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'query',
                'result',
                'note',
            ]);
    }

    /** @test */
    public function it_handles_command_execution_attempts(): void
    {
        $response = $this->getJson('/api/exec?command=whoami');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'command',
                'output',
                'exit_code',
            ])
            ->assertJson([
                'exit_code' => 0,
            ]);

        $this->assertNotEmpty($response->json('output'));
    }

    /** @test */
    public function it_handles_command_execution_via_post(): void
    {
        $response = $this->postJson('/api/exec', [
            'command' => 'ls -la',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'command',
                'output',
                'exit_code',
            ]);
    }

    /** @test */
    public function it_serves_command_execution_from_cmd_endpoint(): void
    {
        $response = $this->postJson('/api/cmd', [
            'command' => 'pwd',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'command',
                'output',
                'exit_code',
            ]);
    }

    /** @test */
    public function it_returns_fake_s3_upload_credentials(): void
    {
        $response = $this->getJson('/api/s3/upload');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'bucket',
                'region',
                'access_key',
                'secret_key',
                'presigned_url',
            ])
            ->assertJson([
                'success' => true,
            ]);

        $this->assertStringContainsString('fake', strtolower($response->json('presigned_url')));
    }

    /** @test */
    public function it_returns_fake_internal_docs(): void
    {
        $response = $this->getJson('/api/internal/docs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'api_version',
                'endpoints',
                'authentication',
            ]);

        $this->assertIsArray($response->json('endpoints'));
        $this->assertGreaterThan(0, count($response->json('endpoints')));
    }

    /** @test */
    public function it_does_not_require_authentication_for_honeypot_endpoints(): void
    {
        // All honeypot endpoints should be accessible without authentication
        $endpoints = [
            ['GET', '/api/admin/users'],
            ['GET', '/api/.env'],
            ['GET', '/api/admin/config'],
            ['POST', '/api/admin/login'],
            ['GET', '/api/phpinfo'],
            ['GET', '/api/debug'],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $method === 'GET'
                ? $this->getJson($url)
                : $this->postJson($url, []);

            $response->assertStatus(200);
        }
    }

    /** @test */
    public function it_returns_consistent_fake_admin_users(): void
    {
        $response1 = $this->getJson('/api/admin/users');
        $response2 = $this->getJson('/api/admin/users');

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        $this->assertEquals($response1->json('total'), $response2->json('total'));
        $this->assertEquals($response1->json('data'), $response2->json('data'));
    }

    /** @test */
    public function it_returns_fake_config_from_multiple_routes(): void
    {
        $response1 = $this->getJson('/api/admin/config');
        $response2 = $this->getJson('/api/config/database');
        $response3 = $this->getJson('/api/config/app');
        $response4 = $this->getJson('/api/credentials');

        $response1->assertStatus(200);
        $response2->assertStatus(200);
        $response3->assertStatus(200);
        $response4->assertStatus(200);

        // All should return same fake config structure
        $this->assertEquals($response1->json(), $response2->json());
        $this->assertEquals($response1->json(), $response3->json());
        $this->assertEquals($response1->json(), $response4->json());
    }

    /** @test */
    public function it_returns_fake_database_backup_via_post(): void
    {
        $response = $this->postJson('/api/admin/backup');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'backup_file',
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function it_serves_database_dump_from_multiple_routes(): void
    {
        $response1 = $this->get('/api/database/dump');
        $response2 = $this->get('/api/backup.sql');

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        $this->assertEquals($response1->getContent(), $response2->getContent());
    }

    /** @test */
    public function it_serves_database_backup_from_multiple_routes(): void
    {
        $response1 = $this->getJson('/api/admin/backup');
        $response2 = $this->getJson('/api/db/backup');

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        $this->assertEquals($response1->json(), $response2->json());
    }

    /** @test */
    public function it_contains_honeypot_indicators_in_responses(): void
    {
        $endpoints = [
            '/api/.env',
            '/api/admin/config',
            '/api/phpinfo',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->get($endpoint);
            $content = $response->getContent();

            // Verify responses contain "fake" or "example" data
            $this->assertTrue(
                str_contains(strtolower($content), 'fake') ||
                str_contains(strtolower($content), 'example') ||
                str_contains(strtolower($content), '***'),
                "Endpoint {$endpoint} should contain fake/example data"
            );
        }
    }

    /** @test */
    public function it_returns_varying_attempts_remaining_for_admin_login(): void
    {
        $response = $this->postJson('/api/admin/login', [
            'username' => 'admin',
            'password' => 'test',
        ]);

        $response->assertStatus(401);
        $attemptsRemaining = $response->json('attempts_remaining');

        $this->assertIsInt($attemptsRemaining);
        $this->assertGreaterThanOrEqual(1, $attemptsRemaining);
        $this->assertLessThanOrEqual(3, $attemptsRemaining);
    }

    /** @test */
    public function it_provides_realistic_looking_fake_data(): void
    {
        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(200);

        $users = $response->json('data');
        $firstUser = $users[0];

        // Verify structure looks realistic
        $this->assertArrayHasKey('id', $firstUser);
        $this->assertArrayHasKey('username', $firstUser);
        $this->assertArrayHasKey('email', $firstUser);
        $this->assertArrayHasKey('role', $firstUser);
        $this->assertArrayHasKey('created_at', $firstUser);

        // Verify data types
        $this->assertIsInt($firstUser['id']);
        $this->assertIsString($firstUser['username']);
        $this->assertIsString($firstUser['email']);
        $this->assertStringContainsString('@', $firstUser['email']);
    }
}
