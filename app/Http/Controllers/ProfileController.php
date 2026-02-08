<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    /**
     * Show the user's profile.
     */
    public function show(Request $request)
    {
        try {
            return view('profile.show', [
                'user' => $request->user(),
            ]);
        } catch (\Exception $e) {
            Log::error('Profile show failed', [
                'operation' => 'show',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to load profile');
        }
    }

    /**
     * Generate a new API token for the user.
     */
    public function generateToken(Request $request)
    {
        try {
            $user = $request->user();
            $token = $user->generateApiToken();

            return back()->with('token', $token)->with('success', 'API token generated successfully!');
        } catch (\Exception $e) {
            Log::error('Generate API token failed', [
                'operation' => 'generateToken',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to generate API token');
        }
    }

    /**
     * Revoke the user's API token.
     */
    public function revokeToken(Request $request)
    {
        try {
            $user = $request->user();
            $user->revokeApiToken();

            return back()->with('success', 'API token revoked successfully!');
        } catch (\Exception $e) {
            Log::error('Revoke API token failed', [
                'operation' => 'revokeToken',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to revoke API token');
        }
    }
}
