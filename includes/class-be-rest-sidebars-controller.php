<?php
/**
 * BE REST Endpoints Controller Class for Sidebars
 *
 * Creates and registers the endpoints for sidebar data.
 *
 * @since 1.0.0
 * @TODO Consider performance optimizations. Mainly around E-Tag headers.
 *       This should possibly be placed into the REST engine in general.
 * @package BE REST Endpoints
 */

/**
 * Class for controlling the sidebars endpoint.
 */
class BE_REST_Sidebars_Controller extends WP_REST_Controller {

	/**
	 * Class constructor.
	 *
	 * When class is instantiated WP REST API routes are registered.
	 */
	public function __construct() {
		add_action( 'be_rest_register_endpoints', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the routes for the objects of the controller.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		$version   = '1';
		$namespace = 'be/v' . $version;
		$base      = 'sidebars';
		// Returns all sidebars at /wp-json/be/v1/sidebars/.
		register_rest_route( $namespace, '/' . $base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => array(),
			),
		) );
		// Individual sidebar by ID at /wp-json/be/v1/sidebars/<sidebar_id>.
		register_rest_route( $namespace, '/' . $base . '/(?P<sidebar_id>[\w-]+-[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => array(
					'sidbear_id' => array(
						'description'       => __( 'Sidebar ID', 'be-rest-endpoints' ),
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			),
		) );
		// Returns Schema Data at /wp-json/be/v1/sidebars/schema/.
		register_rest_route( $namespace, '/' . $base . '/schema', array(
			'methods'  => WP_REST_Server::READABLE,
			// Grabs get_public_item_schema from parent class WP_REST_Controller.
			'callback' => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Get a collection of items.
	 *
	 * Returns sidebars and active widgets in their respective sidebar.
	 *
	 * @global array $wp_registered_sidebars Multi-dimensional array of sidebars and their parameters.
	 * @global array $sidebars_widgets       Multi-dimensional array of sidebars and the widgets they contain.
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {
		global $wp_registered_sidebars;
		$sidebars_widgets = wp_get_sidebars_widgets();
		$data = array();

		foreach ( $wp_registered_sidebars as $sidebar_id => $sidebar ) {
			$sidebar_data = $this->prepare_sidebar_for_response( $sidebar, $sidebar_id, $sidebars_widgets, $request );
			$data[ $sidebar_id ] = $sidebar_data;
		}

		// Creates response with registered widgets.
		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Check if a given request has access to get items
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {
		/**
		 * This hook is for the permissions check of getting sidebars.
		 *
		 * By default returns true for any request. Any custom functionality
		 * added needs to result in a boolean
		 *
		 * @param WP_REST_Request $request Object representing REST request.
		 */
		$return = apply_filters( 'be_rest_get_sidebars_permissions', true );

		if ( is_bool( $return ) ) {
			return $return;
		} else {
			return new WP_Error( 'cant-get', __( 'Custom permissions handling MUST return a boolean value. FALSE for no permissions, TRUE for permissions.', 'be-rest-endpoints' ), array( 'status' => 400 ) );
		}
	}

	/**
	 * Get a sidebar.
	 *
	 * Returns sidebars and active widgets in their respective sidebar.
	 *
	 * @global array $wp_registered_sidebars Multi-dimensional array of sidebars and their parameters.
	 * @global array $sidebars_widgets       Multi-dimensional array of sidebars and the widgets they contain.
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_item( $request ) {
		global $wp_registered_sidebars;
		$sidebars_widgets = wp_get_sidebars_widgets();

		// If valid sidebar pepare the response.
		if ( array_key_exists( $request['sidebar_id'], $wp_registered_sidebars ) ) {
			$sidebar = $wp_registered_sidebars[ $request['sidebar_id'] ];

			$data = $this->prepare_sidebar_for_response( $sidebar, $request['sidebar_id'], $sidebars_widgets, $request );

			return new WP_REST_Response( $data, 200 );
		// If sidebar id is invalid return a WP_Error().
		} else {
			return new WP_Error( 'cant-get', __( 'Sidebar ID provided is not valid. Please modify request.', 'be-rest-endpoints' ), array( 'status' => 400 ) );
		}

		// Something went wrong.
		return new WP_Error( 'cant-get', __( 'Something went wrong with the request try again.', 'be-rest-endpoints' ), array( 'status' => 500 ) );
	}

	/**
	 * Check if a given request has access to get sidebar.
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_item_permissions_check( $request ) {
		/**
		 * This hook is for the permissions check of getting one sidebar.
		 *
		 * By default returns true for any request. Any custom functionality
		 * added needs to result in a boolean
		 *
		 * @param WP_REST_Request $request Object representing REST request.
		 */
		$return = apply_filters( 'be_rest_get_widget_permissions', true );

		if ( is_bool( $return ) ) {
			return $return;
		} else {
			return new WP_Error( 'cant-get', __( 'Custom permissions handling MUST return a boolean value. FALSE for no permissions, TRUE for permissions.', 'be-rest-endpoints' ), array( 'status' => 400 ) );
		}
	}

	/**
	 * Prepare the sidebar for the REST response.
	 *
	 * Adds active widgets into each sidebar.
	 *
	 * @param  mixed  $sidebar          WordPress representation of the sidebar.
	 * @param  string $sidebar_id       ID for the registered sidebar.
	 * @param  mixed  $sidebars_widgets Equivalent to wp_get_sidebars_widgets().
	 * @return array  Array of the sidebar passed into the function.
	 */
	public function prepare_sidebar_for_response( $sidebar, $sidebar_id, $sidebars_widgets ) {
		// Set default to false.
		$sidebar['active_widgets'] = false;

		if ( array_key_exists( $sidebar_id, $sidebars_widgets ) ) {
			// If there are active widgets add them to array if not add false value.
			if ( ! empty( $sidebars_widgets[ $sidebar_id ] ) ) {
				$sidebar['active_widgets'] = $sidebars_widgets[ $sidebar_id ];
			}
		}
		return $sidebar;
	}

	/**
	 * Get JSON Schema for sidebar.
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema' => 'http://json-schema.org/draft-04/schema#',
			'title'   => 'sidebar',
			'type'    => 'object',
			/**
			 * Base properties for every sidebar.
			 */
			'properties' => array(
				'name'            => array(
					'description' => __( 'Name of the sidebar.', 'be-rest-endpoints' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'id'              => array(
					'description' => __( 'Sidebar ID.', 'be-rest-endpoints' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'description'     => array(
					'description' => __( "Description of the sidebar's purpose.", 'be-rest-endpoints' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'class'           => array(
					'description' => __( 'CSS class for the sidebar.', 'be-rest-endpoints' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'before_widget'   => array(
					'description' => __( 'HTML output before the widget.', 'be-rest-endpoints' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'after_widget'    => array(
					'description' => __( 'HTML output after the widget.', 'be-rest-endpoints' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'before_title'    => array(
					'description' => __( 'HTML output before the widget title.', 'be-rest-endpoints' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'after_title'     => array(
					'description' => __( 'HTML output after the widget title.', 'be-rest-endpoints' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'active_widgets'  => array(
					'anyOf'   => array(
						array(
							'type'        => 'boolean',
							'description' => __( 'Displays false if no widgets are active.' ),
						),
						array(
							'type'        => 'array',
							'description' => __( 'An indexed array of widget IDs and their order in the sidebar.', 'be-rest-endpoints' ),
						),
					),
					'context' => array( 'view', 'edit', 'embed' ),
				),
			),
		);
		return $schema;
	}
}
