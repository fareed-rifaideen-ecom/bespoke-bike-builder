# Bespoke Bike Builder

A custom WordPress plugin for **The Cycle Hub**, powering a premium, review-led custom bicycle build experience — starting with the Pinarello Dogma F Road Bike.

Bespoke Bike Builder is not a WooCommerce product-add-on. Custom-build options, compatibility rules, and customer submissions are all managed by the plugin itself, entirely separate from the normal WooCommerce catalogue.

## What it does

- **Step-by-step customer builder** ([bbb_builder] shortcode) — a one-step-at-a-time wizard covering Build Type, Frame Colour, Frame Size, Cockpit, Groupset and Wheelset, followed by a Review step and a lead capture step (Name, Email, Phone, optional Remarks).
- **Frame Only support** — automatically skips Cockpit, Groupset and Wheelset steps when the customer selects a frame-only build.
- **Compatibility rules** — staff can restrict which options are selectable in one step based on a selection made in another (e.g. a given Frame Size only allows certain Cockpit widths).
- **Live image preview** — the main preview image always reflects the customer's Frame Colour selection, with temporary hover previews when browsing other photo-bearing options, and a click-to-zoom magnifier on the main image itself.
- **Optional estimated pricing** — an admin-controlled toggle (off by default) that shows a running estimated total and a per-option price breakdown to customers, backed by a server-side recalculation that never trusts prices submitted from the browser.
- **Draft & resume** — customers can save progress and resume later via a secure link.
- **Staff Frontend Portal** ([bbb_staff_portal] shortcode) — a login-gated, non-wp-admin page where staff can review submissions and update build statuses without ever touching wp-admin.
- **Notification recipients** — configurable list of staff email addresses notified on every new build request.
- **Custom dark "Pinarello Dark" visual theme** for the public-facing builder, with an admin-selectable header mode (recoloured theme header, or a fully custom two-row header).
- **Audit logging** — every submission and status change is recorded to a dedicated event log table.

## Admin menu (wp-admin → Bespoke Bike Builder)

All plugin screens live under one top-level menu:

| Screen | Purpose |
|---|---|
| Bespoke Bike Builder | Dashboard confirming build templates are set up correctly |
| Build Requests | Every customer submission, with full build spec, Remarks, and a status dropdown |
| Manage Options | Add/edit/deactivate build options, upload option photos, configure compatibility rules |
| Header Settings | Choose between the recoloured theme header or the custom two-row header |
| Notifications | Configure which staff email addresses are notified of new build requests |
| Pricing | Toggle whether estimated prices are shown to customers |
| Notices | Edit the disclaimer wording, Notes-step agreement checkbox text, and WhatsApp number/messages |
| Staff Roles | View which staff roles exist and which users currently hold them |

## Roles

Non-Administrator staff access is governed by real WordPress roles/capabilities, assignable from Users → Edit User:

- **Custom Build Manager** — leads, statuses, assignments, options, rules, imports, quotes, deposit requests
- **Custom Build Sales Staff** — leads, notes, assigned builds, status updates, WhatsApp, quotes, deposit request actions
- **Custom Build Option Manager** — options, images, availability and imports only

## Requirements

- WordPress, Flatsome theme
- WooCommerce (for the deposit order bridge, in a later phase)
- PHP 7.4+

## Author

Fareed M. Rifaideen — [fareed-rifaideen.netlify.app](https://fareed-rifaideen.netlify.app)

## License

GPL v2 or later
