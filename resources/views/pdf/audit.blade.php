<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Document Generation Audit</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1f2933;
        }
        h1, h2, h3 {
            margin: 0 0 8px;
        }
        h1 {
            font-size: 20px;
        }
        h2 {
            font-size: 16px;
            margin-top: 20px;
        }
        h3 {
            font-size: 14px;
            margin-top: 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        th, td {
            border: 1px solid #d9e2ec;
            padding: 6px 8px;
            vertical-align: top;
        }
        th {
            background: #f5f7fa;
            text-align: left;
        }
        .muted {
            color: #627d98;
        }
        .section {
            margin-bottom: 16px;
        }
        pre {
            background: #f5f7fa;
            padding: 8px;
            border: 1px solid #d9e2ec;
            white-space: pre-wrap;
            word-break: break-word;
        }
    </style>
</head>
<body>
    <h1>Document Generation Audit</h1>
    <p class="muted">Exported at: {{ $auditData['exported_at'] ?? 'N/A' }}</p>

    <div class="section">
        <h2>Run Summary</h2>
        <table>
            <tbody>
                <tr>
                    <th>Run ID</th>
                    <td>{{ $auditData['run_id'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Document Type</th>
                    <td>{{ $auditData['document_type'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td>{{ $auditData['status'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Created At</th>
                    <td>{{ $auditData['created_at'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Updated At</th>
                    <td>{{ $auditData['updated_at'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Document Hash</th>
                    <td>{{ $auditData['document_hash'] ?? 'N/A' }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>User</h2>
        <table>
            <tbody>
                <tr>
                    <th>User ID</th>
                    <td>{{ $auditData['user']['id'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Name</th>
                    <td>{{ $auditData['user']['name'] ?? 'N/A' }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Approval</h2>
        <table>
            <tbody>
                <tr>
                    <th>Approved At</th>
                    <td>{{ $auditData['approval']['approved_at'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Approved By</th>
                    <td>{{ $auditData['approval']['approved_by'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Approver Name</th>
                    <td>{{ $auditData['approval']['approver_name'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Notes</th>
                    <td>{{ $auditData['approval']['notes'] ?? 'N/A' }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Generation</h2>
        <table>
            <tbody>
                <tr>
                    <th>Total Iterations</th>
                    <td>{{ $auditData['generation']['total_iterations'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Final Score</th>
                    <td>{{ $auditData['generation']['final_score'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Stopped Reason</th>
                    <td>{{ $auditData['generation']['stopped_reason'] ?? 'N/A' }}</td>
                </tr>
            </tbody>
        </table>

        <h3>Model Config</h3>
        <pre>{{ json_encode($auditData['generation']['model_config'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </div>

    <div class="section">
        <h2>Iterations</h2>
        @if(!empty($auditData['iterations']))
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Phase</th>
                        <th>Weighted Score</th>
                        <th>Improvement Delta</th>
                        <th>AI Model Used</th>
                        <th>Tokens Used</th>
                        <th>Cost Estimate</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($auditData['iterations'] as $iteration)
                        <tr>
                            <td>{{ $iteration['iteration_number'] ?? 'N/A' }}</td>
                            <td>{{ $iteration['phase'] ?? 'N/A' }}</td>
                            <td>{{ $iteration['weighted_score'] ?? 'N/A' }}</td>
                            <td>{{ $iteration['improvement_delta'] ?? 'N/A' }}</td>
                            <td>{{ $iteration['ai_model_used'] ?? 'N/A' }}</td>
                            <td>{{ $iteration['tokens_used'] ?? 'N/A' }}</td>
                            <td>{{ $iteration['cost_estimate'] ?? 'N/A' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="muted">No iterations recorded.</p>
        @endif
    </div>

    <div class="section">
        <h2>Dispatch</h2>
        <table>
            <tbody>
                <tr>
                    <th>Dispatched At</th>
                    <td>{{ $auditData['dispatch']['dispatched_at'] ?? 'N/A' }}</td>
                </tr>
            </tbody>
        </table>

        <h3>Dispatch Result</h3>
        <pre>{{ json_encode($auditData['dispatch']['result'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </div>
</body>
</html>
