<?php

namespace Tests\Unit\Models;

use App\Models\HoneypotLog;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class HoneypotLogTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $log = HoneypotLog::create([
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Mozilla/5.0',
            'method' => 'POST',
            'path' => '/wp-admin',
            'full_url' => 'https://example.com/wp-admin',
            'headers' => ['Accept' => '*/*'],
            'query_params' => ['action' => 'login'],
            'body' => 'username=admin&password=test',
            'referer' => 'https://google.com',
            'attempted_auth' => true,
            'severity' => 'high',
            'is_blocked' => false,
        ]);

        $this->assertEquals('192.168.1.100', $log->ip_address);
        $this->assertEquals('/wp-admin', $log->path);
        $this->assertEquals('high', $log->severity);
    }

    /** @test */
    public function it_casts_arrays()
    {
        $log = HoneypotLog::create([
            'ip_address' => '10.0.0.1',
            'headers' => ['User-Agent' => 'Bot', 'Accept' => 'text/html'],
            'query_params' => ['id' => '1', 'action' => 'delete'],
        ]);

        $this->assertIsArray($log->headers);
        $this->assertIsArray($log->query_params);
        $this->assertEquals('Bot', $log->headers['User-Agent']);
    }

    /** @test */
    public function it_casts_is_blocked_as_boolean()
    {
        $log = HoneypotLog::create([
            'ip_address' => '10.0.0.1',
            'is_blocked' => true,
        ]);

        $this->assertIsBool($log->is_blocked);
        $this->assertTrue($log->is_blocked);
    }

    /** @test */
    public function it_gets_top_targeted_paths()
    {
        HoneypotLog::create(['ip_address' => '1.1.1.1', 'path' => '/wp-admin']);
        HoneypotLog::create(['ip_address' => '2.2.2.2', 'path' => '/wp-admin']);
        HoneypotLog::create(['ip_address' => '3.3.3.3', 'path' => '/admin']);

        $paths = HoneypotLog::getTopTargetedPaths(5);

        $this->assertCount(2, $paths);
        $this->assertEquals('/wp-admin', $paths[0]['path']);
        $this->assertEquals(2, $paths[0]['count']);
    }

    /** @test */
    public function it_gets_top_attacking_ips()
    {
        HoneypotLog::create(['ip_address' => '1.1.1.1', 'path' => '/a']);
        HoneypotLog::create(['ip_address' => '1.1.1.1', 'path' => '/b']);
        HoneypotLog::create(['ip_address' => '2.2.2.2', 'path' => '/c']);

        $ips = HoneypotLog::getTopAttackingIPs(5);

        $this->assertCount(2, $ips);
        $this->assertEquals('1.1.1.1', $ips[0]['ip_address']);
        $this->assertEquals(2, $ips[0]['count']);
    }

    /** @test */
    public function it_checks_if_ip_should_be_blocked()
    {
        $ip = '10.10.10.10';

        // Create 5 recent attempts
        for ($i = 0; $i < 5; $i++) {
            HoneypotLog::create([
                'ip_address' => $ip,
                'path' => "/attempt-{$i}",
                'created_at' => now()->subMinutes(5),
            ]);
        }

        $this->assertFalse(HoneypotLog::shouldBlockIP($ip, 10));
        $this->assertTrue(HoneypotLog::shouldBlockIP($ip, 5));
    }

    /** @test */
    public function it_blocks_ip()
    {
        $ip = '192.168.1.1';
        HoneypotLog::create(['ip_address' => $ip, 'is_blocked' => false]);
        HoneypotLog::create(['ip_address' => $ip, 'is_blocked' => false]);

        HoneypotLog::blockIP($ip);

        $this->assertEquals(2, HoneypotLog::where('ip_address', $ip)->where('is_blocked', true)->count());
    }
}
