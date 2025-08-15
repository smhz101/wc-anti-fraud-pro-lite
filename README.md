# WC Anti-Fraud Pro Lite

Pre-checkout risk checks for WooCommerce.

## ✨ Features

- **Bot & form traps:** static + rotating honeypots, minimum render time.
- **Device age:** first-seen cookie to detect “fresh” devices.
- **Velocity controls:** IP/email attempt limits, temporary bans, review/unban in **Bans** tab.
- **Context checks:** user-agent, referrer, disposable email detection.
- **Geo rules:** allow/deny billing countries.
- **Cart heuristics:** low-value guest friction, flagged-SKU patterns.
- **Validation Profiles:** Generic, US, UK, CA, AU, EU with live preview; add custom regex for phone/postal.
- **Structured JSON logs:** Woo → Status → Logs (`wc-antifraud-pro-lite`), PII-redacted with correlation IDs.
- **Gateway friction:** optionally hide or hard-fail selected “card” gateways; PayPal/Wallets untouched unless added.
- **Import/Export:** one-click JSON backup/restore of settings.
- **Maintenance tools:** clear caches, reset counters, purge expired bans.

## Requirements

> **Works with:** WordPress ≥ 6.0 • WooCommerce ≥ 7.0 • PHP ≥ 8.0

## 📦 Installation

1. Upload the folder to `/wp-content/plugins/` or install the zip via Plugins → Add New.
2. Activate **WC Anti-Fraud Pro Lite**.
3. Go to **WooCommerce → Anti-Fraud** to configure.

## 🚀 Quick start

1. Pick a **Validation Profile** (e.g., _Generic_ or your region).
2. Enable **velocity limits** and set ban TTL.
3. Choose **card gateways** to protect (optional).
4. Save and test the checkout; inspect **Woo → Status → Logs** for decision details.

## ⚙️ Settings overview

- Profiles: choose Generic / US / UK / CA / AU / EU; preview phone/postal acceptance live.
- Custom regex: extend/override phone/postal patterns per your business needs.
- Velocity: attempts per IP/email, ban duration, and whitelist overrides.
- Gateways: list of “card” gateways to apply friction (filterable).
- Bans: view/search bans, unban, and add manual bans.
- Tools: import/export, clear transients, wipe counters.
- Logs: link to WooCommerce Logs screen with a pre-filtered source.

## 🧩 Developer notes

### Filters & actions (selection)

- wca_validation_presets (filter): alter/add regional validation presets.

```
add_filter('wca_validation_presets', function($presets){
    $presets['pk'] = [
        'label'  => 'Pakistan',
        'phone'  => '/^\+?92[\s-]?\d{3}[\s-]?\d{7}$/',
        'postal' => '/^\d{5}$/',
    ];
    return $presets;
});
```

- Decision hooks: The plugin fires actions around pass/block events with a structured payload.

  - `do_action( 'wca_risk_blocked', $payload );`
  - `do_action( 'wca_risk_passed', $payload );`
  - `do_action( 'wca_risk_flagged', $payload );`

## Extending gateway coverage

Gateways are filterable so you can treat additional ones as “card-like”:

```
add_filter('wca_card_like_gateways', fn($ids) => array_merge($ids, ['stripe_sepa','custom_cc']));
```

## 🔐 Privacy

- PII is redacted in logs by default (e.g., anonymized IP).
- The plugin uses a first-party cookie to measure "device age". You can change the cookie name and TTL via filters.
- Configure data retention (logs, bans) in Settings → Tools or via filters.

> Consult your local laws (e.g., GDPR/PECR) and update your site’s privacy policy to disclose anti-fraud cookies.

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
