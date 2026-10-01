# Engineering Audit Log

This file is the persistent audit trail for marketplace-analytics engineering audits.
Work is performed on the `development` branch unless explicitly stated otherwise.

## Status vocabulary

- **RESOLVED** — production implementation and relevant regression coverage are complete.
- **MIGRATED** — implementation is correct; dependent tests/consumers are being updated to the current contract.
- **OPEN** — known issue remains and requires further work.
- **BLOCKED** — cannot be validated until an external/environment condition is fixed.
- **SUPERSEDED** — an older behavior was intentionally replaced by a newer contract.

## Historical / current audit

| ID | Issue | Status | Evidence / decision | Commit(s) |
|---|---|---|---|---|
| P0-FULFILLMENT | Fulfillment quantity was conflated with ordered/returned quantity | RESOLVED | Fulfillment/cancellation remain separate nullable fields; HPP no longer derives confirmed fulfillment from `quantity - returned_quantity`. | `6e9d76d`, `bc8ee62` |
| P0-REFUND | Monetary refund was conflated with physical return | RESOLVED | Refund is represented independently and remains valid when `returned_quantity = 0`; source refund sign is preserved. | `8510caf`, `8124e3b` |
| P0-TEST-INFRA | PHPUnit used stale/configured DB environment and missed fulfillment columns | RESOLVED | Testing bootstrap/environment stabilized and reconciliation query exposes fulfillment fields. | `6afced7`, `c106a38` |
| P0-HPP-BASIS | Confirmed HPP used return-adjusted quantity | RESOLVED | Confirmed HPP requires an explicit supported quantity basis; unavailable basis remains unavailable. | `bac49c3`, `33b9a2d`, `49f8325` |
| P0-HPP-PROVENANCE | Automatic mapping could incorrectly become confirmed HPP | RESOLVED | Exact/normalized automatic mappings remain `mapping_unconfirmed`; manual mapping can become confirmed. | `49f8325`, `de6ead6` |
| P0-UNKNOWN | Missing financial/HPP inputs were converted to zero | RESOLVED | Unknown/unavailable values remain null; confirmed zero remains distinguishable. | `0ff3508`, `15e710f`, `6650b36` |
| P0-SOURCE-SIGN | Fee/tax source signs were normalized/inverted downstream | RESOLVED | Source monetary signs are preserved without ABS/double inversion. | `cd1bafa`, `aaeeb0b`, `c3761dd` |
| P0-CANONICAL | Multiple consumers used divergent financial formulas | RESOLVED | Canonical financial projection introduced and routed through Reconciliation, Orders, Customer, Dashboard, and Excel. | `ba59981`, `39f58b7`, `e70a447`, `5921e10`, `7754653`, `05ef412`, `4fccd0c`, `6004f9f`, `895299b`, `78067b0` |
| P0-CANONICAL-STATUS | Estimated fee/tax inputs were incorrectly treated as confirmed | RESOLVED | Estimated provenance produces provisional canonical status. | `e70a447`, `5921e10` |
| BUG-DASHBOARD-HPP | Dashboard accumulator did not initialize `hpp`, causing undefined-array-key failures | RESOLVED | Added explicit `hpp` accumulator initialization. | `1209820` |
| TEST-HPP-RETURN-BASIS | Legacy tests expect returned quantity to reduce confirmed HPP | MIGRATED | Core Master HPP expectations now use explicit ordered/allocation quantity; returned quantity is not used as an HPP deduction. | `b6aeeec` |
| TEST-MAPPING-CONFIRMATION | Legacy tests expect automatic exact/normalized mappings to be `ok` | MIGRATED | Current contract requires `mapping_unconfirmed` until confirmation/manual mapping. | Pending |
| TEST-UNAVAILABLE-ZERO | Legacy tests expect unavailable HPP/revenue/fee/tax to be zero | MIGRATED | Current contract preserves unavailable values as null. | Pending |
| TEST-CUSTOMER-CANONICAL | Customer summary/detail tests encode legacy financial formulas | MIGRATED | Expected values must be aligned with canonical ordered-quantity and availability semantics. | Pending |
| TEST-ORDER-CANONICAL | Order summary/export tests encode legacy return-adjusted subtotal and zero fallbacks | MIGRATED | Tests must assert canonical projection semantics. | Pending |
| TEST-PROFIT-MARGIN | Profit-margin tests encode zero-for-missing-HPP and return-adjusted HPP | MIGRATED | Unavailable HPP/revenue expectations were migrated to null; returned-quantity HPP expectation was updated to explicit allocation quantity. Remaining suite validation is pending. | `5811ff1` |
| TEST-DI-CONSTRUCTOR | A test directly instantiated `MarketplaceReconciliationService` with a user ID after constructor became DI-based | RESOLVED | Test now resolves the service from the container; production constructor remains dependency-injected. | `0921d9d` |
| ENV-TEST-DISK-PERMISSIONS | SecurityHardeningTest cannot access testing report disk due filesystem permission | BLOCKED | Environment/storage permission problem; not a financial semantics issue. | Pending |
| FULL-SUITE-BASELINE | Full suite after canonical migration | OPEN | Baseline: 238 passed, 36 failed, 1,798 assertions. Failures are being classified rather than blindly suppressed. | Baseline: pre-follow-up |

## Validation policy

1. Do not change production semantics solely to satisfy stale tests.
2. Every production bug gets its own focused commit when practical.
3. Contract migrations are reflected in regression tests.
4. Environment failures are tracked separately from application failures.
5. Before closing an issue, run the narrowest relevant test first, then the full suite.
6. Record the resulting test evidence and commit SHA in this log.
