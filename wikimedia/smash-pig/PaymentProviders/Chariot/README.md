# Chariot SmashPig prototype

This package now focuses on `GET /v1/donations`, since that is currently the most useful Chariot endpoint for batch-like export data.

## What `GetReport` writes for `--mode donations`

Given a Chariot `deposit_id`, the maintenance script writes:

- raw JSON response
- UI-style CSV with column names matching the Chariot export as closely as possible
- normalized audit CSV using SmashPig-style field names
- unknown-fields JSON so new/unhandled fields are easy to spot

## Audit CSV naming

The audit CSV uses field names that align with existing SmashPig audit conventions where possible, for example:

- `gateway`
- `audit_file_gateway`
- `type`
- `date`
- `order_id`
- `gateway_txn_id`
- `backend_processor_txn_id`
- `currency`
- `gross`
- `original_total_amount`
- `settled_total_amount`
- `settled_fee_amount`
- `settled_net_amount`
- `settlement_batch_reference`

When Chariot exposes information that does not map cleanly to an existing common audit field, the script uses explicit names such as:

- `match_amount`
- `platform_fee_amount`
- `platform_name`
- `platform_grant_id`
- `daf_organization`
- `daf_program`

## Example command

```bash
export CHARIOT_API_KEY="sk_live_xxxx"
mkdir -p ./private/wmf_audit/chariot/incoming

env PHP_IDE_CONFIG="serverName=civi" \
php vendor/wikimedia/smash-pig/PaymentProviders/Chariot/Maintenance/GetReport.php \
  -- \
  --mode donations \
  --deposit-id deposit_01kk37x3jqr1511e31axfwepdh \
  --page-limit 100 \
  --max-pages 5 \
  --path ./private/wmf_audit/chariot/incoming
```

## Files produced

For a donations run, expect files like:

- `donations-YYYY-mm-dd-HHMMSS-deposit-<id>.json`
- `donations-YYYY-mm-dd-HHMMSS-deposit-<id>.csv`
- `donations-audit-YYYY-mm-dd-HHMMSS-deposit-<id>.csv`
- `donations-unknowns-YYYY-mm-dd-HHMMSS-deposit-<id>.json`

## Notes

- `settlement_batch_reference` is currently populated from the CLI `--deposit-id` when the donation payload does not contain a deposit id directly.
- `settled_date` currently uses `deposit_settled_at` when present, otherwise falls back to `created_at`.
- Unknown or not-yet-mapped fields are left blank in the CSVs and surfaced in the unknowns JSON report.

## Donations mode options

The donations endpoint uses Chariot's field names directly:

- `--deposit-id` (optional)
- `--payment-source-id` (optional)
- `--limit` (optional)
- `--page-token` (optional)
- `--created-after` (optional, maps to `created_at.after`)
- `--created-before` (optional, maps to `created_at.before`)

Example:

```bash
php vendor/wikimedia/smash-pig/PaymentProviders/Chariot/Maintenance/GetReport.php   --mode donations   --path ./private/wmf_audit/chariot/incoming   --created-after 2026-02-01T00:00:00Z   --created-before 2026-03-01T00:00:00Z   --limit 100
```

## Unit test

A lightweight unit test is included at:

```bash
php vendor/wikimedia/smash-pig/PaymentProviders/Chariot/Tests/ApiTest.php
```


## PHPUnit-style audit parser test

A fixture-based parser test is included at:

```bash
phpunit vendor/wikimedia/smash-pig/PaymentProviders/Chariot/Tests/AuditTest.php
```
