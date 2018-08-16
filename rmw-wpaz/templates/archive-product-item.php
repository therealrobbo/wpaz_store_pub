<?php
/**
 * RMW WordPress Amazon - Partial template for Product listing, single item template
 *
 * To override this include the following template file in your theme directory
 * archive-product-item.php
 */

global $rmwAmazon;

$wpaz_product_id = get_the_ID();
$product_fields  = $rmwAmazon->get_fields( $wpaz_product_id );
$is_discounted   = ( isset( $product_fields['amazon_discount'] ) && !empty( $product_fields['amazon_discount'] ) );
?>
<div class="row product_preview">
	<div class="product_img col-md-3 col-sm-3 col-xs-3">
		<a href="<?= $rmwAmazon->get_url() ?>" <?= $rmwAmazon->get_url_target() ?>><?php the_post_thumbnail( 'wpaz-product-thumb' ) ?></a>
	</div>

	<div class="product_copy col-md-9 col-sm-9 col-xs-9">
		<a href="<?= $rmwAmazon->get_url() ?>" <?= $rmwAmazon->get_url_target() ?>>
			<h3 class="post-title"><?= get_the_title() ?></h3>
		</a>
		<?php the_content( ) ?>
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
		<?php if( isset( $product_fields['availability'] ) && !empty( $product_fields['availability'] ) ) { ?>
			<div class="product_info">
				<label>Availability</label>
				<span><?= $product_fields['availability'] ?></span>
			</div>
		<?php } ?>
		<div class="product_info">
			<label>Categories</label>
			<span><?php the_terms( $wpaz_product_id, 'product_categories', '', ' | ', '' ); ?></span>
		</div>
		<div class="product-button">
			<a href="<?= $rmwAmazon->get_url() ?>" <?= $rmwAmazon->get_url_target() ?> class="">Get it on Amazon</a>
		</div>
	</div>
</div><!-- .product_preview -->