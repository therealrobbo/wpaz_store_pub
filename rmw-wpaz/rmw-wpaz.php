<?php
/*
Plugin Name: Robot Monkey Web WordPress Amazon Store
Plugin URI: http://robotmonkeyweb.com
Description: WordPress Amazon Affiliate Store Interface
Version: 1.0
Author: Rob M. Worley
Author URI: http://rmw.technology
License: Private
*/


/*  Copyright 2017  Robot Monkey Web

    This program is the exclusive intellectual property of Robot Monkey Web

	It may not be reused, redistributed or altered without
	permission from Robot Monkey Web
*/
include ( 'src/rmw_wpaz.php' );

global $rmwAmazon;
$rmwAmazon = new RMW_WPAZ( __FILE__ );

//////  http://codex.wordpress.org/Custom_Queries
?>