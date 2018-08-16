<?php
/**
 * RMW WordPress Amazon - Product listing partial template
 *
 * To override this include the following template file in your theme directory
 * archive-product-list.php
 */

global $rmwAmazon;
?>
<div id="posts" class="section press-feed">
	<div class="container-fluid">

		<?php if(have_posts()) { ?>

			<div id="press-rows" class="row">
				<?php while(have_posts()) { ?>
					<?php the_post() ?>
					<?php $rmwAmazon->archive_item( ); ?>
				<?php } // End while() ?>
			</div>

		<?php } else { ?>

			<h2 class="no-posts"><?php _e('Sorry, there are no ' . $rmwAmazon->post_type . 's to display...'); ?></h2>
		<?php } ?>

	</div> <!-- end of .container-fluid -->
</div> <!-- end of #posts.section -->
