<?php
/**
 * Property Listings Management Screen template.
 *
 * @package OVR
 * @var array  $request       Parsed filter/sort/pagination parameters.
 * @var array  $listings      Array of WP_Post objects.
 * @var int    $total         Total matching listings.
 * @var int    $max_pages     Total pages.
 * @var array  $stats         Counts: total, active, inactive, featured, paid.
 * @var bool   $is_admin      Current user can manage_options.
 * @var array  $service_types Available paid service catalogue rows.
 */

use OVR\Admin\PropertyListScreen;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$request       = $request ?? [];
$listings      = $listings ?? [];
$total         = (int) ( $total ?? 0 );
$max_pages     = max( 1, (int) ( $max_pages ?? 1 ) );
$stats         = $stats ?? [];
$service_types = $service_types ?? [];

// Taxonomy terms for dropdowns.
$property_types = get_terms( [ 'taxonomy' => 'ovr_property_type', 'hide_empty' => false ] );
$villages       = get_terms( [ 'taxonomy' => 'ovr_village', 'hide_empty' => false ] );
$village_terms  = is_wp_error( $villages ) ? [] : $villages;
$pt_terms       = is_wp_error( $property_types ) ? [] : $property_types;

// Fetch active services for all displayed listings in one query.
$listing_ids = array_map( static fn( $p ) => $p->ID, $listings );
$services_map = [];
if ( $listing_ids ) {
    global $wpdb;
    $id_list = implode( ',', array_map( 'absint', $listing_ids ) );
    $rows = $wpdb->get_results(
        "SELECT ls.*, ps.name AS service_name, ps.badge, ps.slug AS service_slug
         FROM {$wpdb->prefix}ovr_listing_services ls
         LEFT JOIN {$wpdb->prefix}ovr_paid_services ps ON ls.service_id = ps.id
         WHERE ls.listing_id IN ({$id_list}) AND ps.deleted_at IS NULL
         ORDER BY ls.active DESC, ls.end_date ASC",
        ARRAY_A
    );
    foreach ( $rows as $r ) {
        $services_map[ (int) $r['listing_id'] ][] = $r;
    }
}

// Parameter helpers.
$q = static function ( string $key, string $default = '' ) use ( $request ): string {
    return isset( $request[ $key ] ) && '' !== $request[ $key ] ? $request[ $key ] : $default;
};
$checked = static function ( string $key, string $value ) use ( $request ): string {
    return ( isset( $request[ $key ] ) && (string) $request[ $key ] === $value ) ? 'selected' : '';
};
?>
<style>
.ovr-ld *{box-sizing:border-box}
.ovr-ld{--p:#004c4c;--pc:#006666;--opc:#93e1e0;--pfd:#86d4d3;--sec:#006c4a;--secc:#74f7be;--ter:#735c00;--terc:#cca72f;--err:#ba1a1a;--errc:#ffdad6;--bg:#f7faf9;--surf:#fff;--sclow:#f1f4f3;--sv:#3f4948;--outline:#6f7979;--ov:#bec9c8;--on:#181c1c;font-family:Inter,system-ui,-apple-system,sans-serif;color:var(--on)}
.ovr-pls-header{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px;margin:0 0 20px}
.ovr-pls-header h1{margin:0;font-size:24px;font-weight:700}
.ovr-pls-stats{display:flex;gap:14px;flex-wrap:wrap}
.ovr-pls-stat{font-size:13px;display:inline-flex;align-items:center;gap:5px;background:var(--sclow);padding:5px 12px;border-radius:9999px;font-weight:600;color:var(--sv)}
.ovr-pls-stat strong{color:var(--on)}
.ovr-pls-actions{display:flex;gap:8px;flex-wrap:wrap}
.ovr-pls-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:8px;font-family:inherit;font-size:13px;font-weight:600;border:1px solid var(--ov);background:var(--surf);color:var(--on);cursor:pointer;text-decoration:none;transition:background .15s}
.ovr-pls-btn:hover{background:var(--sclow)}
.ovr-pls-btn--primary{background:var(--p);color:#fff;border-color:var(--p)}
.ovr-pls-btn--primary:hover{background:#003838}
.ovr-pls-btn .material-symbols-outlined{font-size:18px}

/* Global filters toolbar */
.ovr-pls-global-filters{display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:16px;padding:14px 16px;background:var(--surf);border:1px solid var(--ov);border-radius:12px}
.ovr-pls-global-filters label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;color:var(--sv);display:flex;flex-direction:column;gap:3px}
.ovr-pls-global-filters select,.ovr-pls-global-filters input[type="date"]{height:34px;border:1px solid var(--ov);border-radius:7px;padding:0 10px;font-family:inherit;font-size:13px;background:var(--surf);color:var(--on);min-width:120px}
.ovr-pls-global-filters select{cursor:pointer}

/* Table */
.ovr-pls-table-wrap{background:var(--surf);border:1px solid var(--ov);border-radius:12px;overflow-x:auto;overflow-y:visible}
.ovr-pls-table{width:100%;border-collapse:collapse;font-size:13px;table-layout:auto}
.ovr-pls-table th{text-align:left;padding:0;background:var(--sclow);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;color:var(--sv);white-space:nowrap;border-bottom:1px solid var(--ov);vertical-align:bottom}
.ovr-pls-table th .ovr-pls-th-inner{padding:8px 10px;display:flex;flex-direction:column;gap:4px;min-height:62px}
.ovr-pls-table th .ovr-pls-th-label{display:flex;align-items:center;gap:4px}
.ovr-pls-table th .ovr-pls-th-label a{color:var(--sv);text-decoration:none;display:inline-flex;align-items:center;gap:2px}
.ovr-pls-table th .ovr-pls-th-label a:hover{color:var(--on)}
.ovr-pls-sort-icon{font-size:14px;vertical-align:middle}
.ovr-pls-th-filter input,.ovr-pls-th-filter select{width:100%;height:30px;border:1px solid var(--ov);border-radius:6px;padding:0 8px;font-family:inherit;font-size:12px;background:var(--surf);color:var(--on);box-sizing:border-box}
.ovr-pls-th-filter select{cursor:pointer;padding-right:20px}
.ovr-pls-th-filter input:focus,.ovr-pls-th-filter select:focus{border-color:var(--p);outline:none;box-shadow:0 0 0 2px var(--opc)}
.ovr-pls-th-check{width:40px}
.ovr-pls-th-id{width:90px}
.ovr-pls-th-status{width:72px}
.ovr-pls-th-name{min-width:160px}
.ovr-pls-th-price{width:90px}
.ovr-pls-th-type{width:100px}
.ovr-pls-th-addr{min-width:180px}
.ovr-pls-th-village{width:140px}
.ovr-pls-th-email{min-width:160px}
.ovr-pls-th-date{width:130px}
.ovr-pls-th-services{min-width:140px}
.ovr-pls-th-views{width:70px}

.ovr-pls-table td{padding:10px;border-bottom:1px solid var(--ov);vertical-align:middle;line-height:1.4}
.ovr-pls-table tr:last-child td{border-bottom:none}
.ovr-pls-table tr:hover td{background:rgba(0,76,76,.03)}

.ovr-pls-pid{font-weight:700;font-size:14px;color:var(--p);text-decoration:none;font-variant-numeric:tabular-nums}
.ovr-pls-pid:hover{text-decoration:underline}
.ovr-pls-pid-copy{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border:none;border-radius:4px;background:transparent;color:var(--sv);cursor:pointer;font-size:14px;vertical-align:middle;margin-left:2px}
.ovr-pls-pid-copy:hover{background:var(--sclow);color:var(--p)}

.ovr-pls-icon{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;font-size:18px}
.ovr-pls-visible{color:#00714e;background:#d6f3e6}
.ovr-pls-hidden{color:#93000a;background:#fde2e2}
.ovr-pls-active{color:#00714e;background:#d6f3e6}
.ovr-pls-inactive{color:#93000a;background:#fde2e2}

.ovr-pls-name{font-weight:600;color:var(--p);text-decoration:none}
.ovr-pls-name:hover{text-decoration:underline}
.ovr-pls-addr{color:var(--sv);max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ovr-pls-email{color:var(--p);text-decoration:none}
.ovr-pls-email:hover{text-decoration:underline}

/* Paid Service badges in table */
.ovr-pls-service-badges{display:flex;gap:4px;flex-wrap:wrap;align-items:center}
.ovr-pls-badge{display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:700;padding:2px 7px;border-radius:9999px;text-transform:uppercase;letter-spacing:.02em;line-height:1.3}
.ovr-pls-badge--featured{background:#fef3c7;color:#92400e;border:1px solid #fde68a}
.ovr-pls-badge--slider{background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe}
.ovr-pls-badge--priority{background:#ede9fe;color:#5b21b6;border:1px solid #ddd6fe}
.ovr-pls-badge--spotlight{background:#fce7f3;color:#9d174d;border:1px solid #fbcfe8}
.ovr-pls-badge--bump{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
.ovr-pls-badge--default{background:#e5e7eb;color:#374151;border:1px solid #d1d5db}
.ovr-pls-badge--inactive{opacity:.5}
.ovr-pls-service-add{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border:none;border-radius:6px;background:var(--p);color:#fff;cursor:pointer;font-size:16px;transition:background .15s}
.ovr-pls-service-add:hover{background:#003838}

/* Footer (pagination + bulk) */
.ovr-pls-footer{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-top:16px;padding:12px 0}
.ovr-pls-bulk{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.ovr-pls-bulk select{height:34px;border:1px solid var(--ov);border-radius:7px;padding:0 10px;font-family:inherit;font-size:13px;background:var(--surf);color:var(--on);cursor:pointer}
.ovr-pls-pagination{display:flex;align-items:center;gap:4px}
.ovr-pls-page{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;border:1px solid var(--ov);border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;color:var(--on);background:var(--surf)}
.ovr-pls-page:hover{background:var(--sclow)}
.ovr-pls-page.is-active{background:var(--p);color:#fff;border-color:var(--p)}
.ovr-pls-page .material-symbols-outlined{font-size:18px}
.ovr-pls-page[disabled]{opacity:.4;pointer-events:none}
.ovr-pls-info{font-size:13px;color:var(--sv);margin:0}

/* Modal */
.ovr-pls-overlay{position:fixed;inset:0;z-index:99999;background:rgba(10,14,24,.6);display:flex;align-items:center;justify-content:center;padding:20px}
.ovr-pls-modal{background:var(--surf);border-radius:14px;max-width:520px;width:100%;box-shadow:0 24px 60px rgba(0,0,0,.25);overflow:hidden;display:flex;flex-direction:column}
.ovr-pls-modal-head{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--ov)}
.ovr-pls-modal-head h3{margin:0;font-size:17px;font-weight:700}
.ovr-pls-modal-close{background:none;border:none;font-size:22px;cursor:pointer;color:var(--sv);padding:0;line-height:1}
.ovr-pls-modal-close:hover{color:var(--on)}
.ovr-pls-modal-body{padding:22px;display:flex;flex-direction:column;gap:16px}
.ovr-pls-modal-body label{font-size:13px;font-weight:600;display:flex;flex-direction:column;gap:4px}
.ovr-pls-modal-body select,.ovr-pls-modal-body input[type="date"],.ovr-pls-modal-body textarea{height:36px;border:1px solid var(--ov);border-radius:8px;padding:0 10px;font-family:inherit;font-size:13px;background:var(--surf);color:var(--on)}
.ovr-pls-modal-body textarea{height:auto;min-height:60px;padding:8px 10px;resize:vertical}
.ovr-pls-modal-body .ovr-pls-modal-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.ovr-pls-modal-footer{display:flex;justify-content:flex-end;gap:10px;padding:16px 22px;border-top:1px solid var(--ov)}
.ovr-pls-modal-err{font-size:13px;color:var(--err);margin:0}
.ovr-pls-modal-info{font-size:13px;color:var(--sv);margin:0;padding:10px;background:var(--sclow);border-radius:8px}

/* Toast */
.ovr-pls-toast{position:fixed;bottom:30px;left:50%;transform:translateX(-50%);z-index:100000;padding:12px 24px;border-radius:10px;font-size:14px;font-weight:600;box-shadow:0 8px 32px rgba(0,0,0,.15);opacity:0;transition:opacity .3s;pointer-events:none}
.ovr-pls-toast.is-success{background:#d6f3e6;color:#00714e;border:1px solid #a6e3c8;opacity:1}
.ovr-pls-toast.is-error{background:#fde2e2;color:#93000a;border:1px solid #f5b8b8;opacity:1}
.ovr-pls-toast.is-loading{background:#e8ecf0;color:#1c2430;border:1px solid #cdd2d8;opacity:1}

/* Checkbox column */
.ovr-pls-cb{width:16px;height:16px;accent-color:var(--p);cursor:pointer;margin:0}

/* Empty state */
.ovr-pls-empty{text-align:center;padding:60px 20px;color:var(--sv)}
.ovr-pls-empty .material-symbols-outlined{font-size:48px;margin-bottom:12px;display:block}
.ovr-pls-empty p{font-size:15px;margin:0}

/* Responsive */
@media (max-width:782px){
.ovr-pls-header{flex-direction:column;align-items:flex-start}
.ovr-pls-table th .ovr-pls-th-inner{min-height:auto;padding:6px 8px}
.ovr-pls-th-date,.ovr-pls-th-views,.ovr-pls-th-type,.ovr-pls-th-email{display:none}
.ovr-pls-table td:nth-child(11),.ovr-pls-table td:nth-child(13),.ovr-pls-table td:nth-child(6),.ovr-pls-table td:nth-child(10){display:none}
.ovr-pls-global-filters{padding:12px}
.ovr-pls-footer{flex-direction:column;align-items:stretch}
}
</style>

<?php $base_url = 'admin.php?page=' . PropertyListScreen::PAGE_SLUG; ?>

<div class="ovr-pls-header">
    <div>
        <h1><?php esc_html_e( 'All Properties', 'ovr-core' ); ?></h1>
        <div class="ovr-pls-stats">
            <span class="ovr-pls-stat"><strong><?php echo (int) ( $stats['total'] ?? 0 ); ?></strong> <?php esc_html_e( 'Total', 'ovr-core' ); ?></span>
            <span class="ovr-pls-stat"><strong><?php echo (int) ( $stats['active'] ?? 0 ); ?></strong> <?php esc_html_e( 'Active', 'ovr-core' ); ?></span>
            <span class="ovr-pls-stat"><strong><?php echo (int) ( $stats['inactive'] ?? 0 ); ?></strong> <?php esc_html_e( 'Inactive', 'ovr-core' ); ?></span>
            <span class="ovr-pls-stat"><strong><?php echo (int) ( $stats['featured'] ?? 0 ); ?></strong> <?php esc_html_e( 'Featured', 'ovr-core' ); ?></span>
            <span class="ovr-pls-stat"><strong><?php echo (int) ( $stats['paid'] ?? 0 ); ?></strong> <?php esc_html_e( 'With Paid Services', 'ovr-core' ); ?></span>
        </div>
    </div>
    <div class="ovr-pls-actions">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ovr-edit-listing' ) ); ?>" class="ovr-pls-btn ovr-pls-btn--primary">
            <span class="material-symbols-outlined">add</span><?php esc_html_e( 'Add New Listing', 'ovr-core' ); ?>
        </a>
    </div>
</div>

<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" id="ovr-pls-form">
<input type="hidden" name="page" value="<?php echo esc_attr( PropertyListScreen::PAGE_SLUG ); ?>">

<!-- Global Filters -->
<div class="ovr-pls-global-filters">
    <label>
        <?php esc_html_e( 'Subscription Plan', 'ovr-core' ); ?>
        <select name="sub" onchange="this.form.submit()">
            <option value=""><?php esc_html_e( 'Any plan', 'ovr-core' ); ?></option>
            <?php foreach ( (array) \OVR\Subscription\Plans::get_plans() as $slug => $plan ) : ?>
                <option value="<?php echo esc_attr( $slug ); ?>" <?php echo $checked( 'subscription', $slug ); ?>>
                    <?php echo esc_html( is_array( $plan ) ? ( $plan['name'] ?? $slug ) : $plan ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        <?php esc_html_e( 'Paid Service', 'ovr-core' ); ?>
        <select name="ps" onchange="this.form.submit()">
            <option value="0"><?php esc_html_e( 'Any service', 'ovr-core' ); ?></option>
            <?php foreach ( $service_types as $st ) : ?>
                <option value="<?php echo (int) $st['id']; ?>" <?php echo $checked( 'paid_service', (string) $st['id'] ); ?>>
                    <?php echo esc_html( $st['name'] ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        <?php esc_html_e( 'From', 'ovr-core' ); ?>
        <input type="date" name="df" value="<?php echo esc_attr( $q( 'date_from' ) ); ?>" onchange="this.form.submit()">
    </label>
    <label>
        <?php esc_html_e( 'To', 'ovr-core' ); ?>
        <input type="date" name="dt" value="<?php echo esc_attr( $q( 'date_to' ) ); ?>" onchange="this.form.submit()">
    </label>
    <label style="align-self:flex-end">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . PropertyListScreen::PAGE_SLUG . '&reset=1' ) ); ?>" class="ovr-pls-btn" style="padding:7px 14px">
            <span class="material-symbols-outlined">filter_alt_off</span><?php esc_html_e( 'Reset', 'ovr-core' ); ?>
        </a>
    </label>
</div>

<div class="ovr-pls-table-wrap">
<table class="ovr-pls-table" role="grid">
<thead>
<tr>
    <th class="ovr-pls-th-check"><div class="ovr-pls-th-inner"><input type="checkbox" class="ovr-pls-cb" id="ovr-pls-select-all" title="<?php esc_attr_e( 'Select all', 'ovr-core' ); ?>"></div></th>
    <th class="ovr-pls-th-id">
        <div class="ovr-pls-th-inner">
            <span class="ovr-pls-th-label">
                <a href="<?php echo esc_url( PropertyListScreen::sort_url( 'id', $request ) ); ?>"><?php esc_html_e( 'ID', 'ovr-core' ); ?><?php echo PropertyListScreen::sort_indicator( 'id', $request ); ?></a>
            </span>
            <div class="ovr-pls-th-filter">
                <input type="number" name="pid" value="<?php echo esc_attr( $q( 'pid' ) ); ?>" placeholder="<?php esc_attr_e( 'Exact ID', 'ovr-core' ); ?>" min="1" onchange="this.form.submit()">
            </div>
        </div>
    </th>
    <th class="ovr-pls-th-status">
        <div class="ovr-pls-th-inner">
            <span class="ovr-pls-th-label"><?php esc_html_e( 'Display', 'ovr-core' ); ?></span>
            <div class="ovr-pls-th-filter">
                <select name="ds" onchange="this.form.submit()">
                    <option value=""><?php esc_html_e( 'All', 'ovr-core' ); ?></option>
                    <option value="approved" <?php echo $checked( 'display_status', 'approved' ); ?>><?php esc_html_e( 'Visible', 'ovr-core' ); ?></option>
                    <option value="hidden" <?php echo $checked( 'display_status', 'hidden' ); ?>><?php esc_html_e( 'Hidden', 'ovr-core' ); ?></option>
                    <option value="suspended" <?php echo $checked( 'display_status', 'suspended' ); ?>><?php esc_html_e( 'Suspended', 'ovr-core' ); ?></option>
                    <option value="pending_review" <?php echo $checked( 'display_status', 'pending_review' ); ?>><?php esc_html_e( 'Pending Review', 'ovr-core' ); ?></option>
                </select>
            </div>
        </div>
    </th>
    <th class="ovr-pls-th-status">
        <div class="ovr-pls-th-inner">
            <span class="ovr-pls-th-label"><?php esc_html_e( 'Owner', 'ovr-core' ); ?></span>
            <div class="ovr-pls-th-filter">
                <select name="os" onchange="this.form.submit()">
                    <option value=""><?php esc_html_e( 'All', 'ovr-core' ); ?></option>
                    <option value="active" <?php echo $checked( 'owner_status', 'active' ); ?>><?php esc_html_e( 'Active', 'ovr-core' ); ?></option>
                    <option value="inactive" <?php echo $checked( 'owner_status', 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'ovr-core' ); ?></option>
                </select>
            </div>
        </div>
    </th>
    <th class="ovr-pls-th-name">
        <div class="ovr-pls-th-inner">
            <span class="ovr-pls-th-label">
                <a href="<?php echo esc_url( PropertyListScreen::sort_url( 'title', $request ) ); ?>"><?php esc_html_e( 'Name', 'ovr-core' ); ?><?php echo PropertyListScreen::sort_indicator( 'title', $request ); ?></a>
            </span>
            <div class="ovr-pls-th-filter">
                <input type="text" name="s" value="<?php echo esc_attr( $q( 'search' ) ); ?>" placeholder="<?php esc_attr_e( 'Search', 'ovr-core' ); ?>" onchange="this.form.submit()">
            </div>
        </div>
    </th>
    <th class="ovr-pls-th-price">
        <div class="ovr-pls-th-inner">
            <span class="ovr-pls-th-label">
                <a href="<?php echo esc_url( PropertyListScreen::sort_url( 'price', $request ) ); ?>"><?php esc_html_e( 'Price', 'ovr-core' ); ?><?php echo PropertyListScreen::sort_indicator( 'price', $request ); ?></a>
            </span>
        </div>
    </th>
    <th class="ovr-pls-th-type">
        <div class="ovr-pls-th-inner">
            <span class="ovr-pls-th-label"><?php esc_html_e( 'Type', 'ovr-core' ); ?></span>
            <div class="ovr-pls-th-filter">
                <select name="pt" onchange="this.form.submit()">
                    <option value=""><?php esc_html_e( 'All', 'ovr-core' ); ?></option>
                    <?php foreach ( $pt_terms as $t ) : ?>
                        <option value="<?php echo esc_attr( $t->slug ); ?>" <?php echo $checked( 'property_type', $t->slug ); ?>><?php echo esc_html( $t->name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </th>
    <th class="ovr-pls-th-addr">
        <div class="ovr-pls-th-inner">
            <span class="ovr-pls-th-label"><?php esc_html_e( 'Address', 'ovr-core' ); ?></span>
            <div class="ovr-pls-th-filter">
                <input type="text" name="s" value="<?php echo esc_attr( $q( 'search' ) ); ?>" placeholder="<?php esc_attr_e( 'Search address', 'ovr-core' ); ?>" onchange="this.form.submit()">
            </div>
        </div>
    </th>
    <th class="ovr-pls-th-village">
        <div class="ovr-pls-th-inner">
            <span class="ovr-pls-th-label">
                <a href="<?php echo esc_url( PropertyListScreen::sort_url( 'village', $request ) ); ?>"><?php esc_html_e( 'Village', 'ovr-core' ); ?><?php echo PropertyListScreen::sort_indicator( 'village', $request ); ?></a>
            </span>
            <div class="ovr-pls-th-filter">
                <select name="vl" onchange="this.form.submit()">
                    <option value=""><?php esc_html_e( 'All', 'ovr-core' ); ?></option>
                    <?php foreach ( $village_terms as $t ) : ?>
                        <option value="<?php echo esc_attr( $t->slug ); ?>" <?php echo $checked( 'village', $t->slug ); ?>><?php echo esc_html( $t->name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </th>
    <th class="ovr-pls-th-email">
        <div class="ovr-pls-th-inner">
            <span class="ovr-pls-th-label">
                <a href="<?php echo esc_url( PropertyListScreen::sort_url( 'email', $request ) ); ?>"><?php esc_html_e( 'Owner Email', 'ovr-core' ); ?><?php echo PropertyListScreen::sort_indicator( 'email', $request ); ?></a>
            </span>
            <div class="ovr-pls-th-filter">
                <input type="text" name="em" value="<?php echo esc_attr( $q( 'owner_email' ) ); ?>" placeholder="<?php esc_attr_e( 'Search email', 'ovr-core' ); ?>" onchange="this.form.submit()">
            </div>
        </div>
    </th>
    <th class="ovr-pls-th-date">
        <div class="ovr-pls-th-inner">
            <span class="ovr-pls-th-label">
                <a href="<?php echo esc_url( PropertyListScreen::sort_url( 'date', $request ) ); ?>"><?php esc_html_e( 'Updated', 'ovr-core' ); ?><?php echo PropertyListScreen::sort_indicator( 'date', $request ); ?></a>
            </span>
            <div class="ovr-pls-th-filter" style="display:flex;gap:4px">
                <input type="date" name="df" value="<?php echo esc_attr( $q( 'date_from' ) ); ?>" placeholder="<?php esc_attr_e( 'From', 'ovr-core' ); ?>" style="width:50%" onchange="this.form.submit()">
                <input type="date" name="dt" value="<?php echo esc_attr( $q( 'date_to' ) ); ?>" placeholder="<?php esc_attr_e( 'To', 'ovr-core' ); ?>" style="width:50%" onchange="this.form.submit()">
            </div>
        </div>
    </th>
    <?php if ( $is_admin ) : ?>
    <th class="ovr-pls-th-services">
        <div class="ovr-pls-th-inner">
            <span class="ovr-pls-th-label"><?php esc_html_e( 'Paid Services', 'ovr-core' ); ?></span>
        </div>
    </th>
    <?php endif; ?>
</tr>
</thead>
<tbody>
<?php if ( empty( $listings ) ) : ?>
<tr>
    <td colspan="<?php echo $is_admin ? 12 : 11; ?>">
        <div class="ovr-pls-empty">
            <span class="material-symbols-outlined">search_off</span>
            <p><?php esc_html_e( 'No properties match your filters.', 'ovr-core' ); ?></p>
        </div>
    </td>
</tr>
<?php else : ?>
    <?php foreach ( $listings as $post ) : setup_postdata( $post );
        $pid         = (int) $post->ID;
        $title       = get_the_title( $pid );
        $address     = (string) get_post_meta( $pid, '_ovr_address', true );
        $city        = (string) get_post_meta( $pid, '_ovr_city', true );
        $addr_disp   = $address ? $address . ( $city ? ', ' . $city : '' ) : ( $city ?: '—' );
        $price       = PropertyListScreen::format_price( get_post_meta( $pid, '_ovr_base_price', true ) );
        $admin_status = get_post_meta( $pid, '_ovr_admin_status', true ) ?: 'approved';
        $owner_status = get_post_meta( $pid, '_ovr_listing_status', true ) ?: 'active';
        $villages    = wp_get_object_terms( $pid, 'ovr_village', [ 'fields' => 'names' ] );
        $village     = ! is_wp_error( $villages ) && $villages ? $villages[0] : '—';
        $types       = wp_get_object_terms( $pid, 'ovr_property_type', [ 'fields' => 'names' ] );
        $type_label  = ! is_wp_error( $types ) && $types ? $types[0] : '—';
        $owner       = get_userdata( (int) $post->post_author );
        $owner_email = $owner ? $owner->user_email : '';
        $updated     = $post->post_modified;
        $services    = $services_map[ $pid ] ?? [];
        $edit_url    = admin_url( 'admin.php?page=ovr-edit-listing&post=' . $pid );
    ?>
    <tr data-listing-id="<?php echo (int) $pid; ?>">
        <td><input type="checkbox" class="ovr-pls-cb ovr-pls-listing-cb" value="<?php echo (int) $pid; ?>"></td>
        <td>
            <a href="<?php echo esc_url( $edit_url ); ?>" class="ovr-pls-pid">#<?php echo (int) $pid; ?></a>
            <button type="button" class="ovr-pls-pid-copy" data-copy="<?php echo (int) $pid; ?>" title="<?php esc_attr_e( 'Copy ID', 'ovr-core' ); ?>" aria-label="<?php esc_attr_e( 'Copy Property ID', 'ovr-core' ); ?>">
                <span class="material-symbols-outlined">content_copy</span>
            </button>
        </td>
        <td><?php echo PropertyListScreen::display_status_icon( $admin_status ); ?></td>
        <td><?php echo PropertyListScreen::owner_status_icon( $owner_status ); ?></td>
        <td><a href="<?php echo esc_url( $edit_url ); ?>" class="ovr-pls-name" title="<?php echo esc_attr( $title ); ?>"><?php echo esc_html( $title ?: '(' . __( 'no title', 'ovr-core' ) . ')' ); ?></a></td>
        <td><?php echo esc_html( $price ); ?></td>
        <td><?php echo esc_html( $type_label ); ?></td>
        <td><span class="ovr-pls-addr" title="<?php echo esc_attr( $addr_disp ); ?>"><?php echo esc_html( $addr_disp ); ?></span></td>
        <td><?php echo esc_html( $village ); ?></td>
        <td>
            <?php if ( $owner_email ) : ?>
                <a href="mailto:<?php echo esc_attr( $owner_email ); ?>" class="ovr-pls-email"><?php echo esc_html( $owner_email ); ?></a>
            <?php else : ?>
                <span style="color:var(--sv)">—</span>
            <?php endif; ?>
        </td>
        <td style="white-space:nowrap;font-size:12px;color:var(--sv)"><?php echo esc_html( mysql2date( get_option( 'date_format' ), $updated ) ); ?><br><?php echo esc_html( mysql2date( get_option( 'time_format' ), $updated ) ); ?></td>
        <?php if ( $is_admin ) : ?>
        <td>
            <div class="ovr-pls-service-badges">
                <?php if ( $services ) : ?>
                    <?php foreach ( $services as $svc ) : ?>
                        <?php
                        $badge_class = 'ovr-pls-badge--default';
                        $st = $svc['service_slug'] ?? '';
                        if ( false !== strpos( $st, 'featured' ) ) $badge_class = 'ovr-pls-badge--featured';
                        elseif ( false !== strpos( $st, 'slider' ) ) $badge_class = 'ovr-pls-badge--slider';
                        elseif ( false !== strpos( $st, 'priority' ) ) $badge_class = 'ovr-pls-badge--priority';
                        elseif ( false !== strpos( $st, 'spotlight' ) ) $badge_class = 'ovr-pls-badge--spotlight';
                        elseif ( false !== strpos( $st, 'bump' ) ) $badge_class = 'ovr-pls-badge--bump';
                        ?>
                        <span class="ovr-pls-badge <?php echo $badge_class; ?><?php echo empty( $svc['active'] ) ? ' ovr-pls-badge--inactive' : ''; ?>">
                            <?php echo esc_html( $svc['badge'] ?: $svc['service_name'] ?: __( 'Service', 'ovr-core' ) ); ?>
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
                <button type="button" class="ovr-pls-service-add" data-listing-id="<?php echo (int) $pid; ?>" title="<?php esc_attr_e( 'Add paid service', 'ovr-core' ); ?>" aria-label="<?php esc_attr_e( 'Add paid service', 'ovr-core' ); ?>">
                    <span class="material-symbols-outlined" style="font-size:16px">add</span>
                </button>
            </div>
        </td>
        <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    <?php wp_reset_postdata(); ?>
<?php endif; ?>
</tbody>
</table>
</div>

<div class="ovr-pls-footer">
    <?php if ( $is_admin ) : ?>
    <div class="ovr-pls-bulk">
        <select id="ovr-pls-bulk-action">
            <option value=""><?php esc_html_e( 'Bulk Actions', 'ovr-core' ); ?></option>
            <option value="activate"><?php esc_html_e( 'Activate', 'ovr-core' ); ?></option>
            <option value="deactivate"><?php esc_html_e( 'Deactivate', 'ovr-core' ); ?></option>
            <option value="approve"><?php esc_html_e( 'Approve (make visible)', 'ovr-core' ); ?></option>
            <option value="hide"><?php esc_html_e( 'Hide', 'ovr-core' ); ?></option>
            <option value="delete"><?php esc_html_e( 'Move to Trash', 'ovr-core' ); ?></option>
        </select>
        <button type="button" class="ovr-pls-btn" id="ovr-pls-bulk-apply"><?php esc_html_e( 'Apply', 'ovr-core' ); ?></button>
    </div>
    <?php endif; ?>
    <div class="ovr-pls-pagination">
        <p class="ovr-pls-info"><?php printf( esc_html__( '%d listing(s)', 'ovr-core' ), (int) $total ); ?></p>
        <?php
        $current_page = max( 1, (int) ( $request['paged'] ?? 1 ) );
        if ( $max_pages > 1 ) {
            $page_args = $_GET;
            unset( $page_args['page'] );
            // Prev.
            if ( $current_page > 1 ) {
                $prev_url = add_query_arg( array_merge( $page_args, [ 'paged' => $current_page - 1 ] ), admin_url( 'admin.php?page=' . PropertyListScreen::PAGE_SLUG ) );
                echo '<a href="' . esc_url( $prev_url ) . '" class="ovr-pls-page"><span class="material-symbols-outlined">chevron_left</span></a>';
            }
            // Pages.
            $start = max( 1, $current_page - 2 );
            $end   = min( $max_pages, $current_page + 2 );
            if ( $start > 1 ) {
                echo '<a href="' . esc_url( add_query_arg( array_merge( $page_args, [ 'paged' => 1 ] ), admin_url( 'admin.php?page=' . PropertyListScreen::PAGE_SLUG ) ) ) . '" class="ovr-pls-page">1</a>';
                if ( $start > 2 ) echo '<span class="ovr-pls-info" style="padding:0 4px">…</span>';
            }
            for ( $i = $start; $i <= $end; $i++ ) {
                $cls = $i === $current_page ? ' is-active' : '';
                echo '<a href="' . esc_url( add_query_arg( array_merge( $page_args, [ 'paged' => $i ] ), admin_url( 'admin.php?page=' . PropertyListScreen::PAGE_SLUG ) ) ) . '" class="ovr-pls-page' . $cls . '">' . $i . '</a>';
            }
            if ( $end < $max_pages ) {
                if ( $end < $max_pages - 1 ) echo '<span class="ovr-pls-info" style="padding:0 4px">…</span>';
                echo '<a href="' . esc_url( add_query_arg( array_merge( $page_args, [ 'paged' => $max_pages ] ), admin_url( 'admin.php?page=' . PropertyListScreen::PAGE_SLUG ) ) ) . '" class="ovr-pls-page">' . $max_pages . '</a>';
            }
            // Next.
            if ( $current_page < $max_pages ) {
                $next_url = add_query_arg( array_merge( $page_args, [ 'paged' => $current_page + 1 ] ), admin_url( 'admin.php?page=' . PropertyListScreen::PAGE_SLUG ) );
                echo '<a href="' . esc_url( $next_url ) . '" class="ovr-pls-page"><span class="material-symbols-outlined">chevron_right</span></a>';
            }
        }
        ?>
    </div>
</div>

</form>

<!-- Add Service Modal (hidden) -->
<div class="ovr-pls-overlay" id="ovr-pls-modal" style="display:none" role="dialog" aria-modal="true" aria-labelledby="ovr-pls-modal-title">
    <div class="ovr-pls-modal">
        <div class="ovr-pls-modal-head">
            <h3 id="ovr-pls-modal-title"><?php esc_html_e( 'Add Paid Service', 'ovr-core' ); ?></h3>
            <button type="button" class="ovr-pls-modal-close" id="ovr-pls-modal-close" aria-label="<?php esc_attr_e( 'Close', 'ovr-core' ); ?>">&times;</button>
        </div>
        <div class="ovr-pls-modal-body">
            <input type="hidden" id="ovr-pls-modal-listing-id" value="0">

            <label>
                <?php esc_html_e( 'Service Type', 'ovr-core' ); ?>
                <select id="ovr-pls-modal-service">
                    <option value=""><?php esc_html_e( '— Select —', 'ovr-core' ); ?></option>
                    <?php foreach ( $service_types as $st ) : ?>
                        <option value="<?php echo (int) $st['id']; ?>" data-duration="<?php echo (int) ( $st['duration_days'] ?? 30 ); ?>">
                            <?php echo esc_html( $st['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <?php esc_html_e( 'Duration', 'ovr-core' ); ?>
                <select id="ovr-pls-modal-duration">
                    <option value="7"><?php esc_html_e( '7 Days', 'ovr-core' ); ?></option>
                    <option value="14"><?php esc_html_e( '14 Days', 'ovr-core' ); ?></option>
                    <option value="30" selected><?php esc_html_e( '30 Days', 'ovr-core' ); ?></option>
                    <option value="60"><?php esc_html_e( '60 Days', 'ovr-core' ); ?></option>
                    <option value="90"><?php esc_html_e( '90 Days', 'ovr-core' ); ?></option>
                    <option value="custom"><?php esc_html_e( 'Custom', 'ovr-core' ); ?></option>
                </select>
            </label>

            <div class="ovr-pls-modal-row">
                <label>
                    <?php esc_html_e( 'Start Date', 'ovr-core' ); ?>
                    <input type="date" id="ovr-pls-modal-start">
                </label>
                <label>
                    <?php esc_html_e( 'End Date', 'ovr-core' ); ?>
                    <input type="date" id="ovr-pls-modal-end">
                </label>
            </div>

            <label>
                <?php esc_html_e( 'Internal Notes (optional)', 'ovr-core' ); ?>
                <textarea id="ovr-pls-modal-notes" placeholder="<?php esc_attr_e( 'e.g. Complimentary upgrade for promotional campaign.', 'ovr-core' ); ?>"></textarea>
            </label>

            <p class="ovr-pls-modal-err" id="ovr-pls-modal-err"></p>
        </div>
        <div class="ovr-pls-modal-footer">
            <button type="button" class="ovr-pls-btn" id="ovr-pls-modal-cancel"><?php esc_html_e( 'Cancel', 'ovr-core' ); ?></button>
            <button type="button" class="ovr-pls-btn ovr-pls-btn--primary" id="ovr-pls-modal-apply">
                <span class="material-symbols-outlined">check</span><?php esc_html_e( 'Apply Service', 'ovr-core' ); ?>
            </button>
        </div>
    </div>
</div>

<div id="ovr-pls-toast" class="ovr-pls-toast"></div>

<script>
(function(){
var form = document.getElementById('ovr-pls-form');
var modal = document.getElementById('ovr-pls-modal');
var modalListingId = document.getElementById('ovr-pls-modal-listing-id');
var modalService = document.getElementById('ovr-pls-modal-service');
var modalDuration = document.getElementById('ovr-pls-modal-duration');
var modalStart = document.getElementById('ovr-pls-modal-start');
var modalEnd = document.getElementById('ovr-pls-modal-end');
var modalNotes = document.getElementById('ovr-pls-modal-notes');
var modalErr = document.getElementById('ovr-pls-modal-err');
var modalApply = document.getElementById('ovr-pls-modal-apply');
var toastEl = document.getElementById('ovr-pls-toast');

// ── Select all ──
var selectAll = document.getElementById('ovr-pls-select-all');
if (selectAll) {
    selectAll.addEventListener('change', function(){
        document.querySelectorAll('.ovr-pls-listing-cb').forEach(function(cb){
            cb.checked = selectAll.checked;
        });
    });
}

// ── Copy PID ──
document.querySelectorAll('.ovr-pls-pid-copy').forEach(function(btn){
    btn.addEventListener('click', function(){
        var text = btn.getAttribute('data-copy') || '';
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function(){
                showToast('<?php echo esc_js( __( 'ID copied!', 'ovr-core' ) ); ?>', 'success');
            });
        }
    });
});

// ── Modal: open ──
document.querySelectorAll('.ovr-pls-service-add').forEach(function(btn){
    btn.addEventListener('click', function(){
        var lid = btn.getAttribute('data-listing-id');
        modalListingId.value = lid;
        modalService.value = '';
        modalDuration.value = '30';
        modalNotes.value = '';
        modalErr.textContent = '';

        var today = new Date();
        var yyyy = today.getFullYear();
        var mm = String(today.getMonth() + 1).padStart(2, '0');
        var dd = String(today.getDate()).padStart(2, '0');
        modalStart.value = yyyy + '-' + mm + '-' + dd;

        var end = new Date(today);
        end.setDate(end.getDate() + 30);
        var ey = end.getFullYear();
        var em = String(end.getMonth() + 1).padStart(2, '0');
        var ed = String(end.getDate()).padStart(2, '0');
        modalEnd.value = ey + '-' + em + '-' + ed;

        modal.style.display = 'flex';
    });
});

// ── Modal: duration change → auto-fill end date ──
modalDuration.addEventListener('change', function(){
    if (modalDuration.value !== 'custom') {
        var days = parseInt(modalDuration.value, 10);
        if (modalStart.value) {
            var start = new Date(modalStart.value);
            var end = new Date(start);
            end.setDate(end.getDate() + days);
            modalEnd.value = end.toISOString().slice(0, 10);
        }
    }
});

modalStart.addEventListener('change', function(){
    if (modalDuration.value !== 'custom' && modalStart.value) {
        var days = parseInt(modalDuration.value, 10);
        var start = new Date(modalStart.value);
        var end = new Date(start);
        end.setDate(end.getDate() + days);
        modalEnd.value = end.toISOString().slice(0, 10);
    }
});

// ── Modal: apply ──
modalApply.addEventListener('click', function(){
    var lid = parseInt(modalListingId.value, 10);
    var sid = parseInt(modalService.value, 10);
    if (!lid || !sid) {
        modalErr.textContent = '<?php echo esc_js( __( 'Please select a service type.', 'ovr-core' ) ); ?>';
        return;
    }

    var fd = new FormData();
    fd.set('action', 'ovr_admin_add_listing_service');
    fd.set('nonce', '<?php echo esc_js( wp_create_nonce( 'ovr_admin_nonce' ) ); ?>');
    fd.set('listing_id', lid);
    fd.set('service_id', sid);
    fd.set('start_date', modalStart.value);
    fd.set('end_date', modalEnd.value);
    fd.set('notes', modalNotes.value);

    modalApply.disabled = true;
    modalErr.textContent = '';

    fetch(ajaxurl, { method:'POST', credentials:'same-origin', body:fd })
        .then(function(r){ return r.json(); })
        .then(function(res){
            modalApply.disabled = false;
            if (res.success) {
                showToast(res.data.message || '<?php echo esc_js( __( 'Service assigned.', 'ovr-core' ) ); ?>', 'success');
                modal.style.display = 'none';
                // Reload the page to reflect the change.
                window.location.reload();
            } else {
                modalErr.textContent = res.data && res.data.message ? res.data.message : '<?php echo esc_js( __( 'Failed to assign service.', 'ovr-core' ) ); ?>';
            }
        })
        .catch(function(){
            modalApply.disabled = false;
            modalErr.textContent = '<?php echo esc_js( __( 'Network error. Please try again.', 'ovr-core' ) ); ?>';
        });
});

// ── Modal: close ──
function closeModal(){
    modal.style.display = 'none';
}
document.getElementById('ovr-pls-modal-close').addEventListener('click', closeModal);
document.getElementById('ovr-pls-modal-cancel').addEventListener('click', closeModal);
modal.addEventListener('click', function(e){
    if (e.target === modal) { closeModal(); }
});

// ── Bulk action ──
var bulkBtn = document.getElementById('ovr-pls-bulk-apply');
if (bulkBtn) {
    bulkBtn.addEventListener('click', function(){
        var action = document.getElementById('ovr-pls-bulk-action').value;
        if (!action) { showToast('<?php echo esc_js( __( 'Please select an action.', 'ovr-core' ) ); ?>', 'error'); return; }

        var ids = [];
        document.querySelectorAll('.ovr-pls-listing-cb:checked').forEach(function(cb){
            ids.push(parseInt(cb.value, 10));
        });
        if (!ids.length) { showToast('<?php echo esc_js( __( 'No listings selected.', 'ovr-core' ) ); ?>', 'error'); return; }

        if (action === 'delete' && !confirm('<?php echo esc_js( __( 'Move selected listings to trash?', 'ovr-core' ) ); ?>')) { return; }

        var fd = new FormData();
        fd.set('action', 'ovr_admin_bulk_action');
        fd.set('nonce', '<?php echo esc_js( wp_create_nonce( 'ovr_admin_nonce' ) ); ?>');
        fd.set('bulk_action', action);
        ids.forEach(function(id){ fd.append('listing_ids[]', id); });

        bulkBtn.disabled = true;
        fetch(ajaxurl, { method:'POST', credentials:'same-origin', body:fd })
            .then(function(r){ return r.json(); })
            .then(function(res){
                bulkBtn.disabled = false;
                if (res.success) {
                    showToast(res.data.message || '<?php echo esc_js( __( 'Done.', 'ovr-core' ) ); ?>', 'success');
                    window.location.reload();
                } else {
                    showToast(res.data && res.data.message ? res.data.message : '<?php echo esc_js( __( 'Action failed.', 'ovr-core' ) ); ?>', 'error');
                }
            })
            .catch(function(){
                bulkBtn.disabled = false;
                showToast('<?php echo esc_js( __( 'Network error.', 'ovr-core' ) ); ?>', 'error');
            });
    });
}

// ── Toast ──
function showToast(msg, type){
    if (!toastEl) { return; }
    toastEl.textContent = msg;
    toastEl.className = 'ovr-pls-toast is-' + (type || 'info');
    clearTimeout(toastEl._hide);
    toastEl._hide = setTimeout(function(){
        toastEl.className = 'ovr-pls-toast is-' + (type || 'info');
        toastEl.style.transition = 'opacity .3s';
        setTimeout(function(){ toastEl.className = 'ovr-pls-toast'; }, 50);
    }, 3000);
}
})();
</script>
