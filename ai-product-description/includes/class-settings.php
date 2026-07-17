<?php
/**
 * Admin settings for AI providers.
 *
 * @package AI_Product_Description
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers multi-provider AI credentials settings.
 */
final class AI_Product_Desc_Settings {

	public const OPTION_KEY = 'ai_product_desc_settings';
	public const PAGE_SLUG  = 'ai-product-desc-settings';
	public const CAPABILITY = 'manage_options';

	/**
	 * Hook into WordPress admin.
	 */
	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Supported AI providers and their default connection fields.
	 *
	 * Add a new provider here when you want another backend later.
	 *
	 * @return array<string, array{label: string, defaults: array<string, string>}>
	 */
	public static function get_providers(): array {
		return array(
			'gapgpt'  => array(
				'label'    => __( 'GapGPT', 'ai-product-description' ),
				'defaults' => array(
					'api_key'  => '',
					'base_url' => 'https://api.gapgpt.app/v1',
					'model'    => 'gpt-4o',
				),
			),
			'openai'  => array(
				'label'    => __( 'OpenAI (ChatGPT)', 'ai-product-description' ),
				'defaults' => array(
					'api_key'  => '',
					'base_url' => 'https://api.openai.com/v1',
					'model'    => 'gpt-4o',
				),
			),
			'custom'  => array(
				'label'    => __( 'Custom (OpenAI-compatible)', 'ai-product-description' ),
				'defaults' => array(
					'api_key'  => '',
					'base_url' => '',
					'model'    => '',
				),
			),
		);
	}

	/**
	 * Default full option structure.
	 *
	 * @return array{active_provider: string, providers: array<string, array<string, string>>}
	 */
	public static function get_defaults(): array {
		$providers = array();

		foreach ( self::get_providers() as $id => $provider ) {
			$providers[ $id ] = $provider['defaults'];
		}

		return array(
			'active_provider' => 'gapgpt',
			'providers'       => $providers,
		);
	}

	/**
	 * Merged saved settings with defaults.
	 *
	 * @return array{active_provider: string, providers: array<string, array<string, string>>}
	 */
	public static function get_settings(): array {
		$saved    = get_option( self::OPTION_KEY, array() );
		$defaults = self::get_defaults();

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$settings = wp_parse_args( $saved, $defaults );
		$settings['providers'] = isset( $saved['providers'] ) && is_array( $saved['providers'] )
			? $saved['providers']
			: array();

		foreach ( $defaults['providers'] as $id => $provider_defaults ) {
			$settings['providers'][ $id ] = wp_parse_args(
				$settings['providers'][ $id ] ?? array(),
				$provider_defaults
			);
		}

		if ( ! isset( self::get_providers()[ $settings['active_provider'] ] ) ) {
			$settings['active_provider'] = $defaults['active_provider'];
		}

		return $settings;
	}

	/**
	 * Active provider id + its connection fields.
	 *
	 * @return array{id: string, label: string, api_key: string, base_url: string, model: string}
	 */
	public static function get_active_provider_config(): array {
		$settings  = self::get_settings();
		$providers = self::get_providers();
		$id        = $settings['active_provider'];
		$config    = $settings['providers'][ $id ] ?? $providers[ $id ]['defaults'];

		return array(
			'id'       => $id,
			'label'    => $providers[ $id ]['label'],
			'api_key'  => (string) ( $config['api_key'] ?? '' ),
			'base_url' => (string) ( $config['base_url'] ?? '' ),
			'model'    => (string) ( $config['model'] ?? '' ),
		);
	}

	/**
	 * Register option + sections/fields.
	 */
	public static function register_settings(): void {
		register_setting(
			'ai_product_desc_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::get_defaults(),
			)
		);

		add_settings_section(
			'ai_product_desc_provider_section',
			__( 'AI Provider', 'ai-product-description' ),
			array( __CLASS__, 'render_provider_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'active_provider',
			__( 'Active provider', 'ai-product-description' ),
			array( __CLASS__, 'render_active_provider_field' ),
			self::PAGE_SLUG,
			'ai_product_desc_provider_section'
		);

		foreach ( self::get_providers() as $provider_id => $provider ) {
			add_settings_section(
				'ai_product_desc_section_' . $provider_id,
				sprintf(
					/* translators: %s: provider name */
					__( '%s connection', 'ai-product-description' ),
					$provider['label']
				),
				static function () use ( $provider_id, $provider ): void {
					self::render_provider_connection_section( $provider_id, $provider['label'] );
				},
				self::PAGE_SLUG
			);

			add_settings_field(
				$provider_id . '_api_key',
				__( 'API Key', 'ai-product-description' ),
				static function () use ( $provider_id ): void {
					self::render_provider_text_field( $provider_id, 'api_key', 'password' );
				},
				self::PAGE_SLUG,
				'ai_product_desc_section_' . $provider_id
			);

			add_settings_field(
				$provider_id . '_base_url',
				__( 'Base URL', 'ai-product-description' ),
				static function () use ( $provider_id ): void {
					self::render_provider_text_field( $provider_id, 'base_url', 'url' );
				},
				self::PAGE_SLUG,
				'ai_product_desc_section_' . $provider_id
			);

			add_settings_field(
				$provider_id . '_model',
				__( 'Model', 'ai-product-description' ),
				static function () use ( $provider_id ): void {
					self::render_provider_text_field( $provider_id, 'model', 'text' );
				},
				self::PAGE_SLUG,
				'ai_product_desc_section_' . $provider_id
			);
		}
	}

	/**
	 * Sanitize saved option.
	 *
	 * @param mixed $input Raw form input.
	 * @return array{active_provider: string, providers: array<string, array<string, string>>}
	 */
	public static function sanitize_settings( $input ): array {
		$defaults = self::get_defaults();
		$output   = $defaults;

		if ( ! is_array( $input ) ) {
			return $output;
		}

		$providers_def = self::get_providers();
		$active        = isset( $input['active_provider'] ) ? sanitize_key( $input['active_provider'] ) : $defaults['active_provider'];

		if ( ! isset( $providers_def[ $active ] ) ) {
			$active = $defaults['active_provider'];
		}

		$output['active_provider'] = $active;
		$output['providers']       = array();

		foreach ( $providers_def as $id => $provider ) {
			$raw = isset( $input['providers'][ $id ] ) && is_array( $input['providers'][ $id ] )
				? $input['providers'][ $id ]
				: array();

			$output['providers'][ $id ] = array(
				'api_key'  => isset( $raw['api_key'] ) ? sanitize_text_field( $raw['api_key'] ) : '',
				'base_url' => isset( $raw['base_url'] ) ? esc_url_raw( trim( (string) $raw['base_url'] ) ) : $provider['defaults']['base_url'],
				'model'    => isset( $raw['model'] ) ? sanitize_text_field( $raw['model'] ) : $provider['defaults']['model'],
			);
		}

		return $output;
	}

	/**
	 * Settings page markup.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'ai-product-description' ) );
		}
		?>
		<div class="wrap ai-product-desc-settings">
			<h1><?php esc_html_e( 'تنظیمات', 'ai-product-description' ); ?></h1>
			<p><?php esc_html_e( 'Configure AI providers for product description generation. Choose an active provider and fill its connection fields.', 'ai-product-description' ); ?></p>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'ai_product_desc_settings_group' );
				do_settings_sections( self::PAGE_SLUG );
				submit_button( __( 'Save settings', 'ai-product-description' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Intro for active-provider section.
	 */
	public static function render_provider_section(): void {
		echo '<p>' . esc_html__( 'Select which provider the plugin should use. Credentials for other providers are kept so you can switch later.', 'ai-product-description' ) . '</p>';
	}

	/**
	 * Active provider dropdown.
	 */
	public static function render_active_provider_field(): void {
		$settings = self::get_settings();
		?>
		<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[active_provider]" id="ai-product-desc-active-provider">
			<?php foreach ( self::get_providers() as $id => $provider ) : ?>
				<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $settings['active_provider'], $id ); ?>>
					<?php echo esc_html( $provider['label'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Section description + data attribute for JS show/hide.
	 *
	 * @param string $provider_id Provider id.
	 * @param string $label       Provider label.
	 */
	public static function render_provider_connection_section( string $provider_id, string $label ): void {
		$settings = self::get_settings();
		$active   = $settings['active_provider'] === $provider_id;
		?>
		<div class="ai-product-desc-provider-panel" data-provider="<?php echo esc_attr( $provider_id ); ?>" <?php echo $active ? '' : 'hidden'; ?>>
			<p>
				<?php
				printf(
					/* translators: %s: provider name */
					esc_html__( 'Connection details for %s.', 'ai-product-description' ),
					esc_html( $label )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Single text/password/url field for a provider.
	 *
	 * @param string $provider_id Provider id.
	 * @param string $field       Field key.
	 * @param string $type        Input type.
	 */
	public static function render_provider_text_field( string $provider_id, string $field, string $type = 'text' ): void {
		$settings = self::get_settings();
		$value    = $settings['providers'][ $provider_id ][ $field ] ?? '';
		$name     = sprintf( '%s[providers][%s][%s]', self::OPTION_KEY, $provider_id, $field );
		$id       = sprintf( 'ai-product-desc-%s-%s', $provider_id, $field );
		$active   = $settings['active_provider'] === $provider_id;
		?>
		<div class="ai-product-desc-provider-field" data-provider="<?php echo esc_attr( $provider_id ); ?>" <?php echo $active ? '' : 'hidden'; ?>>
			<input
				type="<?php echo esc_attr( $type ); ?>"
				class="regular-text"
				name="<?php echo esc_attr( $name ); ?>"
				id="<?php echo esc_attr( $id ); ?>"
				value="<?php echo esc_attr( (string) $value ); ?>"
				autocomplete="off"
			>
		</div>
		<?php
	}
}
