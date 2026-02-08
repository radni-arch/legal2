<?php

namespace App\Http\Livewire;

use App\Services\CircuitBreaker;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Circuit Breaker Monitoring Dashboard Component
 *
 * Displays real-time status of all circuit breakers with historical data
 * and manual reset capabilities.
 */
class CircuitBreakerMonitor extends Component
{
    /**
     * Services to monitor
     */
    public array $services = ['openai', 'eoglasna', 'neo4j', 'aws_textract'];

    /**
     * Auto-refresh interval (in seconds)
     */
    public int $refreshInterval = 5;

    /**
     * Reset a specific circuit breaker
     */
    public function resetCircuit(string $serviceName): void
    {
        try {
            $circuitBreaker = new CircuitBreaker($serviceName);
            $circuitBreaker->reset();

            session()->flash('success', "Circuit breaker for {$serviceName} has been reset.");
        } catch (\Throwable $e) {
            session()->flash('error', "Failed to reset circuit breaker: {$e->getMessage()}");
        }
    }

    /**
     * Get the current status of all circuit breakers
     */
    public function getCircuitStatusesProperty(): array
    {
        $statuses = [];

        foreach ($this->services as $service) {
            try {
                $circuitBreaker = new CircuitBreaker($service);
                $statuses[$service] = $circuitBreaker->getStatus();
            } catch (\Throwable $e) {
                $statuses[$service] = [
                    'service' => $service,
                    'state' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $statuses;
    }

    /**
     * Get historical events from the last 24 hours
     */
    public function getRecentEventsProperty(): array
    {
        try {
            return DB::table('circuit_breaker_events')
                ->where('created_at', '>=', now()->subDay())
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            // Database table might not exist yet
            return [];
        }
    }

    /**
     * Render the component
     */
    public function render()
    {
        return view('livewire.circuit-breaker-monitor', [
            'circuitStatuses' => $this->circuitStatuses,
            'recentEvents' => $this->recentEvents,
        ]);
    }
}
