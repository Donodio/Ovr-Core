# Our Villages Rental — Administrator Guide

_For platform administrators. Everything below lives in **wp-admin**. Most OVR
tools are under the **Properties** menu (the "OVR" property type), plus a
**Platform Overview** dashboard._

> Printable: open this file in your browser (or any Markdown viewer) and use
> File → Print / Save as PDF.

---

## 1. The Platform Overview dashboard

**Properties → Platform Overview** is your home base.

- **Stat tiles**: Revenue (month / year), Featured / Pending / Expired listings,
  Total Listings, Members, Pending Reviews, Pending Renewals, Inquiries Today,
  **Map Engagement**, and **System Health**.
- **Customize**: click *Customize* to show/hide and drag-reorder tiles. Your
  layout is saved to your own account.
- **Global Search** box: jump to any listing, member, payment, review or
  inquiry by keyword or ID.
- **System Health** flags schema, cron, uploads and cloud-storage problems.

---

## 2. Listings

### Reviewing & managing
- **Properties → All Listings**: filter by status, owner, village; export CSV/Excel.
- Each listing has a **Listing Status & Promotion** sidebar (active/inactive,
  featured, bump) and an **SEO** sidebar (meta title/description, noindex).
- **Pending review**: listings awaiting approval show in the Pending tile and
  list filter. Approving/rejecting sends the owner an email (see Emails).
- **Deleted Listings**: soft-deleted listings are recoverable here for a grace
  period before the cleanup cron removes them.

### Promotions
- **Featured** and **Bump** boost a listing in search and on the homepage.
- Paid upgrades are defined in **Paid Services** (below) and activate on payment.

---

## 3. Homepage Slides (hero slideshow)

**Properties → Homepage Slides.** Build the rotating hero on the homepage.

1. Click **+ Add Slide**, choose an image, and (optionally) a heading, subtitle
   and button text + link.
2. Tick **Visible in slideshow** for each slide you want live; drag rows to set order.
3. **Save Slides.**
4. Edit the homepage in **Elementor**, open the **OVR Hero Section** widget, and
   set **Background = Homepage Slideshow**. Choose whether captions come from the
   widget or from each slide, and set the rotation interval.

If no slides are enabled, the hero falls back to its single background image.

---

## 4. Ad Banners

**Properties → Ad Banners.** Promotional banners with click/impression tracking.

1. **Add Banner**: image, link URL, **placement**, optional schedule (start/end),
   sort order, enabled.
2. Place it on the front end with the shortcode shown on the screen, e.g.
   `[ovr_ad_banner placement="homepage"]` (placements: Homepage, Search Top,
   Search Sidebar, Single Property, Owner Dashboard).
3. The list shows **Impressions, Clicks and CTR** per banner plus totals. Clicks
   are tracked through a redirect so counts stay accurate even with page caching.

---

## 5. Emails

**Properties → Emails.** Every automated email is editable.

- Edit **subject**, **HTML body**, optional plain-text body, and **recipient**
  (user / admin / both / custom).
- Insert the listed `{{variables}}` — they're filled in when the email sends.
- **Preview** renders with sample data; **Send Test** emails it to you (works
  even on disabled templates).
- Untick **Enabled** to stop a particular email from sending.

---

## 6. Reviews & Comments

**Properties → Reviews & Comments.** Moderate guest reviews.

- Approve / reject / delete; approving stamps an approval date and publishes it.
- Only reviews at or above the reputation threshold surface in the testimonials
  carousel.
- Analytics show average rating and per-property breakdown.

---

## 7. Paid Services (listing upgrades)

**Properties → Paid Services.** The catalogue of paid promotions.

- Full CRUD: name, description, price, duration, badge, priority weight, max
  simultaneous, **Renewable / Auto-Renew**, active flag.
- Three behaviours via *service type*: Featured, Bump (Top of Page), Homepage Slider.
- Purchase reporting cards: Active / Expired / Upcoming (next 7 days).
- Trash & restore; CSV/Excel export.

---

## 8. Members & payments

- **Users**: filter by role (landlords vs renters), export; manage accounts.
- **Membership / Membership Plans**: the subscription tiers and their pricing.
- **Payments**: every transaction, with revenue cards and CSV export.
- **CRM**: inquiry pipeline and contact records.
- **Support**: support tickets + knowledge base.
- **Bookings**: reservations, manual or imported (iCal / WordPress sync).

---

## 9. Settings

**Properties → OVR Settings.** Tabbed:

- **General**: site name, support email, phone, logo/favicon, timezone, date format.
- **Listings**: max listings / photos / videos / documents per owner; default status.
- **Subscriptions**: default membership.
- **Media**: image quality, WebP, watermark (enable/position/opacity).
- **Security**: password rules, session timeout, login-attempt lockout, admin 2FA.
- **Storage**: Backblaze B2 credentials + options (see Cloud Storage).

Settings here actually drive behaviour (image quality, login throttling, 2FA,
favicon, listing caps, watermarking) — they are not placeholders.

---

## 10. Cloud Storage

**Properties → Cloud Storage.** Monitor and recover media offloaded to Backblaze B2.

- **Connection** card: configured/not; **Test Connection**.
- **Stats**: offload coverage, files in B2, bytes stored, pending offload,
  originals missing locally.
- **Recovery**: **Offload Pending** (catch up images not yet uploaded) and
  **Restore Missing Originals** (re-download from B2 and rebuild sizes).

Configure credentials under Settings → Storage. Credentials remain the client's.

---

## 11. Import Listings (CSV)

**Properties → Import (CSV).** Bulk-create listings from a spreadsheet.

1. **Upload** a CSV with a header row.
2. **Map** each column to a field (auto-mapped by header name where possible):
   title, description, owner email, beds/baths/price/address/geo, taxonomies
   (village/type/amenity/rental type), and image URLs (featured + gallery).
3. Set options: default owner, post status, **match by title** (update vs
   duplicate), import images.
4. **Dry Run** to preview create/update/skip counts, then **Run Import**.

Image URLs are downloaded into the Media Library. Large files: import in batches
(1,000 rows per run).

---

## 12. Audit Log

**Properties → Audit Log.** A tamper-evident trail of admin-significant actions
(logins, user/listing/settings/payment/subscription changes, review moderation,
email edits) with actor, subject, old/new values and user agent. Filter by date,
action, entity, entity ID, admin; export CSV/Excel. Entries older than the
retention window (365 days) are purged monthly.

---

## 13. Routine maintenance

- Watch **System Health** on the dashboard.
- Review **Pending Reviews** and **Pending Renewals** tiles regularly.
- Keep **Emails** templates enabled and current.
- After bulk media changes, check **Cloud Storage** coverage.
- See `docs/HANDOVER.md` for cron, environment and deployment details.
