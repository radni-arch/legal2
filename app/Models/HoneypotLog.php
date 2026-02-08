<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class HoneypotLog extends Model
{
    protected $fillable = [
        'ip_address',
        'user_agent',
        'method',
        'path',
        'full_url',
        'headers',
        'query_params',
        'body',
        'referer',
        'attempted_auth',
        'severity',
        'is_blocked',
        'notes',
    ];

    protected $casts = [
        'headers' => 'array',
        'query_params' => 'array',
        'is_blocked' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the most targeted paths.
     */
    public static function getTopTargetedPaths(int $limit = 10): array
    {
        return self::select('path', DB::raw('COUNT(*) as count'))
            ->groupBy('path')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get the most active attacking IPs.
     */
    public static function getTopAttackingIPs(int $limit = 10): array
    {
        return self::select('ip_address', DB::raw('COUNT(*) as count'))
            ->groupBy('ip_address')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get recent honeypot activity.
     */
    public static function getRecentActivity(int $hours = 24, int $limit = 50): array
    {
        return self::where('created_at', '>=', now()->subHours($hours))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get statistics for a specific IP address.
     */
    public static function getIPStatistics(string $ip): array
    {
        $logs = self::where('ip_address', $ip)->get();

        return [
            'total_attempts' => $logs->count(),
            'first_seen' => $logs->min('created_at'),
            'last_seen' => $logs->max('created_at'),
            'paths_attempted' => $logs->pluck('path')->unique()->values()->toArray(),
            'methods_used' => $logs->pluck('method')->unique()->values()->toArray(),
            'user_agents' => $logs->pluck('user_agent')->unique()->values()->toArray(),
            'is_blocked' => $logs->where('is_blocked', true)->count() > 0,
        ];
    }

    /**
     * Get honeypot statistics summary.
     */
    public static function getStatistics(): array
    {
        $totalAttempts = self::count();
        $uniqueIPs = self::distinct('ip_address')->count();
        $last24h = self::where('created_at', '>=', now()->subHours(24))->count();
        $last7days = self::where('created_at', '>=', now()->subDays(7))->count();
        $blockedIPs = self::where('is_blocked', true)->distinct('ip_address')->count();

        return [
            'total_attempts' => $totalAttempts,
            'unique_ips' => $uniqueIPs,
            'attempts_last_24h' => $last24h,
            'attempts_last_7days' => $last7days,
            'blocked_ips' => $blockedIPs,
            'avg_attempts_per_ip' => $uniqueIPs > 0 ? round($totalAttempts / $uniqueIPs, 2) : 0,
        ];
    }

    /**
     * Check if an IP should be blocked based on attempt count.
     */
    public static function shouldBlockIP(string $ip, int $threshold = 10): bool
    {
        $recentAttempts = self::where('ip_address', $ip)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return $recentAttempts >= $threshold;
    }

    /**
     * Mark an IP as blocked.
     */
    public static function blockIP(string $ip): void
    {
        self::where('ip_address', $ip)->update(['is_blocked' => true]);
    }
}
