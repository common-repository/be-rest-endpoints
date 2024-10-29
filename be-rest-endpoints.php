<?php
/**
 * This plugin adds widget and sidebar endpoints to the WP REST API v2.
 *
 * @link
 * @since   1.0.0
 * @package BE REST Endpoints
 * @version 1.0.0
 *
 * @wordpress-plugin
 * Plugin Name: BE REST Endpoints
 * Pugin URI: http://be-webdesign.com/plugins/be-rest-endpoints/
 * Description: A plugin that adds WP REST API endpoints for sidebars and widgets.  Useful for theme and plugin authors who want to access widget information via the WP REST API. Currently uses WP REST API v2 but will expand over time with additional functionality.
 * Version: 1.0.0
 * Author: BE Webdesign
 * Author: http://be-webdesign.com/
 * License: GPLv2 or later
 * Text Domain: be-rest-endpoints
 * Domain Path: /i18n/languages/
 */

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

Copyright 2016 BE Webdesign.
*/

// Exit if plugin is directly accessed.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load the BE_REST_Endpoints Class.
if ( ! class_exists( 'BE_REST_Endpoints' ) ) {

	/**
	 * Main BE REST Endpoints Class.
	 */
	final class BE_REST_Endpoints {
		/**
		 * Plugin version.
		 *
		 * @var string
		 */
		public $version = '1.0.0';

		/**
		 * Constructor function.
		 *
		 * Sets up all of the initial plugin necessities.
		 */
		public function __construct() {
			// Used to check version of WP to make sure it is greater than 4.4!
			add_action( 'admin_init', array( $this, 'check_version' ) );
			// If you are using an unsupported version of wordpress then don't do anything.
			if ( ! $this->compatible_version() ) {
				return;
			}

			// Plugin setup.
			$this->define_constants();
			$this->includes();
			$this->init_hooks();

			do_action( 'be_rest_loaded' );
		}

		/**
		 * Define Constants
		 */
		private function define_constants() {
			$this->define( 'BE_REST_VERSION', '1.0.0' );
			$this->define( 'BE_REST_MINIMUM_WP_VERSION', '4.4' );
			$this->define( 'BE_REST__PLUGIN_URL', plugin_dir_url( __FILE__ ) );
			$this->define( 'BE_REST__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}

		/**
		 * Define constant if not already set
		 *
		 * @param string      $name  Name of constant.
		 * @param string|bool $value Value of constant.
		 */
		private function define( $name, $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}

		/**
		 * Include required core files.
		 */
		private function includes() {
			/**
			 * Loads enpoint controller classes.
			 */
			if ( class_exists( 'WP_REST_Controller' ) ) {
				include_once( 'includes/class-be-rest-sidebars-controller.php' );
				include_once( 'includes/class-be-rest-widgets-controller.php' );
			}
		}

		/**
		 * Hook into actions and filters
		 */
		private function init_hooks() {
			register_activation_hook( __FILE__, array( $this, 'plugin_activate' ) );
			add_action( 'init', array( $this, 'init' ) );

			if ( class_exists( 'WP_REST_Controller' ) ) {
				add_action( 'rest_api_init', array( $this, 'add_endpoints' ) );
			}
		}

		/**
		 * Initializes class settings.
		 *
		 * Currently does nothing.
		 */
		public function init() {

		}

		/**
		 * This function runs an activation check to make sure plugin runs correctly.
		 */
		public static function activation_check() {
			if ( ! self::compatible_version() ) {
				deactivate_plugins( plugin_basename( __FILE__ ) );
				wp_die( esc_html__( 'BE REST Endpoints requires WordPress 4.4 or higher!', 'be-rest-endpoints' ) );
			}
		}

		/**
		 * Document
		 */
		public function check_version() {
			if ( ! self::compatible_version() ) {
				if ( is_plugin_active( plugin_basename( __FILE__ ) ) ) {
					deactivate_plugins( plugin_basename( __FILE__ ) );
					add_action( 'admin_notices', array( $this, 'disabled_notice' ) );
					if ( isset( $_GET['activate'] ) ) {
						unset( $_GET['activate'] );
					}
				}
			}
		}

		/**
		 * Echos an error notification.
		 */
		public function disabled_notice() {
			echo '<div class="error"><p>', esc_html__( 'BE REST Endpoints requires WordPress 4.4 or higher!', 'be-rest-endpoints' ), '</p></div>';
		}

		/**
		 * Document
		 */
		static function compatible_version() {
			if ( version_compare( $GLOBALS['wp_version'], '4.4', '<' ) ) {
				return false;
			}
			// Add sanity checks for other version requirements here.
			return true;
		}

		/**
		 * Adds custom endpoints to WP REST API.
		 *
		 * Currently adds basic endpoints for Sidebars and Widgets.
		 * Will expand in the future.
		 *
		 * @see $this->init_hooks This function is only called if WP_REST_Controller exists.
		 * @see add_action( 'rest_api_init', array( $this, 'add_endpoints' ) ) hooked.
		 */
		public function add_endpoints() {
			// Default actions for registering WP REST API Endpoints.
			new BE_REST_Sidebars_Controller();
			new BE_REST_Widgets_Controller();

			/**
			 * Action where endpoint routes are registered for this plugin.
			 */
			do_action( 'be_rest_register_endpoints' );
		}

		/**
		 * Fires on plugin activation.
		 *
		 * Currently does nothing.
		 *
		 * @TODO Eventually set up site options.
		 */
		public function plugin_activate() {
			self::activation_check();
		}

		/**
		 * Fires on plugin deactivation.
		 *
		 * Currently does nothing.
		 */
		public function plugin_deactivate() {

		}
	}
} // End class_exists() wrapper.

/**
 * Constructs the BE_REST_Endpoints Class if there is already not a BE_REST_Endpoints in existence.
 *
 * @see Class BE_REST_Endpoints
 */
function be_rest_init() {
	new BE_REST_Endpoints();
}

/**
 * Ensures that this plugin is loaded after WP REST API plugin.
 *
 * Once WP_REST_Controller is in core this will not be necessary and will be
 * possibly removed or kept for backwards compatability.
 *
 * @see add_action( 'activated_plugin' ) && add_action( 'deactivated_plugin' ) Hooked into both.
 */
function load_be_rest_last() {
	// Ensure path to this file is via main wp plugin path.
	$wp_path_to_this_file = preg_replace( '/(.*)plugins\/(.*)$/', WP_PLUGIN_DIR . '/$2', __FILE__ );
	$this_plugin          = plugin_basename( trim( $wp_path_to_this_file ) );
	$active_plugins       = get_option( 'active_plugins' );
	$this_plugin_key      = array_search( $this_plugin, $active_plugins );

	if ( in_array( $this_plugin, $active_plugins ) && end( $active_plugins ) !== $this_plugin ) {
		array_splice( $active_plugins, $this_plugin_key, 1 );
		array_push( $active_plugins, $this_plugin );
		update_option( 'active_plugins', $active_plugins );
	}
}
add_action( 'activated_plugin', 'load_be_rest_last' );
add_action( 'deactivated_plugin', 'load_be_rest_last' );

// Initializes Everything.
be_rest_init();
