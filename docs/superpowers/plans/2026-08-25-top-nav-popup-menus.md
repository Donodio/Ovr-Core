# Top Navigation Popup Menus Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the flat OVR top navigation with three icon-bearing popup menus (Explore Rentals, Site Information, always; My Account when logged in) plus the six supporting pages/forms they link to.

**Architecture:** Extend the existing role-aware nav arrays in `OVR\Frontend\Header` with grouped `children` carrying Material Symbols `icon` keys; `templates/components/header-nav.php` already renders dropdowns and gains icon/disabled rendering. Supporting features are independent units following established plugin patterns: page registry (`Pages.php`), renderer-class + template + shortcode triads (`VillagesArchive` pattern), AJAX handlers in `AjaxHandler`, conditional asset enqueues in `Assets.php`.

**Tech Stack:** WordPress 6.4+ / PHP 8.2+, vanilla JS, Material Symbols (already loaded site-wide), pdf-lib (bundled locally, ID-request page only). **No automated test framework exists** — this repo verifies via `php -l` syntax checks plus the manual QA procedures in `TESTING.md`; every task below ends in explicit verification steps instead of unit tests.

**Spec:** `docs/superpowers/specs/2026-08-25-top-nav-popup-menus-design.md`

---

## File Structure Overview

| File | Action | Responsibility |
|---|---|---|
| `src/Frontend/Header.php` | Modify | Nav data: three menu groups, icons, disabled flags, active-group detection |
| `templates/components/header-nav.php` | Modify | Render icons + disabled rows in dropdowns/mobile drawer; active-group class |
| `assets/css/ovr-public.css` | Modify | Icon alignment, disabled state, active trigger state |
| `src/Core/Pages.php` | Modify | Six new pages, `PAGES_VERSION` bump, contact shortcode sync |
| `src/Frontend/VillageSections.php` | Create | Renderer for village-section shortcut grid |
| `templates/pages/village-sections.php` | Create | Grid markup (All Areas + sections, 2-across) |
| `src/Shortcodes/ShortcodeManager.php` | Modify | Register `[ovr_village_sections]`, `[ovr_id_request]`, `[ovr_contact_form]` |
| `src/Frontend/ContactForm.php` | Create | Contact form renderer + AJAX submit handler |
| `templates/pages/contact-form.php` | Create | Form markup |
| `assets/js/ovr-contact.js` | Create | AJAX submission |
| `src/Admin/Settings.php` | Modify | `newest_listings_count` field; `id_form_template` media picker |
| `src/Frontend/IdRequest.php` | Create | ID-request renderer + field schema |
| `templates/pages/id-request.php` | Create | Fillable form markup |
| `assets/js/ovr-id-request.js` | Create | Client-side PDF fill/generate |
| `assets/js/vendor/pdf-lib.min.js` | Create (vendor) | pdf-lib library |
| `src/Ajax/AjaxHandler.php` | Modify | Register contact AJAX action |

---

## Chunk 1: Navigation Data & Rendering

### Task 1: Menu-group data in Header.php

**Files:**
- Modify: `src/Frontend/Header.php`

- [ ] **Step 1: Add shared helpers + public menu groups**

Insert after `mega_menu()` (before the `MENU_LOCATION` constant):

```php
	/**
	 * Build a search-results URL with optional query args.
	 *
	 * @param array<string, string> $params Query args appended to the bare search URL.
	 */
	private static function search_url( array $params = [] ): string {
		$url = Pages::get_page_url( 'ovr_page_search' );
		return $params ? add_query_arg( $params, $url ) : $url;
	}

	/**
	 * "Newest Listings" result cap (Settings > General; defaults to 12).
	 */
	private static function newest_limit(): int {
		$s = (array) get_option( 'ovr_settings', [] );
		return max( 1, (int) ( $s['newest_listings_count'] ?? 12 ) );
	}

	/**
	 * The two popup menus every visitor sees: Explore Rentals + Site Information.
	 *
	 * Shape mirrors the legacy dropdown contract (label/url/target/divider)
	 * with two additions: `icon` (Material Symbols name) and `disabled`.
	 *
	 * @return array<string, array{label:string, url:string, icon:string, children?:array<int,array<string,mixed>>}>
	 */
	public static function public_menu_groups(): array {
		return [
			'explore'   => [
				'label'    => __( 'Explore Rentals', 'ovr-core' ),
				'url'      => self::search_url(),
				'icon'     => 'travel_explore',
				'children' => [
					[ 'label' => __( 'Search All Rentals', 'ovr-core' ), 'icon' => 'search',        'url' => self::search_url() ],
					[ 'label' => __( 'Featured Properties', 'ovr-core' ), 'icon' => 'star',         'url' => self::search_url( [ 'featured_only' => '1' ] ) ],
					[ 'label' => __( 'Deals & Cancellations', 'ovr-core' ), 'icon' => 'local_offer', 'url' => self::search_url( [ 'deals_only' => '1' ] ) ],
					[ 'label' => __( 'Long Term Rentals', 'ovr-core' ), 'icon' => 'event_repeat',  'url' => self::search_url( [ 'rental_type' => 'long-term-rental' ] ) ],
					[ 'label' => __( 'Newest Listings', 'ovr-core' ), 'icon' => 'fiber_new',        'url' => self::search_url( [ 'sort' => 'newest', 'per_page' => (string) self::newest_limit() ] ) ],
					[ 'label' => __( 'Search by Village Section', 'ovr-core' ), 'icon' => 'map',    'url' => Pages::get_page_url( 'ovr_page_village_sections' ) ],
					[ 'label' => __( 'Map Search', 'ovr-core' ), 'icon' => 'location_on',           'url' => self::search_url( [ 'view' => 'map' ] ) ],
					[ 'divider' => true ],
					[ 'label' => __( 'Renting in The Villages – An Overview', 'ovr-core' ), 'icon' => 'menu_book', 'url' => Pages::get_page_url( 'ovr_page_renting_overview' ) ],
					[ 'label' => __( 'Verified Owners', 'ovr-core' ), 'icon' => 'verified',         'url' => Pages::get_page_url( 'ovr_page_verified_owners' ) ],
				],
			],
			'site_info' => [
				'label'    => __( 'Site Information', 'ovr-core' ),
				'url'      => '',
				'icon'     => 'info',
				'children' => [
					[ 'label' => __( 'Rental Owner Information', 'ovr-core' ), 'icon' => 'real_estate_agent', 'url' => Pages::get_page_url( 'ovr_page_owner_information' ) ],
					[ 'label' => __( 'The Villages Lifestyle', 'ovr-core' ), 'icon' => 'diversity_3', 'url' => 'https://www.thevillages.com/lifestyle/', 'target' => '_blank' ],
					[ 'label' => __( 'The Villages Town Squares', 'ovr-core' ), 'icon' => 'storefront', 'url' => 'https://www.thevillages.com/shopping-dining/', 'target' => '_blank' ],
					[ 'label' => __( 'Golf The Villages', 'ovr-core' ), 'icon' => 'golf_course', 'url' => 'https://www.golfthevillages.com', 'target' => '_blank' ],
					[ 'label' => __( 'OVR User Agreement', 'ovr-core' ), 'icon' => 'gavel', 'url' => Pages::get_page_url( 'ovr_page_user_agreement' ) ],
					[ 'divider' => true ],
					[ 'label' => __( 'Forgot My Password', 'ovr-core' ), 'icon' => 'lock_reset', 'url' => Pages::get_page_url( 'ovr_page_forgot_password' ) ],
					[ 'label' => __( 'Contact OVR', 'ovr-core' ), 'icon' => 'mail', 'url' => Pages::get_page_url( 'ovr_page_contact' ) ],
					[ 'label' => __( 'Sign up to Advertise', 'ovr-core' ), 'icon' => 'campaign', 'url' => Pages::get_page_url( 'ovr_page_register' ) ],
					[ 'divider' => true ],
					[ 'label' => __( 'Site Testimonials', 'ovr-core' ), 'icon' => 'reviews', 'disabled' => true ],
					[ 'label' => __( 'OVR Business Partners', 'ovr-core' ), 'icon' => 'handshake', 'disabled' => true ],
				],
			],
		];
	}

	/**
	 * Logged-in account menu (landlord capability users; admins keep the
	 * visitor menus plus their Site Admin jump).
	 */
	public static function account_menu_group(): array {
		$dash = Pages::get_page_url( 'ovr_page_dashboard' );
		return [
			'label'    => __( 'My Account', 'ovr-core' ),
			'url'      => $dash,
			'icon'     => 'account_circle',
			'children' => [
				[ 'label' => __( 'My Dashboard', 'ovr-core' ), 'icon' => 'dashboard',  'url' => $dash ],
				[ 'label' => __( 'My Listings', 'ovr-core' ), 'icon' => 'home_work',   'url' => add_query_arg( 'tab', 'properties', $dash ) ],
				[ 'label' => __( 'My Inquiries', 'ovr-core' ), 'icon' => 'forum',      'url' => add_query_arg( 'tab', 'inquiries', $dash ) ],
				[ 'label' => __( 'Online Villages ID Request', 'ovr-core' ), 'icon' => 'badge', 'url' => Pages::get_page_url( 'ovr_page_id_request' ) ],
				[ 'label' => __( 'Villages Guest Passes', 'ovr-core' ), 'icon' => 'confirmation_number', 'url' => 'https://gcs.thevillages.com/cgi-bin/gc100', 'target' => '_blank' ],
				[ 'divider' => true ],
				[ 'label' => __( 'Log Out', 'ovr-core' ), 'icon' => 'logout', 'url' => wp_logout_url( home_url( '/' ) ) ],
			],
		];
	}
```

- [ ] **Step 2: Rewrite `visitor_nav_items()` and `landlord_nav_items()` to serve the new groups**

Replace both method bodies (keep their docblocks updated; signatures unchanged):

```php
	public static function visitor_nav_items(): array {
		return self::public_menu_groups();
	}

	/**
	 * Landlord top-nav: the public menus plus My Account deep links.
	 *
	 * @return array<string, array{label:string, url:string, icon:string, children?:array<int,array<string,mixed>>}>
	 */
	public static function landlord_nav_items(): array {
		return array_merge( self::public_menu_groups(), [ 'account' => self::account_menu_group() ] );
	}
```

Note: the old `'home'`, `'search_listings'`, `'deals'`, `'about'`, `'contact'`, `'reviews'`, `'membership'` flat entries are intentionally gone (spec §3.3: Reviews/Membership remain inside the dashboard sidebar only). Until Chunk 2 lands, six children resolve to `home_url( '/' )` via the sanctioned `Pages::get_page_url()` fallback — expected intermediate state, not a bug. Match the surrounding file's indentation style when merging snippets (`Header.php` uses tabs).

- [ ] **Step 3: Add active-GROUP detection**

Add beside `detect_active_nav()` (which stays for the custom-menu override path — its slugs only ever match admin-assigned flat menus; add a one-line comment noting that):

```php
	/**
	 * Which popup-menu TRIGGER should read as active for the current request.
	 */
	private static function detect_active_group(): string {
		$in_explore = is_page( (int) get_option( 'ovr_page_search' ) )
			|| is_page( (int) get_option( 'ovr_page_village_sections' ) )
			|| is_tax( 'ovr_village' );
		if ( $in_explore ) {
			return 'explore';
		}
		$in_site_info = is_page( (int) get_option( 'ovr_page_owner_information' ) )
			|| is_page( (int) get_option( 'ovr_page_user_agreement' ) )
			|| is_page( (int) get_option( 'ovr_page_forgot_password' ) )
			|| is_page( (int) get_option( 'ovr_page_contact' ) )
			|| is_page( (int) get_option( 'ovr_page_register' ) )
			|| is_page( (int) get_option( 'ovr_page_renting_overview' ) )
			|| is_page( (int) get_option( 'ovr_page_verified_owners' ) );
		if ( $in_site_info ) {
			return 'site_info';
		}
		$in_account = is_page( (int) get_option( 'ovr_page_dashboard' ) )
			|| is_page( (int) get_option( 'ovr_page_id_request' ) );
		if ( $in_account ) {
			return 'account';
		}
		return '';
	}
```

- [ ] **Step 4: Expose `active_group` to the template**

In `template_vars()`, add to the returned array:

```php
			'active_group'        => self::detect_active_group(),
```

- [ ] **Step 5: Syntax check**

Run: `php -l src/Frontend/Header.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
git add src/Frontend/Header.php
git commit -m "Nav: Explore Rentals / Site Information / My Account menu groups with icons"
```

### Task 2: Render icons, disabled rows, active triggers in header-nav.php

**Files:**
- Modify: `templates/components/header-nav.php`

- [ ] **Step 1: Desktop dropdown loop — icons + disabled rows**

Replace lines 51–59 (the inner `<div class="ovr-nav-dropdown">` loop) with:

```php
                        <div class="ovr-nav-dropdown" role="menu">
                            <?php foreach ( $item['children'] as $child ) : ?>
                                <?php if ( ! empty( $child['divider'] ) ) : ?>
                                    <div class="ovr-nav-dropdown-divider" role="separator"></div>
                                <?php elseif ( ! empty( $child['disabled'] ) ) : ?>
                                    <span class="ovr-nav-dropdown-link ovr-nav-dropdown-link--disabled" role="menuitem" aria-disabled="true"
                                          title="<?php esc_attr_e( 'Coming soon', 'ovr-core' ); ?>">
                                        <span class="material-symbols-outlined ovr-nav-child-icon" aria-hidden="true"><?php echo esc_html( $child['icon'] ?? '' ); ?></span>
                                        <?php echo esc_html( $child['label'] ); ?>
                                    </span>
                                <?php else : ?>
                                    <a class="ovr-nav-dropdown-link" role="menuitem" href="<?php echo esc_url( $child['url'] ); ?>"<?php echo ! empty( $child['target'] ) ? ' target="_blank" rel="noopener"' : ''; ?>>
                                        <span class="material-symbols-outlined ovr-nav-child-icon" aria-hidden="true"><?php echo esc_html( $child['icon'] ?? '' ); ?></span>
                                        <?php echo esc_html( $child['label'] ); ?>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
```

Also give the toggle button the trigger icon by replacing line 47–50's inner content:

```php
                        <button type="button" class="ovr-nav-link ovr-nav-toggle" aria-haspopup="true" aria-expanded="false" data-ovr-nav-toggle>
                            <span class="material-symbols-outlined ovr-nav-trigger-icon" aria-hidden="true"><?php echo esc_html( $item['icon'] ?? '' ); ?></span>
                            <?php echo esc_html( $item['label'] ); ?>
                            <span class="material-symbols-outlined ovr-nav-caret" aria-hidden="true">expand_more</span>
                        </button>
```

And mark the open group's wrapper active: change line 46 to include the active-group class:

```php
                    <div class="ovr-nav-item ovr-has-menu<?php echo ( $active_group ?? '' ) === $slug ? ' active' : ''; ?>">
```

- [ ] **Step 2: Mobile drawer — same treatment**

Replace the drawer's children loop (lines 119–125) with disabled-aware, icon-bearing markup:

```php
                            <?php foreach ( $item['children'] as $child ) : ?>
                                <?php if ( ! empty( $child['divider'] ) ) : ?>
                                    <div class="ovr-mobile-divider"></div>
                                <?php elseif ( ! empty( $child['disabled'] ) ) : ?>
                                    <span class="ovr-mobile-link ovr-mobile-link--disabled" aria-disabled="true">
                                        <span class="material-symbols-outlined ovr-nav-child-icon" aria-hidden="true"><?php echo esc_html( $child['icon'] ?? '' ); ?></span>
                                        <?php echo esc_html( $child['label'] ); ?> — <?php esc_html_e( 'Coming soon', 'ovr-core' ); ?>
                                    </span>
                                <?php else : ?>
                                    <a href="<?php echo esc_url( $child['url'] ); ?>" class="ovr-mobile-link"<?php echo ! empty( $child['target'] ) ? ' target="_blank" rel="noopener"' : ''; ?>>
                                        <span class="material-symbols-outlined ovr-nav-child-icon" aria-hidden="true"><?php echo esc_html( $child['icon'] ?? '' ); ?></span>
                                        <?php echo esc_html( $child['label'] ); ?>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
```

- [ ] **Step 2 (continued): Mobile drawer — de-duplicate the legacy logged-in links**

The drawer's trailing logged-in block (lines 135–141: Dashboard + Sign Out) duplicates the My Account group's entries for landlords. Restrict it to admins, who get no group:

```php
			<?php if ( $is_logged_in && $is_admin_user ) : ?>
				<a href="<?php echo esc_url( Pages::get_page_url( 'ovr_page_dashboard' ) ); ?>" class="ovr-mobile-link">
					<?php esc_html_e( 'Dashboard', 'ovr-core' ); ?>
				</a>
				<a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="ovr-mobile-link">
					<?php esc_html_e( 'Sign Out', 'ovr-core' ); ?>
				</a>
			<?php endif; ?>
```

- [ ] **Step 3: Actions area — Sign Out pill becomes admin-only**

Admins never receive the My Account group (`nav_items()` routes them to visitor menus + Site Admin jump), so the desktop Sign Out pill is their only admin logout. Wrap lines 87–90 in an admin guard instead of deleting:

```php
				<?php if ( $is_admin_user ) : ?>
					<a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>"
					   class="ovr-btn ovr-btn-outline ovr-btn-pill" style="padding:10px 20px;font-size:14px">
						<?php esc_html_e( 'Sign Out', 'ovr-core' ); ?>
					</a>
				<?php endif; ?>
```

Landlords lose the pill — Log Out lives in My Account ▾ per spec §2.

- [ ] **Step 4: Syntax check**

Run: `php -l templates/components/header-nav.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add templates/components/header-nav.php
git commit -m "Nav template: icons in dropdowns/drawer, disabled future items, admin-only Sign Out"
```

### Task 3: CSS states

**Files:**
- Modify: `assets/css/ovr-public.css` (append near the existing `.ovr-nav-dropdown` rules, ~line 785)

- [ ] **Step 1: Append styles**

```css
/* Popup menu icons, disabled future items, active trigger (spec 2026-08-25). */
.ovr-nav-trigger-icon,
.ovr-nav-child-icon {
	font-size: 20px;
	line-height: 1;
	vertical-align: middle;
	margin-inline-end: 8px;
	color: var(--ovr-primary, #000961);
	opacity: 0.85;
	flex: 0 0 auto;
}
.ovr-nav-dropdown-link {
	display: flex;
	align-items: center;
	gap: 4px;
	white-space: nowrap;
}
.ovr-nav-dropdown-link--disabled {
	cursor: not-allowed;
	opacity: 0.45;
	user-select: none;
}
.ovr-nav-item.active .ovr-nav-toggle {
	color: var(--ovr-primary, #000961);
	box-shadow: inset 0 -2px 0 var(--ovr-gold, #DEAF0C);
}
.ovr-mobile-link {
	display: flex;
	align-items: center;
}
.ovr-mobile-link--disabled {
	cursor: not-allowed;
	opacity: 0.45;
}
```

- [ ] **Step 2: Verify visually**

Load any page logged-out → hover/click **Explore Rentals** and **Site Information**: every row shows an icon; Site Testimonials/OVR Business Partners are muted and unclickable. Trigger underline appears on `/search`. Repeat in mobile width (drawer).

- [ ] **Step 3: Commit**

```bash
git add assets/css/ovr-public.css
git commit -m "Nav CSS: icon alignment, disabled future items, active trigger underline"
```

### Task 4: Custom-menu override respects children

**Files:**
- Modify: `src/Frontend/Header.php` — `menu_nav_items()`

- [ ] **Step 1: Include child items from Appearance→Menus hierarchy**

Replace the loop body so child menu items attach to their parent key:

```php
		$out = [];
		foreach ( $menu_items as $item ) {
			$entry = [
				'label'  => $item->title,
				'url'    => $item->url,
				'target' => '_blank' === $item->target ? '_blank' : '',
				'icon'   => '', // Admin-assigned custom menus carry no icon metadata.
			];
			if ( (int) $item->menu_item_parent === 0 ) {
				$out[ 'item-' . (int) $item->ID ] = $entry;
			} else {
				$parent_key = 'item-' . (int) $item->menu_item_parent;
				if ( isset( $out[ $parent_key ] ) ) {
					$out[ $parent_key ]['children'][] = $entry; // target kept: code-defined children honor it too.
				}
			}
		}
		return $out;
```

(The desktop loop already guards with `! empty( $item['children'] )`, and the icon span prints nothing for empty icons.)

- [ ] **Step 2: Syntax check**

Run: `php -l src/Frontend/Header.php && php -l templates/components/header-nav.php`
Expected: `No syntax errors detected` ×2

- [ ] **Step 3: Commit**

```bash
git add src/Frontend/Header.php
git commit -m "Nav: custom ovr_primary menus may define dropdown children"
```

---

## Chunk 2: New Pages Registry & Village-Section Shortcut Page

### Task 5: Register six pages + contact-shortcode sync

**Files:**
- Modify: `src/Core/Pages.php`

- [ ] **Step 1: Bump version + add page entries**

Change `PAGES_VERSION` to `'8'`. Extend `$pages` in `create_pages()`:

```php
			'ovr_page_village_sections' => [ 'Browse by Village Section', '[ovr_village_sections]', 'village-sections' ],
			'ovr_page_renting_overview' => [ 'Renting in The Villages – An Overview', self::default_placeholder_content( 'renting-overview' ), 'renting-in-the-villages' ],
			'ovr_page_verified_owners'  => [ 'Verified Owners', self::default_placeholder_content( 'verified-owners' ), 'verified-owners' ],
			'ovr_page_owner_information' => [ 'Rental Owner Information', self::default_placeholder_content( 'owner-information' ), 'rental-owner-information' ],
			'ovr_page_user_agreement'   => [ 'OVR User Agreement', self::default_placeholder_content( 'user-agreement' ), 'user-agreement' ],
			'ovr_page_id_request'       => [ 'Online Villages ID Request', '[ovr_id_request]', 'villages-id-request' ],
```

- [ ] **Step 2: Placeholder content factory**

Add private method (About-page pattern):

```php
	/**
	 * Editable placeholder copy for the four static info pages. Each is
	 * rewritten by the admin in WP admin → Pages without touching code.
	 */
	private static function default_placeholder_content( string $key ): string {
		$intros = [
			'renting-overview'  => 'An overview of what renting in The Villages is like — neighborhoods, golf carts, town squares, and what to expect in an owner-direct rental.',
			'verified-owners'   => 'What the Verified Owner badge means, how owners are verified, and why it matters for renters.',
			'owner-information' => 'Everything rental owners need to know about advertising a home on Our Villages Rental.',
			'user-agreement'    => 'The terms governing use of the Our Villages Rental website.',
		];
		$text = $intros[ $key ] ?? '';
		return '<!-- wp:paragraph --><p>' . esc_html( $text ) . '</p><!-- /wp:paragraph -->'
			. "\n\n<!-- wp:paragraph --><p>Placeholder content — replace this page in WordPress admin → Pages.</p><!-- /wp:paragraph -->";
	}
```

- [ ] **Step 3: Idempotent contact-shortcode append**

Extend `maybe_sync_pages()` after `create_pages()`:

```php
		self::ensure_contact_shortcode();
```

and add:

```php
	/**
	 * Append [ovr_contact_form] to the existing Contact page once, preserving
	 * any admin edits made before this version.
	 */
	private static function ensure_contact_shortcode(): void {
		$page_id = absint( get_option( 'ovr_page_contact' ) );
		if ( ! $page_id || ! get_post_status( $page_id ) ) {
			return;
		}
		$post = get_post( $page_id );
		if ( ! $post || has_shortcode( (string) $post->post_content, 'ovr_contact_form' ) ) {
			return;
		}
		wp_update_post( [
			'ID'           => $page_id,
			'post_content' => (string) $post->post_content . "\n\n[ovr_contact_form]",
		] );
	}
```

- [ ] **Step 4: Verify pages appear**

Run: `php -l src/Core/Pages.php` (expect clean), then load any front-end page twice (version bump fires once) and confirm WP admin → Pages lists the six new slugs.

- [ ] **Step 5: Commit**

```bash
git add src/Core/Pages.php
git commit -m "Pages: six new nav-support pages + contact shortcode sync (v8)"
```

### Task 6: Village-section shortcut page

**Files:**
- Create: `src/Frontend/VillageSections.php`
- Create: `templates/pages/village-sections.php`
- Modify: `src/Shortcodes/ShortcodeManager.php`

- [ ] **Step 1: Renderer class** (`src/Frontend/VillageSections.php`)

```php
<?php
/**
 * Browse-by-Village-Section shortcut page.
 *
 * Mirrors the search-results chip strip exactly (same terms, same images)
 * but as large 2-across cards: "All Areas" first, then one card per
 * ovr_village term linking to a single-section filtered search.
 *
 * @package OVR\Frontend
 */

namespace OVR\Frontend;

use OVR\Core\Pages;
use OVR\Core\TemplateLoader;
use OVR\Search\SearchFilters;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VillageSections {

	public function init(): void {}

	/**
	 * @return array{all:array{name:string,image:string,url:string}, sections:array<int, array{name:string,image:string,url:string,count:int}>}
	 */
	public static function sections_data(): array {
		$search = Pages::get_page_url( 'ovr_page_search' );

		// "All Areas" tile — stone-wall banner fallback wins over the generic
		// ovr-placeholder.jpg (get_village_image never returns ''), but a real
		// assigned term image still takes precedence.
		$all_img = OVR_PLUGIN_URL . 'assets/images/the-villages-banner.svg';
		$all_term = get_term_by( 'slug', 'the-villages', 'ovr_village' );
		if ( $all_term && ! is_wp_error( $all_term ) ) {
			$img = SearchFilters::get_village_image( $all_term );
			if ( '' !== $img && $img !== OVR_PLUGIN_URL . 'assets/images/ovr-placeholder.jpg' ) {
				$all_img = $img;
			}
		}

		$sections = [];
		foreach ( SearchFilters::get_villages() as $term ) {
			$link = add_query_arg( [ 'village_section' => [ $term->slug ] ], $search );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			$sections[] = [
				'name'  => $term->name,
				'image' => SearchFilters::get_village_image( $term ),
				'url'   => $link,
				'count' => (int) $term->count,
			];
		}

		return [
			'all'       => [ 'name' => __( 'All Areas', 'ovr-core' ), 'image' => $all_img, 'url' => $search ],
			'sections'  => $sections,
		];
	}

	public static function render(): string {
		return TemplateLoader::get_rendered( 'pages/village-sections.php', self::sections_data() );
	}
}
```

- [ ] **Step 2: Template** (`templates/pages/village-sections.php`)

```php
<?php
/**
 * Browse by Village Section — large 2-across shortcut grid.
 *
 * TemplateLoader::get_rendered() extract()s args into top-level variables
 * (same contract as templates/pages/villages.php receiving $groups).
 *
 * @var array{name:string,image:string,url:string} $all
 * @var array<int, array{name:string,image:string,url:string,count:int}> $sections
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$all      = $all ?? [];
$sections = $sections ?? [];
?>
<div class="ovr-wrap ovr-vsec">
	<header class="ovr-vsec-head">
		<h1 class="ovr-vsec-title"><?php esc_html_e( 'Browse by Village Section', 'ovr-core' ); ?></h1>
		<p class="ovr-vsec-lede"><?php esc_html_e( 'Pick an area of The Villages to see every available home there.', 'ovr-core' ); ?></p>
	</header>
	<div class="ovr-vsec-grid">
		<a href="<?php echo esc_url( $all['url'] ); ?>" class="ovr-vsec-card ovr-vsec-card--all">
			<span class="ovr-vsec-photo">
				<img src="<?php echo esc_url( $all['image'] ); ?>" alt="<?php echo esc_attr( $all['name'] ); ?>">
			</span>
			<span class="ovr-vsec-body">
				<span class="ovr-vsec-name"><?php echo esc_html( $all['name'] ); ?></span>
				<span class="ovr-vsec-meta"><?php esc_html_e( 'Every listing, every section', 'ovr-core' ); ?></span>
			</span>
			<span class="ovr-vsec-arrow material-symbols-outlined" aria-hidden="true">arrow_forward</span>
		</a>
		<?php foreach ( $sections as $s ) : ?>
			<a href="<?php echo esc_url( $s['url'] ); ?>" class="ovr-vsec-card">
				<span class="ovr-vsec-photo">
					<img src="<?php echo esc_url( $s['image'] ); ?>" alt="<?php echo esc_attr( $s['name'] ); ?>" loading="lazy">
					<span class="ovr-vsec-badge">
						<?php
						printf(
							esc_html( _n( '%s home', '%s homes', $s['count'], 'ovr-core' ) ),
							esc_html( number_format_i18n( $s['count'] ) )
						);
						?>
					</span>
				</span>
				<span class="ovr-vsec-body">
					<span class="ovr-vsec-name"><?php echo esc_html( $s['name'] ); ?></span>
				</span>
				<span class="ovr-vsec-arrow material-symbols-outlined" aria-hidden="true">arrow_forward</span>
			</a>
		<?php endforeach; ?>
	</div>
</div>
<style>
.ovr-vsec-head{text-align:center;margin:32px 0 24px}
.ovr-vsec-title{font-size:34px;color:var(--ovr-primary,#000961);margin:0 0 8px}
.ovr-vsec-lede{color:#5F6B7A;font-size:17px;margin:0}
.ovr-vsec-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:20px;max-width:1080px;margin:0 auto 48px;padding:0 16px}
@media (max-width:720px){.ovr-vsec-grid{grid-template-columns:1fr}}
.ovr-vsec-card{position:relative;display:block;background:#fff;border:1px solid #DBDBDB;border-radius:10px;overflow:hidden;text-decoration:none;transition:box-shadow .15s ease}
.ovr-vsec-card:hover{box-shadow:0 4px 14px rgba(0,9,97,.12)}
.ovr-vsec-photo{display:block;position:relative;height:220px;background:#eef1f5}
.ovr-vsec-photo img{width:100%;height:100%;object-fit:cover;display:block}
.ovr-vsec-badge{position:absolute;top:12px;right:12px;background:rgba(255,255,255,.92);border-radius:999px;padding:4px 12px;font-size:13px;font-weight:600;color:#000961}
.ovr-vsec-body{display:flex;flex-direction:column;gap:2px;padding:14px 16px}
.ovr-vsec-name{font-size:19px;font-weight:700;color:#000961}
.ovr-vsec-meta{font-size:14px;color:#5F6B7A}
.ovr-vsec-arrow{position:absolute;bottom:16px;right:16px;color:#00A2E8}
</style>
```

- [ ] **Step 3: Shortcode registration** — in `ShortcodeManager::register_shortcodes()` under "Page shortcodes":

```php
		add_shortcode( 'ovr_village_sections', [ $this, 'shortcode_village_sections' ] );
```

and the method beside `shortcode_villages_archive()`:

```php
	public function shortcode_village_sections(): string {
		return \OVR\Frontend\VillageSections::render();
	}
```

- [ ] **Step 4: Verify + commit**

Visit `/village-sections/`: All Areas card + one card per section, 2-across; clicking any card lands on `/search?village_section[]=…` pre-filtered. Match `Pages.php`'s 4-space indentation when merging Task 5 snippets.

```bash
php -l src/Frontend/VillageSections.php && php -l templates/pages/village-sections.php && php -l src/Shortcodes/ShortcodeManager.php
git add src/Frontend/VillageSections.php templates/pages/village-sections.php src/Shortcodes/ShortcodeManager.php
git commit -m "Village Sections shortcut page: 2-across grid linking to section-filtered search"
```

---

## Chunk 3: Contact OVR Form

### Task 7: Contact form renderer, AJAX handler, JS

**Files:**
- Create: `src/Frontend/ContactForm.php`
- Create: `templates/pages/contact-form.php`
- Create: `assets/js/ovr-contact.js`
- Modify: `src/Shortcodes/ShortcodeManager.php`
- Modify: `src/Ajax/AjaxHandler.php`
- Modify: `src/Core/Assets.php`

- [ ] **Step 1: Renderer + AJAX class** (`src/Frontend/ContactForm.php`)

```php
<?php
/**
 * Contact OVR form: renders the form and handles its AJAX submission.
 *
 * Anti-spam (new behavior, none existed previously): nonce + honeypot +
 * per-IP transient throttle (max 5 submissions/hour). Delivery goes through
 * the existing Mailer 'contact_form' admin template, whose recipient
 * resolution already prefers Settings > support_email.
 *
 * @package OVR\Frontend
 */

namespace OVR\Frontend;

use OVR\Core\TemplateLoader;
use OVR\Email\Mailer;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ContactForm {

	public function init(): void {}

	public static function render(): string {
		return TemplateLoader::get_rendered( 'pages/contact-form.php', [] );
	}

	/**
	 * AJAX: wp_ajax_ovr_contact / nopriv.
	 */
	public static function ajax_submit(): void {
		// $die = false so failures return distinct JSON errors (spec §4.5).
		if ( ! check_ajax_referer( 'ovr_contact', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed. Please reload the page and try again.', 'ovr-core' ) ], 403 );
		}

		// Honeypot — real users never see/fill this field. Fake success so
		// bots learn nothing (mirrors the inquiry-handler pattern).
		if ( ! empty( $_POST['website'] ) ) {
			wp_send_json_success( [ 'message' => __( 'Thanks! Your message has been sent.', 'ovr-core' ) ] );
		}

		// Per-IP throttle: 5 per rolling hour.
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key  = 'ovr_contact_rl_' . md5( $ip );
		$hits = (int) get_transient( $key );
		if ( $hits >= 5 ) {
			wp_send_json_error( [ 'message' => __( 'Too many messages sent. Please try again later.', 'ovr-core' ) ], 429 );
		}
		set_transient( $key, $hits + 1, HOUR_IN_SECONDS );

		$name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$phone   = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$subject = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
		$message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );

		if ( '' === $name || ! is_email( $email ) || '' === $message ) {
			wp_send_json_error( [ 'message' => __( 'Please provide your name, a valid email, and a message.', 'ovr-core' ) ], 400 );
		}

		// Phone/Subject fold into the body (template defines only three vars).
		$body = '';
		if ( '' !== $subject ) {
			$body .= 'Subject: ' . $subject . "\n";
		}
		if ( '' !== $phone ) {
			$body .= 'Phone: ' . $phone . "\n";
		}
		$body .= "\n" . $message;

		$sent = Mailer::send( 'contact_form', [
			'sender_name'     => $name,
			'sender_email'    => $email,
			'contact_message' => $body,
		], [] );

		if ( ! $sent ) {
			wp_send_json_error( [ 'message' => __( 'Your message could not be sent. Please email us directly.', 'ovr-core' ) ], 500 );
		}
		wp_send_json_success( [ 'message' => __( 'Thanks! Your message has been sent.', 'ovr-core' ) ] );
	}
}
```

- [ ] **Step 2: Template** (`templates/pages/contact-form.php`)

```php
<?php
/**
 * Contact OVR form.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$n = wp_create_nonce( 'ovr_contact' );
?>
<div class="ovr-wrap ovr-cform" data-ovr-contact data-nonce="<?php echo esc_attr( $n ); ?>">
	<h1><?php esc_html_e( 'Contact OVR', 'ovr-core' ); ?></h1>
	<p class="ovr-cform-lede"><?php esc_html_e( 'Questions about a listing, your account, or advertising? Send us a note.', 'ovr-core' ); ?></p>
	<form class="ovr-cform-form" novalidate>
		<p class="ovr-cform-hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>
		<label><?php esc_html_e( 'Name *', 'ovr-core' ); ?><input type="text" name="name" required maxlength="100"></label>
		<label><?php esc_html_e( 'Email *', 'ovr-core' ); ?><input type="email" name="email" required maxlength="150"></label>
		<label><?php esc_html_e( 'Phone', 'ovr-core' ); ?><input type="tel" name="phone" maxlength="30"></label>
		<label><?php esc_html_e( 'Subject', 'ovr-core' ); ?><input type="text" name="subject" maxlength="150"></label>
		<label><?php esc_html_e( 'Message *', 'ovr-core' ); ?><textarea name="message" rows="7" required maxlength="5000"></textarea></label>
		<button type="submit" class="ovr-btn ovr-btn-primary"><?php esc_html_e( 'Send Message', 'ovr-core' ); ?></button>
		<p class="ovr-cform-status" role="status" aria-live="polite"></p>
	</form>
</div>
<style>
.ovr-cform{max-width:560px;margin:32px auto 56px;padding:0 16px}
.ovr-cform-hp{position:absolute;left:-9999px}
.ovr-cform-form label{display:block;font-weight:600;font-size:15px;margin-bottom:14px}
.ovr-cform-form input,.ovr-cform-form textarea{display:block;width:100%;margin-top:4px;padding:11px 12px;font-size:16px;border:1px solid #DBDBDB;border-radius:6px}
.ovr-cform-status{min-height:22px;font-size:15px}
.ovr-cform-status.is-error{color:#B3261E}.ovr-cform-status.is-ok{color:#2E7D32}
</style>
```

- [ ] **Step 3: JS** (`assets/js/ovr-contact.js`)

```js
(function () {
	var root = document.querySelector('[data-ovr-contact]');
	if (!root) { return; }
	var form = root.querySelector('form');
	var status = root.querySelector('.ovr-cform-status');
	var ajaxUrl = window.ovrData && window.ovrData.ajaxUrl; // Assets.php localizes 'ajaxUrl' (camelCase) under ovrData.
	if (!ajaxUrl) { return; }

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		status.textContent = 'Sending…';
		status.className = 'ovr-cform-status';

		var fd = new FormData(form);
		fd.append('action', 'ovr_contact');
		fd.append('nonce', root.getAttribute('data-nonce'));

		fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
			.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
			.then(function (res) {
				var msg = (res.j && res.j.data && res.j.data.message) || 'Something went wrong.';
				status.textContent = msg;
				status.className = 'ovr-cform-status ' + (res.ok && res.j.success ? 'is-ok' : 'is-error');
				if (res.ok && res.j.success) { form.reset(); }
			})
			.catch(function () {
				status.textContent = 'Network error — please try again.';
				status.className = 'ovr-cform-status is-error';
			});
	});
})();
```

Confirmed: `Assets.php` line ~152 localizes `ovrData` with `'ajaxUrl'` (camelCase) — the JS above already matches it, no adaptation needed.

- [ ] **Step 4: Wire registrations**

`AjaxHandler` — in `init(): void` (the class has no constructor), alongside the other `wp_ajax` pairs:

```php
		add_action( 'wp_ajax_ovr_contact', [ \OVR\Frontend\ContactForm::class, 'ajax_submit' ] );
		add_action( 'wp_ajax_nopriv_ovr_contact', [ \OVR\Frontend\ContactForm::class, 'ajax_submit' ] );
```

`ShortcodeManager::register_shortcodes()`:

```php
		add_shortcode( 'ovr_contact_form', [ $this, 'shortcode_contact_form' ] );
```
method:
```php
	public function shortcode_contact_form(): string {
		return \OVR\Frontend\ContactForm::render();
	}
```

`Assets::enqueue_public_scripts()` (conditional, auth-style):

```php
		// Contact form page (conditional).
		if ( is_page( (int) get_option( 'ovr_page_contact' ) ) ) {
			wp_enqueue_script(
				'ovr-contact',
				OVR_PLUGIN_URL . 'assets/js/ovr-contact.js',
				[ 'ovr-public' ],
				OVR_VERSION,
				true
			);
		}
```

- [ ] **Step 5: Verify + commit**

Visit Contact page logged-out: send valid message → success row; check the inbox at Settings→support_email address arrives with Subject/Phone prefixes; submit 6× quickly → throttle message; empty required fields → validation error; tamper the nonce → 403 "Security check failed" (throttle increments before validation by design — invalid submissions consume quota).

```bash
php -l src/Frontend/ContactForm.php && php -l templates/pages/contact-form.php && node --check assets/js/ovr-contact.js
git add src/Frontend/ContactForm.php templates/pages/contact-form.php assets/js/ovr-contact.js src/Shortcodes/ShortcodeManager.php src/Ajax/AjaxHandler.php src/Core/Assets.php
git commit -m "Contact OVR: AJAX form -> support_email via contact_form template, nonce+honeypot+throttle"
```

---

## Chunk 4: Newest-Listings Setting

### Task 8: Settings field

**Files:**
- Modify: `src/Admin/Settings.php`

- [ ] **Step 1: Sanitize** — beside `listings_per_page` (line ~74):

```php
		if ( isset( $input['newest_listings_count'] ) ) $clean['newest_listings_count'] = max( 1, (int) $input['newest_listings_count'] );
```

- [ ] **Step 2: Field markup** — directly after the *Listings Per Page* input block (~line 624), same table-row pattern:

```php
				<tr>
					<th scope="row"><label for="ovr-newest-count"><?php esc_html_e( 'Newest Listings Count', 'ovr-core' ); ?></label></th>
					<td>
						<input id="ovr-newest-count" name="<?php echo esc_attr( self::OPTION ); ?>[newest_listings_count]" type="number" min="1"
						       value="<?php echo esc_attr( (string) ( $s['newest_listings_count'] ?? 12 ) ); ?>" class="small-text">
						<p class="description"><?php esc_html_e( 'How many homes the “Newest Listings” menu shortcut shows (defaults to 12).', 'ovr-core' ); ?></p>
					</td>
				</tr>
```

(Mirror the exact surrounding markup of the existing field — table structure may differ; keep visual parity.)

- [ ] **Step 3: Verify + commit**

Set value to 5 in admin → Explore Rentals ▾ → Newest Listings URL contains `per_page=5` and results page shows max 5 homes.

```bash
php -l src/Admin/Settings.php
git add src/Admin/Settings.php
git commit -m "Settings: newest_listings_count drives the Newest Listings menu shortcut"
```

---

## Chunk 5: Online Villages ID Request (fillable PDF)

### Task 9: Vendor pdf-lib

**Files:**
- Create: `assets/js/vendor/pdf-lib.min.js`

- [ ] **Step 1: Download pinned release**

```bash
mkdir -p assets/js/vendor && curl -fsSL -o assets/js/vendor/pdf-lib.min.js https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js && ls -la assets/js/vendor/
```
Expected: file ≈ 600 KB. Confirm header contains `pdf-lib`.

- [ ] **Step 2: Commit vendor asset**

```bash
git add assets/js/vendor/pdf-lib.min.js
git commit -m "Vendor: pdf-lib 1.17.1 for client-side ID-request PDF generation"
```

### Task 10: ID-request page (schema, form, PDF builder)

**Files:**
- Create: `src/Frontend/IdRequest.php`
- Create: `templates/pages/id-request.php`
- Create: `assets/js/ovr-id-request.js`
- Modify: `src/Shortcodes/ShortcodeManager.php`
- Modify: `src/Core/Assets.php`
- Modify: `src/Admin/Settings.php`

- [ ] **Step 1: Renderer + field schema** (`src/Frontend/IdRequest.php`)

```php
<?php
/**
 * Online Villages ID Request.
 *
 * Senior-readable web form mirroring the Lifestyle ID request. Submission
 * stays entirely client-side: JS fills the admin-supplied AcroForm template
 * (Settings > id_form_template) or composes a built-in printable sheet.
 * No PII is stored server-side.
 *
 * @package OVR\Frontend
 */

namespace OVR\Frontend;

use OVR\Core\TemplateLoader;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class IdRequest {

	public function init(): void {}

	/**
	 * Single source of truth for the form. `pdf_field` maps each input to
	 * the LifestyleIDForm2025.pdf AcroForm field name; correct mappings are
	 * one-line edits once the original PDF is compared against output.
	 *
	 * @return array<int, array{section:string, fields:array<int, array{name:string,label:string,type:string,required?:bool,options?:array<int,string>,pdf_field:string}>}>
	 */
	public static function schema(): array {
		return [
			[
				'section' => __( 'Property Owner Information', 'ovr-core' ),
				'fields'  => [
					[ 'name' => 'owner_name',  'label' => __( 'Owner Name', 'ovr-core' ),  'type' => 'text', 'required' => true, 'pdf_field' => 'OwnerName' ],
					[ 'name' => 'owner_phone', 'label' => __( 'Owner Phone', 'ovr-core' ), 'type' => 'tel',  'required' => true, 'pdf_field' => 'OwnerPhone' ],
					[ 'name' => 'owner_email', 'label' => __( 'Owner Email', 'ovr-core' ), 'type' => 'email', 'required' => false, 'pdf_field' => 'OwnerEmail' ],
					[ 'name' => 'property_address', 'label' => __( 'Rental Property Address', 'ovr-core' ), 'type' => 'text', 'required' => true, 'pdf_field' => 'PropertyAddress' ],
				],
			],
			[
				'section' => __( 'Renter / Guest Requesting ID', 'ovr-core' ),
				'fields'  => [
					[ 'name' => 'guest_name',  'label' => __( 'Full Name', 'ovr-core' ), 'type' => 'text', 'required' => true, 'pdf_field' => 'GuestName' ],
					[ 'name' => 'guest_dob',   'label' => __( 'Date of Birth', 'ovr-core' ), 'type' => 'date', 'required' => false, 'pdf_field' => 'GuestDOB' ],
					[ 'name' => 'guest_phone', 'label' => __( 'Phone', 'ovr-core' ), 'type' => 'tel', 'required' => true, 'pdf_field' => 'GuestPhone' ],
					[ 'name' => 'guest_email', 'label' => __( 'Email', 'ovr-core' ), 'type' => 'email', 'required' => false, 'pdf_field' => 'GuestEmail' ],
				],
			],
			[
				'section' => __( 'Rental Term', 'ovr-core' ),
				'fields'  => [
					[ 'name' => 'lease_start', 'label' => __( 'Lease Start Date', 'ovr-core' ), 'type' => 'date', 'required' => true, 'pdf_field' => 'LeaseStart' ],
					[ 'name' => 'lease_end',   'label' => __( 'Lease End Date', 'ovr-core' ),   'type' => 'date', 'required' => true, 'pdf_field' => 'LeaseEnd' ],
					[ 'name' => 'ids_requested', 'label' => __( 'Number of IDs Requested', 'ovr-core' ), 'type' => 'number', 'required' => true, 'pdf_field' => 'IDsRequested' ],
				],
			],
		];
	}

	/**
	 * Template URL passed to JS ('' = built-in mode).
	 */
	public static function template_url(): string {
		$s = (array) get_option( 'ovr_settings', [] );
		$url = trim( (string) ( $s['id_form_template'] ?? '' ) );
		if ( '' !== $url && ! preg_match( '/\.pdf(\?|$)/i', $url ) ) {
			return ''; // Non-PDF selection degrades to built-in mode.
		}
		return $url;
	}

	public static function render(): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to request a Villages Lifestyle ID.', 'ovr-core' ) . '</p>';
		}
		return TemplateLoader::get_rendered( 'pages/id-request.php', [
			'schema'   => self::schema(),
			'template' => self::template_url(),
		] );
	}
}
```

- [ ] **Step 2: Template** (`templates/pages/id-request.php`) — loops `$schema` sections/fields into labeled inputs (labels above inputs, ≥16px, per DESIGN.md §5/§13), a Submit button, and a Print button; embeds config:

```php
<script>window.OVR_ID_FORM={templateUrl:<?php echo '' !== $template ? wp_json_encode( esc_url_raw( $template ) ) : "''"; ?>};</script>
```

Include a short scoped `<style>` matching the contact-form styles — copy/adapt the `.ovr-cform-*` rules from `templates/pages/contact-form.php` into this page's own scoped block (they are not site-wide CSS). Match `Settings.php`'s 4-space indentation when merging Task 8 snippets.

- [ ] **Step 3: JS builder** (`assets/js/ovr-id-request.js`)

Behavior contract:
1. Collect values by `name` into an object; validate required fields, mark errors red.
2. Fetch `window.OVR_ID_FORM.templateUrl` when non-empty → `PDFDocument.load(bytes)` → `doc.getForm()` → for each schema field, match its exact `pdf_field` name via `form.getTextField(pdf_field)` inside try/catch; set text when found, silently skip when the lookup throws (field absent from template) → `doc.save()` → download `villages-id-request.pdf`.
3. Fallback mode (no template): `PDFDocument.create()` letter-size page, draw title "Villages Lifestyle ID Request", today's date, then each field as bold label + value lines, wrap long text; save/download.
4. Print button: open the generated blob URL in a new tab (`window.open(blobUrl)`).
5. Any error surfaces an inline alert box; console retains stack.

- [ ] **Step 4: Registrations**

Shortcode `[ovr_id_request]` → `IdRequest::render()` (same pattern as Task 6 Step 3). Conditional enqueue in `Assets.php`:

```php
		if ( is_page( (int) get_option( 'ovr_page_id_request' ) ) ) {
			wp_enqueue_script( 'ovr-pdf-lib', OVR_PLUGIN_URL . 'assets/js/vendor/pdf-lib.min.js', [], '1.17.1', true );
			wp_enqueue_script( 'ovr-id-request', OVR_PLUGIN_URL . 'assets/js/ovr-id-request.js', [ 'ovr-pdf-lib' ], OVR_VERSION, true );
		}
```

- [ ] **Step 5: Settings media picker** — `sanitize()` gains:

```php
		if ( isset( $input['id_form_template'] ) ) $clean['id_form_template'] = esc_url_raw( $input['id_form_template'] );
```

Add a Settings row (near the branding/logo_url field): text input showing the stored URL + "Choose PDF" button following the `wp.media` pattern in `src/Admin/HeroSlidesAdmin.php:199` (`wp_enqueue_media()` is already enqueued on the settings screen by `Settings::enqueue_media()` — no extra call needed), accepting `.pdf`. When saved value is non-empty but not `.pdf`, render a warning paragraph beneath the field: "Selected file is not a PDF — the ID form will use its built-in layout."

- [ ] **Step 6: Verify + commit**

Logged-in: open page → fill form → Download produces a PDF (built-in mode) that opens and prints; upload a PDF template in Settings (any AcroForm sample) → repeat and confirm fields populate where names match; Safari + Chrome both download. Visual check: form styling matches the contact page (labels above inputs, ≥16px).

```bash
php -l src/Frontend/IdRequest.php && php -l templates/pages/id-request.php && node --check assets/js/ovr-id-request.js
git add src/Frontend/IdRequest.php templates/pages/id-request.php assets/js/ovr-id-request.js src/Shortcodes/ShortcodeManager.php src/Core/Assets.php src/Admin/Settings.php
git commit -m "Villages ID Request: fillable web form -> filled/built-in PDF via pdf-lib"
```

---

## Final Verification (after all chunks)

- [ ] Full nav matrix QA per spec §6: logged-out / landlord / admin; every Explore Rentals link lands on correctly filtered results; shortcut page → section search; contact delivery; ID form both modes; mobile drawer parity.
- [ ] Update `docs/TESTING.md` with a new section covering these flows.
- [ ] Commit docs: `git add docs/TESTING.md && git commit -m "Docs: QA procedure for popup nav feature"`

## Discovered During Planning (out of scope — file tickets)

- Contact mail `Reply-To` is the site `from_email`, not the visitor's address (Mailer.php:74) — support must copy the sender email from the body. Follow-up candidate: pass visitor email as Reply-To for contact submissions only.
- Pre-existing bug: `AjaxHandler.php` line 40 re-registers `wp_ajax_nopriv_ovr_submit_inquiry` to `submit_inquiry_post`, silently overriding the handler registered at line 38.
