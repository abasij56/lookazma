<?php
/**
 * Edit address form — same card layout as the light account form.
 *
 * @package HelloElementorChild
 * @version 9.3.0
 */

defined( 'ABSPATH' ) || exit;

$page_title = ( 'billing' === $load_address )
	? __( 'آدرس صورت حساب', 'hello-elementor-child' )
	: __( 'آدرس ارسال', 'hello-elementor-child' );

do_action( 'woocommerce_before_edit_account_address_form' );
?>

<?php if ( ! $load_address ) : ?>
	<?php wc_get_template( 'myaccount/my-address.php' ); ?>
<?php else : ?>

	<form method="post" class="lk-account-address-form" novalidate>
		<section class="lk-account-form__section">
			<h2 class="lk-account-form__title">
				<?php
				if ( class_exists( 'Hello_Elementor_Child_Light_Account_Template' ) ) {
					echo Hello_Elementor_Child_Light_Account_Template::icon_html( 'map-pin.svg', 'lk-account-form__title-icon', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				echo esc_html( apply_filters( 'woocommerce_my_account_edit_address_title', $page_title, $load_address ) );
				?>
			</h2>

			<div class="woocommerce-address-fields">
				<?php do_action( "woocommerce_before_edit_address_form_{$load_address}" ); ?>

				<div class="woocommerce-address-fields__field-wrapper">
					<?php
					foreach ( $address as $key => $field ) {
						woocommerce_form_field( $key, $field, wc_get_post_data_by_key( $key, $field['value'] ) );
					}
					?>
				</div>

				<?php do_action( "woocommerce_after_edit_address_form_{$load_address}" ); ?>

				<div class="lk-account-form__actions">
					<button type="submit" class="button" name="save_address" value="<?php esc_attr_e( 'ذخیره آدرس', 'hello-elementor-child' ); ?>">
						<?php
						if ( class_exists( 'Hello_Elementor_Child_Light_Account_Template' ) ) {
							echo Hello_Elementor_Child_Light_Account_Template::icon_html( 'save.svg', 'lk-account-save__icon', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
						esc_html_e( 'ذخیره آدرس', 'hello-elementor-child' );
						?>
					</button>
					<?php wp_nonce_field( 'woocommerce-edit_address', 'woocommerce-edit-address-nonce' ); ?>
					<input type="hidden" name="action" value="edit_address" />
				</div>
			</div>
		</section>
	</form>

<?php endif; ?>

<?php do_action( 'woocommerce_after_edit_account_address_form' ); ?>
