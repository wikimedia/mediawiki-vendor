# Stripe audit import for SmashPig

This directory adds Stripe maintenance and audit parsing support for SmashPig.

It supports:

- payout-scoped settlement files from the Stripe Reports API (`settlement-report`)
- payout-scoped settlement files built directly from the Stripe API (`settlement-api`)
- payments activity interval reports (`payments`)
- fees interval reports (`fees`)

The public audit entrypoint is `StripeAudit`, which chooses an internal parser based on the generated filename first and CSV contents second.

## Stripe documentation

- Reports overview: https://docs.stripe.com/reports
- Reports API overview: https://docs.stripe.com/reports/api
- Report types index: https://docs.stripe.com/reports/report-types
- Payout reconciliation report types: https://docs.stripe.com/reports/report-types/payout-reconciliation
- Balance change from activity report types: https://docs.stripe.com/reports/report-types/balance-change-from-activity
- All fees report types: https://docs.stripe.com/reports/report-types/all-fees
- Payouts API: https://docs.stripe.com/api/payouts
- Balance transactions API: https://docs.stripe.com/api/balance_transactions
- Metadata: https://docs.stripe.com/metadata
- Authentication: https://docs.stripe.com/api/authentication

## Config paths

The maintenance script uses the same `loadYamlConfig()` pattern as other SmashPig maintenance scripts.

You can either pass an explicit config path:

```bash
php vendor/wikimedia/smash-pig/PaymentProviders/Stripe/Maintenance/GetReport.php \
  --config localsettings/SmashPig/local-sandbox/stripe/main.yaml
```

or use `--config-name`, which looks for:

- `/etc/fundraising/<name>.yaml`
- `$HOME/.fundraising/<name>.yaml`

If the YAML file is missing or unreadable, the script logs a warning and continues with CLI, env, and built-in defaults.

## Sample YAML

A sample file is included as `config.example.yaml`. A typical config looks like:

```yaml
incoming_path: /srv/civi-sites/wmf/private/wmf_audit/stripe/incoming

accounts:
  WMF_ONLINE:
    secret: sk_live_...

  WMF_GRAVY:
    secret: sk_live_...

  WMF_EVERY_ORG:
    secret: sk_live_...

stripe:
  gateway_account: WMF_ONLINE
  report_type: settlement-report
  timezone: UTC
  list_payouts: true
  add_payout_row: true
  write_empty_files: false
  poll_interval: 5
  poll_timeout: 300
```

Secret keys can be looked up from YAML under `accounts.<gateway_account>.secret`.

## Defaults and option resolution

Resolution order is:

1. explicit CLI option
2. YAML config
3. built-in default

Built-in defaults:

- `report-type`: `settlement-report`
- `timezone`: `UTC`
- `status`: `paid`
- `poll-interval`: `5`
- `poll-timeout`: `300`
- `add-payout-row`: `true`
- `write-empty-files`: `false`
- `start-date`: yesterday
- `end-date`: yesterday
- list payouts by default when `--payout-id` is not supplied

There is intentionally no built-in default for `path`. Set it via `--path` or YAML (`incoming_path`, `stripe.incoming_path`, or `stripe.path`).

## Gateway accounts and API keys

The maintenance script supports multiple Stripe accounts via `--gateway-account`.

The selected gateway account determines:

- which API key is read
- the `gateway_account` column written into generated CSVs
- the `gateway_account` field written by the parser
- the generated filename

API key lookup order is:

1. `--api-key`
2. YAML `accounts.<gateway_account>.secret`
3. `STRIPE_SECRET_KEY_<ACCOUNT>`
4. YAML `stripe.api_key`
5. `STRIPE_SECRET_KEY`

Environment lookup uses the `gateway_account` value uppercased with non-alphanumeric characters replaced by underscores.

Examples:

- `WMF_ONLINE` -> `STRIPE_SECRET_KEY_WMF_ONLINE`
- `WMF_GRAVY` -> `STRIPE_SECRET_KEY_WMF_GRAVY`
- `WMF_EVERY_ORG` -> `STRIPE_SECRET_KEY_WMF_EVERY_ORG`

When using YAML secrets, prefer account names that already match the env-style form, for example `WMF_ONLINE`, `WMF_GRAVY`, and `WMF_EVERY_ORG`.

For local development in the CiviCRM container:

```bash
export PHP_IDE_CONFIG="serverName=civi"

export STRIPE_SECRET_KEY_WMF_ONLINE="sk_live_..."
export STRIPE_SECRET_KEY_WMF_GRAVY="sk_live_..."
export STRIPE_SECRET_KEY_WMF_EVERY_ORG="sk_live_..."
```

## Important CLI note

Run the script with option values separated by spaces.
Do not use `--option=value` for this script.

Base command:

```bash
php vendor/wikimedia/smash-pig/PaymentProviders/Stripe/Maintenance/GetReport.php --help
```

## Daily default behavior

If you do not provide dates, the script defaults to yesterday.

If you do not provide `--payout-id`, the script behaves as if list-payouts were enabled.

That means a daily run can be as simple as:

```bash
php vendor/wikimedia/smash-pig/PaymentProviders/Stripe/Maintenance/GetReport.php \
  --gateway-account WMF_ONLINE \
  --path ./private/wmf_audit/stripe/incoming
```

## Commands

### Settlement files

WMF_ONLINE:

```bash
php vendor/wikimedia/smash-pig/PaymentProviders/Stripe/Maintenance/GetReport.php \
  --gateway-account WMF_ONLINE \
  --list-payouts \
  --start-date 2026-02-01 \
  --end-date 2026-02-28 \
  --report-type settlement-report \
  --path ./private/wmf_audit/stripe/incoming
```

WMF_GRAVY:

```bash
php vendor/wikimedia/smash-pig/PaymentProviders/Stripe/Maintenance/GetReport.php \
  --gateway-account WMF_GRAVY \
  --list-payouts \
  --start-date 2026-02-01 \
  --end-date 2026-02-28 \
  --report-type settlement-report \
  --path ./private/wmf_audit/stripe/incoming
```

WMF_EVERY_ORG:

```bash
php vendor/wikimedia/smash-pig/PaymentProviders/Stripe/Maintenance/GetReport.php \
  --gateway-account WMF_EVERY_ORG \
  --list-payouts \
  --start-date 2026-02-01 \
  --end-date 2026-02-28 \
  --report-type settlement-report \
  --path ./private/wmf_audit/stripe/incoming
```

### Payments files

WMF_ONLINE:

```bash
php vendor/wikimedia/smash-pig/PaymentProviders/Stripe/Maintenance/GetReport.php \
  --gateway-account WMF_ONLINE \
  --start-date 2026-02-01 \
  --end-date 2026-02-28 \
  --report-type payments \
  --path ./private/wmf_audit/stripe/incoming
```

WMF_GRAVY:

```bash
php vendor/wikimedia/smash-pig/PaymentProviders/Stripe/Maintenance/GetReport.php \
  --gateway-account WMF_GRAVY \
  --start-date 2026-02-01 \
  --end-date 2026-02-28 \
  --report-type payments \
  --path ./private/wmf_audit/stripe/incoming
```

WMF_EVERY_ORG:

```bash
php vendor/wikimedia/smash-pig/PaymentProviders/Stripe/Maintenance/GetReport.php \
  --gateway-account WMF_EVERY_ORG \
  --start-date 2026-02-01 \
  --end-date 2026-02-28 \
  --report-type payments \
  --path ./private/wmf_audit/stripe/incoming
```

## Empty report behavior

By default the script logs and does not write a file when no data rows are returned.

Use `--write-empty-files` to force creation of a header-only CSV.

Example:

```bash
php vendor/wikimedia/smash-pig/PaymentProviders/Stripe/Maintenance/GetReport.php \
  --gateway-account WMF_ONLINE \
  --start-date 2026-02-01 \
  --end-date 2026-02-28 \
  --report-type payments \
  --write-empty-files \
  --path ./private/wmf_audit/stripe/incoming
```

## Report types

### `settlement-report`

Uses Stripe's payout reconciliation report for a single payout.

- Docs: https://docs.stripe.com/reports/report-types/payout-reconciliation
- Report type used here: `payout_reconciliation.by_id.itemized.4`

### `settlement-api`

Uses the Stripe Balance Transactions API instead of the Reports API.

- Docs: https://docs.stripe.com/api/balance_transactions
- Related payout docs: https://docs.stripe.com/api/payouts

### `payments`

Uses Stripe's balance change from activity report.

- Docs: https://docs.stripe.com/reports/report-types/balance-change-from-activity
- Report type used here: `balance_change_from_activity.itemized.7`

### `fees`

Uses Stripe's all fees report.

- Docs: https://docs.stripe.com/reports/report-types/all-fees
- Report type used here: `all_fees.balance_transaction_created.itemized.2`

## Parser behavior

Files are parsed by `StripeAudit`.

Parser detection order:

- Primary: filename prefix
- Secondary: CSV header inspection

## Shared field mappings

Both internal parsers normalize to the same finite audit schema already used by the other processors.

Key mappings:

- `payment_metadata[external_identifier]` -> `order_id`
- `payment_intent_id` -> `backend_processor_txn_id`
- `gateway_account` -> `gateway_account`
- `audit_file_gateway` -> `stripe`
- `backend_processor` -> `stripe`
- settlement payout id -> `settled_batch_reference`
- `fee` -> `settled_fee_amount`
- `net` -> `settled_net_amount`
- `currency` -> `settled_currency`
