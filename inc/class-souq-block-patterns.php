<?php
/**
 * Homepage block patterns.
 *
 * Every homepage section is a pattern built from core WordPress blocks (plus
 * the Souq Product Grid block), inserted into the Home page's content. That
 * makes each section reorderable, editable and removable in the native block
 * editor rather than through bespoke admin screens (PRODUCT.md principle 1).
 *
 * Patterns reference media by attachment ID where available so images resolve
 * through the media library and pick up responsive srcset, rather than being
 * hardcoded theme-file URLs.
 *
 * @package Souq
 */

namespace Souq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Block_Patterns {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'rest_api_init', array( $this, 'register_newsletter_route' ) );
	}

	/**
	 * Finds a usable image for a pattern.
	 *
	 * Resolution order: a media-library item whose slug matches $prefer (so a
	 * site can drop in its own "hero-banner" and have the pattern pick it up),
	 * then the Nth newest image, then the bundled placeholder SVG. Patterns are
	 * therefore never inserted with a broken image reference.
	 *
	 * @param int    $offset   Which recent image to fall back to.
	 * @param string $fallback Bundled SVG filename used when the library is empty.
	 * @param string $prefer   Attachment slug to look for first.
	 * @param string $size     Registered image size to return.
	 */
	private function image( $offset = 0, $fallback = 'placeholder-hero.svg', $prefer = '', $size = 'full' ) {
		if ( '' !== $prefer ) {
			$match = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_mime_type' => 'image',
					'post_status'    => 'inherit',
					'name'           => sanitize_title( $prefer ),
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);

			if ( ! empty( $match ) ) {
				$url = wp_get_attachment_image_url( $match[0], $size );
				if ( $url ) {
					return array(
						'id'  => (int) $match[0],
						'url' => $url,
					);
				}
			}
		}

		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'offset'         => (int) $offset,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $ids ) ) {
			$url = wp_get_attachment_image_url( $ids[0], $size );
			if ( $url ) {
				return array(
					'id'  => (int) $ids[0],
					'url' => $url,
				);
			}
		}

		return array(
			'id'  => 0,
			'url' => SOUQ_URI . '/assets/images/' . $fallback,
		);
	}

	/**
	 * The default storefront homepage, composed from the patterns in order.
	 *
	 * Built from the pattern methods directly rather than read back out of the
	 * pattern registry, so it is safe to call during theme activation before
	 * patterns have been registered.
	 *
	 * @return string Block markup.
	 */
	public function homepage_content() {
		$sections = array(
			$this->hero_markup(),
			$this->categories_markup(),
			$this->product_grid_markup(),
			$this->band_markup(),
			$this->newsletter_markup(),
		);

		return implode( "\n\n", array_filter( array_map( 'trim', $sections ) ) );
	}

	public function register() {
		register_block_pattern_category(
			'souq',
			array( 'label' => __( 'Souq Storefront', 'souq' ) )
		);

		$patterns = array(
			'hero'                => array( __( 'Souq: Editorial Hero', 'souq' ), $this->hero_markup() ),
			'featured-categories' => array( __( 'Souq: Category Tiles', 'souq' ), $this->categories_markup() ),
			'product-grid'        => array( __( 'Souq: Product Grid', 'souq' ), $this->product_grid_markup() ),
			'editorial-band'      => array( __( 'Souq: Editorial Promise Band', 'souq' ), $this->band_markup() ),
			'newsletter'          => array( __( 'Souq: Newsletter', 'souq' ), $this->newsletter_markup() ),
		);

		foreach ( $patterns as $slug => $pattern ) {
			register_block_pattern(
				'souq/' . $slug,
				array(
					'title'      => $pattern[0],
					'categories' => array( 'souq' ),
					'content'    => $pattern[1],
				)
			);
		}
	}

	/**
	 * Cinematic full-bleed hero with the brand name set very large and widely
	 * letterspaced over the photograph.
	 */
	private function hero_markup() {
		// Landscape crop: a 4:5 product shot cropped into a wide hero shows
		// only a flat horizontal slice of the garment.
		$image   = $this->image( 0, 'placeholder-hero.svg', 'hero-banner', 'souq-hero' );
		$id_attr = $image['id'] ? ',"id":' . $image['id'] : '';

		return '<!-- wp:cover {"url":"' . esc_url( $image['url'] ) . '"' . $id_attr . ',"dimRatio":25,"overlayColor":"ink","minHeight":78,"minHeightUnit":"vh","align":"full","className":"souq-hero","style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-cover alignfull souq-hero" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0;min-height:78vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-25 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="' . esc_url( $image['url'] ) . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
<!-- wp:heading {"textAlign":"center","level":1,"className":"souq-hero__wordmark","style":{"color":{"text":"#fdfaf3"}}} -->
<h1 class="wp-block-heading has-text-align-center souq-hero__wordmark" style="color:#fdfaf3">' . esc_html( get_bloginfo( 'name' ) ) . '</h1>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center","className":"souq-hero__tagline","style":{"color":{"text":"#fdfaf3"}}} -->
<p class="has-text-align-center souq-hero__tagline" style="color:#fdfaf3">' . esc_html__( 'Everyday pieces, quietly considered', 'souq' ) . '</p>
<!-- /wp:paragraph -->
<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $this->shop_url() ) . '">' . esc_html__( 'Shop the collection', 'souq' ) . '</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div></div>
<!-- /wp:cover -->';
	}

	/**
	 * Category tiles. Real product categories where they exist, so the pattern
	 * is useful the moment it is inserted.
	 */
	private function categories_markup() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 3,
				'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			$terms = array();
		}

		$columns = '';
		$i       = 1;

		foreach ( $terms as $term ) {
			$thumb_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
			$image    = $thumb_id
				? array( 'id' => $thumb_id, 'url' => wp_get_attachment_image_url( $thumb_id, 'souq-category-tile' ) )
				: $this->image( $i, 'placeholder-category.svg' );

			$link    = get_term_link( $term );
			$link    = is_wp_error( $link ) ? $this->shop_url() : $link;
			$id_attr = ! empty( $image['id'] ) ? ',"id":' . (int) $image['id'] : '';

			$columns .= '<!-- wp:column {"className":"souq-tile"} -->
<div class="wp-block-column souq-tile"><!-- wp:image {"sizeSlug":"large"' . $id_attr . ',"linkDestination":"custom","className":"souq-tile__media"} -->
<figure class="wp-block-image size-large souq-tile__media"><a href="' . esc_url( $link ) . '"><img src="' . esc_url( $image['url'] ) . '" alt="' . esc_attr( $term->name ) . '"/></a></figure>
<!-- /wp:image -->
<!-- wp:paragraph {"align":"center","className":"souq-tile__label"} -->
<p class="has-text-align-center souq-tile__label"><a href="' . esc_url( $link ) . '">' . esc_html( $term->name ) . '</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->';
			$i++;
		}

		if ( '' === $columns ) {
			return '';
		}

		return '<!-- wp:group {"align":"wide","className":"souq-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide souq-section">
<!-- wp:heading {"textAlign":"center","className":"souq-section-heading"} -->
<h2 class="wp-block-heading has-text-align-center souq-section-heading">' . esc_html__( 'Shop by category', 'souq' ) . '</h2>
<!-- /wp:heading -->
<!-- wp:columns {"align":"wide","className":"souq-tiles"} -->
<div class="wp-block-columns alignwide souq-tiles">' . $columns . '</div>
<!-- /wp:columns --></div>
<!-- /wp:group -->';
	}

	private function product_grid_markup() {
		return '<!-- wp:souq/product-grid {"heading":"' . esc_attr__( 'New arrivals', 'souq' ) . '","source":"recent","count":4,"showViewAll":true,"align":"wide"} /-->';
	}

	/**
	 * Full-bleed photographic band carrying three short brand promises,
	 * separated by hairline rules.
	 */
	private function band_markup() {
		$image   = $this->image( 2, 'placeholder-banner.svg', 'promise-band', 'souq-banner' );
		$id_attr = $image['id'] ? ',"id":' . $image['id'] : '';

		$promises = array(
			__( 'Considered design', 'souq' ),
			__( 'Natural fabrics', 'souq' ),
			__( 'Made to be worn often', 'souq' ),
		);

		$columns = '';
		foreach ( $promises as $promise ) {
			$columns .= '<!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"align":"center","className":"souq-band__item","style":{"color":{"text":"#fdfaf3"}}} -->
<p class="has-text-align-center souq-band__item" style="color:#fdfaf3">' . esc_html( $promise ) . '</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->';
		}

		return '<!-- wp:cover {"url":"' . esc_url( $image['url'] ) . '"' . $id_attr . ',"dimRatio":45,"overlayColor":"ink","minHeight":34,"minHeightUnit":"vh","align":"full","className":"souq-band"} -->
<div class="wp-block-cover alignfull souq-band" style="min-height:34vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-40 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="' . esc_url( $image['url'] ) . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
<!-- wp:columns {"className":"souq-band__row"} -->
<div class="wp-block-columns souq-band__row">' . $columns . '</div>
<!-- /wp:columns --></div></div>
<!-- /wp:cover -->';
	}

	private function newsletter_markup() {
		$html  = '<div class="souq-newsletter">';
		$html .= '<h2 class="souq-section-heading">' . esc_html__( 'Join the list', 'souq' ) . '</h2>';
		$html .= '<p class="souq-newsletter__intro">' . esc_html__( 'New arrivals and restocks, once a fortnight.', 'souq' ) . '</p>';
		$html .= '<form class="souq-newsletter__form" data-souq-newsletter>';
		$html .= '<label class="screen-reader-text" for="souq-newsletter-email">' . esc_html__( 'Email address', 'souq' ) . '</label>';
		$html .= '<input class="souq-input" type="email" id="souq-newsletter-email" name="email" required placeholder="' . esc_attr__( 'you@example.com', 'souq' ) . '">';
		$html .= '<button type="submit" class="souq-btn souq-btn--primary">' . esc_html__( 'Subscribe', 'souq' ) . '</button>';
		$html .= '</form>';
		$html .= '<p class="souq-newsletter__status" data-souq-newsletter-status role="status" aria-live="polite"></p>';
		$html .= '</div>';

		return '<!-- wp:group {"align":"full","className":"souq-section souq-section--surface","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull souq-section souq-section--surface">
<!-- wp:html -->' . $html . '<!-- /wp:html --></div>
<!-- /wp:group -->';
	}

	private function shop_url() {
		return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
	}

	/* ---------------------------------------------------------------------
	   Newsletter endpoint
	   --------------------------------------------------------------------- */

	public function register_newsletter_route() {
		register_rest_route(
			'souq/v1',
			'/newsletter',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_newsletter_signup' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'email' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_email',
					),
				),
			)
		);
	}

	public function handle_newsletter_signup( \WP_REST_Request $request ) {
		$email = $request->get_param( 'email' );

		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'souq_invalid_email', __( 'Please enter a valid email address.', 'souq' ), array( 'status' => 400 ) );
		}

		$existing = get_posts(
			array(
				'post_type'      => 'souq_subscriber',
				'post_status'    => 'private',
				'title'          => $email,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $existing ) ) {
			return rest_ensure_response( array( 'message' => __( 'You are already on the list.', 'souq' ) ) );
		}

		wp_insert_post(
			array(
				'post_type'   => 'souq_subscriber',
				'post_title'  => $email,
				'post_status' => 'private',
			)
		);

		return rest_ensure_response( array( 'message' => __( 'Thank you. You are on the list.', 'souq' ) ) );
	}
}
