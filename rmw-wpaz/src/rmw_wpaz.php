<?php
/**
 * Primary source object for the RMW WordPress Amazon plugin type
 */
require_once( 'vendor/autoload.php' );
use ApaiIO\ApaiIO;
use ApaiIO\Configuration\GenericConfiguration;
use ApaiIO\Operations\Lookup;

if ( ! class_exists( 'RMW_WPAZ' ) ) {


	class RMW_WPAZ {

		public  $util        = null;

		private $nonce      = 'rmw_wpaz_nonce';
		private $is_admin   = false;

		private $amazon          = null;
		public  $amazon_response = '';

		public  $post_type  = 'product';

		private $plugin_path, $plugin_file, $plugin_url;

		private $field_list       = null;
		private $fields_defaulted = null;
		public  $field_vals       = null;
		private $field_prefix     = 'rmwwpaz_';
		private $field_dates      = array( );
		private $field_checkboxes = array( );

		private $option_fields    = null;
		public  $options          = null;
		private $cron_report_slug = 'rmw_wpaz_cron';
		private $fetch_sql        = null;
		private $fetch_count      = 0;
		private $fetch_prods      = null;

		const POSTS_PER_PAGE = 24;

		public $shortcode_product_info = null;


		public function __construct( $plugin_file ) {

			$this->plugin_file = $plugin_file;
			$this->plugin_path = plugin_dir_path( $plugin_file );
			$this->plugin_url  = plugins_url( '', $plugin_file );

			$this->is_admin = is_admin( );

			// Plugin install / deactivate
			register_activation_hook(   $this->plugin_file, array( $this, 'install' ) );
			register_deactivation_hook( $this->plugin_file, array( $this, 'deactivate' ) );

			// After all plugins are loaded, run the init function
			add_action( 'plugins_loaded', array( $this, 'init' ) );

			$this->field_list = array(
				'asin'                    => '',
				'author'                  => '',
				'list_price'              => 0,
				'publisher'               => '',
				'url'                     => '',
				'sales_rank'              => 0,
				'is_ranked'               => 0,
				'rank_change'             => 0,
				'amazon_price'            => 0,
				'amazon_discount'         => 0,
				'amazon_discount_percent' => 0,
				'availability'            => '',
				'last_fetch'              => '',
				'history'                 => '',
			);

			$this->option_fields = array(
				'access_key'    => '',
				'secret_key'    => '',
				'country'       => '',
				'associate_tag' => ''
			);

		}

		public function init() {
			if ( class_exists( 'RMW_Utilities' ) ) {
				$this->util = new RMW_Utilities( );

				$this->register_hooks( );

				$this->options_get();

				$this->util->admin_columns_request( $this->post_type, 'ASIN',         $this->field_prefix . 'asin',        'raw' );
				$this->util->admin_columns_request( $this->post_type, 'Last Fetched', $this->field_prefix . 'last_fetch',  'date|M j, Y g:i a',                 true );
				$this->util->admin_columns_request( $this->post_type, 'Sales Rank',   $this->field_prefix . 'sales_rank',  array( $this, 'render_sales_rank' ), true );
				$this->util->admin_columns_request( $this->post_type, 'Preview',      $this->field_prefix . 'thumbnail',   array( $this, 'render_thumbnail' ) );
				$this->util->admin_columns_register();
			} else {
				add_action( 'admin_notices', function( ) {
					print( '<div class="notice notice-error">' .
					       '<p><strong>Error:</strong> <strong>RMW WordPress Amazon Store</strong> requires the <strong>RMW Utilities</strong> plugin to operate</p></div>' );
				});
			}
		}


		private function register_hooks( ) {

			// Basic custom post type setup
			add_action( 'init', array( $this, 'register_post_types' ) );

			// Add custom fields editing and saving facilities to the EDIT Product post type
			add_action( 'add_meta_boxes',  array( $this, 'add_custom_fields' ) );
			add_action( 'save_post',       array( $this, 'save_meta') );
			add_action( 'admin_notices',   array( $this, 'generate_notices' ) );

			// Ajax lookup by ASIN
			add_action( 'wp_ajax_rmw_wpaz_find_product', array( $this, 'ajax_find_product' ) );
			add_action( 'wp_ajax_rmw_wpaz_copy_image',   array( $this, 'ajax_copy_image' ) );

			// Add the menu item for the page
			add_action('admin_menu', array( $this, 'options_page_create' ) );

			// Tell WP what to execute when our chron process runs
			add_action( 'rmw_wpaz_cron', array( $this, 'cron' ) );

			// Set WP query parameters for archive pages
			add_filter('pre_get_posts', array( $this, 'archive_order') );


			// Select template file when displaying a product
			add_filter( 'single_template',   array( $this, 'single_template' ) );
			add_filter( 'archive_template',  array( $this, 'archive_template' ) ) ;
			add_filter( 'taxonomy_template', array( $this, 'archive_template' ) ) ;

			// Load Event styles when get_header is called
			add_action( 'get_header', array( $this, 'get_header' ) );

			// Add a shortcode for this product type
			add_shortcode( 'az_product', array( $this, 'render_shortcode' ) );
		}

		//--------------------------------------------------------------------------------------------------------------
		// --------------------------------- B A S I C   P L U G I N   S E T U P ---------------------------------------
		//--------------------------------------------------------------------------------------------------------------
		/**
		 * Install our plugin to word press
		 */
		public function install( ) {

			// Check for versioning
			if ( version_compare( get_bloginfo( 'version' ), '3.1', '<' ) ) {

				// Deactivate plugin on older versions
				deactivate_plugins( basename( $this->plugin_file ) ); // Deactivate our plugin
			} else {
				// Schedule the cron process for this plugin
				if ( !wp_next_scheduled( 'rmw_wpaz_cron' )) {
					wp_schedule_event( time(), 'hourly', 'rmw_wpaz_cron');
				}
			}
		}


		/**
		 * Cleanup Code to execute upon deactivation of the plugin
		 */
		public function deactivate( ) {
			// Place deactivation code here if necessary
		}


		//--------------------------------------------------------------------------------------------------------------
		// --------------------------------------- C U S T O M   P O S T   T Y P E   S E T U P -------------------------
		//--------------------------------------------------------------------------------------------------------------
		/**
		 * Define the RMW Amazon Product custom post type to WordPress
		 */
		public function register_post_types () {

			// Set up the arguments for the Amazon Product Post Type
			$product_args = 	array (
				'public'             => true,
				'publicly_queryable' => true,
				'query_var'          => $this->post_type,
				'has_archive'        => true,
				'rewrite' => array(
					'slug'       => $this->post_type,
					'with_front' => false,
				),
				'supports' => array(
					'title',
					'editor',
					'thumbnail'
				),
				'labels' => array(
					'name'               => 'Products',
					'singular_name'      => 'Product',
					'add_new'            => 'Add New Product',
					'add_new_item'       => 'Add New Product',
					'edit_item'          => 'Edit Product',
					'new_item'           => 'New Product',
					'view_item'          => 'View Product',
					'search_items'       => 'Search Products',
					'not_found'          => 'No Products Found',
					'not_found_in_trash' => 'No Products Found In Trash'
				),
				'menu_position' => 20,
				'menu_icon'     => 'dashicons-cart'
			);

			// Register the event post type
			register_post_type( $this->post_type, $product_args );

			// Set up store category taxonomy
			$department_args = array(
				'hierarchical'       => true,
				'publicly_queryable' => true,
				'query_var'          => 'product_cat',
				'show_tagcloud'      => true,
				'rewrite' => array(
					'slug' => 'product_cat',
					'with_front' => false
				),
				'labels' => array(
					'name'              => 'Product Categories',
					'singular_name'     => 'Product Category',
					'edit_item'         => 'Edit Product Category',
					'update_item'       => 'Update Product Category',
					'add_new_item'      => 'Add New Product Category',
					'new_item_name'     => 'New Product Category',
					'all_items'         => 'All Product Categories',
					'search_items'      => 'Search Product Categories',
					'parent_item'       => 'Parent Product Category',
					'parent_item_colon' => 'Parent Product Category:',
				),
			);

			/* Register the production genre taxonomy. */
			register_taxonomy( 'product_categories', array( $this->post_type ), $department_args );


			// Custom Image Sizes
			add_image_size( 'wpaz-product-thumb',  150,  230, false );
			add_image_size( 'wpaz-product-tiny',    75,   75, true );
		}


		//--------------------------------------------------------------------------------------------------------------
		//----------------------------- C R O N   P R O C E S S --------------------------------------------------------
		//--------------------------------------------------------------------------------------------------------------
		public function cron_get_report( $date = null ) {
			$date = ( !empty( $date ) ? $date : date( "Y-m-d H:i:s" ) );
			return( $this->util->log_get( $this->cron_report_slug, $date ) );
		}


		public function cron( ) {

			$this->util->log_start( $this->cron_report_slug );

			// Get the products in last updated order
			$product_list = $this->get_products_by_last_fetch( );

			$this->util->log_message( 'count = ' . $this->fetch_count );
			$this->util->log_message( 'SQL = ' . $this->fetch_sql );

			if ( !empty( $product_list ) ) {

				$this->util->log_message( 'Fetched products' );

				// Go through the products that need updating
				foreach( $product_list as $product_rec ) {

					$this->util->log_message( 'Handling product rec. ID #' . $product_rec->ID . ", title=" . $product_rec->post_title . ", asin=" . $product_rec->meta['asin'] );

					// Is there an ASIN?
					if ( isset( $product_rec->meta['asin'] ) ) {

						$this->util->log_message( 'Ajax lookup...' );
						set_time_limit( 30 );

						// Get the product info from Amazon
						if ( $this->ajax_lookup_product( $product_rec->meta['asin'], $product_info ) ) {

							$this->util->log_message( '...success!' );

							// Save the updateable items from the previous record
							$previous_fetch = $product_rec->meta['last_fetch'];
							$old_info = array(
								'list_price'              => $product_rec->meta['list_price'],
								'sales_rank'              => $product_rec->meta['sales_rank'],
								'amazon_price'            => $product_rec->meta['amazon_price'],
								'amazon_discount'         => $product_rec->meta['amazon_discount'],
								'amazon_discount_percent' => $product_rec->meta['amazon_discount_percent'],
							);

							// Update the items with the newly-fetched info
							$this->util->meta_save_field( $product_rec->ID, $this->field_prefix . 'list_price',              $product_info['list_price'] );
							$this->util->meta_save_field( $product_rec->ID, $this->field_prefix . 'sales_rank',              $product_info['sales_rank'] );
							$this->util->meta_save_field( $product_rec->ID, $this->field_prefix . 'is_ranked',               $product_info['is_ranked'] );
							$this->util->meta_save_field( $product_rec->ID, $this->field_prefix . 'amazon_price',            $product_info['amazon_price'] );
							$this->util->meta_save_field( $product_rec->ID, $this->field_prefix . 'amazon_discount',         $product_info['amazon_discount'] );
							$this->util->meta_save_field( $product_rec->ID, $this->field_prefix . 'amazon_discount_percent', $product_info['amazon_discount_percent'] );
							$this->util->meta_save_field( $product_rec->ID, $this->field_prefix . 'last_fetch',              date( "Y-m-d H:i:s") );

							$this->util->meta_save_field( $product_rec->ID, $this->field_prefix . 'rank_change',
								$old_info['sales_rank'] - $product_info['sales_rank']  );

							// Now save the history for this product's price, rank, etc
							if ( isset( $product_rec->meta['history'] ) ) {
								$product_history = json_decode( $product_rec->meta['history'], true );
							} else {
								$product_history = array( );
							}
							$product_history[$previous_fetch] = $old_info;
							$this->util->meta_save_field(
								$product_rec->ID,
								$this->field_prefix . 'history',
								json_encode( $product_history )
							);
						} else {
							$this->util->log_message( '...FAILED :-(' );
						}
					} else {
						$this->util->log_message( 'NO ASIN!!!' );
					}
				}
			} else {
				$this->util->log_message( 'NO PRODUCTS!!!' );
			}

			$this->util->log_end(  );
		}

		//--------------------------------------------------------------------------------------------------------------
		//----------------------------- C U S T O M   F I E L D S ------------------------------------------------------
		//--------------------------------------------------------------------------------------------------------------
		/**
		 * For the admin screens, render the thumbnail column
		 *
		 * @param $post_id
		 *
		 * @return string
		 */
		public function render_thumbnail( $post_id ) {
			return( get_the_post_thumbnail( $post_id, 'wpaz-product-tiny' ) );
		}

		public function render_sales_rank( $post_id ) {

			$product_meta = $this->get_fields( $post_id );
			$return_string = number_format( $product_meta['sales_rank'] );
			if ( !empty( $product_meta['rank_change'] ) ) {
				$return_string .= " (" . number_format( $product_meta['rank_change'] ) . ")";
			}

			return( $return_string );
		}

		/**
		 * Add custom fields to the Event post type
		 */
		public function add_custom_fields() {

			global $post;

			if( !empty( $post ) ) {
				$post_type = get_post_type( $post );
				if( $post_type == $this->post_type ) {
					add_meta_box(
						'rmw_wpaz_meta',                            // $id
						'Product Information',                      // $title
						array( $this, 'display_custom_fields' ),    // $callback
						$this->post_type,                           // $page
						'normal',                                   // $context
						'high');                                    // $priority
				}
			}
		}


		/**
		 * Display the form on the admin page for custom fields related to this post type
		 *
		 * @param $object
		 * @param $box
		 */
		function display_custom_fields( $object, $box ) {

			// General RMW meta form stuff
			$this->util->meta_enqueue_support(  );

			wp_enqueue_style( 'rmw_wpaz_meta_css', $this->plugin_url . '/css/product-edit.css' );

			wp_enqueue_script( 'rmw_wpaz_meta_js', $this->plugin_url . '/js/product-edit.js', array( 'jquery' ), time(), true );


			wp_nonce_field( basename( $this->plugin_file ), $this->nonce );

			include( $this->plugin_path . 'templates/product_edit_meta.php' );

		}


		private function set_field_defaulted( $field_name ) {
			if ( empty( $this->fields_defaulted ) ) {
				$this->fields_defaulted = array( );
			}
			$this->fields_defaulted[] = $field_name;
		}


		/**
		 * Get the current Event Post's custom fields as an associative array
		 *
		 * @param bool $post_id
		 * @param bool $for_display
		 *
		 * @return array
		 */
		public function get_fields( $post_id = false, $for_display = true ) {

			$this->field_vals = array();
			foreach( $this->field_list as $field_name => $field_default ) {

				$db_field_name = $this->field_prefix . $field_name;
				$this->field_vals[$field_name] = get_post_meta( $post_id, $db_field_name, true );
				if ( $this->field_vals[$field_name] == '' ) {
					$this->set_field_defaulted( $field_name );
					$this->field_vals[$field_name] = $field_default;
				}
				if ( $for_display && in_array( $field_name, $this->field_dates ) ) {
					$this->field_vals[$field_name] = date( "F d, Y", strtotime( $this->field_vals[$field_name] ) );
				}
			}

			return( $this->field_vals );
		}


		/**
		 * Save our custom fields to the new post
		 *
		 * @param $post_id
		 * @return mixed
		 */
		public function save_meta($post_id) {

			//  Verify the nonce before proceeding.
			if ( !isset( $_POST[$this->nonce] ) || !wp_verify_nonce( $_POST[$this->nonce], basename( $this->plugin_file ) ) )
				return $post_id;

			// Make sure we're saving an event and not a sub-venue of an event
			$post_type = get_post_field('post_type', $post_id);
			if ( $post_type != $this->post_type ) {
				return( $post_id );
			}

			foreach( $this->field_list as $field_name => $field_default ) {

				// Pull the field value from the form
				if ( in_array( $field_name, $this->field_checkboxes ) ) {
					$field_value = ( isset( $_POST[$field_name] ) ? '1' : '0');
				} elseif ( in_array( $field_name, $this->field_dates ) ) {
					$field_value = ( isset( $_POST[$field_name] ) ? date( "Y-m-d", strtotime( $_POST[$field_name] ) ) : '0000-00-00' );
				} else {
					$field_value = ( isset( $_POST[$field_name] ) ? sanitize_text_field( $_POST[$field_name] ) : '' );
				}

				$this->util->meta_save_field( $post_id, $this->field_prefix . $field_name, $field_value );
			}

			return $post_id;
		}


		/**
		 * Validate the custom fields
		 */
		public function validate_custom_fields( ) {
			// No validations needed so far
		}


		/**
		 * Run validations and display any resulting notices
		 */
		public function generate_notices( ) {
			global $pagenow, $post;

			// Only do this for editing an existing post page
			if ( $this->is_admin && ( $pagenow == 'post.php' ) ) {

				// Only do this if we have an existing post...
				if( !empty( $post ) ) {
					$post_type = get_post_type( $post );

					// Only do this if the post is of the Event post type
					if ( $post_type == $this->post_type ) {

						$this->get_fields( get_the_ID( $post ) );

						// Validate fields
						$this->validate_custom_fields();

						// Display error messages
						$this->util->show_admin_notices();
					}
				}
			}
		}


		//--------------------------------------------------------------------------------------------------------------
		// --------------------------------------- A J A X   C A L L ---------------------------------------------------
		//--------------------------------------------------------------------------------------------------------------
		/**
		 * Establish a connection to Amazon if necessary, and return the handle
		 *
		 * @return ApaiIO|null
		 */
		private function get_amazon( ) {
			if ( empty( $this->amazon ) ) {

				$response = true;

				$conf = new GenericConfiguration();
				$client = new \GuzzleHttp\Client();
				$request = new \ApaiIO\Request\GuzzleRequest( $client );
				try {
					$conf
						->setCountry( $this->options['country'] )
						->setAccessKey( $this->options['access_key'] )
						->setSecretKey( $this->options['secret_key'] )
						->setAssociateTag( $this->options['associate_tag'] )
						->setRequest($request);
				} catch (\Exception $e) {
					$response = false;
					$this->amazon_response = $e->getMessage();
				}

				if ( $response ) {
					$this->amazon = new ApaiIO( $conf );
				}
			}

			return( $this->amazon );
		}



		public function ajax_lookup_product_xml( $asin, &$product_xml ) {

			$product_xml = null;

			$amazon = $this->get_amazon();
			if ( empty( $amazon ) ) {

				$return_code    = false;
			} else {
				$lookup = new Lookup();
				$lookup->setItemId( $asin );
				$lookup->setResponseGroup( array( 'Large', 'VariationSummary' ) );

				$return_code  = true;
				try {
					$product_raw = $amazon->runOperation( $lookup );
				} catch (\Exception $e) {
					$return_code = false;
					$this->amazon_response = $e->getMessage();
				}

				$product_xml = simplexml_load_string( $product_raw );
			}

			return( $return_code );
		}

		function ajax_product_price( $product_xml ) {

			if ( property_exists( $product_xml->Items->Item->ItemAttributes, 'ListPrice' ) ) {
				$return_val = (string) $product_xml->Items->Item->ItemAttributes->ListPrice->Amount;
			} else {
				$return_val = (string) $product_xml->Items->Item->VariationSummary->LowestPrice->Amount;
			}

			return( $return_val );
		}

		private function ajax_val_default( $field_name, $xml_val ) {
			if ( empty( $xml_val ) ) {
				$return_val = $this->field_list[$field_name];
			} else {
				$return_val = $xml_val;
			}

			return( $return_val );
		}

		/**
		 * Helper function to actually look up the product from Amazon, given an ASIN
		 *
		 * @param $asin - the amazon product ID number
		 * @param $product_info - will return as an associative array, populated with product info
		 *
		 * @return bool - true on success, false if we failed to fire an Amazon request
		 */
		public function ajax_lookup_product( $asin, &$product_info ) {

			$product_info = null;

			if ( !$this->ajax_lookup_product_xml( $asin, $product_xml ) ) {
				$return_code = false;
			} else {
				if ( property_exists( $product_xml->Items->Request, 'Errors' ) ) {
					$return_code = false;
					$this->amazon_response = (string) $product_xml->Items->Request->Errors->Error->Message;
				} else {
					$return_code  = true;
					$sales_rank   = $this->ajax_val_default( 'sales_rank',      (string) $product_xml->Items->Item->SalesRank );
					$product_info = array(
						'title'                   => $this->ajax_val_default( 'title',  (string) $product_xml->Items->Item->ItemAttributes->Title ),
						'author'                  => $this->ajax_val_default( 'author', (string) $product_xml->Items->Item->ItemAttributes->Author ),
						'list_price'              => $this->ajax_product_price( $product_xml ),
						'publisher'               => $this->ajax_val_default( 'publisher',       (string) $product_xml->Items->Item->ItemAttributes->Publisher ),
						'url'                     => $this->ajax_val_default( 'url',             (string) $product_xml->Items->Item->DetailPageURL ),
						'sales_rank'              => ( empty( $sales_rank ) ? '0' : $sales_rank ),
						'is_ranked'               => ( ( $sales_rank > 0 ) ? '1' : '0' ),
						'image'                   => $this->ajax_val_default( 'image',           (string) $product_xml->Items->Item->LargeImage->URL ),
						'amazon_price'            => $this->ajax_val_default( 'amazon_price',    (string) $product_xml->Items->Item->Offers->Offer->OfferListing->Price->Amount ),
						'amazon_discount'         => $this->ajax_val_default( 'amazon_discount', (string) $product_xml->Items->Item->Offers->Offer->OfferListing->AmountSaved->Amount ),
						'amazon_discount_percent' => $this->ajax_val_default(
							'amazon_discount_percent',
							(string) $product_xml->Items->Item->Offers->Offer->OfferListing->PercentageSaved
						),
						'availability'            => $this->ajax_val_default( 'availability', (string) $product_xml->Items->Item->Offers->Offer->OfferListing->Availability ),
						'description'             => $this->ajax_val_default( 'description',  (string) $product_xml->Items->Item->EditorialReviews->EditorialReview->Content ),
						'last_fetch'              => date( "Y-m-d H:i:s")
					);
				}
			}

			return( $return_code );
		}

		/**
		 * Ajax-callable function to copy an image from Amazon to our Media Library.
		 *
		 * Assumes post vars:
		 *    'image_url' - the URL of the image on Amazon
		 *    'title'     - the Title of the product on Amazon
		 *    'id'        - the ID of the post we're attaching the image to
		 */
		public function ajax_copy_image( ) {
			$response_code    = 0;
			$response_message = '';
			$attach_id        = 0;

			$remote_url   = $_POST['image_url'];
			$post_title   = $_POST['title'];
			$post_id      = $_POST['id'];

			if ( empty( $remote_url ) || empty( $post_title ) || empty( $post_id ) ) {
				$response_code = 1;
				$response_message = 'Missing arguments';
			} else {

				$remote_name  = basename( $remote_url );
				$wp_upload_dir = wp_upload_dir();
				$file_name     = $wp_upload_dir['path'] . "/" . $remote_name;
				copy( $remote_url, $file_name );

				// Check the type of file. We'll use this as the 'post_mime_type'.
				$filetype = wp_check_filetype( $remote_name, null );

				// Prepare an array of post data for the attachment.
				$attachment = array(
					'guid'           => $wp_upload_dir['url'] . '/' . $remote_name,
					'post_mime_type' => $filetype['type'],
					'post_title'     => preg_replace( '/\.[^.]+$/', '', $post_title ),
					'post_content'   => '',
					'post_status'    => 'inherit'
				);

				// Insert the attachment.
				$attach_id = wp_insert_attachment( $attachment, $file_name, $post_id );

				// Generate the metadata for the attachment, and update the database record.
				$attach_data = wp_generate_attachment_metadata( $attach_id, $file_name );
				wp_update_attachment_metadata( $attach_id, $attach_data );
			}

			// Echo out the response
			print( json_encode( array(
				'code'     => $response_code,
				'message'  => $response_message,
				'image_id' => $attach_id
			)));

			die();
		}


		/**
		 * Ajax-callable function to find a product on Amazon and return its info.
		 *
		 * Assums _POST var
		 *    'asin' - amazon product ID
		 */
		public function ajax_find_product( ) {

			$response_code    = 0;
			$response_message = '';
			$product_info     = '';

			$asin = $_POST['asin'];
			if ( empty( $asin ) ) {
				$response_code    = 1;
				$response_message = "ERROR! Empty ASIN!";
			} else {

				if ( !$this->ajax_lookup_product( $asin, $product_info ) ) {
					$response_code    = 2;
					$response_message = $this->amazon_response;
				}
			}

			print( json_encode( array(
				'code'    => $response_code,
				'message' => $response_message,
				'product' => $product_info
			) ) );

			die();
		}

		//--------------------------------------------------------------------------------------------------------------
		// --------------------------------- A M A Z O N   O P T I O N S   P A G E -------------------------------------
		//--------------------------------------------------------------------------------------------------------------
		/**
		 * Render the HTML code for a Product shortcode. This uses the plugin's built-in template by default,
		 * but will load a template-specific override template if available.
		 */
		public function render_shortcode_item( ) {

			ob_start();

			if ( file_exists( get_template_directory() . '/shortcode-product.php' ) ) {
				include( get_template_directory() . '/shortcode-product.php' );
			} else {
				include( $this->plugin_path . '/templates/shortcode-product.php' );
			}
			$return_contents = ob_get_contents();

			ob_end_clean();

			return( $return_contents );
		}



		public function render_shortcode( $shortcode_args ) {

			$args_in = shortcode_atts( array( 'id' => 0 ), $shortcode_args );

			if ( empty( $args_in['id'] ) ) {
				$return_string = "<!-- Shortcode error [az_product] id_unknown! -->";
			} else {
				$this->util->enqueue_style_override( 'rwm-wpaz-product-listing-css', $this->plugin_url, '/css/product-listing.css' );
				$this->shortcode_product_info = $this->get_product( $args_in['id'] );
				$return_string = $this->render_shortcode_item();
			}

			return ( $return_string );
		}

		//--------------------------------------------------------------------------------------------------------------
		// --------------------------------- A M A Z O N   O P T I O N S   P A G E -------------------------------------
		//--------------------------------------------------------------------------------------------------------------
		/**
		 * Read the Amazon options from the DB and hold them in the options property
		 *
		 * @return array|null
		 */
		public function options_get(  ) {
			$this->options = $this->util->options_get( $this->option_fields, $this->field_prefix );
			return( $this->options );
		}


		/**
		 * Tell the admin system about our page (triggered by admin_menu action)
		 */
		function options_page_create() {
			add_submenu_page(
				'edit.php?post_type=' . $this->post_type, // Parent Slug
				'Amazon Options',                         // Page Title
				'Amazon Options',                         // Menu title
				'publish_posts',                          // Capability
				'rmw_wpaz_options',                       // Menu Slug
				array( $this, 'options_display_page'),
				1 // Position
			);
		}


		/**
		 * Set up a form nonce field for the settings form
		 */
		public function nonce_field( ) {
			wp_nonce_field( basename( $this->plugin_file ), $this->nonce );
		}


		/**
		 * Get option fields from the _POST var (form). Fill with defaults if none posted
		 *
		 * @return array
		 */
		function options_get_from_post( ) {
			return( $this->util->options_get_from_post( $this->option_fields, $this->field_checkboxes ) );
		}


		/**
		 * Save the specified options to the database
		 *
		 * @param $option_vals
		 */
		private function options_save( $option_vals ) {
			$this->util->options_save( $option_vals, $this->field_prefix );
		}


		/**
		 * Validate the form options
		 *
		 * @param $option_vals
		 *
		 * @return bool
		 */
		private function options_validate( $option_vals ) {

			// No validations needed so far
			return( true );
		}

		/**
		 * Display the Subscribe Form Settings page
		 */
		function options_display_page( ) {

			// if we are doing an UPDATE action from the form/user...
			$form_action = ( isset( $_POST['action'] ) ? $_POST['action'] : 'none' );
			if( $form_action == 'update' ) {

				// Get the post values from the FORM...
				$post_vals = $this->options_get_from_post();

				//  We are requested to update the DB. Verify the nonce before saving...
				if ( isset( $_POST[$this->nonce] ) && wp_verify_nonce( $_POST[$this->nonce], basename( $this->plugin_file ) ) ) {

					// ...nonce verified. Validate the option vals.
					if ( $this->options_validate( $post_vals ) ) {

						// ...Options values validated. Save them!
						$this->options_save( $post_vals );

						$this->util->set_admin_notice( 'The Amazon options were saved!', 'success' );
					}

				}
			}

			// Enqueue the CSS and JS for the edit page
			wp_enqueue_style(  'rmw_wpaz_options_css', $this->plugin_url . '/css/options_page.css' );
			wp_enqueue_script( 'rmw_wpaz_options_js',  $this->plugin_url . '/js/options_page.js' );


			// Reload the options from the DB
			$this->options_get();

			// Display the option form
			include( $this->plugin_path . '/templates/options_page.php' );
		}


		//--------------------------------------------------------------------------------------------------------------
		//------------------------------- T E M P L A T E   F U N C T I O N S ------------------------------------------
		//--------------------------------------------------------------------------------------------------------------
		function format_price( $price, $default_zero = "none" ) {
			$return_string = $default_zero;

			if ( !empty( $price ) ) {
				$return_string = "$" . number_format( floatval( $price / 100 ), 2 );
			}

			return( $return_string );
		}


		private function get_products_by_last_fetch( ) {

			$return_list = null;

			$product_count = wp_count_posts( $this->post_type );
			$this->fetch_count = $product_count->publish;
			$this->fetch_prods = $product_count;
			$search_limit  = ( ( $this->fetch_count  <= 24 ) ? 1 : intval( $this->fetch_count / 24 ) + 1 );

			$query_args = array(
				'post_type'      => $this->post_type,
				'meta_key'       => $this->field_prefix . "last_fetch",
				'post_status'    => 'publish',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'posts_per_page' => $search_limit,
			);
			$product_query   = new WP_Query( $query_args );
			$this->fetch_sql = $product_query->request;
			$products        = $product_query->posts;
			if( !empty( $products ) && ( count( $products ) > 0 ) ) {
				$return_list = array();

				foreach( $products as $product_rec ) {
					$product_rec->meta = $this->get_fields( $product_rec->ID );
					$return_list[] = $product_rec;
				}
			}

			return( $return_list );
		}

		private function get_product( $product_id ) {

			$product = $this->get_fields( $product_id );
			$product['id']           = $product_id;
			$product['title']        = get_the_title( $product_id );
			$product['description']  = get_post_field('post_content', $product_id );

			return( $product );
		}

		/**
		 * Triggered by the single_template filter. Select the appropriate Press template
		 *
		 * @param $original
		 *
		 * @return mixed
		 */
		public function single_template( $original ) {

			return( $this->util->plugin_template_override(
				$original,
				'single-' . $this->post_type . '.php',
				$this->plugin_path . 'templates/',
				'',
				$this->post_type
			) );
		}




		/**
		 * Triggered by the archive_template filter. Select the appropriate press template for archive listings
		 *
		 * @param $original
		 *
		 * @return mixed
		 */
		public function archive_template( $original ) {

			return( $this->util->plugin_template_override(
				$original,
				'archive-' . $this->post_type . '.php',
				$this->plugin_path . 'templates/',
				'',
				$this->post_type,
				'product_categories'
			) );
		}


		/**
		 * Render the HTML code to preview a Product in the Product listing. This uses the plugin's built-in template by default,
		 * but will load a template-specific override template if available.
		 */
		public function archive_item( ) {

			if ( file_exists( get_template_directory() . '/archive-product-item.php' ) ) {
				include( get_template_directory() . '/archive-product-item.php' );
			} else {
				include( $this->plugin_path . '/templates/archive-product-item.php' );
			}
		}


		/**
		 * Render the HTML code to preview a Product in the Product listing. This uses the plugin's built-in template by default,
		 * but will load a template-specific override template if available.
		 */
		public function archive_list( ) {

			if ( file_exists( get_template_directory() . '/archive-product-list.php' ) ) {
				include( get_template_directory() . '/archive-product-list.php' );
			} else {
				include( $this->plugin_path . '/templates/archive-product-list.php' );
			}
		}


		/**
		 * Get the URL of a product
		 *
		 * @param bool $az_click
		 *
		 * @return mixed
		 */
		public function get_url( $az_click = true ) {

			if ( $az_click ) {
				// Return the URL of the product on Amazon
				$return_url = $this->field_vals['url'];
			} else {
				// Return the permalink of the product
				$return_url = get_permalink();
			}

			return( $return_url );
		}


		/**
		 * Get the URL target of a press item
		 *
		 * @return mixed
		 */
		public function get_url_target( $az_click = true ) {

			if ( $az_click ) {
				// Target the URL to a new window/tab
				$target_string = "target='_blank'";
			} else {
				// Target the URL to the same window/tab
				$target_string = "";
			}

			return( $target_string );
		}


		/**
		 * Enqueue style sheet(s) for displaying events to the end user. This loads the plugin's built-in style sheet,
		 * followed by any template-specific style sheet
		 */
		public function get_header(  ) {

			// If the page is dealing with RMW Product Post Type (and not some other post type)...
			if ( get_post_type() == $this->post_type ) {

				// Is it the archive page?
				if ( is_archive() ) {

					$this->util->enqueue_style_override( 'rwm-wpaz-product-listing-css', $this->plugin_url, '/css/product-listing.css' );
					$this->util->enqueue_script_override( 'rwm-wpaz-product-listing-js', $this->plugin_url, '/js/product-listing.js', array( 'jquery' ) );

				} else {

					// Not an archive page, enqueue the styles for the event single
					$this->util->enqueue_style_override( 'rwm-wpaz-product-single-css', $this->plugin_url, '/css/product-single.css' );
				}
			}
		}


		//--------------------------------------------------------------------------------------------------------------
		//------------------------------- A R C H I V E   O R D E R ----------------------------------------------------
		//--------------------------------------------------------------------------------------------------------------
		function archive_order( $wp_query ) {

			// Don't mess with anything happening inside the cron job
			if ( !defined( 'DOING_CRON' ) ) {

				// Don't mess with the order if it is in the admin section
				if ( !$this->is_admin ) {

					// For SEARCH queries, make sure we're adding the Press post type to searches
					if ( $wp_query->is_main_query() ) {
						if ( $wp_query->is_search ) {
							$post_searches = $wp_query->get( 'post_type' );
							$post_searches[] = $this->post_type;
							$wp_query->set( 'post_type', $post_searches );
						}
					}


					// The rest is about manipulating archive queries. First, don't mess with singleton queries...
					if ( !$wp_query->is_single( ) ) {

						// For the rest of this, only concern ourselves with Press post types
						$post_type = ( isset( $wp_query->query['post_type'] ) ? $wp_query->query['post_type'] : '' );
						if ( $post_type == $this->post_type ) {
							$wp_query->set( 'meta_key',       $this->field_prefix . "sales_rank" );
							$wp_query->set( 'meta_query', array(
								'relation' => 'AND',
								'meta_is_ranked' => array(
									'key'     => $this->field_prefix . 'is_ranked',
									'compare' => 'EXISTS',
									'type'    => 'numeric'
								),
								'meta_sales_rank' => array(
									'key'     => $this->field_prefix . 'sales_rank',
									'compare' => 'EXISTS',
									'type'    => 'numeric'
								)
							) );
							$wp_query->set( 'orderby', array( 'meta_is_ranked' => 'DESC', 'meta_sales_rank' => 'ASC' ) );
							$wp_query->set( 'posts_per_page', self::POSTS_PER_PAGE );
						}
					}
				} else {

					// On the admin side, do special sorting for the list query...
					if ( !$wp_query->is_single( ) ) {

						// Only concern ourselves with Press post types
						$post_type = ( isset( $wp_query->query['post_type'] ) ? $wp_query->query['post_type'] : '' );
						if ( $post_type == $this->post_type ) {

							// Only intervene for special Press ordering
							$orderby = $wp_query->get( 'orderby' );
							if ( $orderby == $this->field_prefix . "sales_rank" ) {

								$order = $wp_query->get( 'order' );
								$op_order = ( ( $order == 'asc' ) ? 'desc' : 'asc' );
								$wp_query->set( 'meta_query', array(
									'relation' => 'AND',
									'meta_is_ranked' => array(
										'key'     => $this->field_prefix . 'is_ranked',
										'compare' => 'EXISTS',
										'type'    => 'numeric'
									),
									'meta_sales_rank' => array(
										'key'     => $this->field_prefix . 'sales_rank',
										'compare' => 'EXISTS',
										'type'    => 'numeric'
									)
								) );
								$wp_query->set( 'orderby', array( 'meta_is_ranked' => $op_order, 'meta_sales_rank' => $order ) );

							}
							if ( $orderby == $this->field_prefix . "last_fetch" ) {

								$wp_query->set( 'meta_key', $this->field_prefix . "last_fetch" );
								$wp_query->set('orderby',   'meta_value');
							}
						}
					}
				}
			}
		}


	}

}
