<?php
/**
 * Primary source object for the RMW Utilities plugin type
 */
if ( ! class_exists( 'RMW_Utilities' ) ) {


	class RMW_Utilities {

		private $plugin_path, $plugin_url;

		public $admin_notices = null;

		private $page_templates = null;

		const META_SUPPORT_DATEPICKER = 1;
		const META_SUPPORT_MEDIA_GAL  = 2;
		private $meta_support       = null;
		private $meta_support_files = null;

		private $customizer = null;

		private $admin_columns = null;

		private $log_slug         = null;
		private $log_option_name  = null;
		private $log_lines        = null;

		public function __construct( ) {

			$this->plugin_path = plugin_dir_path( __DIR__ );
			$this->plugin_url  = plugins_url( '', __DIR__ );
		}


		//--------------------------------------------------------------------------------------------------------------
		//-------------------------------- A D M I N   N O T I C E S ---------------------------------------------------
		//--------------------------------------------------------------------------------------------------------------
		/**
		 * Simple queuing up of notices to be displayed as admin_notices
		 *
		 * @param $message - message textg
		 * @param string $type - 'info', 'error' or 'warning'
		 */
		public function set_admin_notice( $message, $type = 'info'  ) {
			if ( empty( $this->admin_notices ) ) {
				$this->admin_notices = array( );
			}

			$this->admin_notices[] = array( 'message' => $message, 'type' => $type );
		}


		/**
		 * write the HTML to display any queued admin notices at the top of the editingpage
		 */
		public function show_admin_notices( ) {
			if ( !empty( $this->admin_notices ) ) {
				foreach( $this->admin_notices as $notice_info ) {
					print( '<div class="notice notice-' . $notice_info['type'] . '">' .
					       '<p><strong>' . strtoupper( $notice_info['type'] ) . ':</strong> ' . $notice_info['message'] . '</p></div>'  );
				}
			}
		}


		//--------------------------------------------------------------------------------------------------------------
		//-------------------------------- E N Q U E U E   R E S O U R C E S -------------------------------------------
		//--------------------------------------------------------------------------------------------------------------
		/**
		 * Enqueue a style from a plugin, and also enqueue an overriding style from the template directory if it exists
		 *
		 * @param $handle     - style handle (required by WP)
		 * @param $plugin_url - URL of the plugin directory
		 * @param $style_path - path of the CSS file relative to the plugin dir and the template dir
		 */
		public function enqueue_style_override( $handle, $plugin_url, $style_path ) {
			wp_enqueue_style( $handle . '_default', $plugin_url . $style_path );
			if ( file_exists( get_template_directory() . $style_path ) ) {
				wp_enqueue_style( $handle . '_theme', get_template_directory_uri() . $style_path );
			}
		}


		/**
		 * Enqueues a javascript from a plugin. If a corresponding script exists on the Theme directory, then that
		 * one is enqueued instead
		 *
		 * @param $handle - style handle (required by WP)
		 * @param $plugin_url - URL of the plugin directory
		 * @param $script_path - path of the JS file relative to the plugin dir and the template dir
		 * @param array $deps
		 */
		public function enqueue_script_override( $handle, $plugin_url, $script_path, $deps = array() ) {

			if ( file_exists( get_template_directory() . $script_path ) ) {
				wp_enqueue_script( $handle . "_theme", get_template_directory() . $script_path, $deps );
			} else {
				wp_enqueue_script( $handle . "_default", $plugin_url . $script_path, $deps );
			}
		}



		//--------------------------------------------------------------------------------------------------------------
		//-------------------------------- P L U G I N   T E M P L A T E S ---------------------------------------------
		//--------------------------------------------------------------------------------------------------------------

		/**
		 * Select a template looking first in an optional theme sub-directory. If it isn't found there, look in a
		 * subdirectory of the calling plugin. Designed to be called as a single_template or archive_template filter,
		 * for example.
		 *
		 * @param $default        - the default template selected by WordPress
		 * @param $template_name  - the file name of the template to look for
		 * @param $plugin_path    - the path in the plugin directory to look in
		 * @param $template_path  - the subdirectory in the Theme sub-directory to look in (optional)
		 * @param $this_post_type - specify a post type to restrict this to operating only for that post type
		 * @param $this_post_type - specify a taxonomy to restrict this to operating only for that post type
		 *
		 * @return string - the selected template file
		 */
		public function plugin_template_override( $default, $template_name, $plugin_path, $template_path = '', $this_post_type = null, $this_taxonomy = null ) {

			$return_template = $default;

			// Only concern ourselves with Event post types
			if ( empty( $this_post_type ) || is_post_type_archive ( $this_post_type ) || is_singular( $this_post_type ) ||
			     ( !empty( $this_taxonomy ) && is_tax( $this_taxonomy ) ) ) {

				// First try to locate the template in the template dir_path
				$base_name = $template_path . $template_name;
				$template = locate_template( $base_name );
				if ( $template && ! empty( $template ) ) {
					$return_template = $template;
				} else {
					// If the template does not exist in the template dir path
					$base_name = $plugin_path . $template_name;

					// If the file exists as a template in our plugin tree
					if ( file_exists( $base_name ) ) {
						$return_template = $base_name;
					}
				}
			}

			return( $return_template );
		}

		//--------------------------------------------------------------------------------------------------------------
		//------------------------------- T E M P L A T E   C U S T O M I Z E R ----------------------------------------
		//--------------------------------------------------------------------------------------------------------------
		/**
		 * Initialize a customizer section for our settings
		 *
		 * @param $section_name
		 * @param $section_title
		 * @param $settings
		 * @param int $priority
		 */
		public function customizer_init( $section_name, $section_title, $settings, $priority = 40 ) {

			$this->customizer = array(
				'section_name'  => $section_name,
				'section_title' => $section_title,
				'priority'      => $priority,
				'settings'      => $settings
			);

			// Set Customizer template customization fields
			add_action('customize_register', array( $this, 'customizer_register' ) );

		}


		/**
		 * Load all the theme customization settings from_db
		 */
		public function customizer_get(  ) {

			foreach( $this->customizer['settings'] as $setting_key => $setting_info ) {
				$this->customizer['settings'][$setting_key]['value'] = get_theme_mod( $setting_key );
				if ( empty( $this->customizer['settings'][$setting_key]['value'] ) ) {
					$this->customizer['settings'][$setting_key]['value'] = $this->customizer['settings'][$setting_key]['default'];
				}
			}

			return( $this->customizer['settings'] );
		}



		/**
		 * Register the customization settings into the WP Customizer
		 * @param $wp_customize
		 */
		public function customizer_register( $wp_customize ) {
			$wp_customize->add_section(
				$this->customizer['section_name'],
				array(
					'title'    => __( $this->customizer['section_title'], 'text-domain'),
					'priority' => $this->customizer['priority'],
				)
			);

			foreach( $this->customizer['settings'] as $setting_key => $setting_args ) {
				if ( $setting_args['type'] == 'image' ) {
					$wp_customize->add_setting( $setting_key, array( 'default' => $setting_args['default'] ) );
					$wp_customize->add_control(
						new WP_Customize_Image_Control(
							$wp_customize,
							$setting_key,
							array(
								'label'      => __( $setting_args['label'], 'theme_name' ),
								'section'    => $this->customizer['section_name'],
								'settings'   => $setting_key,
							)
						)
					);
				} else if ( $setting_args['type'] == 'text' ) {
					$wp_customize->add_setting(
						$setting_key,
						array(
							'default'           => $setting_args['default'],
							'type'              => 'theme_mod',
							'capability'        => 'edit_theme_options',
							'transport'         => '',
							'sanitize_callback' => 'esc_textarea',
						)
					);
					$wp_customize->add_control(
						$setting_key,
						array(
							'default'     => $setting_args['default'],
							'type'        => 'text',
							'priority'    => 10,
							'section'     => $this->customizer['section_name'],
							'label'       => __( $setting_args['label'], 'textdomain' ),
							'description' => '',
						)
					);

				} else if ( $setting_args['type'] == 'url' ) {
					$wp_customize->add_setting(
						$setting_key,
						array(
							'default'           => $setting_args['default'],
							'type'              => 'theme_mod',
							'capability'        => 'edit_theme_options',
							'transport'         => '',
							'sanitize_callback' => 'esc_url',
						)
					);
					$wp_customize->add_control(
						$setting_key,
						array(
							'default'     => $setting_args['default'],
							'type'        => 'url',
							'priority'    => 10,
							'section'     => $this->customizer['section_name'],
							'label'       => __( $setting_args['label'], 'textdomain' ),
							'description' => '',
						)
					);

				}
			}

		}


		//--------------------------------------------------------------------------------------------------------------
		//-------------------------------- P L U G I N   P A G E   T E M P L A T E S -----------------------------------
		//--------------------------------------------------------------------------------------------------------------
		public function page_template_init( $template_list, $plugin_path, $template_dir ) {

			$this->page_templates = array( );

			$this->page_templates['list']         = $template_list;
			$this->page_templates['plugin_path']  = $plugin_path;
			$this->page_templates['template_dir'] = $template_dir;

			// Handle the auto inclusion of the contact us Page Template without requiring it to be in the theme dir
			if ( version_compare( floatval( get_bloginfo( 'version' ) ), '4.7', '<' ) ) {
				add_filter( 'page_attributes_dropdown_pages_args', array( $this, 'page_templates_register' ) );
			} else {
				add_filter( 'theme_page_templates', array( $this, 'page_templates_add' ) );
			}
			add_filter( 'wp_insert_post_data', array( $this, 'page_templates_register' ) );
			add_filter( 'template_include',    array( $this, 'page_templates_view' ) );
		}

		/**
		 * Adds our Contact Us template to the page dropdown for v4.7+
		 *
		 */
		public function page_templates_add( $posts_templates ) {
			$posts_templates = array_merge( $posts_templates, $this->page_templates['list'] );
			return $posts_templates;
		}


		/**
		 * Adds our templates to the pages cache in order to trick WordPress
		 * into thinking the template file exists where it doesn't really exist.
		 */
		public function page_templates_register( $atts ) {

			// Create the key used for the themes cache
			$cache_key = 'page_templates-' . md5( get_theme_root() . '/' . get_stylesheet() );

			// Retrieve the cache list.
			// If it doesn't exist, or it's empty prepare an array
			$templates = wp_get_theme()->get_page_templates();
			if ( empty( $templates ) ) {
				$templates = array();
			}
			// New cache, therefore remove the old one
			wp_cache_delete( $cache_key , 'themes');

			// Now add our template to the list of templates by merging our templates
			// with the existing templates array from the cache.
			$templates = array_merge( $templates, $this->page_templates['list'] );

			// Add the modified cache to allow WordPress to pick it up for listing
			// available templates
			wp_cache_add( $cache_key, $templates, 'themes', 1800 );

			return $atts;
		}


		/**
		 * Checks if the template is assigned to the page
		 */
		public function page_templates_view( $template ) {

			// Get global post
			global $post;

			$return_template = $template;

			// If we have a post...
			if ( $post ) {

				// If they chose one of our templates...
				if ( isset( $this->page_templates['list'][get_post_meta($post->ID, '_wp_page_template', true)] ) ) {

					$file = $this->page_templates['plugin_path'] . "/" . $this->page_templates['template_dir'] . "/" . get_post_meta( $post->ID, '_wp_page_template', true );

					// If the file exists as a template in our plugin tree
					if ( file_exists( $file ) ) {
						$return_template = $file;
					}
				}
			}
			// Return template
			return $return_template;
		}

		//--------------------------------------------------------------------------------------------------------------
		//-------------------------------- M E T A   B O X -------------------------------------------------------------
		//--------------------------------------------------------------------------------------------------------------
		/*
		 * Initialize support for meta boxes
		 */
		private function meta_support_init( ) {

			// If meta support has not been initialized
			if ( empty( $this->meta_support ) ) {

				// Initialize it
				$this->meta_support = array( );

				$this->meta_support_files = array(
					self::META_SUPPORT_DATEPICKER => array(
						'css' => array(
							'rmw_meta_datepicker'   => $this->plugin_url . '/css/jquery-ui-datepicker.css',
							'rmw_meta_ui-theme'     => $this->plugin_url . '/css/jquery-ui-theme.css',
							'rmw_meta_ui-structure' => $this->plugin_url . '/css/jquery-ui-structure.css'
						),
						'js' => array(
							'rmw_meta_datepicker_js' => $this->plugin_url . '/js/meta_datepicker.js'
						)
					),
					self::META_SUPPORT_MEDIA_GAL => array(
						'js' => array(
							'rmw_meta_media_js' => $this->plugin_url . '/js/meta_media_upload.js'
						)
					),
				);
			}

		}


		/**
		 * Request specific support functionality for meta boxes ( see META_SUPPORT_* constants above)
		 *
		 * @param $support_request_id - one of the META_SUPPORT_* constants above
		 */
		public function meta_request_support( $support_request_id ) {
			$this->meta_support_init();
			$this->meta_support[$support_request_id] = true;
		}


		/**
		 * Enqueue all requested support files for the meta box
		 *
		 */
		public function meta_enqueue_support(  ) {

			$this->meta_support_init();
			wp_enqueue_style( 'rmw_meta_css', $this->plugin_url . '/css/meta_edit.css' );

			foreach( $this->meta_support as $support_id => $support_on ) {

				if ( $support_on ) {

					$support_files = $this->meta_support_files[$support_id];
					foreach( $support_files as $support_file_type => $support_file_list ) {
						if ( $support_file_type == 'css' ) {
							foreach( $support_file_list as $style_handle => $support_file_path ) {
								wp_enqueue_style( $style_handle,   $support_file_path );

							}
						}
						if ( $support_file_type == 'js' ) {
							foreach( $support_file_list as $script_handle => $support_file_path ) {
								wp_enqueue_script( $script_handle, $support_file_path, null, time(), true );
							}
						}
					}
				}
			}

		}


		/**
		 * Save a field from a meta form into the database as post_meta
		 *
		 * @param $post_id
		 * @param $field_name
		 * @param $field_value
		 */
		public function meta_save_field( $post_id, $field_name, $field_value ) {

			// Get the current value from the database
			$current_value = get_post_meta( $post_id, $field_name, true );

			// If a new meta value was added and there was no previous value, add it.
			if ( ( $field_value != '' )  && ( $current_value == '' ) ) {
				add_post_meta( $post_id, $field_name, $field_value, true );

				// If the new meta value does not match the old value, update it.
			} elseif ( $field_value != '' ) {
				update_post_meta( $post_id, $field_name, $field_value );

				// If there is no new meta value but an old value exists, delete it.
			} else {
				delete_post_meta( $post_id, $field_name );
			}
		}

		//--------------------------------------------------------------------------------------------------------------
		//-------------------------------- I M A G E   H E L P E R S ---------------------------------------------------
		//--------------------------------------------------------------------------------------------------------------
		/**
	     * Given an image url, get the WP attachment ID
		 *
		 * @param $image_url
		 *
		 * @return mixed
		 */
		public function image_get_attachment_id( $image_url ) {

			global $wpdb;

			$attachment = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid='%s';", $image_url ) );
			return $attachment[0];
		}


		/**
		 * Get an image's thumbnail URL from it's base URL
		 *
		 * @param $image_url - the Image URL
		 * @param $size      - the thumbnail size
		 *
		 * @return bool
		 */
		public function image_get_thumb( $image_url, $size ) {

			$return_val = false;

			$attachment_id = $this->image_get_attachment_id( $image_url );

			if ( !empty( $attachment_id ) ){
				$return_val = wp_get_attachment_image_src( $attachment_id, $size );
			}

			return( $return_val );
		}


		//--------------------------------------------------------------------------------------------------------------
		//-------------------------------- A D M I N   C U S T O M   C O L U M N S -------------------------------------
		//--------------------------------------------------------------------------------------------------------------
		/**
		 * Initialize the property for managing admin columns
		 */
		private function admin_columns_init( ) {

			// If the admin_columns property is null, make it an array
			if ( empty( $this->admin_columns ) ) {
				$this->admin_columns = array( );
			}
		}


		/**
		 * Calling plugin can call this repeatedly to register columns to show on the admin list.
		 *
		 * @param $post_type        - The post_type supported by the plugin
		 * @param $column_head      - The Column Heading
		 * @param $meta_field_name  - The name of the meta field to include
		 * @param string $render_as - = 'raw' to show the raw field data in the column (default)
		 *                            = 'date|{date-string}' for date field, followed by the PHP date format string
		 *                            = 'image_url|{image-size}' for an image rendered at a specified size (WP size string)
		 *                            = function() to use a custom render function
		 * @param bool $is_sortable - true if the column will be sortable
		 *                              NOTE: actual sorting must be handled by the caller via add_filter('pre_get_posts', 'some_function' ) ;
		 */
		public function admin_columns_request( $post_type, $column_head, $meta_field_name, $render_as = 'raw', $is_sortable = false ) {

			// Verify initialization of the admin_columns support
			$this->admin_columns_init( );

			// Add the columns info
			$new_col_info = array(
				'column_head' => $column_head,
				'field_name'  => $meta_field_name,
				'render_as'   => $render_as,
				'is_sortable' => $is_sortable
			);
			if ( !isset( $this->admin_columns[$post_type] ) ) {
				$this->admin_columns[$post_type] = array();
			}
			$this->admin_columns[$post_type][] = $new_col_info;
		}


		/**
		 * Calling plugin should call this after at least one call to $util->admin_columns_request
		 * This will trigger the rendering of the column(s) in the Admin Dashboard of Wordpress
		 */
		public function admin_columns_register( ) {

			// If we have some admin_column data
			if ( !empty( $this->admin_columns ) ) {


				// Go through each item set up by the admin_columns_request function
				foreach( $this->admin_columns as $post_type => $column_list ) {

					// add the appropriate filters
					add_filter( 'manage_' . $post_type . '_posts_columns',       array( $this, 'admin_columns_add' ) );
					add_filter( 'manage_edit-' . $post_type . '_sortable_columns',    array( $this, 'admin_columns_sortable' ) );

					add_action( 'manage_' . $post_type . '_posts_custom_column', array( $this, 'admin_columns_render' ), 10, 2 );
				}
			}
		}


		/**
		 * Should not be called directly. Callback method for Wordpress filters
		 *
		 * @param $columns
		 *
		 * @return array
		 */
		public function admin_columns_add( $columns ) {

			$new_columns = array();
			foreach( $this->admin_columns as $post_type => $column_list ) {
				foreach( $column_list as $column_info ) {
					$new_columns[$column_info['field_name']] = $column_info['column_head'];
				}
			}

			return array_merge( $columns, $new_columns );
		}


		/**
		 * Should not be called directly. Callback method for Wordpress filters
		 *
		 * @param $sortable_columns
		 *
		 * @return array
		 */
		public function admin_columns_sortable( $sortable_columns ) {

			foreach( $this->admin_columns as $post_type => $column_list ) {
				foreach( $column_list as $column_info ) {
					if ( $column_info['is_sortable'] ) {
						$sortable_columns[$column_info['field_name']] = $column_info['field_name'];
					}
				}
			}

			return ( $sortable_columns );
		}


		/**
		 * Should not be called directly. Callback method for Wordpress filters
		 *
		 * @param $column_field_name
		 * @param $post_id
		 */
		public function admin_columns_render( $column_field_name, $post_id ) {

			// Start by preparing to echo nothing
			$echo_string = '';

			// Go through each item set up by the admin_columns_request function
			foreach( $this->admin_columns as $post_type => $column_list ) {

				foreach( $column_list as $column_info ) {

					// If the column field name matches the one we're trying to render
					if ( $column_info['field_name'] == $column_field_name ) {

						// Is the render type a callback function?
						if ( is_callable( $column_info['render_as'] ) ) {

							// Call the callback function
							$echo_string = $column_info['render_as']( $post_id );
						} else {
							// Get the meta data for this column
							$column_data = get_post_meta( $post_id, $column_info['field_name'], true );

							// Are they requesting just raw data?
							if ( $column_info['render_as'] == 'raw' ) {

								// Just echo back the raw data
								$echo_string = $column_data;

							// Are they requesting a date string?
							} elseif( substr( $column_info['render_as'], 0, 4) == 'date' ) {
								$date_parts = explode( "|", $column_info['render_as'] );
								$echo_string = date( $date_parts[1], strtotime( $column_data ) );

							// Are they rendering an image_url?
							} elseif( substr( $column_info['render_as'], 0, 9) == 'image_url' ) {
								$image_parts = explode( "|", $column_info['render_as'] );
								$echo_string = "<img src='" . $this->image_get_thumb( $column_data, $image_parts[1] )[0] . "' />";
							}
						}
						break;
					}
				}
			}

			echo $echo_string;
		}


		//--------------------------------------------------------------------------------------------------------------
		//-------------------------------- P L U G I N   O P T I O N S -------------------------------------------------
		//--------------------------------------------------------------------------------------------------------------
		/**
		 * Get plugin option fields from the _POST var (form). Fill with defaults if none posted
		 *
		 * @param $option_fields - list of prospective option fields as $name => $default_value
		 * @param $checkboxes    - list of fields represented as checkboxes
		 *
		 * @return array - array of $option_name => $option_val (from post)
		 */
		public function options_get_from_post( $option_fields, $checkboxes = null ) {
			$return_vals = array();
			foreach( $option_fields as $key => $default ) {
				if ( !empty( $checkboxes ) && in_array( $key, $checkboxes ) ) {
					$field_value = ( isset( $_POST[ $key ] ) ? '1' : '0' );
				} else {
					$field_value = ( isset( $_POST[$key] ) ? $_POST[$key] : $default );
				}
				$return_vals[$key] = $field_value;
			}

			return( $return_vals );
		}


		/**
		 * Save the plugin options to the database
		 *
		 * @param $option_vals - array of ( $option_name => $value )
		 * @param $db_prefix   - prefix to add to option name before saving to DB (to ensure uniqueness and grouping)
		 */
		public function options_save( $option_vals, $db_prefix = '' ) {
			foreach( $option_vals as $key => $value ) {
				$db_key = $db_prefix . $key;
				update_option( $db_key, $value );
			}
		}


		/**
		 * Read options from the DB
		 *
		 * @param $option_fields - array of option fields as ( $option_name => $default_value )
		 * @param $option_prefix - prefix of option name when stored in DB.
		 *
		 * @return array
		 */
		public function options_get( $option_fields, $option_prefix ) {

			$return_list = array( );
			foreach( $option_fields as $key => $default ) {
				$db_name = $option_prefix . $key;
				$return_list[$key] = get_option( $db_name, $default );
			}

			return( $return_list );
		}


		public function pretty_r( $object ) {
			$search_chars  = array( " ",     "<",    ">" );
			$replace_chars = array( "&nbsp", "&lt;", "&gt;" );
			print( nl2br( str_replace( $search_chars, $replace_chars, print_r( $object, true ) ) ) );
		}

		//--------------------------------------------------------------------------------------------------------------
		//-------------------------------- P L U G I N   O P T I O N S -------------------------------------------------
		//--------------------------------------------------------------------------------------------------------------
		private function log_slug_sanitize( $log_slug, $date = null ) {

			// Create the stuff around the log name for the unique option record
			$datestamp         = ( !empty( $date ) ? strtotime( $date ) : time( ) );
			$option_name_extra = "rmwlog_" . "_" . date( "Ymd", $datestamp );

			// Strip out bad letters from the slug
			$log_slug       = preg_replace("[^\w\s\d\.\-_~,;:\[\]\(\]]", '', $log_slug );

			// Shorten the slug if necessary and save it.
			$this->log_slug = substr( $log_slug, 0, ( 120 - strlen( $option_name_extra ) ) );

			// Save the database option name
			$this->log_option_name = "rmwlog_" . $this->log_slug . "_" . date( "Ymd", $datestamp );
		}

		private function log_line_format( $message, $data = '' ) {
			$log_package = array(
				'date'    => date( "H:i:s"),
				'message' => $message,
			);
			if ( !empty( $data ) ) {
				$log_package['data']  = $data;
			}

			return( $log_package );
		}

		public function log_get( $log_slug, $date ) {
			$this->log_slug_sanitize( $log_slug, $date );
			$log_lines = get_option( $this->log_option_name, '' );
			return( !empty( $log_lines ) ? json_decode( $log_lines, true ) : null ) ;
		}

		public function log_start( $log_slug ) {

			$this->log_slug_sanitize( $log_slug );
			if ( empty( $this->log_lines ) ) {
				$this->log_lines = array( );
			}
			$this->log_lines[$this->log_slug] = $this->log_get( $log_slug, '' );
			if ( empty( $this->log_lines[$this->log_slug] ) ) {
				$this->log_lines[$this->log_slug] = array( );
			}
			$this->log_message( "<strong>-------------------- Starting Log --------------------</strong>" );
		}

		public function log_message( $message, $data = '' ) {

			if ( isset( $this->log_slug ) && ( isset( $this->log_lines[$this->log_slug] ) ) ) {
				$this->log_lines[$this->log_slug][] = $this->log_line_format( $message, $data );
				update_option( $this->log_option_name, json_encode( $this->log_lines[$this->log_slug] ) );
			}
		}

		public function log_end( ) {
			$this->log_message( "-------------------- Log End --------------------" );
			if ( isset( $this->log_slug ) && ( isset( $this->log_lines[$this->log_slug] ) ) ) {
				update_option( $this->log_option_name, json_encode( $this->log_lines[$this->log_slug] ) );
			}
		}
	}
}

