<?php
/*
Plugin Name: Attribute Price Editor
Description: Allows shop owners to edit the price of a specific product attribute from a table.
Version: 1.0
Author: Your Name
Text Domain: attr-price-editor
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Security

/**
 * Register a shortcode that renders the page
 */
function attr_price_editor_shortcode() {
    ob_start();
    attr_price_editor_render_page();
    return ob_get_clean();
}
add_shortcode( 'attr_price_editor', 'attr_price_editor_shortcode' );

/**
 * Render the actual HTML
 */
function attr_price_editor_render_page() {
    // 1️⃣ Get the attribute taxonomy name
    $attr_slug = 'pa_%d8%a8%d8%b3%d8%aa%d9%87-%d8%a8%d9%86%d8%af%db%8c';
    // 2️⃣ Get all categories (in your case only `ptype`)
    $terms = get_terms( [
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
    ] );
    ?>
    <h2>Update Attribute Price</h2>
    <form id="attr-price-filter" method="post">
        <label for="attr-cat-select">Choose a category:</label>
        <select id="attr-cat-select" name="attr_cat">
            <option value="">-- All categories --</option>
            <?php foreach ( $terms as $term ) : ?>
                <option value="<?php echo esc_attr( $term->term_id ); ?>">
                    <?php echo esc_html( $term->name ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Show products</button>
    </form>

    <?php
    // When a category is selected, display the table
    if ( isset( $_POST['attr_cat'] ) && ! empty( $_POST['attr_cat'] ) ) {
        $cat_id = intval( $_POST['attr_cat'] );
        $args   = [
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'tax_query'      => [
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $cat_id,
                ],
            ],
        ];
        $products = new WP_Query( $args );
        if ( $products->have_posts() ) : ?>
            <table class="widefat" id="attr-price-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Image</th>
                        <th>Name</th>
                        <th><?php echo esc_html( 'attribute_' . $attr_slug ); ?></th>
                        <th>Price</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $i = 1;
                while ( $products->have_posts() ) :
                    $products->the_post();
                    $product_id   = get_the_ID();
                    $product_img  = get_the_post_thumbnail( $product_id, 'thumbnail' );
                    $product_name = get_the_title();
                    // Grab current attribute price
                    $attr_key   = 'attribute_' . $attr_slug;
                    $attr_value = get_post_meta( $product_id, $attr_key, true );
                    $attr_value = ( $attr_value !== '' ) ? $attr_value : 0;
                    ?>
                    <tr data-product="<?php echo esc_attr( $product_id ); ?>">
                        <td><?php echo esc_html( $i ); ?></td>
                        <td><?php echo $product_img; ?></td>
                        <td><?php echo esc_html( $product_name ); ?></td>
                        <td><?php echo esc_html( $attr_slug ); ?></td>
                        <td>
                            <input type="number"
                                   step="0.01"
                                   min="0"
                                   class="attr-price-input"
                                   value="<?php echo esc_attr( $attr_value ); ?>">
                        </td>
                        <td>
                            <button type="button"
                                    class="attr-save-btn"
                                    data-product="<?php echo esc_attr( $product_id ); ?>">
                                Save
                            </button>
                        </td>
                    </tr>
                <?php
                    $i++;
                endwhile;
                wp_reset_postdata();
                ?>
                </tbody>
            </table>
        <?php
        else :
            echo '<p>No products found in this category.</p>';
        endif;
    }
    ?>
    <script>
    (function($){
        $(function(){
            $('.attr-save-btn').on('click', function(){
                var btn   = $(this);
                var prod  = btn.data('product');
                var price = btn.closest('tr').find('.attr-price-input').val();

                $.post(ajaxurl, {
                    action: 'attr_price_update',
                    product_id: prod,
                    price: price,
                    _ajax_nonce: '<?php echo wp_create_nonce( 'attr_price_nonce' ); ?>'
                }, function(response){
                    if(response.success){
                        alert('Price updated!');
                    }else{
                        alert('Error: ' + response.data);
                    }
                });
            });
        });
    })(jQuery);
    </script>
    <?php
}

/**
 * AJAX handler to update the price
 */
add_action( 'wp_ajax_attr_price_update', 'attr_price_update_handler' );
function attr_price_update_handler() {
    check_ajax_referer( 'attr_price_nonce', '_ajax_nonce' );

    $product_id = intval( $_POST['product_id'] );
    $price      = floatval( $_POST['price'] );

    // The attribute taxonomy name
    $attr_slug = 'pa_%d8%a8%d8%b3%d8%aa%d9%87-%d8%a8%d9%86%d8%af%db%8c';
    $meta_key  = 'attribute_' . $attr_slug;

    // Update the meta value
    $updated = update_post_meta( $product_id, $meta_key, $price );

    if ( $updated !== false ) {
        wp_send_json_success();
    } else {
        wp_send_json_error( 'Could not update the meta value.' );
    }
}
