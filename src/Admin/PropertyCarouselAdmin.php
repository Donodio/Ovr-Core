<?php
/**
 * Homepage Carousel admin page.
 *
 * Lets the site owner pick which published listings appear in the curated tier
 * of the Sponsored Property Carousel, and reorder them with up/down. The
 * ordered pick list is persisted in `ovr_settings['homepage_carousel_ids']`.
 *
 * @package OVR\Admin
 * @since   1.1.2
 */

namespace OVR\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PropertyCarouselAdmin {

	public const PAGE_SLUG      = 'ovr-core-property-carousel';
	public const TOGGLE_ACTION = 'ovr_carousel_toggle';
	public const MOVE_ACTION   = 'ovr_carousel_move';
	public const SETTING_KEY   = 'homepage_carousel_ids';

	private string $hook_suffix = '';

	public function init(): void {
		add_action( 'admin_menu', [ $this, 'register_page' ] );
		add_action( 'admin_post_' . self::TOGGLE_ACTION, [ $this, 'handle_toggle' ] );
		add_action( 'admin_post_' . self::MOVE_ACTION,   [ $this, 'handle_move' ] );
	}

	public function register_page(): void {
		$this->hook_suffix = (string) add_submenu_page(
			'edit.php?post_type=ovr_property',
			__( 'Homepage Carousel', 'ovr-core' ),
			__( 'Homepage Carousel', 'ovr-core' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render' ]
		);
	}

	/** @return int[] Ordered curated IDs from settings. */
	public static function read_ids(): array {
		$settings = (array) get_option( 'ovr_settings', [] );
		$raw      = (string) ( $settings[ self::SETTING_KEY ] ?? '' );
		return array_values( array_filter( array_map( 'absint', preg_split( '/[\s,]+/', $raw ) ) ) );
	}

	/** Persist the ordered curated ID list, preserving unrelated settings keys. */
	public static function save_ids( array $ids ): void {
		$settings                        = (array) get_option( 'ovr_settings', [] );
		$settings[ self::SETTING_KEY ] = implode( ',', array_map( 'absint', $ids ) );
		update_option( 'ovr_settings', $settings );
	}

	public function handle_toggle(): void {
		check_admin_referer( self::TOGGLE_ACTION );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'ovr-core' ) );
		}

		$id  = absint( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$ids = self::read_ids();
		if ( in_array( $id, $ids, true ) ) {
			$ids = array_values( array_diff( $ids, [ $id ] ) );
		} elseif ( $id > 0 ) {
			$ids[] = $id;
		}
		self::save_ids( $ids );
		$this->redirect_back();
	}

	public function handle_move(): void {
		check_admin_referer( self::MOVE_ACTION );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'ovr-core' ) );
		}

		$id  = absint( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$dir = ( isset( $_GET['dir'] ) && 'down' === $_GET['dir'] ) ? 'down' : 'up';
		$ids = self::read_ids();
		$pos = array_search( $id, $ids, true );
		if ( false !== $pos ) {
			$swap = ( 'down' === $dir ) ? $pos + 1 : $pos - 1;
			if ( isset( $ids[ $swap ] ) ) {
				$tmp         = $ids[ $pos ];
				$ids[ $pos ] = $ids[ $swap ];
				$ids[ $swap ] = $tmp;
				$ids         = array_values( $ids );
			}
		}
		self::save_ids( $ids );
		$this->redirect_back();
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'ovr-core' ) );
		}

		$q = new \WP_Query( [
			'post_type'      => 'ovr_property',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );
		$all = array_map( 'absint', (array) $q->posts );

		if ( empty( $all ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Homepage Carousel', 'ovr-core' ) . '</h1>';
			echo '<p>' . esc_html__( 'No published properties yet. Add properties first, then choose which appear in the homepage carousel.', 'ovr-core' ) . '</p></div>';
			return;
		}

		$included      = array_values( array_intersect( self::read_ids(), $all ) );
		$included_flip = array_flip( $included );
		$rest          = array_values( array_filter( $all, fn( $id ) => ! isset( $included_flip[ $id ] ) ) );
		$rows          = array_merge( $included, $rest );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Homepage Carousel', 'ovr-core' ); ?></h1>
			<p><?php esc_html_e( 'These listings fill the curated tier of the Sponsored Property Carousel, shown after the sponsored listings. Click a listing to add or remove it; use Up / Down to reorder included ones.', 'ovr-core' ); ?></p>
			<table class="widefat striped">
				<thead>
				<tr>
					<th style="width:60px;"><?php esc_html_e( 'Image', 'ovr-core' ); ?></th>
					<th><?php esc_html_e( 'Listing', 'ovr-core' ); ?></th>
					<th><?php esc_html_e( 'ID', 'ovr-core' ); ?></th>
					<th><?php esc_html_e( 'In Carousel', 'ovr-core' ); ?></th>
					<th><?php esc_html_e( 'Order', 'ovr-core' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $rows as $id ) :
					$is_included = in_array( $id, $included, true );
					?>
					<tr>
						<td><?php echo wp_kses_post( get_the_post_thumbnail( $id, 'thumbnail' ) ); ?></td>
						<td><a href="<?php echo esc_url( (string) get_edit_post_link( $id ) ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a></td>
						<td><?php echo esc_html( (string) $id ); ?></td>
						<td>
							<a class="button" href="<?php echo esc_url( add_query_arg( [
								'action'   => self::TOGGLE_ACTION,
								'id'       => $id,
								'_wpnonce' => wp_create_nonce( self::TOGGLE_ACTION ),
							], admin_url( 'admin-post.php' ) ) ); ?>">
								<?php echo $is_included ? esc_html__( 'Remove', 'ovr-core' ) : esc_html__( 'Add', 'ovr-core' ); ?>
							</a>
						</td>
						<td>
							<?php if ( $is_included ) : ?>
								<a class="button" href="<?php echo esc_url( add_query_arg( [
									'action'   => self::MOVE_ACTION,
									'id'       => $id,
									'dir'      => 'up',
									'_wpnonce' => wp_create_nonce( self::MOVE_ACTION ),
								], admin_url( 'admin-post.php' ) ) ); ?>">&uarr; <?php esc_html_e( 'Up', 'ovr-core' ); ?></a>
								<a class="button" href="<?php echo esc_url( add_query_arg( [
									'action'   => self::MOVE_ACTION,
									'id'       => $id,
									'dir'      => 'down',
									'_wpnonce' => wp_create_nonce( self::MOVE_ACTION ),
								], admin_url( 'admin-post.php' ) ) ); ?>">&darr; <?php esc_html_e( 'Down', 'ovr-core' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function redirect_back(): void {
		wp_safe_redirect( add_query_arg( [
			'post_type' => 'ovr_property',
			'page'      => self::PAGE_SLUG,
		], admin_url( 'edit.php' ) ) );
		exit;
	}
}