<?php
global $rmwAmazon;
?>
<div class="wrap">
	<?php $rmwAmazon->util->show_admin_notices() ?>
	<h2>Amazon Store Settings</h2>

	<form method="post">
		<input type="hidden" name="action" value="update">
		<?= $rmwAmazon->nonce_field() ?>

		<table class="form-table">
			<tbody>
			<tr>
				<th scope="row"><label for="access_key">Access Key:</label></th>
				<td colspan="2">
					<input class="regular-text" type="text" name="access_key" value="<?= $rmwAmazon->options['access_key'] ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="secret_key">Secret Key:</label></th>
				<td colspan="2">
					<input class="regular-text" type="text" name="secret_key" value="<?= $rmwAmazon->options['secret_key'] ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="country">Country Domain:</label></th>
				<td>
					<input class="regular-text" type="text" name="country" value="<?= $rmwAmazon->options['country'] ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="associate_tag">Associate Tag:</label></th>
				<td>
					<input class="regular-text" type="text" name="associate_tag" value="<?= $rmwAmazon->options['associate_tag'] ?>" />
				</td>
			</tr>
			</tbody>
		</table>


		<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"></p>
	</form>

	<h3>Cron Report</h3>
	<?php
	$cron_report = $rmwAmazon->cron_get_report( );
	if ( !empty( $cron_report ) ) { ?>
		<table>
			<thead>
			<tr>
				<th>Date</th>
				<th>Message</th>
				<th>Additional Info</th>
			</tr>
			</thead>
			<tbody>
			<?php foreach( $cron_report  as $report_item ) { ?>
				<tr>
					<td><?= $report_item['date'] ?></td>
					<td><?= $report_item['message'] ?></td>
					<td><?php if ( !empty( $report_item['data'] ) ) { $rmwAmazon->util->pretty_r( $report_item['data'] ); } ?></td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
	<?php } ?>

</div>