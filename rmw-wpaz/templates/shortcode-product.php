<?php
/**
 * RMW WordPress Amazon - Partial template for Product shortcode rendering
 *
 * To override this include the following template file in your theme directory
 * shortcode-product.php
 */

global $rmwAmazon;
$product_info = $rmwAmazon->shortcode_product_info;
?>
<div class="row product_preview product_shortcode">
	<div class="product_img col-md-3 col-sm-3 col-xs-3">
		<a href="<?= $product_info['url'] ?>" target="_blank"><?= get_the_post_thumbnail( $product_info['id'], 'wpaz-product-thumb' ) ?></a>
	</div>

	<div class="product_copy col-md-9 col-sm-9 col-xs-9">
		<a href="<?= $product_info['url'] ?>" target="_blank">
			<h3 class="post-title"><?= $product_info['title'] ?></h3>
		</a>
		<?= $product_info['description'] ?>
		<div class="product_info <?= ( $is_discounted ? 'product_strikethrough' : '' ) ?>">
			<label>List Price</label>
			<span><?= $rmwAmazon->format_price( $product_fields['list_price'], "(check amazon)" ) ?></span>
		</div>
		<?php if ( isset( $product_fields['amazon_price'] ) && !empty( $product_fields['amazon_price'] ) &&
		           ( $product_fields['list_price'] != $product_fields['amazon_price'] ) ) { ?>
			<div class="product_info ">
				<label>Amazon Price</label>
				<span><?= $rmwAmazon->format_price( $product_fields['amazon_price'] ) ?></span>
			</div>
		<?php } ?>
		<div class="product-button">
			<a href="<?= $rmwAmazon->get_url() ?>" <?= $rmwAmazon->get_url_target() ?> class="">Get it on Amazon</a>
		</div>
	</div>
</div><!-- .product_shortcode -->