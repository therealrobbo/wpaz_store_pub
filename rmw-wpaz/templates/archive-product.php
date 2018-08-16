<?php
/**
 * RMW WordPress Amazon - Product listing template
 *
 * To override this include the following template file in your theme directory
 * archive-product.php
 */


// Load up the sitewide header
get_header();

global $rmwAmazon;
$rmwAmazon->archive_list( );

get_sidebar();

get_footer();
?>