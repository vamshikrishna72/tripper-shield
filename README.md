# PolicyFlow - TrippersShield Insurance Issuance Platform

> **Production-grade Travel Insurance Intake, Idempotent Issuance Orchestration, and Automated Timeout Reconciliation System.**

---

## 1. Project Overview & Business Problem

In high-volume travel insurance distribution, agent networks and digital travel platforms submit thousands of policy requests simultaneously. In distributed insurtech architectures, integration with external insurance underwriters faces critical real-world failure modes:
1. **Duplicate Intakes & Race Conditions**: Agents repeatedly click submit or re-transmit requests during network jitters, risking duplicate policy creation and erroneous billing.
2. **Underwriter Submission Timeouts ("Timeout-After-Issue")**: An insurer gateway may successfully issue a policy and generate a policy number on its side, but drop the TCP connection or time out before the caller receives the confirmation. Blindly resubmitting causes duplicate policy issuance and double-charging.
3. **Asynchronous & Duplicate Webhooks**: Insurer callback webhooks arrive out-of-order, delayed, or repeatedly, risking state machine corruption if not processed idempotently.
4. **Financial Floating-Point Inaccuracies**: Naive floating-point arithmetic causes rounding errors in insurance premiums and ledger mismatches.

**PolicyFlow** solves these challenges using:
- **Intake Idempotency**: Database-level composite uniqueness on `(agent_id, client_request_id)` returning HTTP 201 for new requests and HTTP 200 for repeated requests.
- **Decimal-Safe Financial Calculation Engine**: High-precision `bcmath` computation with immutable snapshot persistence.
- **Explicit Finite State Machine**: Strict state validation, terminal state immutability, and complete audit history.
- **Stable Provider Idempotency Keys**: Cryptographically derived from internal request IDs.
- **Automated Timeout Reconciliation**: Safely queries the insurer's records before ever attempting a retry, ensuring exactly one policy number is generated per logical request.
- **Deterministic Mock Insurer**: Configurable test scenarios (`SUCCESS`, `TEMPORARY_FAILURE`, `TIMEOUT_AFTER_ISSUE`, `DELAYED_CALLBACK`, `DUPLICATE_CALLBACK`) with isolated provider-side state.

---

## 2. Technology Stack

- **Language**: PHP 8.2+ (tested and verified on PHP 8.3.36 CLI)
- **Framework**: Laravel 11 (v11.57.0)
- **Database**:
  - **SQLite**: Out of the box (zero external installation friction for reviewers)
  - **MySQL 8.0+ / PostgreSQL 16**: Supported via standard `.env` configuration and bundled `docker-compose.yml`
- **Testing**: PHPUnit 10.5 with database transactions and refresh database traits
- **Precision Arithmetic**: PHP `bcmath` extension
- **Date/Time Handling**: Carbon 3.15
- **Operational Console UI**: HTML5, CSS3, Vanilla JavaScript, Blade

---

## 3. Architecture & Request Flow

```
                      +---------------------------------------+
                      | Agent / Client Application (Frontend) |
                      +---------------------------------------+
                                          |
                   [POST /api/policy-requests] (agent_id, client_request_id)
                                          v
                      +---------------------------------------+
                      |         PolicyIntakeService           |
                      |  - Unique(agent_id, client_request_id)|
                      |  - PremiumCalculator (BCMath)         |
                      |  - Generates UUID & Idempotency Key   |
                      +---------------------------------------+
                                          |
                      Status: RECEIVED    | (Returns 201 New / 200 Idempotent)
                                          v
                   [POST /api/policy-requests/{id}/submit]
                                          |
                                          v
                      +---------------------------------------+
                      |        PolicyIssuanceService          |
                      |  - StateMachine: VALIDATED ->         |
                      |    SUBMITTED                          |
                      |  - Non-blocking provider dispatch     |
                      +---------------------------------------+
                                    /     |     \
                                   /      |      \
                    [SUCCESS]     /   [TIMEOUT]   \    [PENDING]
                                 /        |        \
                                v         v         v
                         State: ISSUED  State:    State: PENDING_
                                        NEEDS_    CONFIRMATION
                                        RECON-          |
                                        CILIATION       | Inbound Webhook
                                          |             | [/api/mock-insurer/callback]
                                          |             v
                                 [POST /api/.../reconcile]
                                          |
                                          v
                            Discovers Provider Policy
                                          |
                                          v
                                    State: ISSUED
                                 (Single Policy Number)
```

---

## 4. Database Design

The schema is fully normalized, indexed, and enforces database-level constraints:

### 1. `policy_requests`
Primary record for each logical policy intake.
- `id` (UUID, Primary Key)
- `agent_id` (VARCHAR, Indexed)
- `client_request_id` (VARCHAR)
- `customer_name` (VARCHAR)
- `date_of_birth` (DATE)
- `destination` (VARCHAR: DOMESTIC, INTERNATIONAL)
- `trip_start` (DATE)
- `trip_end` (DATE)
- `plan_code` (VARCHAR: BASIC, PLUS, PREMIUM)
- `sum_insured` (BIGINT: 100000, 300000, 500000)
- `travel_days` (INTEGER)
- `customer_age` (INTEGER)
- `premium_amount` (DECIMAL 10,2)
- `currency` (VARCHAR 3, Default 'INR')
- `calculation_breakdown` (JSON)
- `status` (VARCHAR, Indexed)
- `provider_reference` (VARCHAR, Indexed, Nullable)
- `policy_number` (VARCHAR, Unique, Indexed, Nullable)
- `idempotency_key` (VARCHAR, Unique, Indexed)
- `attempt_count` (INTEGER, Default 0)
- `last_attempted_at` (TIMESTAMP, Nullable)
- `issued_at` (TIMESTAMP, Nullable)
- `timestamps`
- **Unique Constraint**: `UNIQUE(agent_id, client_request_id)`

### 2. `policy_state_histories`
Immutable audit log tracking every state transition.
- `id` (BIGINT Auto-Increment)
- `policy_request_id` (UUID Foreign Key -> `policy_requests.id` ON DELETE CASCADE)
- `from_state` (VARCHAR, Nullable)
- `to_state` (VARCHAR)
- `trigger` (VARCHAR)
- `reason` (TEXT, Nullable)
- `metadata` (JSON, Nullable)
- `timestamps`
- **Index**: `(policy_request_id, created_at)`

### 3. `issuance_attempts`
Records every outbound submission attempt to the insurer.
- `id` (BIGINT Auto-Increment)
- `policy_request_id` (UUID Foreign Key -> `policy_requests.id` ON DELETE CASCADE)
- `attempt_number` (INTEGER)
- `scenario` (VARCHAR, Nullable)
- `status` (VARCHAR: SUCCESS, FAILED, TIMEOUT, PENDING)
- `request_payload` (JSON, Sanitized)
- `response_payload` (JSON, Sanitized)
- `error_message` (TEXT, Nullable)
- `timestamps`
- **Index**: `(policy_request_id, attempt_number)`

### 4. `provider_events`
Stores and deduplicates incoming provider callback webhooks.
- `id` (BIGINT Auto-Increment)
- `event_id` (VARCHAR, Unique)
- `policy_request_id` (UUID Foreign Key, Nullable)
- `provider_reference` (VARCHAR, Nullable, Indexed)
- `event_type` (VARCHAR)
- `payload` (JSON)
- `processed` (BOOLEAN, Default false)
- `processed_at` (TIMESTAMP, Nullable)
- `timestamps`

### 5. `mock_insurer_policies`
Simulated provider-side persistent storage, independent of application state.
- `id` (BIGINT Auto-Increment)
- `idempotency_key` (VARCHAR, Unique, Indexed)
- `provider_reference` (VARCHAR, Unique)
- `policy_number` (VARCHAR, Unique)
- `client_request_id` (VARCHAR)
- `customer_name` (VARCHAR)
- `premium_amount` (DECIMAL 10,2)
- `status` (VARCHAR: ISSUED, PENDING, CANCELLED)
- `timestamps`

---

## 5. Premium Calculation Rules

The `PremiumCalculator` service implements decimal-safe arithmetic using PHP's `bcmath` extension (`bcmul` with 6-decimal internal precision) followed by standard financial half-up rounding to two decimal places:

$$\text{Premium} = \text{base\_daily\_rate} \times \text{travel\_days} \times \text{age\_multiplier} \times \text{destination\_multiplier} \times \text{coverage\_multiplier}$$

### Multiplier Tables

| Dimension | Option | Multiplier / Base Rate |
| :--- | :--- | :--- |
| **Plan Base Rate** | `BASIC` | ₹12.00 / day |
| | `PLUS` | ₹20.00 / day |
| | `PREMIUM` | ₹30.00 / day |
| **Age on Trip Start** | 0 – 17 | 1.10 |
| | 18 – 60 | 1.00 |
| | 61 – 70 | 1.50 |
| | 71+ | 2.00 |
| **Destination** | `DOMESTIC` | 1.00 |
| | `INTERNATIONAL` | 1.80 |
| **Coverage Amount** | ₹1,00,000 | 1.00 |
| | ₹3,00,000 | 1.25 |
| | ₹5,00,000 | 1.50 |

### Travel Days Rule
$$\text{travel\_days} = \max(1, (\text{trip\_end} - \text{trip\_start} + 1)) \quad \text{(inclusive)}$$

### Verified Example
- Plan: `PLUS` (Base: ₹20.00)
- Dates: 5 travel days (e.g., 2025-06-01 to 2025-06-05)
- Age: 30 years old on trip start (Multiplier: 1.00)
- Destination: `INTERNATIONAL` (Multiplier: 1.80)
- Coverage: ₹3,00,000 (Multiplier: 1.25)

$$\text{Premium} = 20.00 \times 5 \times 1.00 \times 1.80 \times 1.25 = 100 \times 1.80 \times 1.25 = 180 \times 1.25 = \mathbf{₹225.00}$$

The calculated premium is saved as an immutable snapshot. Subsequent retries reuse the snapshot rather than recalculating it.

---

## 6. State Machine & Transition Rules

Managed explicitly by `App\Services\PolicyStateMachine`:

```
               [RECEIVED]
               /        \
              v          v
        [VALIDATED]    [FAILED]
            |
            v
        [SUBMITTED] ----------------------+
        /    |    \                       |
       /     |     \                      |
      v      v      v                     v
  [ISSUED] [PENDING] [NEEDS_RECON]     [FAILED]
              |            |
              v            v
          [ISSUED]     [ISSUED]
```

### State Transition Matrix

| From State | Allowed Target States | Trigger / Description |
| :--- | :--- | :--- |
| `RECEIVED` | `VALIDATED`, `FAILED` | Validated before dispatch |
| `VALIDATED` | `SUBMITTED`, `FAILED` | Outbound request dispatched |
| `SUBMITTED` | `ISSUED`, `PENDING_CONFIRMATION`, `NEEDS_RECONCILIATION`, `FAILED` | Underwriter response or timeout |
| `PENDING_CONFIRMATION` | `ISSUED`, `FAILED`, `NEEDS_RECONCILIATION` | Webhook delivery or status query |
| `NEEDS_RECONCILIATION` | `ISSUED`, `FAILED`, `SUBMITTED` | Underwriter status lookup |
| `FAILED` | `VALIDATED`, `SUBMITTED` | Safe retry with original premium |
| `ISSUED` | *(None - Terminal)* | **Strictly Terminal: Never regresses** |

---

## 7. Duplicate Prevention & Concurrency Strategy

1. **Intake Uniqueness**:
   - Database table `policy_requests` has a composite unique constraint: `UNIQUE(agent_id, client_request_id)`.
   - `PolicyIntakeService` performs a read check, and wraps inserts in a transaction. If a race condition occurs where two requests execute simultaneously, the database constraint raises a `QueryException` which is caught and gracefully resolves to the existing record.
   - HTTP 201 is returned on new creation; HTTP 200 is returned on duplicate repeat with `is_duplicate: true`.

2. **Transaction Boundaries**:
   - Database transactions are kept small and atomic.
   - **Critical rule**: Database transactions are **never** held open across external HTTP / insurer gateway calls. State is saved as `SUBMITTED` before the call; then updated based on the result.

3. **Provider Idempotency Keys**:
   - Each policy request computes a deterministic provider idempotency key:
     `idem_` + `SHA256("trippershield:policy:" . $id)`.
   - The insurer mock verifies this key to prevent double issuance.

---

## 8. Timeout Reconciliation Strategy

When an external insurer call encounters a network timeout or TCP reset (`MockInsurerTimeoutException`):
1. **Never Blindly Retry**: Immediately sending another issuance request risks generating two distinct insurance policies for one customer.
2. **Transition to `NEEDS_RECONCILIATION`**: The application flags the policy as ambiguous and logs the timeout attempt.
3. **Lookup Operation (`reconcile`)**:
   - Queries the insurer's records using the stable `idempotency_key`.
   - If the insurer reveals that the policy **was** indeed generated on their side (`status: ISSUED`), PolicyFlow extracts the provider's reference and policy number and marks the policy `ISSUED`.
   - Exactly **one** unique policy number is generated and associated with the request.
   - If the insurer has no record, the policy transitions to `FAILED` and can be retried safely.

---

## 9. API Endpoint Documentation

| Method | Endpoint | Description | Status Codes |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/policy-requests` | Create policy request or idempotently return existing | `201`, `200`, `422` |
| `GET` | `/api/policy-requests` | List policy requests (paginated, status & agent filter) | `200` |
| `GET` | `/api/policy-requests/{id}` | Get policy request details with full audit & attempt history | `200`, `404` |
| `POST` | `/api/policy-requests/{id}/submit` | Start or safely resume issuance | `200`, `404`, `422` |
| `POST` | `/api/policy-requests/{id}/reconcile` | Query insurer status and reconcile timeout/ambiguous state | `200`, `404`, `422` |
| `POST` | `/api/mock-insurer/callback` | Webhook endpoint for simulated insurer events | `200`, `422` |

---

## 10. Sample Requests & Responses

### 1. Intake Request (`POST /api/policy-requests`)
```bash
curl -X POST http://127.0.0.1:8000/api/policy-requests \
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
```
**Response (HTTP 201 Created)**:
```json
{
  "success": true,
  "message": "Policy request successfully created and premium snapshot calculated.",
  "is_duplicate": false,
  "data": {
    "id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
    "client_request_id": "CR-TRIPPER-2025-001",
    "agent_id": "AGENT-MUMBAI-01",
    "customer_name": "Rohan Deshmukh",
    "premium_amount": "225.00",
    "status": "RECEIVED"
  }
}
```

### 2. Submit Issuance (`POST /api/policy-requests/{id}/submit`)
```bash
curl -X POST http://127.0.0.1:8000/api/policy-requests/9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d/submit \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"scenario": "SUCCESS"}'
```
**Response (HTTP 200 OK)**:
```json
{
  "success": true,
  "message": "Issuance submitted. Current status: ISSUED",
  "data": {
    "id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
    "status": "ISSUED",
    "provider_reference": "INS-REF-HQ89L2M19V",
    "policy_number": "POL-20250601-A9K4P2"
  }
}
```

---

## 11. Local Installation & Setup

### Prerequisites
- PHP 8.2 or 8.3 with `bcmath`, `curl`, `fileinfo`, `mbstring`, `openssl`, `pdo_sqlite`, `sqlite3`, `zip` extensions.
- Composer 2.x

### Quickstart Steps
```bash
# 1. Clone repository
git clone https://github.com/your-username/policyflow.git
cd policyflow

# 2. Install PHP dependencies
composer install

# 3. Configure environment
cp .env.example .env
php artisan key:generate

# 4. Run database migrations and seed data
php artisan migrate --force
php artisan db:seed --force

# 5. Start the local server
php artisan serve
```

Access the application:
- **REST API Base**: `http://127.0.0.1:8000/api`
- **Operational UI Console**: `http://127.0.0.1:8000/` or `http://127.0.0.1:8000/dashboard`

---

## 12. Environment Configuration

The provided `.env.example` contains placeholders only. Default database configuration uses SQLite for zero external setup friction:
```env
DB_CONNECTION=sqlite
```
For MySQL or PostgreSQL, uncomment the relevant keys in `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=policyflow
DB_USERNAME=policyflow_user
DB_PASSWORD=your_secure_password
```

---

## 13. Migration & Seeding Commands

```bash
# Fresh database setup
php artisan migrate:fresh --force

# Populate with realistic seed data
php artisan db:seed --force
```

The seeder creates:
1. `RECEIVED` policy request (`CLIENT-REQ-1001` / `AGENT-ALPHA`).
2. `ISSUED` policy request (`CLIENT-REQ-1002` / `AGENT-BETA`) with full state history and issuance attempt.
3. `NEEDS_RECONCILIATION` policy request (`CLIENT-REQ-1003` / `AGENT-GAMMA`) ready for one-click timeout reconciliation testing.

---

## 14. Test Commands & Actual Test Results

Run the full automated test suite:
```bash
php artisan test
```

### Verified Test Output
```
   PASS  Tests\Unit\PolicyStateMachineTest
  ✓ valid transition lifecycle                                           1.18s
  ✓ invalid transition throws exception                                  0.04s
  ✓ issued state cannot regress                                          0.04s
  ✓ mark issued is idempotent and preserves policy number                0.05s

   PASS  Tests\Unit\PremiumCalculatorTest
  ✓ specification example produces exact 225                             0.01s
  ✓ all age multiplier boundaries                                        0.00s
  ✓ all plan base rates                                                  0.00s
  ✓ destination multipliers                                              0.00s
  ✓ coverage multipliers                                                 0.00s
  ✓ single day travel equals one day                                     0.00s
  ✓ throws exception when trip end before trip start                     0.00s
  ✓ throws exception when dob after trip start                           0.00s
  ✓ decimal safe rounding precision                                      0.00s

   PASS  Tests\Feature\PolicyIntakeApiTest
  ✓ successful policy request creation                                   0.20s
  ✓ validation fails when trip end before trip start                     0.04s
  ✓ validation fails for unsupported plan                                0.04s
  ✓ validation fails for unsupported destination                         0.04s
  ✓ validation fails for unsupported coverage amount                     0.04s
  ✓ duplicate intake returns existing request with 200                   0.10s
  ✓ database level uniqueness enforced                                   0.04s

   PASS  Tests\Feature\PolicyIssuanceApiTest
  ✓ successful issuance creates one policy number                        0.08s
  ✓ repeated submit on issued policy is idempotent                       0.05s
  ✓ temporary failure and safe retry preserves premium                   0.08s
  ✓ timeout after issue followed by reconciliation                       0.07s
  ✓ delayed callback and duplicate callback idempotency                  0.08s
  ✓ issued policy cannot be overwritten by stale callback                0.04s

   PASS  Tests\Feature\PolicyListingAndFilterTest
  ✓ pagination and status filtering                                      0.06s
  ✓ get policy request details with audit history                        0.05s
  ✓ get policy request returns 404 for unknown id                        0.04s

  Tests:    29 passed (129 assertions)
  Duration: 2.72s
```

---

## 15. Mock Insurer Scenario Configuration

The deterministic mock underwriter supports 5 test scenarios:

| Scenario | Behavior | Test Usage |
| :--- | :--- | :--- |
| `SUCCESS` | Inserts provider record and returns `200` with policy number. | Standard happy-path issuance. |
| `TEMPORARY_FAILURE` | Simulates gateway 503 error; preserves premium for retry. | Retriable failure resilience. |
| `TIMEOUT_AFTER_ISSUE` | Creates provider record, then throws socket timeout to caller. | Reliability Case 2: Timeout reconciliation. |
| `DELAYED_CALLBACK` | Returns `202 PENDING` and awaits asynchronous callback. | Asynchronous webhook flow. |
| `DUPLICATE_CALLBACK` | Delivers duplicate webhook payloads with same `event_id`. | Idempotent callback deduplication. |

**Security Note**: Scenario overrides via `scenario` body parameter or `X-Mock-Scenario` header are strictly guarded and allowed **only in `local` and `testing` environments**. In production environments, client overrides are ignored.

---

## 16. Known Limitations & Simplifying Assumptions

1. **Synchronous Direct Dispatch vs Queue Worker**:
   - In high-throughput enterprise production, outbound submission would typically be pushed to a Redis queue worker (`ProcessPolicySubmissionJob`). In this project, direct synchronous orchestration is implemented to keep setup simple, verifiable, and testable without requiring background daemon processes.
2. **Provider Key Encryption**:
   - Mock insurer policies use standard hashing for idempotency keys. In live production with sensitive medical disclosures, payload-level encryption (e.g. envelope encryption with KMS) would be applied.
3. **Database Dialect Defaults**:
   - The project uses SQLite by default for zero-setup local evaluation, but all migrations and data types (DECIMAL 10,2, UUID, JSON, Indexes) are 100% compliant with MySQL 8.0+ and PostgreSQL 16.

---

## 17. Instructions for Recording a 3–5 Minute Demonstration

When recording a demo (e.g. via Loom):
1. **Introduction (30s)**:
   - Introduce PolicyFlow, TrippersShield business domain, and high-level architecture.
2. **Premium Calculation & Intake (60s)**:
   - Demonstrate `POST /api/policy-requests` with the spec example (`PLUS`, 5 days, age 30, `INTERNATIONAL`, `300000`).
   - Show the resulting **₹225.00** premium snapshot.
   - Resend the identical payload to show intake idempotency returning HTTP 200 with `is_duplicate: true`.
3. **Issuance & Timeout Reconciliation (90s)**:
   - Submit a policy with scenario `TIMEOUT_AFTER_ISSUE`.
   - Show state moving to `NEEDS_RECONCILIATION`.
   - Call `POST /api/policy-requests/{id}/reconcile` and show policy discovering the provider policy and transitioning to `ISSUED` without double issuance.
4. **Audit History & Test Suite (60s)**:
   - Show `GET /api/policy-requests/{id}` displaying the complete `state_history` and `issuance_attempts`.
   - Run `php artisan test` in terminal to show all 29 tests passing.
