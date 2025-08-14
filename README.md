# WC Anti-Fraud Pro Lite

Pre-checkout risk checks for WooCommerce with **structured logging**, **country-agnostic validation presets**, and a **modern tabbed settings UI**.

## Highlights

- Honeypots (static + rotating), min render time, device age (first-seen cookie)
- Velocity limits (IP/email) + temporary bans (with **Bans** tab to review/unban)
- UA/referrer checks, disposable emails, allow/deny billing countries
- Cart heuristics: low-value guest friction, flagged-SKU patterns
- **Validation Profiles (global)**: Generic, US, UK, CA, AU, EU… with **live preview**
- Optional **custom regex additions** for phone/postal (preview shows preset **OR** custom)
- Import/Export settings (JSON), maintenance tools
- **Structured JSON logs** in Woo → Status → Logs (PII redacted, request correlation ID)
- Gateway friction is **opt-in** and applies only to listed “card” gateways; PayPal/Wallets untouched unless you add their IDs.

## Requirements

- WordPress ≥ 6.0, PHP ≥ 8.0, WooCommerce ≥ 7.0

## Install

1. Upload `wc-anti-fraud-pro-lite` to `/wp-content/plugins/`.
2. Activate the plugin.
3. Open **WooCommerce → Anti-Fraud** to configure.

## Logs

See **WooCommerce → Status → Logs**. Choose source `wc-antifraud-pro-lite`.  
Each log line is a compact JSON object with:

- `rid`: request ID
- `event`: `pass`, `blocked`, `order_created`, `gateways_hidden`, etc.
- Context: redacted `ip`, `ua`, `ref`, cart `total`, items, `uid/guest`
- Decision metadata: `reasons`, `profile`, and per-check booleans in `checks`.

Example (abbrev):

```json
{
	"event": "blocked",
	"rid": "8fb9a2c13c1d",
	"checks": { "phone_ok": false, "postal_ok": true },
	"reasons": ["phone_invalid"],
	"ip": "203.0.113.x"
}
```
