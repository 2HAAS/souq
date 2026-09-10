<?php
/**
 * Souq override of WooCommerce's order-received (thank you) page.
 *
 * This is the end of the purchase and the last thing a shopper sees, so it is
 * treated as a moment rather than a receipt: a clear confirmation, the order
 * facts as a scannable grid instead of a bullet list, what happens next, and a
 * way back into the catalogue.
 *
 * Every WooCommerce hook is preserved in its original order, and the
 * failed-payment branch is kept intact: payment gateways attach their own
 * instructions (bank transfer details, redirects) to `woocommerce_thankyou`
 * and to the failure state, and losing either would break real orders.
 *
 * @package Souq
 *
 * @var WC_Order $order
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="woocommerce-order souq-order">

	<?php if ( $order ) : ?>

		<?php do_action( 'woocommerce_before_thankyou', $order->get_id() ); ?>

		<?php if ( $order->has_status( 'failed' ) ) : ?>

			<div class="souq-order__failed">
				<p class="souq-eyebrow"><?php esc_html_e( 'Payment unsuccessful', 'souq' ); ?></p>
				<h1 class="souq-order__title"><?php esc_html_e( 'That payment didn’t go through', 'souq' ); ?></h1>

				<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed souq-order__failed-note">
					<?php esc_html_e( 'The bank declined the transaction. Nothing has been charged, and your order is saved. You can try paying again below.', 'souq' ); ?>
				</p>

				<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed-actions souq-order__actions">
					<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="button pay souq-btn souq-btn--primary">
						<?php esc_html_e( 'Try payment again', 'souq' ); ?>
					</a>
					<?php if ( is_user_logged_in() ) : ?>
						<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="button pay souq-btn souq-btn--outline">
							<?php esc_html_e( 'My account', 'souq' ); ?>
						</a>
					<?php endif; ?>
				</p>
			</div>

		<?php else : ?>

			<header class="souq-order__head">
				<p class="souq-eyebrow"><?php esc_html_e( 'Order confirmed', 'souq' ); ?></p>

				<h1 class="souq-order__title">
					<?php
					$first_name = $order->get_billing_first_name();
					if ( $first_name ) {
						printf(
							/* translators: %s: customer first name */
							esc_html__( 'Thank you, %s', 'souq' ),
							esc_html( $first_name )
						);
					} else {
						esc_html_e( 'Thank you', 'souq' );
					}
					?>
				</h1>

				<p class="souq-order__lede">
					<?php
					if ( $order->get_billing_email() ) {
						printf(
							/* translators: %s: customer email address */
							esc_html__( 'Your order is confirmed. We have sent the details to %s.', 'souq' ),
							'<strong>' . esc_html( $order->get_billing_email() ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						);
					} else {
						esc_html_e( 'Your order is confirmed.', 'souq' );
					}
					?>
				</p>
			</header>

			<?php
			// Kept for compatibility: plugins filter this string, and the
			// default copy is not repeated visually above.
			ob_start();
			wc_get_template( 'checkout/order-received.php', array( 'order' => $order ) );
			$received_notice = trim( wp_strip_all_tags( ob_get_clean() ) );
			?>
			<p class="screen-reader-text"><?php echo esc_html( $received_notice ); ?></p>

			<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details souq-order__facts">

				<li class="woocommerce-order-overview__order order">
					<span class="souq-order__fact-label"><?php esc_html_e( 'Order number', 'souq' ); ?></span>
					<strong><?php echo $order->get_order_number(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
				</li>

				<li class="woocommerce-order-overview__date date">
					<span class="souq-order__fact-label"><?php esc_html_e( 'Date', 'souq' ); ?></span>
					<strong><?php echo wc_format_datetime( $order->get_date_created() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
				</li>

				<?php if ( is_user_logged_in() && $order->get_user_id() === get_current_user_id() && $order->get_billing_email() ) : ?>
					<li class="woocommerce-order-overview__email email">
						<span class="souq-order__fact-label"><?php esc_html_e( 'Email', 'souq' ); ?></span>
						<strong><?php echo $order->get_billing_email(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
					</li>
				<?php endif; ?>

				<li class="woocommerce-order-overview__total total">
					<span class="souq-order__fact-label"><?php esc_html_e( 'Total', 'souq' ); ?></span>
					<strong><?php echo $order->get_formatted_order_total(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
				</li>

				<?php if ( $order->get_payment_method_title() ) : ?>
					<li class="woocommerce-order-overview__payment-method method">
						<span class="souq-order__fact-label"><?php esc_html_e( 'Payment', 'souq' ); ?></span>
						<strong><?php echo wp_kses_post( $order->get_payment_method_title() ); ?></strong>
					</li>
				<?php endif; ?>

			</ul>

			<?php
			/**
			 * Post-purchase expectations. Filterable so a shop can describe its
			 * own dispatch timings without editing the template.
			 *
			 * @param array<int, array{title:string, body:string}> $steps
			 * @param WC_Order                                     $order
			 */
			$steps = apply_filters(
				'souq_order_next_steps',
				array(
					array(
						'title' => __( 'Confirmation email', 'souq' ),
						'body'  => __( 'A copy of this order is on its way to your inbox. Check spam if it has not arrived within a few minutes.', 'souq' ),
					),
					array(
						'title' => __( 'Packing', 'souq' ),
						'body'  => __( 'Your pieces are checked and pressed before they are packed.', 'souq' ),
					),
					array(
						'title' => __( 'On its way', 'souq' ),
						'body'  => __( 'We will be in touch with tracking as soon as your parcel leaves us.', 'souq' ),
					),
				),
				$order
			);
			?>

			<?php if ( ! empty( $steps ) ) : ?>
				<section class="souq-order__next" aria-labelledby="souq-order-next-heading">
					<h2 id="souq-order-next-heading" class="souq-order__section-title"><?php esc_html_e( 'What happens next', 'souq' ); ?></h2>
					<ol class="souq-order__steps">
						<?php foreach ( $steps as $index => $step ) : ?>
							<li class="souq-order__step">
								<span class="souq-order__step-number"><?php echo esc_html( (string) ( $index + 1 ) ); ?></span>
								<div>
									<h3 class="souq-order__step-title"><?php echo esc_html( $step['title'] ); ?></h3>
									<p class="souq-order__step-body"><?php echo esc_html( $step['body'] ); ?></p>
								</div>
							</li>
						<?php endforeach; ?>
					</ol>
				</section>
			<?php endif; ?>

		<?php endif; ?>

		<?php do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() ); ?>
		<?php do_action( 'woocommerce_thankyou', $order->get_id() ); ?>

		<?php if ( ! $order->has_status( 'failed' ) ) : ?>
			<p class="souq-order__actions">
				<a class="souq-btn souq-btn--outline" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
					<?php esc_html_e( 'Continue shopping', 'souq' ); ?>
				</a>
			</p>
		<?php endif; ?>

	<?php else : ?>

		<?php wc_get_template( 'checkout/order-received.php', array( 'order' => false ) ); ?>

	<?php endif; ?>

</div>
