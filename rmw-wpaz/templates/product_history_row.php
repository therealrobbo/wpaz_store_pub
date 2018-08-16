<?php
$rank_class =
	( empty( $historic_info ) ? 'same' :
		( ( $previous_row['sales_rank'] < $historic_info['sales_rank'] ) ? 'rank_decrease' :
			( ( $previous_row['sales_rank'] > $historic_info['sales_rank'] ) ? 'rank_increase' : 'same' ) ) );
$price_class =
	( empty( $historic_info ) ? 'same' :
		( ( $previous_row['list_price'] < $historic_info['list_price'] ) ? 'price_decrease' :
			( ( $previous_row['list_price'] > $historic_info['list_price'] ) ? 'price_increase' : 'same' ) ) );
$discount_class =
	( empty( $historic_info ) ? 'same' :
		( ( $previous_row['amazon_price'] < $historic_info['amazon_price'] ) ? 'price_decrease' :
			( ( $previous_row['amazon_price'] > $historic_info['amazon_price'] ) ? 'price_increase' : 'same' ) ) );
?>
<tr>
	<td><?= date( "M j, Y - g:ia", strtotime( $previous_date ) ) ?></td>
	<td class="<?= $rank_class ?>"><?= number_format( $previous_row['sales_rank'] ) ?></td>
	<td class="<?= $price_class ?>"><?= $rmwAmazon->format_price( $previous_row['list_price'], "n/a" ) ?></td>
	<td class="<?= $discount_class ?>"><?= $rmwAmazon->format_price( $previous_row['amazon_price'] ) ?></td>
	<td class="<?= $discount_class ?>"><?= $rmwAmazon->format_price( $previous_row['amazon_discount'] ) ?></td>
	<td class="<?= $discount_class ?>"><?= $previous_row['amazon_discount_percent'] ?></td>
</tr>
