# Third-Party Services (WHMCS provisioning module)

Resell hosting you buy from other providers (Namecheap, Hostinger, your own
VPS...) through WHMCS. You enter the control panel URL and login details once
in the admin area, and the client can see them in the client area, so they
stop asking for credentials.

Works with any control panel: cPanel, CyberPanel, DirectAdmin, Plesk,
aaPanel, CloudPanel or a custom one.

**Requirements:** WHMCS 9.0.x, PHP 8.2+

## Build status

| Phase | Scope | Status |
|---|---|---|
| 1 | Module registration, product settings, database table, admin service fields | Done |
| 2 | Client area login box (Six theme, Twenty-One compatible) | Planned |
| 3 | Order To-Do, "Mark Ready & Notify Client", access email | Planned |
| 4 | Suspend/terminate To-Dos, upstream renewal reminders (cron) | Planned |
| 5 | Security review, polish, documentation | Planned |

## Installation

Upload the module folder to your WHMCS installation:

```
public_html/billing/modules/servers/thirdpartyservices/
├── thirdpartyservices.php
└── lib/
```

Only `modules/servers/thirdpartyservices/` goes to the server. `tests/` and
`composer.json` are for development only. File permissions: `644` for files,
`755` for folders.

The database table `mod_thirdpartyservices_accounts` is created automatically
the first time the module is used.

## Product setup

1. **Admin → System Settings → Products/Services →** edit the product.
2. **Module Settings** tab → Module Name: **Third-Party Services**.
3. Settings:

| Setting | Meaning |
|---|---|
| Default Provider | Provider pre-filled on new services (admin only) |
| Default Panel Type | Panel pre-selected on new services |
| Hide Password | Hide the panel password from the client area (Phase 2) |
| Allow Auto-Login | One-click login for cPanel / DirectAdmin (Phase 2) |
| Renewal Reminder Days | Days before the provider renewal date to remind you (Phase 4) |

Do not reorder these settings in the code: WHMCS stores them by position
(`configoption1`–`configoption5`).

## Service setup

On **Admin → Clients → (client) → Products/Services**, the module adds these
fields:

| Field | Visible to client |
|---|---|
| Provider | No |
| Panel Type | Yes |
| Control Panel URL | Yes |
| Webmail URL | Yes |
| Server IP | Yes |
| Nameservers (one per line) | Yes |
| Note For Client | Yes |
| Extra Login Details (FTP, SSH, DB host...) | Yes |
| Upstream Renewal Date | No |
| Admin Notes (order ID, cost...) | No |

The panel **username and password** use the standard WHMCS service
Username / Password fields, where WHMCS encrypts the password.

Invalid input (bad URL, IP, nameserver or date) is not saved; the previous
value is kept and an error is shown on the service page.

Client-visible output starts in Phase 2.

## Security notes

- The service password is masked in the WHMCS module log.
- All admin input is validated and all output is HTML-escaped.
- URLs must be `http://` or `https://`; other schemes (e.g. `javascript:`) are rejected.

## Development tests

The tests run the module against an in-memory SQLite database using the same
database library WHMCS uses (Illuminate Database), outside WHMCS:

```bash
composer install
composer test
```

They do not replace testing on a real WHMCS 9 installation.
