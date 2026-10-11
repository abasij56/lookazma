<?php
/**
 * Edit account form — profile cards matching the account mock.
 *
 * Digits adds the mobile field via woocommerce_edit_account_form_start and the
 * OTP row via woocommerce_edit_account_form; both hooks are kept.
 *
 * @package HelloElementorChild
 * @version 10.5.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Small field icon, or an empty string when the account template is unavailable.
 *
 * @param string $file Icon filename.
 */
$lk_acc_icon = static function ( string $file ): string {
	if ( ! class_exists( 'Hello_Elementor_Child_Light_Account_Template' ) ) {
		return '';
	}
	return Hello_Elementor_Child_Light_Account_Template::icon_html( $file, 'lk-account-field__icon', 16 );
};

do_action( 'woocommerce_before_edit_account_form' );
?>

<form class="woocommerce-EditAccountForm edit-account lk-account-form" action="" method="post" <?php do_action( 'woocommerce_edit_account_form_tag' ); ?> >

	<section class="lk-account-form__section">
		<h2 class="lk-account-form__title">
			<?php echo Hello_Elementor_Child_Light_Account_Template::icon_html( 'user.svg', 'lk-account-form__title-icon', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php esc_html_e( 'اطلاعات شخصی', 'hello-elementor-child' ); ?>
		</h2>

		<div class="lk-account-form__grid">
			<div class="lk-account-form__field lk-account-form__field--phone">
				<?php do_action( 'woocommerce_edit_account_form_start' ); ?>
			</div>

			<p class="woocommerce-form-row form-row lk-account-form__field">
				<label for="account_first_name"><?php echo $lk_acc_icon( 'user.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'نام', 'hello-elementor-child' ); ?></span>&nbsp;<span class="required" aria-hidden="true">*</span></label>
				<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_first_name" id="account_first_name" autocomplete="given-name" value="<?php echo esc_attr( $user->first_name ); ?>" aria-required="true" />
			</p>
			<p class="woocommerce-form-row form-row lk-account-form__field">
				<label for="account_last_name"><?php echo $lk_acc_icon( 'user.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'نام خانوادگی', 'hello-elementor-child' ); ?></span>&nbsp;<span class="required" aria-hidden="true">*</span></label>
				<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_last_name" id="account_last_name" autocomplete="family-name" value="<?php echo esc_attr( $user->last_name ); ?>" aria-required="true" />
			</p>

			<p class="woocommerce-form-row form-row lk-account-form__field">
				<label for="account_display_name"><?php echo $lk_acc_icon( 'user.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'نام نمایشی', 'hello-elementor-child' ); ?></span>&nbsp;<span class="required" aria-hidden="true">*</span></label>
				<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_display_name" id="account_display_name" value="<?php echo esc_attr( $user->display_name ); ?>" aria-required="true" />
			</p>
			<p class="woocommerce-form-row form-row lk-account-form__field lk-account-form__field--wide lk-account-form__field--email">
				<label for="account_email"><?php echo $lk_acc_icon( 'mail.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'آدرس ایمیل', 'hello-elementor-child' ); ?></label>
				<input type="email" class="woocommerce-Input woocommerce-Input--email input-text" name="account_email" id="account_email" autocomplete="email" value="<?php echo esc_attr( $user->user_email ); ?>" />
			</p>

			<?php do_action( 'woocommerce_edit_account_form_fields' ); ?>
		</div>
	</section>

	<section class="lk-account-form__section">
		<h2 class="lk-account-form__title">
			<?php echo Hello_Elementor_Child_Light_Account_Template::icon_html( 'lock.svg', 'lk-account-form__title-icon', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php esc_html_e( 'تغییر رمز عبور', 'hello-elementor-child' ); ?>
		</h2>
		<p class="lk-account-form__hint lk-account-form__hint--section"><?php esc_html_e( 'برای امنیت بیشتر، در صورت تمایل تمام رمز عبور خود را تغییر دهید.', 'hello-elementor-child' ); ?></p>

		<div class="lk-account-form__grid lk-account-form__grid--pass">
			<p class="woocommerce-form-row form-row lk-account-form__field">
				<label for="password_current"><?php echo $lk_acc_icon( 'lock.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'رمز عبور فعلی', 'hello-elementor-child' ); ?></span></label>
				<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_current" id="password_current" autocomplete="current-password" placeholder="<?php esc_attr_e( 'رمز عبور فعلی را وارد کنید', 'hello-elementor-child' ); ?>" />
			</p>
			<p class="woocommerce-form-row form-row lk-account-form__field">
				<label for="password_1"><?php echo $lk_acc_icon( 'lock.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'رمز عبور جدید', 'hello-elementor-child' ); ?></span></label>
				<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_1" id="password_1" autocomplete="new-password" placeholder="<?php esc_attr_e( 'رمز عبور جدید را وارد کنید', 'hello-elementor-child' ); ?>" />
			</p>
			<p class="woocommerce-form-row form-row lk-account-form__field">
				<label for="password_2"><?php echo $lk_acc_icon( 'lock.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'تایید رمز عبور جدید', 'hello-elementor-child' ); ?></span></label>
				<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_2" id="password_2" autocomplete="new-password" placeholder="<?php esc_attr_e( 'رمز عبور جدید را دوباره وارد کنید', 'hello-elementor-child' ); ?>" />
			</p>
		</div>
	</section>

	<div class="lk-account-form__hooked">
		<?php do_action( 'woocommerce_edit_account_form' ); ?>
	</div>

	<div class="lk-account-form__actions">
		<?php wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>
		<button type="submit" class="woocommerce-Button button" name="save_account_details" value="<?php esc_attr_e( 'ذخیره تغییرات', 'hello-elementor-child' ); ?>">
			<?php echo Hello_Elementor_Child_Light_Account_Template::icon_html( 'save.svg', 'lk-account-save__icon', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php esc_html_e( 'ذخیره تغییرات', 'hello-elementor-child' ); ?>
		</button>
		<input type="hidden" name="action" value="save_account_details" />
	</div>

	<?php do_action( 'woocommerce_edit_account_form_end' ); ?>
</form>

<?php do_action( 'woocommerce_after_edit_account_form' ); ?>
