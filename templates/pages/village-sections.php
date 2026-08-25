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
