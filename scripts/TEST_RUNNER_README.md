# Parallel Test Runner Scripts

This directory contains scripts for running all test suites in parallel with comprehensive logging and monitoring.

## Overview

The parallel test runner system allows you to run all test suites simultaneously in the background, with each suite logging to its own file. You can monitor progress in real-time or check status at any time.

## Available Scripts

### 1. `run-all-tests-parallel.sh` - Main Test Runner

Starts all test suites in parallel with nohup and tails their logs in real-time.

**Usage:**
```bash
./scripts/run-all-tests-parallel.sh
```

**What it does:**
- Runs Dusk tests (Browser/E2E tests) in background
- Runs Unit tests in background
- Runs Feature tests in background
- Runs Integration tests in background
- Each suite logs to `storage/logs/tests/[suite-name].log`
- Tails all logs with colored output
- Press Ctrl+C to stop monitoring (tests continue in background)

**Test Suites:**
- **Dusk Tests**: Browser/E2E tests (`php artisan dusk`)
- **Unit Tests**: Unit test suite (`php artisan test --testsuite=Unit`)
- **Feature Tests**: Feature test suite (`php artisan test --testsuite=Feature`)
- **Integration Tests**: Integration test suite (`php artisan test --testsuite=Integration`)

### 2. `check-test-status.sh` - Status Monitor

Checks the status of all running or completed test suites.

**Usage:**
```bash
./scripts/check-test-status.sh
```

**Output:**
- Shows which tests are still running (⏳)
- Shows which tests passed (✓)
- Shows which tests failed (✗)
- Displays last log line for running tests
- Shows exit codes for completed tests

### 3. `view-test-logs.sh` - Log Viewer

View logs from specific test suites in real-time.

**Usage:**
```bash
# View specific suite logs
./scripts/view-test-logs.sh dusk
./scripts/view-test-logs.sh unit
./scripts/view-test-logs.sh feature
./scripts/view-test-logs.sh integration

# View all logs combined
./scripts/view-test-logs.sh all
```

**Features:**
- Color-coded output (green for passes, red for failures)
- Real-time streaming with `tail -f`
- Press Ctrl+C to stop viewing

### 4. `stop-all-tests.sh` - Stop All Tests

Stops all running test suite processes.

**Usage:**
```bash
./scripts/stop-all-tests.sh
```

**What it does:**
- Gracefully stops all running test processes
- Force kills any processes that don't stop
- Cleans up PID files
- Shows summary of stopped processes

## Directory Structure

```
./storage/logs/tests/
├── dusk-tests.log         # Dusk/Browser test output
├── unit-tests.log         # Unit test output
├── feature-tests.log      # Feature test output
├── integration-tests.log  # Integration test output
├── all-tests.log          # Combined output (optional)
└── pids/                  # Process ID files
    ├── Dusk Tests.pid
    ├── Unit Tests.pid
    ├── Feature Tests.pid
    └── Integration Tests.pid
```

## Workflow Examples

### Running All Tests in Background

```bash
# Start all tests
./scripts/run-all-tests-parallel.sh

# The script will tail logs automatically
# Press Ctrl+C when you want to stop monitoring
# Tests continue running in background

# Check status later
./scripts/check-test-status.sh

# View specific logs
./scripts/view-test-logs.sh dusk

# Stop all tests if needed
./scripts/stop-all-tests.sh
```

### Monitoring Long-Running Tests

```bash
# Start tests
./scripts/run-all-tests-parallel.sh

# In another terminal, check status
./scripts/check-test-status.sh

# View specific suite
./scripts/view-test-logs.sh unit

# Or check the log files directly
tail -f ./storage/logs/tests/unit-tests.log
```

### Automated CI/CD Usage

```bash
# Start all tests
./scripts/run-all-tests-parallel.sh &

# Wait for them to complete
while ps aux | grep -q "php artisan test"; do
    sleep 10
    ./scripts/check-test-status.sh
done

# Check final status
./scripts/check-test-status.sh
```

## Log File Format

Each log file contains:

```
========================================
Test Suite: [Name]
Started: 2025-11-16 15:30:00
Command: php artisan test --testsuite=Unit
========================================

[START] Unit Tests test suite started at Sat Nov 16 15:30:01 UTC 2025

... test output ...

[END] Unit Tests test suite finished at Sat Nov 16 15:35:00 UTC 2025 with exit code: 0
```

## Exit Codes

- **0**: All tests passed
- **1+**: Tests failed or errors occurred

## Tips

1. **View logs in another terminal**: While tests run, open another terminal to view logs without interrupting the main runner.

2. **Check disk space**: Long test runs can generate large log files. Monitor `./storage/logs/tests/` size.

3. **Clean old logs**: The runner automatically cleans old logs on each run.

4. **Process management**: Each test suite runs in its own process, so one suite's failure doesn't affect others.

5. **Parallel execution**: All suites run simultaneously, making the total run time approximately equal to the longest-running suite.

## Troubleshooting

### Tests don't start

- Check if processes are already running: `./scripts/check-test-status.sh`
- Stop existing processes: `./scripts/stop-all-tests.sh`
- Check logs for errors: `ls -la ./storage/logs/tests/`

### Process cleanup

If scripts don't clean up properly:

```bash
# Manual cleanup
rm -rf ./storage/logs/tests/pids/*.pid

# Kill any orphaned test processes
pkill -f "php artisan test"
pkill -f "php artisan dusk"
```

### Permission issues

```bash
# Make scripts executable
chmod +x ./scripts/*.sh

# Ensure log directory is writable
mkdir -p ./storage/logs/tests
chmod -R 755 ./storage/logs/tests
```

## Integration with Existing Scripts

These parallel runners complement the existing test scripts:

- `./scripts/run-tests.sh` - Integrated test runner (sequential)
- `composer test` - Standard test runner
- `composer test:integrated` - Uses run-tests.sh

Use the parallel runner when you want:
- All test suites to run simultaneously
- Background execution with logging
- Real-time monitoring of multiple suites
- CI/CD integration with status checking
