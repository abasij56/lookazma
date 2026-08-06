<?php
/**
 * Elementor widget: Lookazma Search.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lookazma Search widget.
 */
class Hello_Elementor_Child_Lookazma_Search_Widget extends \Elementor\Widget_Base {

	/**
	 * @return string
	 */
	public function get_name() {
		return 'lookazma_search';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return 'Lookazma Search';
	}

	/**
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-search';
	}

	/**
	 * @return array<int, string>
	 */
	public function get_categories() {
		return array( 'lookazma', 'general' );
	}

	/**
	 * @return array<int, string>
	 */
	public function get_keywords() {
		return array( 'search', 'lookazma', 'جستجو', 'محصول' );
	}

	/**
	 * @return array<string, string>
	 */
	public function get_style_depends() {
		return array( 'lk-lookazma-search' );
	}

	/**
	 * @return array<string, string>
	 */
	public function get_script_depends() {
		return array( 'lk-lookazma-search' );
	}

	/**
	 * Controls.
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'محتوا', 'hello-elementor-child' ),
			)
		);

		$this->add_control(
			'placeholder',
			array(
				'label'   => __( 'Placeholder', 'hello-elementor-child' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'از طریق نام محصول یا Cas No محصول مورد نظر خود را جستجو کنید',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Front-end render.
	 */
	protected function render() {
		if ( class_exists( 'Hello_Elementor_Child_Lookazma_Search' ) ) {
			Hello_Elementor_Child_Lookazma_Search::enqueue_assets();
		}

		$settings    = $this->get_settings_for_display();
		$placeholder = isset( $settings['placeholder'] ) && is_string( $settings['placeholder'] )
			? $settings['placeholder']
			: 'از طریق نام محصول یا Cas No محصول مورد نظر خود را جستجو کنید';

		$action = home_url( '/' );
		?>
		<div class="lookazma_pas" data-lk-lookazma-search>
			<form class="lookazma_pas__form" role="search" method="get" action="<?php echo esc_url( $action ); ?>">
				<label class="screen-reader-text" for="lk-lookazma-search-<?php echo esc_attr( (string) $this->get_id() ); ?>">
					<?php esc_html_e( 'جستجوی محصول', 'hello-elementor-child' ); ?>
				</label>
				<div class="lookazma_pas__field">
					<span class="lookazma_pas__icon" aria-hidden="true">
						<svg class="lookazma_pas__icon-search" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" focusable="false">
							<path fill="currentColor" d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
						</svg>
						<svg class="lookazma_pas__icon-loading" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" focusable="false">
							<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-dasharray="40 60"/>
						</svg>
					</span>
					<input
						type="search"
						id="lk-lookazma-search-<?php echo esc_attr( (string) $this->get_id() ); ?>"
						class="lookazma_pas__input"
						name="s"
						value="<?php echo esc_attr( get_search_query() ); ?>"
						placeholder="<?php echo esc_attr( $placeholder ); ?>"
						autocomplete="off"
						aria-autocomplete="list"
						aria-expanded="false"
						aria-controls="lk-lookazma-dropdown-<?php echo esc_attr( (string) $this->get_id() ); ?>"
					/>
				</div>
				<input type="hidden" name="post_type" value="product" />
			</form>
			<div
				class="lookazma_pas__dropdown"
				id="lk-lookazma-dropdown-<?php echo esc_attr( (string) $this->get_id() ); ?>"
				hidden
				role="listbox"
			>
				<div class="lookazma_pas__scroll" data-lk-scroll></div>
				<a class="lookazma_pas__more" data-lk-more href="#" hidden>
					<?php esc_html_e( 'مشاهده سایر محصولات', 'hello-elementor-child' ); ?>
				</a>
			</div>
		</div>
		<?php
	}
}
