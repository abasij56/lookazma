<?php
/**
 * Native WP hierarchy entry for CPT brand.
 * Ensures /brands/{slug}/ loads the light template even if force_include misses.
 *
 * @package HelloElementorChild
 */

require get_stylesheet_directory() . '/single-brand-light.php';
