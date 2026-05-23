# Design System: Our Villages Rentals (OVR)
**Project ID:** [Insert Stitch Project ID Here]

## 1. Product Identity
Our Villages Rentals is a local, owner-direct rental advertising platform for The Villages, Florida. It is not a generic vacation rental marketplace and should not imitate Airbnb, Vrbo, hotel booking sites, luxury resort sites, or broad travel portals.

The site serves renters, snowbirds, retirees, landlords, and property owners who need a practical way to find, compare, advertise, and manage rental homes inside The Villages community. The experience should preserve the client’s existing rental workflow while improving visual polish, clarity, and usability.

The product promise is:

- Find rental homes in The Villages, Florida.
- List a property as a landlord or owner.
- Contact owners directly.
- Compare homes, save favorites, review availability, and understand flexible seasonal pricing.
- Support monthly, seasonal, weekly, nightly, and custom-term rental pricing without forcing everything into a nightly-rate model.

## 2. Visual Theme & Atmosphere
The interface should feel trustworthy, local, readable, and community-specific. It should look like a polished modern version of a local rental directory/classifieds platform, not a sparse consumer travel app.

The desired atmosphere is:

- **Local and community-rooted:** The Villages lifestyle must be visible through imagery, location labels, Village names, and information architecture.
- **Senior-readable:** Larger typography, clear labels, high contrast, predictable navigation, and generous tap targets.
- **Information-rich but organized:** The site should show practical listing information without feeling chaotic.
- **Owner-direct and transparent:** Landlord contact information, owner profile details, listing ID, last updated date, page views, documents, and rental terms should feel prominent and trustworthy.
- **Commercially practical:** Featured listings, paid upgrades, advertising, and landlord tools should be visible in the right places without dominating the homepage.

Avoid generic “perfect getaway,” “luxury escape,” “retreat,” and “book now” travel language unless it is part of a specific listing. Use plain business language that matches a local rental platform.

## 3. Target Audience & Use Cases
### Primary Audiences
- Retirees and seasonal renters looking for homes in The Villages.
- Snowbirds seeking monthly or multi-month stays, especially January through March.
- Property owners and landlords advertising their homes.
- Returning users who compare multiple homes, save favorites, and contact owners.
- Admin users who manage listings, users, subscriptions, categories, and paid upgrades.

### Core User Jobs
- Browse rentals in a 3-across tile format.
- Search by property ID, date range, free-form Village name, property type, bedrooms, pets, and view.
- Compare multiple rentals.
- Save favorite listings.
- Review a listing’s photos, rates, availability, terms, documents, owner information, reviews, and map.
- Email or call the owner directly.
- Manage listings from a landlord dashboard.
- Update photos, calendar availability, pricing tables, rental terms, and paid upgrades.

## 4. Color Palette & Roles
- **Deep Village Navy (#000961):** Primary structural color. Use for header bars, navigation backgrounds, key headings, footer, selected states, and strong trust-building UI.
- **Community Sky Blue (#00A2E8):** Secondary accent. Use for links, hover states, active filters, secondary buttons, map/list toggle accents, and subtle callouts.
- **Readable Border Gray (#DBDBDB):** Neutral divider and structure color. Use for card borders, form outlines, table borders, calendar grid lines, sidebar dividers, and inactive controls.
- **Featured Gold (#DEAF0C):** Paid visibility and high-priority action color. Use for featured listing badges, paid upgrade indicators, important inquiry CTAs, and advertising/service highlights.
- **Soft Page White (#F8FAFC):** Main page background. Use instead of harsh pure white when a quieter canvas is needed.
- **Paper White (#FFFFFF):** Card, table, input, and content surfaces.
- **Ink Text (#1C2430):** Primary readable body text.
- **Muted Text (#5F6B7A):** Secondary descriptions, metadata, helper labels, and muted filter text.
- **Success Green (#2E7D32):** Available calendar states, verified owner states, and successful form feedback.
- **Unavailable Red (#B3261E):** Unavailable calendar dates, warnings, and failed form states.

Color usage must be functional. Navy creates structure and trust. Blue supports interaction. Gold means paid/featured/important. Gray separates content. Green/red communicate availability and status.

## 5. Typography Rules
- Use a clean, highly readable sans-serif with strong letter clarity. Suitable options: Atkinson Hyperlegible, Source Sans 3, Noto Sans, or a similarly legible sans-serif.
- Avoid trendy tiny typography, ultra-light text, condensed display fonts, and decorative scripts.
- Body text should be at least 16px on desktop and mobile.
- Important listing metadata should not drop below 14px.
- Headings should be clear and restrained, not oversized marketing hero text except on the homepage hero.
- Use line height around 1.5 to 1.65 for paragraphs.
- Use normal letter spacing. Do not use negative letter spacing.
- Buttons, navigation, form labels, and filter labels should be immediately readable for older users.

Recommended hierarchy:

- Page H1: 36-44px desktop, 28-34px mobile.
- Section H2: 26-32px desktop, 22-26px mobile.
- Card title: 18-22px.
- Body: 16-18px.
- Metadata: 14-16px.
- Form labels: 15-16px.

## 6. Layout Principles
The interface should be practical, full-width, and screen-efficient.

### Global Layout
- Use a persistent top navigation header.
- Keep the layout clean, but do not strip out important listing data.
- Favor structured grids, tables, tab sections, sidebars, and clear panels.
- Avoid decorative floating cards that do not serve a workflow.
- Avoid nested cards inside cards.

### Desktop Search Layout
- Full-width layout.
- Left filter sidebar.
- Main results area with 3-across listing cards.
- Optional right sidebar for featured listing or advertising/service panel.
- Grid/list/map view controls in the upper right.
- Pagination at top and bottom when useful.

### Listing Detail Layout
- Two-column upper section:
  - Left: large image gallery.
  - Right: listing summary, inquiry/contact, owner info, compare/favorite, QR/statistics.
- Below: availability, pricing table, tabs, terms, documents, map, video/panorama.
- Do not place “Similar Homes” at the bottom.

### Mobile Layout
- Header collapses cleanly but keeps Search Rentals, Login, and Advertise/List Property accessible.
- Filters become collapsible or drawer-based.
- Listing cards stack vertically.
- Listing detail owner contact and inquiry CTA remain easy to access.
- Calendar and pricing table must remain readable through horizontal scroll or stacked cards.

## 7. Global Header & Navigation
The header should appear across the site and include:

- OVR logo.
- Site name: “Our Villages Rental” or “Our Villages Rentals.”
- Tagline: “Serving landlords and renters since 2013.”
- Primary navigation:
  - Search Rentals
  - Map
  - Villages Info
  - OVR Info
  - Advertise
  - Login

Navigation can use dropdown or popover panels. These panels should be plain, readable, and menu-like rather than decorative mega menus.

### Search Rentals Menu
- All Homes
- Long Term Only / 6+ Months
- Deals + Cancellations
- New Listings
- Featured Homes
- My Favorites

### Advertise Menu
- If logged out: advertising/listing information and registration.
- If logged in: My Dashboard.

### Villages Info Menu
- ID Form
- Golf the Villages
- Villages.net
- TheVillages.com
- Local businesses and resources

### OVR / Site Info Menu
- About OVR
- PDF Updates
- Verify Owner / Landlord FAQ
- Renter FAQ
- Site Terms
- Contact OVR

## 8. Homepage Design Rules
The homepage is a local community landing page with two primary actions:

1. **Find a Rental**
2. **List My Property**

Do not lead with a large generic search bar. Do not make landlord subscription/pricing plans the main homepage content.

Homepage structure:

- Header with logo, navigation, login, and advertise/list property action.
- Hero with The Villages lifestyle imagery, not abstract gradients or generic house stock photos.
- H1 should be specific: “Rental Homes in The Villages, Florida.”
- Two strong action panels/buttons: Find a Rental and List My Property.
- Short “Who We Are” section.
- Village/community image strip for areas such as Spanish Springs, Lake Sumter Landing, Brownwood, Sawgrass, and Eastport.
- Featured Homes section showing paid featured listings.
- Paid Services / Listing Upgrades section lower on the page, not hero-dominant.
- Helpful information links.
- Disclaimer area near the footer.

Homepage tone should be direct and local, not travel-marketing heavy.

## 9. Search Results Design Rules
Search results should feel like a polished rental directory.

Required elements:

- Left search filter sidebar.
- Full-width results area.
- 3-across tile format on desktop.
- Grid/list/map toggle.
- Pagination.
- Right sidebar may show featured listing or advertising/service area.

Required filters:

- Property ID with Go button.
- Check-in date.
- Check-out date.
- Village name as free-form text input.
- Property type dropdown.
- Bedrooms dropdown.
- Allows pets checkbox.
- View filter if applicable.
- Apply button.

Avoid a primary price filter because pricing is flexible and not always comparable.

Listing cards should show:

- Photo.
- Listing title.
- Village name.
- Listing ID.
- Bedroom/bath/square footage or guests.
- Review stars only when reviews exist.
- Featured badge when applicable.
- Gold featured/upgraded styling for paid visibility.
- Compare checkbox/link.
- Favorite heart.
- Flexible pricing summary.

Acceptable pricing labels:

- “Check Description or Inquire for Pricing”
- “Monthly rates available”
- “Seasonal term available”
- “From $450 up to $5,200”
- “Jan-Mar term available”

Avoid forcing “$X / night” unless that listing row is truly nightly.

## 10. Listing Detail Design Rules
The listing detail page must be information-rich and practical.

### Top Bar
- Back link.
- Listing ID.
- Listing title.
- Owner/admin edit controls only when authorized.
- Print/share controls if appropriate.

### Gallery
- Large main image.
- Secondary image tiles.
- “+ More Images” tile.
- Clicking an image opens an all-photos scroll view or page before/alongside slideshow behavior.
- Optional video and panorama indicators.

### Right Listing Summary
- Property type and bedroom count.
- Village name.
- Flexible pricing summary.
- Gold “Inquire - Email Owner” CTA.
- Add to compare.
- Add/remove favorites.
- Verified owner banner if applicable.
- Owner name.
- Owner photo.
- Phone.
- Email owner link.
- Number of listings.
- Owner comments.
- QR code.
- Statistics.
- Page views.
- Last updated date.

### Availability
- Show up to six months visible or horizontally scrollable.
- Include left/right arrows.
- Current month plus future months.
- Clear available/unavailable legend.

### Rates / Pricing Table
The pricing table is central to the business model. It must support:

- Date range.
- Rate amount.
- Rate type: nightly, weekly, monthly, custom term.
- Minimum stay.
- Notes.

Example rows:

- December 1-31 | $2,000 | Monthly | 30 nights | December monthly rate.
- July 1-31 | $100 | Nightly | 7 nights | Summer short stay.
- January 1-March 31 | $12,000 | Fixed seasonal term | Full term | Peak season.

Never convert all rates to nightly display.

### Tabs
Use tabs for:

- General Description
- Features
- Reviews

General Description should include:

- Long description.
- What’s it near.

### Additional Sections
- Policies and Payment.
- Documents and Resources.
- Rental Terms.
- Map / Panorama / Video.
- Page disclaimer.

Do not include Similar Homes.

## 11. All Photos View
Create an intermediate photo browsing page or overlay when a listing photo is clicked.

Required elements:

- Back to listing.
- Listing ID and title.
- Scrollable photo grid or masonry layout.
- Optional grouped labels such as Exterior, Kitchen, Bedrooms, Lanai, Golf Cart, Community.
- Slideshow option.

This view should solve the client’s request for seeing all photos in one scrollable place, not only one-by-one slideshow.

## 12. Dashboard & Admin Design Rules
Logged-in landlord dashboard should be simple, readable, and task-oriented.

Dashboard areas:

- My Listings.
- My Subscription.
- Announcements.
- Inquiries.
- Payments.
- Profile.
- Password.

Edit Listing page tabs:

- Photos.
- Calendar.
- General Info.
- Price Table.
- Location.
- Documents.
- Owner Contact.
- Paid Upgrades.

Landlord tools:

- PDF help.
- Villages ID form.
- Upload landlord photo.
- Listing upgrades / paid services.

Admin functions:

- User listings: sort, select, print.
- Listings: sort, select.
- Configurables / parameters / setup.
- Categories.
- Navigation.
- Subscriptions.
- Pages.

## 13. Component Styling
### Buttons
- Primary structural buttons: Deep Village Navy (#000961) with white text.
- Primary conversion/inquiry buttons: Featured Gold (#DEAF0C) with dark text.
- Secondary actions: white or light surface with navy text and gray border.
- Minimum tap height: 48px.
- Corners: 6-8px.
- Avoid excessive pill shapes except for compact filters or status chips.

### Cards
- Property cards use white surfaces, gray borders, clear image areas, and compact but readable details.
- Featured cards use gold badge, gold outline, or subtle gold header/label.
- Avoid heavy shadows. Use light elevation only when it improves scan clarity.

### Forms
- Labels above inputs.
- Clear field outlines.
- Large enough fields for senior users.
- Group related filters and listing edit fields.
- Use select controls for property type/bedrooms, checkboxes for binary options, and free-form input for Village name.

### Tables
- Rate tables must be easy to scan.
- Use strong column labels and alternating row backgrounds or horizontal dividers.
- Tables must support long notes and custom term labels.

### Calendars
- Calendar should be readable at a glance.
- Use green/neutral for available and red or muted blocked states for unavailable.
- Month labels must be clear.
- Six-month availability should be possible on desktop via a wide layout or horizontal carousel.

## 14. Content & Microcopy Rules
Use plain, direct labels:

- Find a Rental
- List My Property
- Email Owner
- Inquire - Email Owner
- Add to Compare List
- Add / Remove Favorites
- Check Description or Inquire for Pricing
- Monthly rates available
- Seasonal term available
- Village of [Name]
- Last Updated
- Page Views

Avoid:

- “Perfect retreat”
- “Dream getaway”
- “Luxury escape”
- “Book now” as the default owner-direct action
- “Per night” as the default pricing model

## 15. Do-Not-Include Rules
- Do not make the homepage mainly about landlord subscription options.
- Do not lead the homepage with a generic rental search pill.
- Do not remove top navigation.
- Do not force all prices into nightly rates.
- Do not use a dropdown listing 150+ Villages.
- Do not show Similar Homes on listing detail.
- Do not hide landlord name, photo, phone, or email.
- Do not omit documents, rental terms, page views, last updated date, or owner information.
- Do not make maps city-wide and visually busy.
- Do not use tiny text.
- Do not over-minimize listing pages.
- Do not create a generic travel marketplace design.

## 16. Stitch Prompting Notes
When generating screens in Stitch, always specify the page type and the required business behavior. Mention that this is an owner-direct local rental directory for The Villages, Florida.

For every generated screen, preserve:

- Navy / sky blue / gray / gold palette.
- Large readable typography.
- Practical information hierarchy.
- The Villages-specific imagery and place names.
- Flexible pricing language.
- Owner contact visibility.
- Featured listing business model.

Required Stitch screens:

1. Homepage.
2. Search Results.
3. Listing Detail.
4. All Photos View.
5. Landlord Dashboard.
6. Edit Listing.
