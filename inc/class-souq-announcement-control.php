<?php
/**
 * Repeatable Customizer control for header announcements.
 *
 * @package Souq
 */

namespace Souq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Announcement_Control extends \WP_Customize_Control {

	public $type = 'souq_announcement_repeater';

	public function render_content() {
		$value = $this->value();
		$value = is_array( $value ) ? $value : array();
		?>
		<?php if ( $this->label ) : ?>
			<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
		<?php endif; ?>
		<?php if ( $this->description ) : ?>
			<span class="description customize-control-description"><?php echo esc_html( $this->description ); ?></span>
		<?php endif; ?>
		<div
			class="souq-announcement-repeater"
			data-setting-id="<?php echo esc_attr( $this->id ); ?>"
			data-value="<?php echo esc_attr( wp_json_encode( $value ) ); ?>"
		></div>
		<button type="button" class="button souq-announcement-add">
			<?php esc_html_e( 'Add announcement', 'souq' ); ?>
		</button>
		<?php
	}
}
