<?php


/**
 * Chemical database class for WordPress.
 *
* @package   Vestachemical
* @author    Joren de Graaf jorendegraaf@gmail.com
* @license   GPL-2.0+
* @link      http://www.jorendegraaf.nl
* @copyright 2014 Joren de Graaf
 */

/**
 * Plugin class. This class should ideally be used to work with the
 * public-facing side of the WordPress site.
 *
 * If you're interested in introducing administrative or dashboard
 * functionality, then refer to `class-vestachemical-admin.php`
 *
 * @package Vestachemical
 * @author  Joren de Graaf jorendegraaf@gmail.com
 */
class Vestachemical {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   1.0.0
	 *
	 * @var     string
	 */
	const VERSION = '1.0.0';

	/**
	 * Unique identifier for your plugin.
	 *
	 *
	 * The variable name is used as the text domain when internationalizing strings
	 * of text. Its value should match the Text Domain file header in the main
	 * plugin file.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $plugin_slug = 'vestachemical';

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin by setting localization and loading public scripts
	 * and styles.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {
		register_activation_hook( __FILE__, 'db_install' );
		add_option( "vesta_db_version", "1.0" );

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Activate plugin when new blog is added
		add_action( 'wpmu_new_blog', array( $this, 'activate_new_site' ) );

		// Load public-facing style sheet and JavaScript.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		/* Define custom functionality.
		 * Refer To http://codex.wordpress.org/Plugin_API#Hooks.2C_Actions_and_Filters
		 */
		add_action( '@TODO', array( $this, 'action_method_name' ) );
		add_filter( '@TODO', array( $this, 'filter_method_name' ) );
		
		// Register custom posts types and taxonomies for chemicals
		add_action( 'init', array($this, 'chemical_post_type') );
		add_action( 'init', array($this,'chemical_taxonomy'), 0 );
		add_action( 'add_meta_boxes', array($this, 'chemical_cas_box') );
		add_action( 'save_post', array($this, 'chemical_cas_box_save' ));
		add_action( 'chemical_category_add_form_fields', array($this,'chemical_taxonomy_group_field'), 10, 2 );
		add_action( 'chemical_category_edit_form_fields', array($this, 'chemical_taxonomy_edit_meta_field'), 10, 2 );
		add_action( 'edited_category', array($this,'save_chemical_taxonomy_custom_meta'), 10, 2 );  
		add_action( 'create_category', array($this,'save_chemical_taxonomy_custom_meta'), 10, 2 );
		add_filter( 'pre_get_posts' , array($this,'include_custom_post_types') );

	}
	/*	Functionality for chemicals
	 *	@TODO Add an template engine to separate PHP and HTML code.
	 */
	function chemical_post_type() {
		  global $wp_taxonomies;
   		  $wp_taxonomies['post_tag']->label = 'Applications';
		  $labels = array(
		    'name'               => _x( 'Chemicals', 'post type general name' ),
		    'singular_name'      => _x( 'Chemicals', 'post type singular name' ),
		    'add_new'            => _x( 'Add New', 'chemical' ),
		    'add_new_item'       => __( 'Add new chemical' ),
		    'edit_item'          => __( 'Edit chemicals' ),
		    'new_item'           => __( 'New chemical' ),
		    'all_items'          => __( 'All chemicals' ),
		    'view_item'          => __( 'View chemicals' ),
		    'search_items'       => __( 'Search chemicals' ),
		    'not_found'          => __( 'No chemicals found' ),
		    'not_found_in_trash' => __( 'No chemicals found in the Trash' ), 
		    'parent_item_colon'  => '',
		    'menu_name'          => 'Chemicals',
		  );
		  $args = array(
		    'labels'        => $labels,
		    'description'   => 'Holds our chemicals and chemical specific data',
		    'public'        => true,
		    'menu_position' => 5,
		    'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'tags' ),
		    'has_archive'   => true,
			'taxonomies' => array('post_tag'),
			'exclude_from_search' => false,
		  );

		  register_post_type( 'chemicals', $args ); 
	}
	/**
	*	Meta box for CAS number
	*/
	function chemical_cas_box() {
	    add_meta_box( 
	        'chemical_cas_box',
	        __( 'CAS number', 'pluginname_textdomain' ),
	        array($this, 'chemical_cas_box_content'),
	        'chemicals',
	        'side',
	        'high'
	    );
	}

	function chemical_cas_box_save( $post_id ) {

	  if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
	  return;

	  if ( !wp_verify_nonce( $_POST['chemical_cas_box_content_nonce'], plugin_basename( __FILE__ ) ) )
	  return;

	  if ( 'page' == $_POST['post_type'] ) {
	    if ( !current_user_can( 'edit_page', $post_id ) )
	    return;
	  } else {
	    if ( !current_user_can( 'edit_post', $post_id ) )
	    return;
	  }
	  $chemical_cas = $_POST['chemical_cas_box'];
	  update_post_meta( $post_id, 'chemical_cas_box', $chemical_cas );
	}

	function chemical_cas_box_content( $post ) {
	  wp_nonce_field( plugin_basename( __FILE__ ), array($this, 'chemical_cas_box_content_nonce' ));
		$value = get_post_meta( $post->ID, 'chemical_cas_box', true );
  echo '<label for="chemical_cas_box"></label>';
	  echo '<input type="text" id="chemical_cas_box" name="chemical_cas_box" placeholder="enter a cas number" value="' . esc_attr( $value ) . '"/>';
	}
	function chemical_taxonomy() {
	  $labels = array(
	    'name'              => _x( 'Chemical categories', 'taxonomy general name' ),
	    'singular_name'     => _x( 'Chemical category', 'taxonomy singular name' ),
	    'search_items'      => __( 'Search chemical categories' ),
	    'all_items'         => __( 'All chemical categories' ),
	    'parent_item'       => __( 'Parent chemical category' ),
	    'parent_item_colon' => __( 'Parent chemical category:' ),
	    'edit_item'         => __( 'Edit chemical category' ), 
	    'update_item'       => __( 'Update chemical category' ),
	    'add_new_item'      => __( 'Add new chemical category' ),
	    'new_item_name'     => __( 'New chemical category' ),
	    'menu_name'         => __( 'Chemical categories' ),
	  );
	  $args = array(
	    'labels' => $labels,
	    'hierarchical' => true,
	  );
	  register_taxonomy( 'chemical_category', 'chemicals', $args );
	}
	/**
	*	Add chemical category field for brand name
	*/
	function chemical_taxonomy_group_field() {
	// this will add the custom meta field to the add new term page
	?>
	<div class="form-field">
		<label for="term_meta[brand_name]"><?php _e( 'Group name', 'pluginname_textdomain' ); ?></label>
		<input type="text" name="term_meta[group_name]" id="term_meta[group_name]" value="">
		<p class="description"><?php _e( 'Enter a group name','pluginname_textdomain' ); ?></p>
	</div>
<?php
	}
	function include_custom_post_types( $query ) {
	    $custom_post_type = get_query_var( 'post_type' );

	    if ( is_archive() ) {
	        if ( empty( $custom_post_type ) ) $query->set( 'post_type' , get_post_types() );
	    }

	    if ( is_search() ) {
	        if ( empty( $custom_post_type ) ) {
	            $query->set( 'post_type' , array(
	                'post', 'page', 'chemicals'
	                )
	            );
	        }
	    }

	    return $query;
	}

	function chemical_taxonomy_edit_meta_field($term) {
	 
		// put the term ID into a variable
		$t_id = $term->term_id;
	 
		// retrieve the existing value(s) for this meta field. This returns an array
		$term_meta = get_option( "taxonomy_$t_id"); ?>
		<tr class="form-field">
		<th scope="row" valign="top"><label for="term_meta[group_name_meta]"><?php _e( 'Group name', 'pluginname_textdomain' ); ?></label></th>
			<td>
				<input type="text" name="term_meta[group_name_meta]" id="term_meta[group_name_meta]" value="<?php echo esc_attr( $term_meta['group_name_meta'] ) ? esc_attr( $term_meta['group_name_meta'] ) : ''; ?>">
				<p class="description"><?php _e( 'Enter a group name','pluginname_textdomain' ); ?></p>
			</td>
		</tr>
	<?php
	}
	function save_chemical_taxonomy_custom_meta( $term_id ) {
		if ( isset( $_POST['term_meta'] ) ) {
			$t_id = $term_id;
			$term_meta = get_option( "taxonomy_$t_id");
			$cat_keys = array_keys( $_POST['term_meta'] );
			foreach ( $cat_keys as $key ) {
				if ( isset ( $_POST['term_meta'][$key] ) ) {
					$term_meta[$key] = $_POST['term_meta'][$key];
				}
			}
			// Save the option array.
			update_option( "taxonomy_$t_id", $term_meta );
		}
	}  

	/**
	 * Return the plugin slug.
	 *
	 * @since    1.0.0
	 *
	 * @return    Plugin slug variable.
	 */
	public function get_plugin_slug() {
		return $this->plugin_slug;
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Activate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       activated on an individual blog.
	 */
	public static function activate( $network_wide ) {
		// Check table existance
		
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide  ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_activate();

					restore_current_blog();
				}

			} else {
				self::single_activate();
			}

		} else {
			self::single_activate();
		}

	}

	/**
	* Check if the table exists for the plugin activation
	*	@since 1.0.0
	*	@param string tableName An unprefixed table name
	*/
	public function checkTable($tableName){
		global $wpdb;
		$table_name =	$wpdb->base_prefix.$tableName;
		if($wpdb->get_var("show tables like '$table_name'") != $tableName){
			return false;

		}
		else{
			return true;
		}
	}

	/**
	*	Install the required tables for this plugin
	*	@since 1.0.0
	*/
	// public function installTables(){
	// 	global $wpdb;
	// 	$charset_collate = $wpdb->get_charset_collate();

	// 	$sqlChemicalDataName = $wpdb->base_prefix."chemical_data";
	// 	$sqlChemicalData = "CREATE TABLE $sqlChemicalDataName (
	// 							id INT(10) NOT NULL AUTO_INCREMENT,
	// 							brand_name_id INT(10) NOT NULL,
	// 							brand_name_ext VARCHAR(100) DEFAULT ='',
	// 							cas_number	VARCHAR(100) DEFAULT ='',
	// 							chemical_name VARCHAR(200) DEFAULT ='',
	// 							chemical_description FULLTEXT,
	// 							group_id INT(10) NOT NULL,
	// 							added_on DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL
 //  								UNIQUE KEY id (id)
	// 						) $charset_collate;";
	// 	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	// 	dbDelta( $sqlChemicalData, true );
	// 	return true;

	// }
	function db_install () {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
	  	$table_name = $wpdb->prefix . "liveshoutbox"; 
		$sql = "CREATE TABLE $table_name (
								id INT(10) NOT NULL AUTO_INCREMENT,
								brand_name_id INT(10) NOT NULL,
								brand_name_ext VARCHAR(100) DEFAULT ='',
								cas_number	VARCHAR(100) DEFAULT ='',
								chemical_name VARCHAR(200) DEFAULT ='',
								chemical_description FULLTEXT,
								group_id INT(10) NOT NULL,
								added_on DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL
  								UNIQUE KEY id (id)
							) $charset_collate;";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	
	}
	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Deactivate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       deactivated on an individual blog.
	 */
	public static function deactivate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_deactivate();

					restore_current_blog();

				}

			} else {
				self::single_deactivate();
			}

		} else {
			self::single_deactivate();
		}

	}

	/**
	 * Fired when a new site is activated with a WPMU environment.
	 *
	 * @since    1.0.0
	 *
	 * @param    int    $blog_id    ID of the new blog.
	 */
	public function activate_new_site( $blog_id ) {

		if ( 1 !== did_action( 'wpmu_new_blog' ) ) {
			return;
		}

		switch_to_blog( $blog_id );
		self::single_activate();
		restore_current_blog();

	}
	/**
	 * Get all blog ids of blogs in the current network that are:
	 * - not archived
	 * - not spam
	 * - not deleted
	 *
	 * @since    1.0.0
	 *
	 * @return   array|false    The blog ids, false if no matches.
	 */
	private static function get_blog_ids() {

		global $wpdb;

		// get an array of blog ids
		$sql = "SELECT blog_id FROM $wpdb->blogs
			WHERE archived = '0' AND spam = '0'
			AND deleted = '0'";

		return $wpdb->get_col( $sql );

	}

	/**
	 * Fired for each blog when the plugin is activated.
	 *
	 * @since    1.0.0
	 */
	private static function single_activate() {
		// @TODO: Define activation functionality here
	}

	/**
	 * Fired for each blog when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 */
	private static function single_deactivate() {
		// @TODO: Define deactivation functionality here
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		$domain = $this->plugin_slug;
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, FALSE, basename( plugin_dir_path( dirname( __FILE__ ) ) ) . '/languages/' );

	}

	/**
	 * Register and enqueue public-facing style sheet.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_slug . '-plugin-styles', plugins_url( 'assets/css/public.css', __FILE__ ), array(), self::VERSION );
	}

	/**
	 * Register and enqueues public-facing JavaScript files.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_slug . '-plugin-script', plugins_url( 'assets/js/public.js', __FILE__ ), array( 'jquery' ), self::VERSION );
	}

	/**
	 * NOTE:  Actions are points in the execution of a page or process
	 *        lifecycle that WordPress fires.
	 *
	 *        Actions:    http://codex.wordpress.org/Plugin_API#Actions
	 *        Reference:  http://codex.wordpress.org/Plugin_API/Action_Reference
	 *
	 * @since    1.0.0
	 */
	public function action_method_name() {
		// @TODO: Define your action hook callback here
	}

	/**
	 * NOTE:  Filters are points of execution in which WordPress modifies data
	 *        before saving it or sending it to the browser.
	 *
	 *        Filters: http://codex.wordpress.org/Plugin_API#Filters
	 *        Reference:  http://codex.wordpress.org/Plugin_API/Filter_Reference
	 *
	 * @since    1.0.0
	 */
	public function filter_method_name() {
		// @TODO: Define your filter hook callback here
	}

}
