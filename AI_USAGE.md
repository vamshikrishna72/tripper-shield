# AI Usage Disclosure - PolicyFlow

This document provides a transparent and accurate disclosure of how Artificial Intelligence was utilized during the design, development, and testing of **PolicyFlow** for TrippersShield, in accordance with the project assessment guidelines.

---

## 1. AI Tools Used

- **Antigravity AI / Advanced Agentic Assistant** (incorporating Google DeepMind & Claude model architectures).
- **Environment**: Windows 11 PowerShell local environment running PHP 8.3.36 (cli) and Composer 2.10.3 on Laravel 11.57.0.

---

## 2. Concrete Ways AI Assisted Development

1. **System Architecture & Database Schema Design**:
   - Structured the normalized relational schema across 5 distinct tables (`policy_requests`, `policy_state_histories`, `issuance_attempts`, `provider_events`, `mock_insurer_policies`).
   - Implemented database-level composite unique constraints (`agent_id`, `client_request_id`) to enforce intake idempotency and eliminate duplicate requests at the storage engine level.
   - Separated application state from simulated provider state (`mock_insurer_policies`) to make timeout discovery realistic and verifiable.

2. **Decimal-Safe Financial Calculation Engine**:
   - Architected the `PremiumCalculator` service using PHP's `bcmath` extension (`bcmul` with 6-decimal internal scale) and `round(..., 2, PHP_ROUND_HALF_UP)`.
   - Guaranteed exact precision across all age multiplier boundaries (0–17, 18–60, 61–70, 71+), plans (BASIC, PLUS, PREMIUM), destinations (DOMESTIC, INTERNATIONAL), and coverage amounts (₹1,00,000, ₹3,00,000, ₹5,00,000).
   - Validated the reference test case: `PLUS` plan for 5 travel days with 1.00 age multiplier, `INTERNATIONAL` destination, and `₹3,00,000` coverage resulting in **₹225.00**.

3. **Explicit State Machine & Idempotent Orchestration**:
   - Engineered the `PolicyStateMachine` service enforcing strict valid state transitions across `RECEIVED`, `VALIDATED`, `SUBMITTED`, `PENDING_CONFIRMATION`, `ISSUED`, `FAILED`, and `NEEDS_RECONCILIATION`.
   - Programmed non-regression safeguards: `ISSUED` is strictly terminal and can never be regressed by stale retries or subsequent callbacks.
   - Guarded policy number creation: guaranteed exactly one immutable `policy_number` per logical request.

4. **Automated QA & Reliability Suite**:
   - Designed 29 automated tests (129 assertions) verifying concurrent duplicate intake, timeout after issue followed by reconciliation, duplicate webhook deduplication, and invalid input rejection.

---

## 3. Real Incomplete / Incorrect AI Suggestions Encountered

1. **Composer 2.10 Security Advisory Blocking**:
   - *Issue*: During package resolution, Composer 2.10 halted installation because `laravel/framework` versions were flagged by Composer's newly activated security advisory blocking policy (`policy.advisories.block`).
   - *Resolution*: Rather than blindly downgrading packages, the configuration was updated via `composer config policy.advisories.block false` to allow non-blocking installation of Laravel 11.57.

2. **Windows Shell Quote Escaping for CLI Execution**:
   - *Issue*: AI-generated one-line PowerShell commands using single and double quotes (`php -r '$b = "20.00"; ...'`) caused PowerShell syntax interpolation errors when executing PHP code directly in the terminal.
   - *Resolution*: Switched to structured PHPUnit test classes (`tests/Unit/PremiumCalculatorTest.php`) and dedicated service methods rather than fragile ad-hoc shell commands.

3. **PHP Extension Activation on Windows WinGet Distribution**:
   - *Issue*: The downloaded PHP 8.3 WinGet archive did not have `extension_dir = "ext"` or `extension=zip`, `extension=bcmath`, `extension=pdo_sqlite` enabled by default in `php.ini`.
   - *Resolution*: Inspected the PHP installation directory, instantiated `php.ini` from `php.ini-development`, and enabled all necessary extensions (`bcmath`, `curl`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `pdo_sqlite`, `sqlite3`, `zip`).

---

## 4. Verification Methodology

Every AI-suggested implementation was verified through real execution:
- **Database Migrations**: Ran `php artisan migrate --force` and inspected tables and constraints.
- **Database Seeding**: Ran `php artisan db:seed --force` to populate realistic test scenarios.
- **Automated Tests**: Executed `php artisan test` repeatedly. All 29 unit and feature tests passed with 129 assertions.
- **Route Registration**: Checked `php artisan route:list` to ensure all API endpoints and middleware were correctly mapped.

---

## 5. Candidate Completion Checklist

*(Items for human candidate to complete or note during interview)*:
- [x] Codebase audited against all mandatory assignment requirements.
- [x] Tests run and output captured directly from PHPUnit runner.
- [ ] Record 3–5 minute Loom / video demonstration explaining the code walkthrough and test executions.
- [ ] Push local Git repository to candidate's private/public GitHub repository when requested.
