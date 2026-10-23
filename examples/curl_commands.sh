#!/usr/bin/env bash

# ==============================================================================
# PolicyFlow - Sample Curl Commands for Local Testing
# ==============================================================================
BASE_URL="http://127.0.0.1:8000/api"

echo "=== 1. Create Policy Request (Intake) ==="
curl -X POST "${BASE_URL}/policy-requests" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "client_request_id": "CR-TRIPPER-2025-001",
    "agent_id": "AGENT-MUMBAI-01",
    "customer_name": "Rohan Deshmukh",
    "date_of_birth": "1995-05-15",
    "destination": "INTERNATIONAL",
    "trip_start": "2025-06-01",
    "trip_end": "2025-06-05",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }'
echo -e "\n"

echo "=== 2. Idempotent Repeated Intake (Returns 200 with existing record) ==="
curl -X POST "${BASE_URL}/policy-requests" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "client_request_id": "CR-TRIPPER-2025-001",
    "agent_id": "AGENT-MUMBAI-01",
    "customer_name": "Rohan Deshmukh",
    "date_of_birth": "1995-05-15",
    "destination": "INTERNATIONAL",
    "trip_start": "2025-06-01",
    "trip_end": "2025-06-05",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }'
echo -e "\n"

echo "=== 3. Submit Policy Issuance (SUCCESS Scenario) ==="
# Replace {POLICY_ID} with the ID returned from intake
POLICY_ID="YOUR_POLICY_UUID_HERE"
curl -X POST "${BASE_URL}/policy-requests/${POLICY_ID}/submit" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"scenario": "SUCCESS"}'
echo -e "\n"

echo "=== 4. Submit Policy Issuance (TIMEOUT_AFTER_ISSUE Scenario) ==="
curl -X POST "${BASE_URL}/policy-requests/${POLICY_ID}/submit" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"scenario": "TIMEOUT_AFTER_ISSUE"}'
echo -e "\n"

echo "=== 5. Reconcile Policy Request (Discovers existing provider policy) ==="
curl -X POST "${BASE_URL}/policy-requests/${POLICY_ID}/reconcile" \
  -H "Accept: application/json"
echo -e "\n"

echo "=== 6. Simulated Provider Callback (Webhook) ==="
curl -X POST "${BASE_URL}/mock-insurer/callback" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "event_id": "EVT-MANUAL-TEST-001",
    "event_type": "POLICY_ISSUED",
    "provider_reference": "INS-ASYNC-TEST",
    "policy_number": "POL-ASYNC-2025-01",
    "policy_request_id": "'"${POLICY_ID}"'"
  }'
echo -e "\n"

echo "=== 7. Get Policy Request Details with Audit History ==="
curl -X GET "${BASE_URL}/policy-requests/${POLICY_ID}" \
  -H "Accept: application/json"
echo -e "\n"

echo "=== 8. List Policies with Status Filtering & Pagination ==="
curl -X GET "${BASE_URL}/policy-requests?status=ISSUED&per_page=5" \
  -H "Accept: application/json"
echo -e "\n"
