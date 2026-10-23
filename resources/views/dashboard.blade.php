<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PolicyFlow | Operational Console</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-base: #0b0f19;
            --bg-card: #111827;
            --bg-elevated: #1e293b;
            --border: #334155;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --accent: #06b6d4;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #8b5cf6;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-base);
            color: var(--text-main);
            line-height: 1.5;
            padding: 24px;
        }

        .container {
            max-width: 1300px;
            margin: 0 auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 28px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #3b82f6, #06b6d4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #fff;
            font-size: 20px;
        }

        h1 {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .subtitle {
            font-size: 13px;
            color: var(--text-muted);
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: inherit;
        }

        .btn-primary {
            background-color: var(--primary);
            color: #fff;
        }
        .btn-primary:hover { background-color: var(--primary-hover); }

        .btn-secondary {
            background-color: var(--bg-elevated);
            color: var(--text-main);
            border-color: var(--border);
        }
        .btn-secondary:hover { background-color: #273549; }

        .btn-warning {
            background-color: #d97706;
            color: #fff;
        }
        .btn-warning:hover { background-color: #b45309; }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .grid-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 24px;
        }

        @media (max-width: 1024px) {
            .grid-layout { grid-template-columns: 1fr; }
        }

        .card {
            background-color: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .card-title {
            font-size: 16px;
            font-weight: 600;
        }

        /* Form elements */
        .form-group {
            margin-bottom: 14px;
        }

        label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        input, select {
            width: 100%;
            padding: 9px 12px;
            border-radius: 8px;
            background-color: var(--bg-elevated);
            border: 1px solid var(--border);
            color: #fff;
            font-size: 13px;
            font-family: inherit;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        /* Table */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        th {
            padding: 10px 14px;
            background-color: rgba(255, 255, 255, 0.02);
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 12px 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            color: var(--text-main);
        }

        tr:hover td {
            background-color: rgba(255, 255, 255, 0.02);
        }

        /* Status Badges */
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .badge-RECEIVED { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
        .badge-VALIDATED { background: rgba(6, 182, 212, 0.15); color: #22d3ee; border: 1px solid rgba(6, 182, 212, 0.3); }
        .badge-SUBMITTED { background: rgba(139, 92, 246, 0.15); color: #a78bfa; border: 1px solid rgba(139, 92, 246, 0.3); }
        .badge-PENDING_CONFIRMATION { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .badge-ISSUED { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-FAILED { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .badge-NEEDS_RECONCILIATION { background: rgba(236, 72, 153, 0.15); color: #f472b6; border: 1px solid rgba(236, 72, 153, 0.3); }

        .mono {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
        }

        /* Console Log Output */
        .console-output {
            background-color: #05070e;
            border: 1px solid #1e293b;
            border-radius: 8px;
            padding: 12px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: #38bdf8;
            max-height: 220px;
            overflow-y: auto;
            white-space: pre-wrap;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.7);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            max-width: 650px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            padding: 24px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="brand">
                <div class="logo">PF</div>
                <div>
                    <h1>PolicyFlow <span style="font-size:12px; font-weight:normal; background:#1e293b; padding:2px 8px; border-radius:12px; margin-left:8px; border:1px solid #334155;">TrippersShield Engine</span></h1>
                    <div class="subtitle">Production-grade Insurance Intake, Idempotent Issuance & Timeout Reconciliation</div>
                </div>
            </div>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="loadPolicies()">↻ Refresh List</button>
            </div>
        </header>

        <div class="grid-layout">
            <!-- Left Main Column -->
            <div>
                <!-- Policy Requests Table -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Active Policy Requests</div>
                        <div style="display: flex; gap: 8px;">
                            <select id="filterStatus" onchange="loadPolicies()" style="width: auto; padding: 4px 8px; font-size: 12px;">
                                <option value="">All Statuses</option>
                                <option value="RECEIVED">RECEIVED</option>
                                <option value="VALIDATED">VALIDATED</option>
                                <option value="SUBMITTED">SUBMITTED</option>
                                <option value="PENDING_CONFIRMATION">PENDING_CONFIRMATION</option>
                                <option value="ISSUED">ISSUED</option>
                                <option value="NEEDS_RECONCILIATION">NEEDS_RECONCILIATION</option>
                                <option value="FAILED">FAILED</option>
                            </select>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Client / Agent</th>
                                    <th>Customer & Plan</th>
                                    <th>Premium (₹)</th>
                                    <th>Status</th>
                                    <th>Policy Number</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="policiesTableBody">
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-muted);">Loading policies...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Live Execution Console Logs -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">API Response Log & State Tracing</div>
                        <button class="btn btn-secondary btn-sm" onclick="clearLog()">Clear</button>
                    </div>
                    <div id="consoleOutput" class="console-output">// PolicyFlow ready. Perform an action to view real-time API request/response payloads.</div>
                </div>
            </div>

            <!-- Right Control Column -->
            <div>
                <!-- Quick Intake Form -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">New Policy Request Intake</div>
                    </div>
                    <form id="intakeForm" onsubmit="submitIntake(event)">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Client Request ID</label>
                                <input type="text" id="clientRequestId" value="CR-TEST-9001" required>
                            </div>
                            <div class="form-group">
                                <label>Agent ID</label>
                                <input type="text" id="agentId" value="AGT-MUM-01" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Customer Name</label>
                            <input type="text" id="customerName" value="Rohan Deshmukh" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Date of Birth</label>
                                <input type="date" id="dob" value="1995-05-15" required>
                            </div>
                            <div class="form-group">
                                <label>Destination</label>
                                <select id="destination">
                                    <option value="INTERNATIONAL" selected>INTERNATIONAL (1.80x)</option>
                                    <option value="DOMESTIC">DOMESTIC (1.00x)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Trip Start</label>
                                <input type="date" id="tripStart" value="2025-06-01" required>
                            </div>
                            <div class="form-group">
                                <label>Trip End</label>
                                <input type="date" id="tripEnd" value="2025-06-05" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Plan Code</label>
                                <select id="planCode">
                                    <option value="PLUS" selected>PLUS (₹20/day)</option>
                                    <option value="BASIC">BASIC (₹12/day)</option>
                                    <option value="PREMIUM">PREMIUM (₹30/day)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Sum Insured (Coverage)</label>
                                <select id="sumInsured">
                                    <option value="300000" selected>₹3,00,000 (1.25x)</option>
                                    <option value="100000">₹1,00,000 (1.00x)</option>
                                    <option value="500000">₹5,00,000 (1.50x)</option>
                                </select>
                            </div>
                        </div>

                        <div style="background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.2); padding: 10px; border-radius: 8px; font-size: 12px; margin-bottom: 14px;">
                            <strong>Expected Formula:</strong> 20 × 5 days × 1.00 × 1.80 × 1.25 = <strong>₹225.00</strong>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;">Create Policy Request (POST /api/policy-requests)</button>
                    </form>
                </div>

                <!-- Simulation & Actions -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Deterministic Scenario Dispatcher</div>
                    </div>
                    <div class="form-group">
                        <label>Target Policy ID</label>
                        <input type="text" id="targetPolicyId" class="mono" placeholder="Select or enter Policy UUID">
                    </div>

                    <div class="form-group">
                        <label>Mock Scenario</label>
                        <select id="mockScenario">
                            <option value="SUCCESS">A. SUCCESS (Issues immediately)</option>
                            <option value="TIMEOUT_AFTER_ISSUE">C. TIMEOUT_AFTER_ISSUE (Simulates Timeout & Ambiguity)</option>
                            <option value="TEMPORARY_FAILURE">B. TEMPORARY_FAILURE (Retriable 503 error)</option>
                            <option value="DELAYED_CALLBACK">D. DELAYED_CALLBACK (Pending async confirmation)</option>
                        </select>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <button class="btn btn-primary" onclick="submitIssuance()">Submit Issuance (POST .../submit)</button>
                        <button class="btn btn-warning" onclick="reconcilePolicy()">Reconcile Timeout (POST .../reconcile)</button>
                        <button class="btn btn-secondary" onclick="simulateCallback()">Deliver Provider Callback Webhook</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Audit Modal -->
    <div id="auditModal" class="modal">
        <div class="modal-content">
            <div class="card-header">
                <div class="card-title">Policy Audit & State History</div>
                <button class="btn btn-secondary btn-sm" onclick="closeModal()">✕</button>
            </div>
            <div id="modalBody" style="font-size: 13px;"></div>
        </div>
    </div>

    <script>
        function log(title, data) {
            const out = document.getElementById('consoleOutput');
            const time = new Date().toLocaleTimeString();
            const text = `[${time}] ${title}\n` + (typeof data === 'string' ? data : JSON.stringify(data, null, 2)) + '\n\n';
            out.textContent = text + out.textContent;
        }

        function clearLog() {
            document.getElementById('consoleOutput').textContent = '// Log cleared.';
        }

        async function loadPolicies() {
            const status = document.getElementById('filterStatus').value;
            const url = '/api/policy-requests' + (status ? `?status=${status}` : '');
            try {
                const res = await fetch(url);
                const json = await res.json();
                const tbody = document.getElementById('policiesTableBody');
                tbody.innerHTML = '';

                if (!json.data || json.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-muted);">No policies found.</td></tr>';
                    return;
                }

                json.data.forEach(p => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>
                            <strong style="color:#fff;">${p.client_request_id}</strong><br>
                            <span class="mono" style="color:var(--text-muted); font-size:11px;">${p.agent_id}</span>
                        </td>
                        <td>
                            ${p.customer_name}<br>
                            <span style="font-size:11px; color:var(--text-muted);">${p.plan_code} • ${p.destination}</span>
                        </td>
                        <td><strong>₹${p.premium_amount}</strong></td>
                        <td><span class="badge badge-${p.status}">${p.status}</span></td>
                        <td><span class="mono">${p.policy_number || '—'}</span></td>
                        <td>
                            <button class="btn btn-secondary btn-sm" onclick="selectPolicy('${p.id}')">Select</button>
                            <button class="btn btn-secondary btn-sm" onclick="viewAudit('${p.id}')">Audit</button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } catch (err) {
                log('Error loading policies', err.message);
            }
        }

        function selectPolicy(id) {
            document.getElementById('targetPolicyId').value = id;
            log('Policy Selected', 'Target policy set to ID: ' + id);
        }

        async function submitIntake(e) {
            e.preventDefault();
            const payload = {
                client_request_id: document.getElementById('clientRequestId').value,
                agent_id: document.getElementById('agentId').value,
                customer_name: document.getElementById('customerName').value,
                date_of_birth: document.getElementById('dob').value,
                destination: document.getElementById('destination').value,
                trip_start: document.getElementById('tripStart').value,
                trip_end: document.getElementById('tripEnd').value,
                plan_code: document.getElementById('planCode').value,
                sum_insured: parseInt(document.getElementById('sumInsured').value, 10),
            };

            try {
                const res = await fetch('/api/policy-requests', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const json = await res.json();
                log(`Intake Response (HTTP ${res.status})`, json);
                if (json.data && json.data.id) {
                    selectPolicy(json.data.id);
                }
                loadPolicies();
            } catch (err) {
                log('Intake Error', err.message);
            }
        }

        async function submitIssuance() {
            const id = document.getElementById('targetPolicyId').value.trim();
            if (!id) return alert('Please enter or select a Policy ID first.');
            const scenario = document.getElementById('mockScenario').value;

            try {
                const res = await fetch(`/api/policy-requests/${id}/submit`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ scenario }),
                });
                const json = await res.json();
                log(`Submit Issuance [${scenario}] (HTTP ${res.status})`, json);
                loadPolicies();
            } catch (err) {
                log('Submit Error', err.message);
            }
        }

        async function reconcilePolicy() {
            const id = document.getElementById('targetPolicyId').value.trim();
            if (!id) return alert('Please enter or select a Policy ID first.');

            try {
                const res = await fetch(`/api/policy-requests/${id}/reconcile`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                });
                const json = await res.json();
                log(`Reconciliation Response (HTTP ${res.status})`, json);
                loadPolicies();
            } catch (err) {
                log('Reconciliation Error', err.message);
            }
        }

        async function simulateCallback() {
            const id = document.getElementById('targetPolicyId').value.trim();
            if (!id) return alert('Please enter or select a Policy ID first.');

            const eventId = 'EVT-' + Math.floor(Math.random() * 899999 + 100000);
            const payload = {
                event_id: eventId,
                event_type: 'POLICY_ISSUED',
                provider_reference: 'INS-ASYNC-WEBHOOK',
                policy_number: 'POL-WEBHOOK-' + Math.floor(Math.random() * 8999 + 1000),
                policy_request_id: id,
            };

            try {
                const res = await fetch('/api/mock-insurer/callback', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const json = await res.json();
                log(`Callback Delivery (HTTP ${res.status})`, json);
                loadPolicies();
            } catch (err) {
                log('Callback Error', err.message);
            }
        }

        async function viewAudit(id) {
            try {
                const res = await fetch(`/api/policy-requests/${id}`);
                const json = await res.json();
                if (!json.data) return;

                const p = json.data;
                let html = `
                    <div style="margin-bottom: 16px;">
                        <strong>Policy ID:</strong> <span class="mono">${p.id}</span><br>
                        <strong>Client ID:</strong> ${p.client_request_id} | <strong>Agent:</strong> ${p.agent_id}<br>
                        <strong>Status:</strong> <span class="badge badge-${p.status}">${p.status}</span><br>
                        <strong>Policy Number:</strong> <span class="mono">${p.policy_number || 'None'}</span><br>
                        <strong>Provider Ref:</strong> <span class="mono">${p.provider_reference || 'None'}</span>
                    </div>
                    <h4 style="margin: 12px 0 8px;">State Transition History:</h4>
                    <div style="border: 1px solid var(--border); border-radius: 8px; overflow: hidden; margin-bottom: 16px;">
                        <table style="font-size: 11px;">
                            <thead>
                                <tr>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Trigger</th>
                                    <th>Reason</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                (p.state_history || []).forEach(h => {
                    html += `
                        <tr>
                            <td>${h.from_state || '—'}</td>
                            <td><strong style="color:#60a5fa;">${h.to_state}</strong></td>
                            <td>${h.trigger}</td>
                            <td>${h.reason || '—'}</td>
                            <td>${new Date(h.created_at).toLocaleTimeString()}</td>
                        </tr>
                    `;
                });

                html += `
                            </tbody>
                        </table>
                    </div>
                    <h4 style="margin: 12px 0 8px;">Issuance Attempts:</h4>
                    <div style="border: 1px solid var(--border); border-radius: 8px; overflow: hidden;">
                        <table style="font-size: 11px;">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Scenario</th>
                                    <th>Status</th>
                                    <th>Error / Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                (p.issuance_attempts || []).forEach(a => {
                    html += `
                        <tr>
                            <td>${a.attempt_number}</td>
                            <td>${a.scenario || 'DEFAULT'}</td>
                            <td>${a.status}</td>
                            <td style="color:${a.error_message ? '#f87171' : 'inherit'};">${a.error_message || 'OK'}</td>
                        </tr>
                    `;
                });

                html += `
                            </tbody>
                        </table>
                    </div>
                `;

                document.getElementById('modalBody').innerHTML = html;
                document.getElementById('auditModal').classList.add('active');
            } catch (err) {
                log('Audit Error', err.message);
            }
        }

        function closeModal() {
            document.getElementById('auditModal').classList.remove('active');
        }

        // Initialize on load
        loadPolicies();
    </script>
</body>
</html>
