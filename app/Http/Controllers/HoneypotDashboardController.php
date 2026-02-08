<?php

namespace App\Http\Controllers;

use App\Models\HoneypotLog;
use Illuminate\Http\Request;

class HoneypotDashboardController extends Controller
{
    /**
     * Show the honeypot monitoring dashboard.
     */
    public function index()
    {
        $statistics = HoneypotLog::getStatistics();
        $topPaths = HoneypotLog::getTopTargetedPaths(15);
        $topIPs = HoneypotLog::getTopAttackingIPs(15);
        $recentActivity = HoneypotLog::getRecentActivity(24, 100);

        return view('honeypot.dashboard', compact('statistics', 'topPaths', 'topIPs', 'recentActivity'));
    }

    /**
     * Show details for a specific IP address.
     */
    public function showIP(Request $request, string $ip)
    {
        $statistics = HoneypotLog::getIPStatistics($ip);
        $logs = HoneypotLog::where('ip_address', $ip)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return view('honeypot.ip-details', compact('ip', 'statistics', 'logs'));
    }

    /**
     * Block an IP address.
     */
    public function blockIP(Request $request, string $ip)
    {
        HoneypotLog::blockIP($ip);

        return back()->with('success', "IP address {$ip} has been marked as blocked.");
    }

    /**
     * Export honeypot logs as JSON.
     */
    public function export(Request $request)
    {
        $hours = $request->input('hours', 24);
        $logs = HoneypotLog::where('created_at', '>=', now()->subHours($hours))
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'export_date' => now()->toIso8601String(),
            'period_hours' => $hours,
            'total_records' => $logs->count(),
            'logs' => $logs,
        ]);
    }

    /**
     * Get honeypot statistics as JSON for API.
     */
    public function apiStats()
    {
        return response()->json([
            'statistics' => HoneypotLog::getStatistics(),
            'top_targeted_paths' => HoneypotLog::getTopTargetedPaths(10),
            'top_attacking_ips' => HoneypotLog::getTopAttackingIPs(10),
            'recent_activity' => HoneypotLog::getRecentActivity(1, 20),
        ]);
    }
}
