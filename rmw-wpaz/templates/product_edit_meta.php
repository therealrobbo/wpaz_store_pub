<?php global
$rmwAmazon;
$product_meta = $rmwAmazon->get_fields( $object->ID, false );
?>
<div class="rmw_meta">

	<!-- ------------------------------ S Y N O P S I S ------------------------------------------------------------ -->
	<div class="rmw_meta_row">
		<h5>Product Info</h5>

		<!-- ------------- ASIN ------------ -->
		<div class="rmw_meta_control_group">

			<label for="asin">Amazon ID:</label>
			<input type="text" class="rmw_meta_large" name="asin" id="asin"
			       value="<?=  esc_attr( $product_meta['asin'] ) ?>" />
			<?php if ( empty( $product_meta['last_fetch'] ) ) { ?>
				<a href="#" id="rmw_product_lookup" >Lookup Product</a>
			<?php } ?>
		</div><!-- .rmw_meta_control_group -->

		<!-- ------------- Author ------------ -->
		<div class="rmw_meta_control_group">

			<label for="author">Author:</label>
			<span id="author_display" class="rmw_meta_large" ><?= $product_meta['author'] ?></span>
			<input type="hidden" name="author" value="<?=  esc_attr( $product_meta['author'] ) ?>" />
		</div><!-- .rmw_meta_control_group -->

		<!-- ------------- List Price ------------ -->
		<div class="rmw_meta_control_group">

			<label for="list_price">List Price:</label>
			<span id="list_price_display" class="rmw_meta_large" ><?= $rmwAmazon->format_price( $product_meta['list_price'], "n/a" ) ?></span>
			<input type="hidden" name="list_price" value="<?=  esc_attr( $product_meta['list_price'] ) ?>" />
		</div><!-- .rmw_meta_control_group -->

		<!-- ------------- Amazon Price ------------ -->
		<div class="rmw_meta_control_group">

			<label for="amazon_price">Amazon Price:</label>
			<span id="amazon_price_display" class="rmw_meta_large" ><?= $rmwAmazon->format_price( $product_meta['amazon_price'], "n/a" ) ?></span>
			<input type="hidden" name="amazon_price" value="<?=  esc_attr( $product_meta['amazon_price'] ) ?>" />
		</div><!-- .rmw_meta_control_group -->

		<!-- ------------- Amazon Discount ------------ -->
		<div class="rmw_meta_control_group">

			<label for="amazon_discount">Amazon Savings:</label>
			<span id="amazon_discount_display" class="rmw_meta_large" ><?= $rmwAmazon->format_price( $product_meta['amazon_discount'] ) ?></span>
			<input type="hidden" name="amazon_discount" value="<?=  esc_attr( $product_meta['amazon_discount'] ) ?>" />
		</div><!-- .rmw_meta_control_group -->

		<!-- ------------- Amazon Discount Percentage ------------ -->
		<div class="rmw_meta_control_group">

			<label for="amazon_discount_percent">Amazon Savings (percentage):</label>
			<span id="amazon_discount_percent_display" class="rmw_meta_large" ><?= ( $product_meta['amazon_discount_percent'] ) ?>%</span>
			<input type="hidden" name="amazon_discount_percent" value="<?=  esc_attr( $product_meta['amazon_discount_percent'] ) ?>" />
		</div><!-- .rmw_meta_control_group -->

		<!-- ------------- Publisher ------------ -->
		<div class="rmw_meta_control_group">

			<label for="publisher">Publisher:</label>
			<span id="publisher_display" class="rmw_meta_large" ><?= $product_meta['publisher'] ?></span>
			<input type="hidden" name="publisher" value="<?=  esc_attr( $product_meta['publisher'] ) ?>" />
		</div><!-- .rmw_meta_control_group -->

		<!-- ------------- Product URL ------------ -->
		<div class="rmw_meta_control_group">

			<label for="url">Product URL:</label>
			<a href="<?= empty( $product_meta['url'] ) ? '#' : $product_meta['url'] ?>"
				<?= empty( $product_meta['url'] ) ? '' : 'target="_blank"' ?>
			   id="url_display" ><?= substr( $product_meta['url'], 0, 100 ) ?>...</a>
			<input type="hidden" name="url" value="<?=  esc_attr( $product_meta['url'] ) ?>" />
		</div><!-- .rmw_meta_control_group -->

		<!-- ------------- Sales rank ------------ -->
		<div class="rmw_meta_control_group">

			<label for="sales_rank">Most Recent Sales Rank:</label>
			<span id="sales_rank_display" class="rmw_meta_large" >
				<?= number_format( $product_meta['sales_rank'] ) ?>
				<?= ( !empty( $product_meta['rank_change'] ) ?
						"<span class='sales_rank_change_" .
							( ( $product_meta['rank_change'] > 0 ) ? "positive" : "negative" ) . "'>(" .
							number_format( $product_meta['rank_change'] ) . ")</span>" :
						"" ) ?>
			</span>
			<input type="hidden" name="sales_rank" value="<?=  esc_attr( $product_meta['sales_rank'] ) ?>" />
			<input type="hidden" name="is_ranked"  value="<?=  ( ( $product_meta['sales_rank'] > 0 ) ? '1' : '0' ) ?>" />
		</div><!-- .rmw_meta_control_group -->

		<!-- ------------- Availability ------------ -->
		<div class="rmw_meta_control_group">

			<label for="availability">Availability:</label>
			<span id="availability_display" class="rmw_meta_large" ><?= $product_meta['availability'] ?></span>
			<input type="hidden" name="availability" value="<?=  esc_attr( $product_meta['availability'] ) ?>" />
		</div><!-- .rmw_meta_control_group -->

		<!-- ------------- Last Fetched ------------ -->
		<div class="rmw_meta_control_group">

			<label for="last_fetch">Data Last Fetched:</label>
			<span id="last_fetch_display" class="rmw_meta_large" ><?= $product_meta['last_fetch'] ?></span>
			<input type="hidden" name="last_fetch" value="<?=  esc_attr( $product_meta['last_fetch'] ) ?>" />
		</div><!-- .rmw_meta_control_group -->

	</div><!-- .rmw_meta_row -->

</div><!-- .rmw_meta -->

<?php
// ---------------------- Product History --------------------------------
if ( isset( $product_meta['history'] ) && !empty( $product_meta['history'] ) ) {
	$product_history = json_decode( $product_meta['history'], true );
	krsort( $product_history ); ?>
	<div class="rmw_meta">
		<h5>Historic Product Info</h5>
		<table id="rmw_product_history">
			<thead>
			<tr>
				<th>Fetch Date</th>
				<th>Sales Rank</th>
				<th>List Price</th>
				<th>Amazon Price</th>
				<th>Amazon Discount</th>
				<th>Discount %</th>
			</tr>
			</thead>
			<tbody>
		<?php
		$previous_row  = null;
		$previous_date = null;
		foreach( $product_history as $update_date => $historic_info ) {
			if ( !empty( $previous_row ) ) {
				include( 'product_history_row.php' );
			}
			$previous_row  = $historic_info;
			$previous_date = $update_date;
			?>
		<?php }
		if ( !empty( $previous_row ) ) {
			include( 'product_history_row.php' );
		}
		?>
			</tbody>
		</table>
	</div><!-- .rmw_meta -->
<?php } ?>
