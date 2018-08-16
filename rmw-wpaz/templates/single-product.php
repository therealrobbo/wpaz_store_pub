<?php
/**
 * RMW WordPress Amazon - Product listing template
 *
 * To override this include the following template file in your theme directory
 * single-product.php
 */
get_header();
global $gPress;
?>

<div class="section post">
	<div id="rmw_press" class="container-fluid">
		<?php if(have_posts()) { ?>
			<?php while(have_posts()) { ?>
				<?php
				the_post();
				$galpress_post_id = get_the_ID();
				$press_fields = $gPress->get_fields( $galpress_post_id );
				$outlet_info   = $gPress->outlets->get_current( $press_fields['outlet_id'] );
				?>
				<div class="row">
					<div class="col-xs-12 col-sm-9">
						<div class="post-content clearfix">

							<?php
							if ( !empty( $outlet_info['logo'] ) ) {
								$logo_src = $gPress->util->image_get_thumb( $outlet_info['logo'], 'press-thumb' )[0];
								?>
								<img class="rmw_press_outlet_logo" src="<?= $logo_src ?>" />
							<?php } ?>
							<h1 class="content-title"><?= get_the_title() ?></h1>
							<?= apply_filters( 'the_content', $press_fields['synopsis'] ); ?>
							<?php if ( !empty( $press_fields['is_embedded'] ) ) { ?>
								<div class="rmw_press_video">
									<?= wp_oembed_get( $press_fields['url'] ); ?>
								</div>
							<?php } ?>
							<div class="col-sm-12">
								<a class="btn btn-red" href="<?= $press_fields['url'] ?>"><?= $press_fields['button_text'] ?></a>
							</div>
						</div>
					</div>

					<div class="recent-posts col-xs-12 col-sm-3">
						<?php
						$recent_posts = new WP_Query(
							array(
								'posts_per_page' =>  5,
								'post__not_in'   =>  array($post->ID)
							)
						);
						?>
						<div class="hr"></div>
						<h3><?= is_english() ? 'Recent Posts' : 'Artículos Recientes' ?></h3>
						<ul class="list-unstyled">
							<?php if($recent_posts->have_posts()) { ?>
								<?php while($recent_posts->have_posts()) { ?>
									<?php $recent_posts->the_post() ?>

									<li><a href="<?= get_permalink() ?>"><?= get_the_title() ?></a></li>

								<?php } // End while() ?>
							<?php } ?>
						</ul>

					</div>
				</div>
			<?php } // End while() ?>

		<?php } else { ?>
			<p><?php _e('Sorry, no posts matched your criteria.'); ?></p>
		<?php } ?>

	</div> <!-- end of .container-fluid -->
</div> <!-- end of .section.post -->

<?php get_footer(); ?>
