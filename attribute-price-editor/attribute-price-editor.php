<?php
/**
 * Plugin Name:  Attribute Price Editor (Client‑Side Search)
 * Description:  ویرایش قیمت محصولات ساده و متغیر بر اساس برچسب و ویژگی «pa_بسته‌ بندی»؛ فیلتر سمت کلاینت بر اساس نام محصول.
 * Version:     1.3
 * Author:      Your Name
 * Text Domain: attr-price-editor
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ------------------------------------------------------------------ */
/*   Shortcode: [attr_price_editor]                                 */
/* ------------------------------------------------------------------ */
function attr_price_editor_shortcode() {
    ob_start();
    attr_price_editor_render_page();
    return ob_get_clean();
}
add_shortcode( 'attr_price_editor', 'attr_price_editor_shortcode' );

/* ------------------------------------------------------------------ */
/*   نمایش فرم انتخاب برچسب و جدول قیمت‌ها                        */
/* ------------------------------------------------------------------ */
function attr_price_editor_render_page() {
    /* ۱️⃣ اسلگ ویژگی (کد URL‑encoded) */
    $attr_slug = 'pa_%d8%a8%d8%b3%d8%aa%d9%87-%d8%a8%d9%86%d8%af%db%8c';   // pa_بسته‌ بندی

    /* ۲️⃣ دریافت همه برچسب‌ها برای لیست کشویی */
    $tags = get_terms( [
        'taxonomy'   => 'product_tag',
        'hide_empty' => false,
    ] );

    /* فرم انتخاب برچسب */
    ?>
    <h2>ویرایش قیمت محصولات بر اساس برچسب</h2>
    <form method="post">
        <label for="tag-select">انتخاب برچسب:</label>
        <select id="tag-select" name="attr_tag">
            <option value="">-- همه برچسب‌ها --</option>
            <?php foreach ( $tags as $tag ) : ?>
                <option value="<?php echo esc_attr( $tag->term_id ); ?>"
                    <?php selected( isset($_POST['attr_tag']) ? intval($_POST['attr_tag']) : 0, $tag->term_id ); ?>>
                    <?php echo esc_html( $tag->name ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">نمایش محصولات</button>
    </form>

    <?php
    /* ۳️⃣ اگر برچسب انتخاب شد، جدول را تولید می‌کنیم */
    if ( isset( $_POST['attr_tag'] ) && $_POST['attr_tag'] !== '' ) {
        $tag_id = intval( $_POST['attr_tag'] );

        /* پرس‌وجو: همه محصولات با آن برچسب */
        $args = [
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'tax_query'      => [
                [
                    'taxonomy' => 'product_tag',
                    'field'    => 'term_id',
                    'terms'    => $tag_id,
                ],
            ],
            'orderby' => 'date',
            'order'   => 'DESC',
        ];
        $products = new WP_Query( $args );

        if ( $products->have_posts() ) : ?>
            <hr>
            <label for="price-table-search">جستجو بر اساس نام محصول:</label>
            <input type="text" id="price-table-search" placeholder="نام محصول..." style="margin-bottom:10px;width:100%;">

            <table class="widefat" id="price-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>تصویر</th>
                        <th>نام محصول</th>
                        <th>ویژگی</th>
                        <th>قیمت</th>
                        <th>عمل</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $row = 1;
                while ( $products->have_posts() ) : $products->the_post();
                    $product_id   = get_the_ID();
                    $product_img  = get_the_post_thumbnail( $product_id, 'thumbnail' );
                    $product_name = get_the_title();

                    /* ---------- دریافت شیء WooCommerce محصول ---------- */
                    $wc_product = wc_get_product( $product_id );
                    if ( ! $wc_product ) {
                        continue;
                    }

                    /* ---------- اگر متغیر است ---------- */
                    if ( $wc_product->is_type( 'variable' ) ) {
                        $variation_ids = $wc_product->get_children();

                        foreach ( $variation_ids as $variation_id ) {
                            $variation   = wc_get_product( $variation_id );
                            $v_price     = $variation->get_regular_price();
                            $attribute   = $variation->get_attribute( 'pa_بسته‌ بندی' );
                            $attr_slug_decoded = urldecode( $attribute );
                            $term = get_term_by( 'slug', $attr_slug_decoded, 'pa_بسته‌ بندی' );
                            $attribute_name = $attr_slug_decoded;
                            if ( $term && ! is_wp_error( $term ) ) {
                                $attribute_name = $term->name;
                            }
                            $attribute   = $attribute ?: 'بدون مقدار';
                            ?>
                            <tr data-product="<?php echo esc_attr( $product_id ); ?>"
                                data-variation="<?php echo esc_attr( $variation_id ); ?>">
                                <td><?php echo esc_html( $row ); ?></td>
                                <td><?php echo $product_img; ?></td>
                                <td class="product-name"><?php echo esc_html( $product_name ); ?></td>
                                <td><?php echo esc_html( $attribute_name ); ?></td>
                                <td>
                                    <input type="number" step="0.01" min="0"
                                        class="attr-price-input"
                                        value="<?php echo esc_attr( $v_price ); ?>">
                                </td>
                                <td>
                                    <button type="button" class="attr-save-btn"
                                            data-product="<?php echo esc_attr( $product_id ); ?>"
                                            data-variation="<?php echo esc_attr( $variation_id ); ?>">
                                        ذخیره
                                    </button>
                                </td>
                            </tr>
                            <?php
                            $row++;
                        }
                    } else {
                        /* ---------- محصول ساده ---------- */
                        $price = $wc_product->get_regular_price();
                        ?>
                        <tr data-product="<?php echo esc_attr( $product_id ); ?>" data-variation="">
                            <td><?php echo esc_html( $row ); ?></td>
                            <td><?php echo $product_img; ?></td>
                            <td class="product-name"><?php echo esc_html( $product_name ); ?></td>
                            <td><?php echo esc_html( 'pa_بسته‌ بندی' ); ?></td>
                            <td>
                                <input type="number" step="0.01" min="0"
                                    class="attr-price-input"
                                    value="<?php echo esc_attr( $price ); ?>">
                            </td>
                            <td>
                                <button type="button" class="attr-save-btn"
                                        data-product="<?php echo esc_attr( $product_id ); ?>"
                                        data-variation="">
                                    ذخیره
                                </button>
                            </td>
                        </tr>
                        <?php
                        $row++;
                    }
                endwhile; ?>
                </tbody>
            </table>
        <?php
        else :
            echo '<p>هیچ محصولی با این برچسب پیدا نشد.</p>';
        endif;
        wp_reset_postdata();
    }
    ?>

    <?php /* --------- JavaScript --------- */ ?>
    <script>
    /* 1️⃣ فیلتر سمت کلاینت بر اساس نام محصول */
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('price-table-search');
        if (!searchInput) return; // اگر جدول نمایش داده نشد

        searchInput.addEventListener('input', function () {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('#price-table tbody tr');

            rows.forEach(function (row) {
                const nameCell = row.querySelector('.product-name');
                const name = nameCell ? nameCell.textContent.toLowerCase() : '';
                row.style.display = name.includes(filter) ? '' : 'none';
            });
        });
    });
    </script>
    <?php
    /* --------- Ajax برای ذخیره قیمت --------- */
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const table = document.getElementById('price-table');
        if (!table) return;

        table.addEventListener('click', function (e) {
            // فقط روی دکمه‌های ذخیره واکنش نشان بده
            if (!e.target.classList.contains('attr-save-btn')) return;

            const btn      = e.target;
            const product  = btn.dataset.product;
            const variation = btn.dataset.variation;
            const price    = parseFloat(btn.closest('tr').querySelector('.attr-price-input').value);

            // 1️⃣ در لحظه‌ی کلیک، لودینگ را نشان بده
            btn.classList.add('btn-loading');
            btn.classList.remove('btn-success', 'btn-error'); // در صورت وجود کلاس‌های قبلی پاک کن

            const data = {
                action: 'attr_price_editor_update',
                product_id: product,
                variation_id: variation,
                price: price,
            };

            const ajaxurl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
            jQuery.post(ajaxurl, data, function (response) {
                // 2️⃣ لودینگ را حذف کن
                btn.classList.remove('btn-loading');

                if (response.success) {
                    // 3️⃣ نشانگر موفقیت
                    btn.classList.add('btn-success');
                    setTimeout(() => btn.classList.remove('btn-success'), 2000); // پس از 2 ثانیه پاک کن
                } else {
                    // 4️⃣ نشانگر خطا
                    btn.classList.add('btn-error');
                    setTimeout(() => btn.classList.remove('btn-error'), 2000);
                }
            }).fail(function () {   // در صورت عدم دریافت پاسخ (مثلاً خطای شبکه)
                btn.classList.remove('btn-loading');
                btn.classList.add('btn-error');
                setTimeout(() => btn.classList.remove('btn-error'), 2000);
            });
        });
    });
    </script>
    <style>
        /* 1. حالت لودینگ (محل قرارگیری آیکون) */
        .btn-loading::after {
            content: "⏳";          /* یا `⌛` یا `⏱` */
            margin-left: 6px;
            font-size: 0.9em;
            display: inline-block;
            animation: spin 1s linear infinite;
        }

        /* 2. آیکون موفقیت */
        .btn-success::after {
            content: "✓";
            margin-left: 6px;
            color: green;
            font-weight: bold;
        }

        /* 3. آیکون خطا */
        .btn-error::after {
            content: "!";
            margin-left: 6px;
            color: red;
            font-weight: bold;
        }

        /* 4. انیمیشن چرخش (اختیاری) */
        @keyframes spin {
            0%   { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

    </style>
    <?php
}

/* ------------------------------------------------------------------ */
/*   Ajax برای ذخیره قیمت                                         */
/* ------------------------------------------------------------------ */
add_action( 'wp_ajax_attr_price_editor_update', 'attr_price_editor_ajax_update' );
function attr_price_editor_ajax_update() {
    if ( ! current_user_can( 'edit_products' ) ) {
        wp_send_json_error( __( 'دسترسی ندارید.', 'text-domain' ) );
    }

    $product_id   = isset( $_POST['product_id']   ) ? absint( $_POST['product_id'] )   : 0;
    $variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
    $price        = isset( $_POST['price'] ) ? floatval( $_POST['price'] ) : 0.0;

    $meta_keys = array( '_price' => $price, '_regular_price' => $price );   // you can add _regular_price / _sale_price if you want
    if ( $variation_id > 0 ) {
        $success = true;

        foreach ( $meta_keys as $key => $value ) {
            $updated = update_post_meta( $variation_id, $key, $value );
            if ( $updated === false ) {
                $success = false;
                break; // stop on first failure
            }
        }

        if ( $success ) {
            wp_send_json_success( array( 'msg' => __( 'قیمت variation به‌روز شد.', 'text-domain' ) ) );
        } else {
            wp_send_json_error( __( 'قیمت variation بروزرسانی نشد.', 'text-domain' ) );
        }

    // 3b) Simple product – write to the product post meta
    } else {
        $success = true;
        foreach ( $meta_keys as $key => $value ) {
            $updated = update_post_meta( $product_id, $key, $value );
            if ( $updated === false ) {
                $success = false;
                break;
            }
        }

        if ( $success ) {
            wp_send_json_success( array( 'msg' => __( 'قیمت محصول به‌روز شد.', 'text-domain' ) ) );
        } else {
            wp_send_json_error( __( 'قیمت محصول بروزرسانی نشد.', 'text-domain' ) );
        }
    }
}


