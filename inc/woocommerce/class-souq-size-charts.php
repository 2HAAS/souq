<?php
/**
 * Size charts: reusable measurement tables assigned per category, with a
 * per-product override.
 *
 * A garment often needs more than one table. A co-ord set is measured as a
 * top and a bottom, and those have different columns (chest/length/sleeve
 * versus waist/length), so a chart is a list of named *sections* rather than
 * a single table.
 *
 * Storage:
 * - `souq_size_chart` CPT, one post per reusable chart.
 *   Meta `_souq_chart_sections`: [ [ title, columns[], rows[][] ], ... ]
 * - `product_cat` term meta `souq_size_chart_id` sets the category default.
 * - Product meta `_souq_size_chart_override` overrides it (0 = inherit).
 *
 * @package Souq
 */

namespace Souq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Size_Charts {

	private static $instance = null;
	const CPT = 'souq_size_chart';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_chart_meta_box' ) );
		add_action( 'save_post_' . self::CPT, array( $this, 'save_chart_meta' ) );

		add_action( 'product_cat_add_form_fields', array( $this, 'category_add_field' ) );
		add_action( 'product_cat_edit_form_fields', array( $this, 'category_edit_field' ) );
		add_action( 'created_product_cat', array( $this, 'save_category_field' ) );
		add_action( 'edited_product_cat', array( $this, 'save_category_field' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_product_override_meta_box' ) );
		add_action( 'save_post_product', array( $this, 'save_product_override' ) );
	}

	public function register_post_type() {
		register_post_type(
			self::CPT,
			array(
				'label'           => __( 'Size Charts', 'souq' ),
				'labels'          => array(
					'name'          => __( 'Size Charts', 'souq' ),
					'singular_name' => __( 'Size Chart', 'souq' ),
					'add_new_item'  => __( 'Add Size Chart', 'souq' ),
					'edit_item'     => __( 'Edit Size Chart', 'souq' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=product',
				'supports'        => array( 'title' ),
				'capability_type' => 'product',
				'map_meta_cap'    => true,
			)
		);
	}

	public function add_chart_meta_box() {
		add_meta_box(
			'souq_chart_table',
			__( 'Measurements', 'souq' ),
			array( $this, 'render_chart_meta_box' ),
			self::CPT,
			'normal',
			'high'
		);
	}

	/**
	 * Sections are edited as plain text: a line beginning with "#" starts a
	 * new named section, the first line after it is the header row, and the
	 * rest are data rows. Comma-separated throughout.
	 *
	 * A visual table builder would be nicer, but this stays keyboard-fast for
	 * the person entering twenty charts and needs no build step.
	 */
	public function render_chart_meta_box( $post ) {
		wp_nonce_field( 'souq_save_chart', 'souq_chart_nonce' );

		$sections = self::get_sections( $post->ID );
		$text     = '';

		if ( empty( $sections ) ) {
			$text = "# Kameez\nSize, Chest, Length, Sleeve\nS, 19, 32, 17\nM, 20.5, 34, 18.5\nL, 21.5, 36, 20\nXL, 23, 38, 21\n\n# Salwar\nSize, Waist, Length\nS, 30, 36\nM, 32, 36\nL, 34, 38\nXL, 36, 38";
		} else {
			foreach ( $sections as $section ) {
				if ( '' !== $section['title'] ) {
					$text .= '# ' . $section['title'] . "\n";
				}
				$text .= implode( ', ', $section['columns'] ) . "\n";
				foreach ( $section['rows'] as $row ) {
					$text .= implode( ', ', $row ) . "\n";
				}
				$text .= "\n";
			}
		}
		?>
		<p class="description">
			<?php esc_html_e( 'Start a section with “# Name” (for example “# Kameez”). The first line after it is the header row; the lines below are the measurements. Separate values with commas. Leave a blank line between sections.', 'souq' ); ?>
		</p>
		<textarea id="souq_chart_sections" name="souq_chart_sections" class="large-text code" rows="16"><?php echo esc_textarea( trim( $text ) ); ?></textarea>

		<h4><?php esc_html_e( 'Preview', 'souq' ); ?></h4>
		<div id="souq-chart-preview"></div>

		<script>
		( function () {
			var input = document.getElementById( 'souq_chart_sections' );
			var out = document.getElementById( 'souq-chart-preview' );

			function esc( s ) {
				var d = document.createElement( 'div' );
				d.textContent = s;
				return d.innerHTML;
			}

			function render() {
				var sections = [];
				var current = null;

				input.value.split( '\n' ).forEach( function ( raw ) {
					var line = raw.trim();
					if ( ! line ) { return; }

					if ( line.charAt( 0 ) === '#' ) {
						current = { title: line.slice( 1 ).trim(), rows: [] };
						sections.push( current );
						return;
					}
					if ( ! current ) {
						current = { title: '', rows: [] };
						sections.push( current );
					}
					current.rows.push( line.split( ',' ).map( function ( c ) { return c.trim(); } ) );
				} );

				out.innerHTML = sections.map( function ( s ) {
					if ( ! s.rows.length ) { return ''; }
					var head = s.rows[ 0 ];
					var body = s.rows.slice( 1 );
					return (
						( s.title ? '<p><strong>' + esc( s.title ) + '</strong></p>' : '' ) +
						'<table class="widefat striped" style="margin-bottom:12px"><thead><tr>' +
						head.map( function ( c ) { return '<th>' + esc( c ) + '</th>'; } ).join( '' ) +
						'</tr></thead><tbody>' +
						body.map( function ( r ) {
							return '<tr>' + r.map( function ( c ) { return '<td>' + esc( c ) + '</td>'; } ).join( '' ) + '</tr>';
						} ).join( '' ) +
						'</tbody></table>'
					);
				} ).join( '' );
			}

			input.addEventListener( 'input', render );
			render();
		} )();
		</script>
		<?php
	}

	public function save_chart_meta( $post_id ) {
		if ( ! isset( $_POST['souq_chart_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['souq_chart_nonce'] ) ), 'souq_save_chart' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['souq_chart_sections'] ) ) {
			return;
		}

		$raw      = sanitize_textarea_field( wp_unslash( $_POST['souq_chart_sections'] ) );
		$sections = array();
		$current  = null;

		foreach ( explode( "\n", $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}

			if ( 0 === strpos( $line, '#' ) ) {
				if ( $current ) {
					$sections[] = $current;
				}
				$current = array(
					'title'   => trim( substr( $line, 1 ) ),
					'columns' => array(),
					'rows'    => array(),
				);
				continue;
			}

			if ( ! $current ) {
				$current = array(
					'title'   => '',
					'columns' => array(),
					'rows'    => array(),
				);
			}

			$cells = array_values( array_filter( array_map( 'trim', explode( ',', $line ) ), 'strlen' ) );
			if ( empty( $cells ) ) {
				continue;
			}

			if ( empty( $current['columns'] ) ) {
				$current['columns'] = $cells;
			} else {
				$current['rows'][] = $cells;
			}
		}

		if ( $current ) {
			$sections[] = $current;
		}

		// Drop sections that never got any measurements.
		$sections = array_values(
			array_filter(
				$sections,
				static function ( $section ) {
					return ! empty( $section['columns'] ) && ! empty( $section['rows'] );
				}
			)
		);

		update_post_meta( $post_id, '_souq_chart_sections', $sections );
	}

	/**
	 * Reads a chart's sections, transparently upgrading the original
	 * single-table format so charts created before multi-section support
	 * keep rendering.
	 */
	public static function get_sections( $chart_id ) {
		$sections = get_post_meta( $chart_id, '_souq_chart_sections', true );

		if ( is_array( $sections ) && ! empty( $sections ) ) {
			return $sections;
		}

		$columns = get_post_meta( $chart_id, '_souq_chart_columns', true );
		$rows    = get_post_meta( $chart_id, '_souq_chart_rows', true );

		if ( ! empty( $columns ) && ! empty( $rows ) ) {
			return array(
				array(
					'title'   => '',
					'columns' => $columns,
					'rows'    => $rows,
				),
			);
		}

		return array();
	}

	/* ---------------------------------------------------------------------
	   Assignment
	   --------------------------------------------------------------------- */

	public function category_add_field() {
		?>
		<div class="form-field">
			<label for="souq_size_chart_id"><?php esc_html_e( 'Default Size Chart', 'souq' ); ?></label>
			<?php $this->render_chart_select( 'souq_size_chart_id', 0 ); ?>
			<p><?php esc_html_e( 'Products in this category show this chart unless they set their own override.', 'souq' ); ?></p>
		</div>
		<?php
	}

	public function category_edit_field( $term ) {
		$chart_id = (int) get_term_meta( $term->term_id, 'souq_size_chart_id', true );
		?>
		<tr class="form-field">
			<th scope="row"><label for="souq_size_chart_id"><?php esc_html_e( 'Default Size Chart', 'souq' ); ?></label></th>
			<td>
				<?php $this->render_chart_select( 'souq_size_chart_id', $chart_id ); ?>
				<p class="description"><?php esc_html_e( 'Products in this category show this chart unless they set their own override.', 'souq' ); ?></p>
			</td>
		</tr>
		<?php
	}

	public function save_category_field( $term_id ) {
		if ( isset( $_POST['souq_size_chart_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_term_meta( $term_id, 'souq_size_chart_id', absint( $_POST['souq_size_chart_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
	}

	public function add_product_override_meta_box() {
		add_meta_box(
			'souq_size_chart_override',
			__( 'Size Chart', 'souq' ),
			array( $this, 'render_product_override_box' ),
			'product',
			'side',
			'default'
		);
	}

	public function render_product_override_box( $post ) {
		wp_nonce_field( 'souq_save_chart_override', 'souq_chart_override_nonce' );
		$current = (int) get_post_meta( $post->ID, '_souq_size_chart_override', true );
		?>
		<p><?php esc_html_e( 'Override the category default for this product only.', 'souq' ); ?></p>
		<?php $this->render_chart_select( 'souq_size_chart_override', $current, __( 'Use category default', 'souq' ) ); ?>
		<?php
	}

	public function save_product_override( $post_id ) {
		if ( ! isset( $_POST['souq_chart_override_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['souq_chart_override_nonce'] ) ), 'souq_save_chart_override' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( isset( $_POST['souq_size_chart_override'] ) ) {
			update_post_meta( $post_id, '_souq_size_chart_override', absint( $_POST['souq_size_chart_override'] ) );
		}
	}

	private function render_chart_select( $name, $selected, $empty_label = '' ) {
		$charts = get_posts(
			array(
				'post_type'      => self::CPT,
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" class="widefat">';
		echo '<option value="0">' . esc_html( $empty_label ? $empty_label : __( '— None —', 'souq' ) ) . '</option>';
		foreach ( $charts as $chart ) {
			printf(
				'<option value="%1$d" %2$s>%3$s</option>',
				(int) $chart->ID,
				selected( $selected, $chart->ID, false ),
				esc_html( $chart->post_title )
			);
		}
		echo '</select>';
	}

	/**
	 * Resolves the chart for a product: its own override, then the first of
	 * its categories that defines one. Returns null when nothing applies, so
	 * templates can omit the size-guide section entirely rather than showing
	 * an empty panel.
	 */
	public static function get_chart_for_product( $product_id ) {
		$chart_id = (int) get_post_meta( $product_id, '_souq_size_chart_override', true );

		if ( ! $chart_id ) {
			$terms = get_the_terms( $product_id, 'product_cat' );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$term_chart_id = (int) get_term_meta( $term->term_id, 'souq_size_chart_id', true );
					if ( $term_chart_id ) {
						$chart_id = $term_chart_id;
						break;
					}
				}
			}
		}

		if ( ! $chart_id ) {
			return null;
		}

		$post = get_post( $chart_id );
		if ( ! $post || self::CPT !== $post->post_type ) {
			return null;
		}

		$sections = self::get_sections( $chart_id );
		if ( empty( $sections ) ) {
			return null;
		}

		return array(
			'id'       => $chart_id,
			'title'    => $post->post_title,
			'sections' => $sections,
		);
	}
}
