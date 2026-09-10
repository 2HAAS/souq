<?php
/**
 * Slide-down AJAX search panel, triggered by the header search icon.
 * Populated by assets/js/ajax-search.js against the /souq/v1/search REST route.
 *
 * @package Souq
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="souq-search-panel" id="souq-search-panel" hidden>
	<div class="souq-container">
		<form role="search" method="get" class="souq-search-panel__form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<input type="hidden" name="post_type" value="product">
			<label for="souq-search-input" class="screen-reader-text"><?php esc_html_e( 'Search products', 'souq' ); ?></label>
			<?php echo souq_icon( 'search', array( 'class' => 'souq-search-panel__icon' ) ); ?>
			<input
				type="search"
				id="souq-search-input"
				name="s"
				class="souq-search-panel__input"
				placeholder="<?php esc_attr_e( 'Search for dresses, kurtis, co-ords…', 'souq' ); ?>"
				autocomplete="off"
			>
			<button type="button" class="souq-search-panel__close" id="souq-search-close">
				<?php echo souq_icon( 'close', array( 'size' => '1.25rem' ) ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Close search', 'souq' ); ?></span>
			</button>
		</form>

		<div class="souq-search-results" id="souq-search-results" aria-live="polite"></div>
	</div>
</div>
