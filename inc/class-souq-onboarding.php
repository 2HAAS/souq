<?php
/**
 * First-run setup.
 *
 * Activating a theme does not, on its own, give a site a storefront homepage:
 * the block patterns are available in the inserter but nothing has inserted
 * them. Without this, a freshly activated Souq shows whatever the site had
 * before (often the blog index or a bare Shop page), which reads as a broken
 * theme rather than an unconfigured one.
 *
 * This builds the front page from the theme's own patterns and wires up a
 * primary menu. It is deliberately non-destructive: existing pages are never
 * overwritten, and every step is skipped if it is already configured.
 *
 * @package Souq
 */

namespace Souq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Onboarding {

	private static $instance = null;

	const NOTICE_TRANSIENT = 'souq_setup_notice';
	const RESULT_TRANSIENT = 'souq_setup_result';
	const ACTION           = 'souq_setup_storefront';
	const RECLAIM_ACTION   = 'souq_reclaim_locations';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'after_switch_theme', array( $this, 'on_activate' ) );
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_request' ) );
		add_action( 'admin_post_' . self::RECLAIM_ACTION, array( $this, 'handle_reclaim' ) );
	}

	/**
	 * On activation, set up automatically only when the site has no static
	 * front page at all. That is the "I just installed this theme and want it
	 * to look like the demo" case, and nothing is being replaced.
	 *
	 * On a site that already has a configured front page, we touch nothing and
	 * offer the setup as a one-click action instead.
	 */
	public function on_activate() {
		if ( 'page' !== get_option( 'show_on_front' ) || ! (int) get_option( 'page_on_front' ) ) {
			$result = $this->run();
			set_transient( self::RESULT_TRANSIENT, $result, DAY_IN_SECONDS );
		}

		set_transient( self::NOTICE_TRANSIENT, 1, DAY_IN_SECONDS );
		flush_rewrite_rules();
	}

	/**
	 * Performs the setup. Safe to run repeatedly.
	 *
	 * @return array<string, string> Step key => human-readable outcome.
	 */
	public function run() {
		$result = array();

		$page_id = $this->ensure_front_page( $result );
		$this->ensure_primary_menu( $result );

		if ( $page_id ) {
			$result['front_page_url'] = get_permalink( $page_id );
		}

		flush_rewrite_rules();

		return $result;
	}

	/**
	 * Creates (or fills) the storefront homepage and sets it as the front page.
	 *
	 * Never overwrites a page that already has content: a merchant's existing
	 * homepage is not ours to replace.
	 */
	private function ensure_front_page( array &$result ) {
		$front_id = (int) get_option( 'page_on_front' );

		if ( 'page' === get_option( 'show_on_front' ) && $front_id ) {
			$existing = get_post( $front_id );
			if ( $existing && '' !== trim( (string) $existing->post_content ) ) {
				$result['page'] = __( 'Left your existing front page alone — it already has content.', 'souq' );
				return $front_id;
			}
		}

		$content = Block_Patterns::instance()->homepage_content();

		if ( '' === $content ) {
			$result['page'] = __( 'Could not build the homepage content.', 'souq' );
			return 0;
		}

		// Reuse an empty page named Home rather than creating a duplicate.
		$page = $front_id ? get_post( $front_id ) : null;

		if ( ! $page ) {
			$found = get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => array( 'publish', 'draft' ),
					'name'           => 'home',
					'posts_per_page' => 1,
				)
			);
			$page = ! empty( $found ) ? $found[0] : null;
		}

		if ( $page && '' !== trim( (string) $page->post_content ) ) {
			// A Home page exists with real content: don't clobber it, just
			// promote it to front page if nothing else holds that role.
			$result['page'] = __( 'Found an existing “Home” page with content and kept it as-is.', 'souq' );
			$page_id        = $page->ID;
		} elseif ( $page ) {
			wp_update_post(
				array(
					'ID'           => $page->ID,
					'post_content' => wp_slash( $content ),
					'post_status'  => 'publish',
				)
			);
			$page_id        = $page->ID;
			$result['page'] = __( 'Filled your empty “Home” page with the storefront layout.', 'souq' );
		} else {
			$page_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_title'   => __( 'Home', 'souq' ),
					'post_name'    => 'home',
					'post_status'  => 'publish',
					'post_content' => wp_slash( $content ),
				)
			);

			if ( is_wp_error( $page_id ) || ! $page_id ) {
				$result['page'] = __( 'Could not create the homepage.', 'souq' );
				return 0;
			}

			$result['page'] = __( 'Created a “Home” page with the storefront layout.', 'souq' );
		}

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_id );

		// Give posts somewhere to live so the blog index isn't orphaned.
		if ( ! (int) get_option( 'page_for_posts' ) ) {
			$journal = get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'name'           => 'journal',
					'posts_per_page' => 1,
				)
			);

			if ( empty( $journal ) ) {
				$journal_id = wp_insert_post(
					array(
						'post_type'   => 'page',
						'post_title'  => __( 'Journal', 'souq' ),
						'post_name'   => 'journal',
						'post_status' => 'publish',
					)
				);
			} else {
				$journal_id = $journal[0]->ID;
			}

			if ( $journal_id && ! is_wp_error( $journal_id ) ) {
				update_option( 'page_for_posts', $journal_id );
			}
		}

		return $page_id;
	}

	/**
	 * Builds a primary menu (Shop plus the top product categories) when no
	 * menu is assigned to that location, so the header isn't near-empty.
	 */
	private function ensure_primary_menu( array &$result ) {
		$locations = get_theme_mod( 'nav_menu_locations' );
		$locations = is_array( $locations ) ? $locations : array();

		if ( ! empty( $locations['primary'] ) && wp_get_nav_menu_object( $locations['primary'] ) ) {
			$result['menu'] = __( 'Left your existing primary menu alone.', 'souq' );
			return;
		}

		$name = __( 'Primary Navigation', 'souq' );
		$menu = wp_get_nav_menu_object( $name );

		if ( ! $menu ) {
			$menu_id = wp_create_nav_menu( $name );
			if ( is_wp_error( $menu_id ) ) {
				$result['menu'] = __( 'Could not create a navigation menu.', 'souq' );
				return;
			}
		} else {
			$menu_id = $menu->term_id;
		}

		// Only populate a menu we just created empty; never append to one a
		// merchant has already built.
		if ( ! wp_get_nav_menu_items( $menu_id ) ) {
			if ( function_exists( 'wc_get_page_permalink' ) ) {
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'  => __( 'Shop all', 'souq' ),
						'menu-item-url'    => wc_get_page_permalink( 'shop' ),
						'menu-item-status' => 'publish',
					)
				);
			}

			$terms = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
					'number'     => 4,
					'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
				)
			);

			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					wp_update_nav_menu_item(
						$menu_id,
						0,
						array(
							'menu-item-title'     => $term->name,
							'menu-item-object'    => 'product_cat',
							'menu-item-object-id' => $term->term_id,
							'menu-item-type'      => 'taxonomy',
							'menu-item-status'    => 'publish',
						)
					);
				}
			}
		}

		$locations['primary'] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );

		$result['menu'] = __( 'Created a primary menu from your product categories.', 'souq' );
	}

	/* ---------------------------------------------------------------------
	   Admin surface
	   --------------------------------------------------------------------- */

	public function add_admin_page() {
		add_theme_page(
			__( 'Souq Setup', 'souq' ),
			__( 'Souq Setup', 'souq' ),
			'edit_theme_options',
			'souq-setup',
			array( $this, 'render_admin_page' )
		);
	}

	public function render_admin_page() {
		$front_id = (int) get_option( 'page_on_front' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Souq Setup', 'souq' ); ?></h1>
			<p><?php esc_html_e( 'Builds the storefront homepage from Souq’s block patterns and wires up a primary menu. Existing pages and menus are never overwritten, so this is safe to run more than once.', 'souq' ); ?></p>

			<p><strong><?php esc_html_e( 'What it does:', 'souq' ); ?></strong></p>
			<ul style="list-style:disc;padding-left:1.5em">
				<li><?php esc_html_e( 'Creates a “Home” page containing the hero, category tiles, product grid, promise band and newsletter sections, and sets it as your front page.', 'souq' ); ?></li>
				<li><?php esc_html_e( 'Creates a primary menu from your product categories if no menu is assigned yet.', 'souq' ); ?></li>
			</ul>
			<p><?php esc_html_e( 'Every section stays fully editable afterwards in the block editor.', 'souq' ); ?></p>

			<h2><?php esc_html_e( 'Header', 'souq' ); ?></h2>
			<p><?php esc_html_e( 'The header ships with four layouts. Every one keeps the same navigation, search, account and cart, so switching never hides anything from shoppers.', 'souq' ); ?></p>
			<ul style="list-style:disc;padding-left:1.5em">
				<?php foreach ( \Souq\Customizer::HEADER_LAYOUTS as $key => $layout ) : ?>
					<li>
						<strong><?php echo esc_html( $layout['label'] ); ?></strong>
						<?php if ( souq_header_config()['layout'] === $key ) : ?>
							<em><?php esc_html_e( '(current)', 'souq' ); ?></em>
						<?php endif; ?>
						<br><?php echo esc_html( $layout['description'] ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p>
				<a class="button" href="<?php echo esc_url( admin_url( 'customize.php?autofocus[section]=souq_header' ) ); ?>">
					<?php esc_html_e( 'Choose a header layout', 'souq' ); ?>
				</a>
			</p>

			<?php if ( $front_id ) : ?>
				<p>
					<?php
					printf(
						/* translators: %s: link to edit the current front page */
						esc_html__( 'Current front page: %s', 'souq' ),
						'<a href="' . esc_url( get_edit_post_link( $front_id ) ) . '">' . esc_html( get_the_title( $front_id ) ) . '</a>'
					);
					?>
				</p>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Footer', 'souq' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %d: number of footer columns */
					esc_html__( 'The footer has %d generic columns — each is a normal widget area, so you can add a Navigation Menu block (the primary menu again, a separate Terms or Returns menu, as many as you like), a Social Icons block for links to any platform, a Paragraph or Custom HTML block for an introduction, or several of those stacked in one column. A column with nothing in it just does not appear, and the footer reflows to however many columns are actually in use.', 'souq' ),
					(int) \SOUQ_FOOTER_MAX_COLUMNS
				);
				?>
			</p>
			<p>
				<a class="button" href="<?php echo esc_url( admin_url( 'widgets.php' ) ); ?>">
					<?php esc_html_e( 'Edit footer columns', 'souq' ); ?>
				</a>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<?php wp_nonce_field( self::ACTION ); ?>
				<?php submit_button( __( 'Set up the storefront', 'souq' ) ); ?>
			</form>

			<?php $this->render_conflicts_panel(); ?>
		</div>
		<?php
	}

	/**
	 * Reports templates from other builders that are rendering instead of
	 * Souq, and offers to hand those locations back.
	 *
	 * This exists because the failure is silent: Elementor Pro's theme-builder
	 * templates outlive a theme switch, so the site keeps showing its previous
	 * product page and header, and the theme looks broken rather than
	 * overridden.
	 */
	private function render_conflicts_panel() {
		$overrides = \Souq\Compatibility::get_overriding_templates();
		$caches    = \Souq\Compatibility::get_active_caches();
		$reclaimed = \Souq\Compatibility::get_reclaimed();

		if ( empty( $overrides ) && empty( $caches ) ) {
			return;
		}
		?>
		<hr>
		<h2><?php esc_html_e( 'Template conflicts', 'souq' ); ?></h2>

		<?php if ( ! empty( $overrides ) ) : ?>
			<p>
				<?php esc_html_e( 'Another builder is rendering some parts of this site instead of Souq. Its templates are stored separately from the theme, so they stayed in place when Souq was activated. Until a location is handed back, Souq’s design for it will not appear on the front end.', 'souq' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::RECLAIM_ACTION ); ?>">
				<?php wp_nonce_field( self::RECLAIM_ACTION ); ?>

				<table class="widefat striped" style="max-width:52rem">
					<thead>
						<tr>
							<th style="width:16rem"><?php esc_html_e( 'Location', 'souq' ); ?></th>
							<th><?php esc_html_e( 'Currently rendered by', 'souq' ); ?></th>
							<th style="width:12rem"><?php esc_html_e( 'Use Souq’s design', 'souq' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( \Souq\Compatibility::RECLAIMABLE as $location => $label ) : ?>
							<?php
							$templates = isset( $overrides[ $location ] ) ? $overrides[ $location ] : array();
							if ( empty( $templates ) ) {
								continue;
							}
							?>
							<tr>
								<td><strong><?php echo esc_html( $label ); ?></strong></td>
								<td>
									<?php foreach ( $templates as $template ) : ?>
										<div>
											<?php if ( $template['edit'] ) : ?>
												<a href="<?php echo esc_url( $template['edit'] ); ?>"><?php echo esc_html( $template['title'] ); ?></a>
											<?php else : ?>
												<?php echo esc_html( $template['title'] ); ?>
											<?php endif; ?>
											<code><?php echo esc_html( $template['type'] ); ?></code>
										</div>
									<?php endforeach; ?>
								</td>
								<td>
									<label>
										<input
											type="checkbox"
											name="souq_reclaim[]"
											value="<?php echo esc_attr( $location ); ?>"
											<?php checked( in_array( $location, $reclaimed, true ) ); ?>
										>
										<?php esc_html_e( 'Hand back to Souq', 'souq' ); ?>
									</label>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p class="description" style="max-width:52rem">
					<?php esc_html_e( 'Handing a location back only stops the other builder rendering it. Its template is left untouched, so you can untick this at any time to go back. To remove a template for good, edit it in the builder instead.', 'souq' ); ?>
				</p>

				<?php submit_button( __( 'Save template settings', 'souq' ) ); ?>
			</form>
		<?php endif; ?>

		<?php if ( ! empty( $caches ) ) : ?>
			<h3><?php esc_html_e( 'Caching', 'souq' ); ?></h3>
			<p style="max-width:52rem">
				<?php
				printf(
					/* translators: %s: comma-separated list of caching plugin names */
					esc_html__( 'These caching layers are active: %s. They will keep serving the previous design until purged, so clear them after changing anything here.', 'souq' ),
					'<strong>' . esc_html( implode( ', ', $caches ) ) . '</strong>'
				);
				?>
			</p>
		<?php endif; ?>
		<?php
	}

	public function handle_request() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'souq' ) );
		}

		check_admin_referer( self::ACTION );

		$result = $this->run();
		set_transient( self::RESULT_TRANSIENT, $result, DAY_IN_SECONDS );

		wp_safe_redirect( admin_url( 'themes.php?page=souq-setup&souq-setup=done' ) );
		exit;
	}

	public function handle_reclaim() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'souq' ) );
		}

		check_admin_referer( self::RECLAIM_ACTION );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$requested = isset( $_POST['souq_reclaim'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['souq_reclaim'] ) ) : array();

		\Souq\Compatibility::set_reclaimed( $requested );

		wp_safe_redirect( admin_url( 'themes.php?page=souq-setup&souq-templates=saved' ) );
		exit;
	}

	public function render_notice() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		$result = get_transient( self::RESULT_TRANSIENT );

		if ( $result ) {
			delete_transient( self::RESULT_TRANSIENT );
			delete_transient( self::NOTICE_TRANSIENT );
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?php esc_html_e( 'Souq storefront set up.', 'souq' ); ?></strong></p>
				<ul style="list-style:disc;padding-left:1.5em;margin:0 0 .5em">
					<?php foreach ( array( 'page', 'menu' ) as $key ) : ?>
						<?php if ( ! empty( $result[ $key ] ) ) : ?>
							<li><?php echo esc_html( $result[ $key ] ); ?></li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>
				<p>
					<?php if ( ! empty( $result['front_page_url'] ) ) : ?>
						<a class="button button-primary" href="<?php echo esc_url( $result['front_page_url'] ); ?>"><?php esc_html_e( 'View your storefront', 'souq' ); ?></a>
					<?php endif; ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'options-reading.php' ) ); ?>"><?php esc_html_e( 'Change front page', 'souq' ); ?></a>
				</p>
			</div>
			<?php
			return;
		}

		if ( ! get_transient( self::NOTICE_TRANSIENT ) ) {
			return;
		}
		?>
		<div class="notice notice-info is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Souq is active.', 'souq' ); ?></strong>
				<?php esc_html_e( 'Build the storefront homepage from Souq’s sections. Your existing pages and menus won’t be changed.', 'souq' ); ?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'themes.php?page=souq-setup' ) ); ?>">
					<?php esc_html_e( 'Set up the storefront', 'souq' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}
