<?php
/**
 * BE REST Endpoints Controller Class for Widgets
 *
 * Creates and registers the endpoints for widget data.
 *
 * @since 1.0.0
 * @TODO Consider performance optimizations. Mainly around E-Tag headers.
 *       This should potentially be done at the actually API level.
 * @package BE REST Endpoints
 */

/**
 * Class for Widgets_Controller.
 */
class BE_REST_Widgets_Controller extends WP_REST_Controller {

	/**
	 * Stores inferred schema data from a widget form.
	 *
	 * Data is stored by widget base. See the example below.
	 *
	 * $inferred_schema = array(
	 *     'text' => array(
	 *         'title' => array(
	 *             'type' => 'string',
	 *         ),
	 *         'text' => array(
	 *             'type' => 'string',
	 *         ),
	 *         'filter' => array(
	 *             'type' => 'boolean',
	 *             'default' => false,
	 *         ),
	 *     ),
	 * );
	 *
	 * @var array $inferred_schema
	 */
	public $inferred_schema = array();

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
	 */
	public function register_routes() {
		$version   = '1';
		$namespace = 'be/v' . $version;
		$base	  = 'widgets';
		// Route for handling widgets collection. /wp-json/be/v1/widgets/.
		register_rest_route( $namespace, '/' . $base, array(
			// Used for reading widget information.
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			),
			// Used to put new widgets into sidebars.
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
			),

			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		// Routes for handling individual widgets. /wp-json/be/v1/widgets/<widget_id>.
		register_rest_route( $namespace, '/' . $base . '/(?P<widget_id>[\w-]+-[\d]+)', array(
			// Reading individual widget.
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => array(
					'widget_id' => array(
						'description'       => __( 'ID of the widget.', 'be-rest-endpoints' ),
						'type'              => 'string',
						'validate_callback' => array( $this, 'validate_widget' ),
					),
					'context' => $this->get_context_param( array( 'default' => 'view' ) ),
				),
			),
			// Editing individual widget.
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			),
			// Deleting individual widget.
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::DELETABLE ),
			),

			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		// Returns Schema Data at /wp-json/be/v1/widgets/schema/.
		register_rest_route( $namespace, '/' . $base . '/schema', array(
			'methods'  => WP_REST_Server::READABLE,
			// Grabs get_public_item_schema from parent class WP_REST_Controller.
			'callback' => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Get a collection of items
	 *
	 * @global $wp_registered_widgets	Multi-dimensional array of registered widgets and associated parameters.
	 *
	 * @param  WP_REST_Request $request  Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response Returns the WP_REST_Response with widget data.
	 */
	public function get_items( $request ) {
		global $wp_registered_widgets;
		$data = array();

		// Creates response with registered widgets.
		foreach ( $wp_registered_widgets as $widget_id => $widget ) {
			// Prepares widget for the REST response with extra information.
			$widget = $this->prepare_widget_for_response( $widget );

			// Set up array to hold all data.
			$data[ $widget_id ] = $widget;
		}

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
		 * This hook is for the permissions check of getting widgets.
		 *
		 * By default returns true for any request. Any custom functionality
		 * added needs to result in a boolean
		 *
		 * @param WP_REST_Request $request Object representing current request.
		 */
		$return = apply_filters( 'be_rest_get_widgets_permissions', true );

		if ( is_bool( $return ) ) {
			return $return;
		} else {
			return new WP_Error( 'cant-get', __( 'Custom permissions handling MUST return a boolean value. FALSE for no permissions, TRUE for permissions.', 'be-rest-endpoints' ), array( 'status' => 400 ) );
		}
	}

	/**
	 * Get widget from /wp-json/be/v1/widgets/<widget_id>
	 *
	 * @global $wp_registered_widgets Multi-dimensional array of available widgets.
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function get_item( $request ) {
		global $wp_registered_widgets;

		$widget = $wp_registered_widgets[ $request['widget_id'] ];

		// @TODO HAVE ERROR HANDLING ADDED.
		$widget = $this->prepare_widget_for_response( $widget );

		$data = $widget;

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Check if a given request has access to get widget.
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_item_permissions_check( $request ) {
		/**
		 * This hook is for the permissions check of getting one widget.
		 *
		 * By default returns true for any request. Any custom functionality
		 * added needs to result in a boolean
		 *
		 * @param WP_REST_Request $request Object representing REST API request.
		 */
		$return = apply_filters( 'be_rest_get_widget_permissions', true );

		if ( is_bool( $return ) ) {
			return $return;
		} else {
			return new WP_Error( 'cant-get', __( 'Custom permissions handling MUST return a boolean value. FALSE for no permissions, TRUE for permissions.', 'be-rest-endpoints' ), array( 'status' => 400 ) );
		}
	}

	/**
	 * Create one widget for the collection.
	 *
	 * @global $wp_registered_widgets  array Multi-dimensional array of registered widgets.
	 * @global $wp_registered_sidebars array Multi-dimensional array of registered sidebars.
	 * @global $sidebars_widgets	   array Multi-dimensional array of sidebars and widgets active in them.
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Request
	 */
	public function create_item( $request ) {
		global $wp_registered_widgets, $wp_registered_sidebars, $wp_registered_widget_controls, $wp_registered_widget_updates;
		$sidebars_widgets = wp_get_sidebars_widgets();

		$valid_widget = false;
		foreach ( $wp_registered_widgets as $registered_widget ) {
			if ( $request['widget_base'] === $registered_widget['callback'][0]->id_base ) {
				$valid_widget = true;

				$widget = $registered_widget;

				break;
			}
		}

		$valid_sidebar = array_key_exists( $request['sidebar_id'], $wp_registered_sidebars );

		if ( ( true === $valid_widget ) && ( true === $valid_sidebar ) ) {

			// Set widget number for new instance.
			$widget_number = $this->next_widget_id_number( $request['widget_base'] );

			// Create the widget instance and store the instance data for a widget type.
			$all_instances = $this->create_widget_instance( $widget, $widget_number, $request );

			$this->set_widget_position( $sidebars_widgets, $widget_number, $request );

			return new WP_REST_Response( $all_instances, 201 );

		// If a invalid widget ID or sidebar ID is provided return proper error.
		} else {
			return new WP_ERROR( 'cant-create', __( 'Invalid widget ID or sidebar ID was provided.', 'be-rest-endpoints' ), array( 'status' => 400 ) );
		}

		return new WP_Error( 'cant-create', __( 'There was an error creating the widget.', 'be-rest-endpoints' ), array( 'status' => 500 ) );
	}

	/**
	 * Update one item from the collection.
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_item( $request ) {
		global $wp_registered_widgets;
		$query_params = $request->get_params();

		// Get just query string params.
		unset( $query_params[0] );
		unset( $query_params['widget_id'] );

		// Get Widget ID base.
		$widget_base = _get_widget_id_base( $request['widget_id'] );

		$widget_number = $wp_registered_widgets[ $request['widget_id'] ]['params'][0]['number'];

		// Test for validity of widget.
		if ( array_key_exists( $request['widget_id'], $wp_registered_widgets ) ) {
			$settings = $wp_registered_widgets[ $request['widget_id'] ]['callback'][0]->get_settings();

			// For each query string parameter test whether it matches the instance and if so add the data.
			foreach ( $query_params as $instance_field => $instance_value ) {
				// Determine whether or not the query params match instance values.
				if ( array_key_exists( $instance_field, $settings[ $widget_number ] ) ) {
					$settings[ $widget_number ][ $instance_field ] = $instance_value;
				}
			}

			$wp_registered_widgets[ $request['widget_id'] ]['callback'][0]->save_settings( $settings );

			// If the position needs to be updated but not the sidebar.
			if ( isset( $request['sidebar_position'] ) && ! isset( $request['sidebar_id'] ) ) {
				$sidebars_widgets = wp_get_sidebars_widgets();
				$this->set_widget_position( $sidebars_widgets, $widget_number, $request );
			}

			// If the sidebar needs to be updated.
			if ( isset( $request['sidebar_id'] ) && isset( $request['widget_id'] ) ) {
				$sidebars_widgets = wp_get_sidebars_widgets();
				// Updates the widgets position into a new sidebar.
				$this->update_widget_sidebar( $sidebars_widgets, $request );
			}

			return new WP_REST_Response( $settings, 200 );
		} else {
			return new WP_Error( 'cant-update', __( 'Invalid widget ID was provided.', 'be-rest-endpoints' ), array( 'status' => 400 ) );
		}

		return new WP_Error( 'cant-update', __( 'There was an error updating the widget.', 'be-rest-endpoints' ), array( 'status' => 500 ) );
	}

	/**
	 * Delete one item from the collection.
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Request
	 */
	public function delete_item( $request ) {
		global $wp_registered_widgets, $wp_registered_sidebars;
		$sidebars_widgets = wp_get_sidebars_widgets();

		$valid_widget = false;
		// Determine whether widget is valid. @TODO consider improving the conditional.
		if ( array_key_exists( $request['widget_id'], $wp_registered_widgets ) ) {
			$valid_widget = true;

			// Setup widget ID.
			$widget_id = $request['widget_id'];

			// Setup widget for information.
			$widget = $wp_registered_widgets[ $request['widget_id'] ];

			// Get widget option name.
			$widget_option_name = $widget['callback'][0]->option_name;

			// Get instance number of the widget.
			$widget_number = $widget['params'][0]['number'];

			// Sidebar ID the widget is in or false.
			foreach ( $sidebars_widgets as $id => $sidebar ) {
				if ( is_array( $sidebar ) ) {
					if ( in_array( $widget_id, $sidebar ) ) {
						$sidebar_id = $id;
						$position = array_search( $widget_id, $sidebar );
						break;
					}
				}
			}

			// If the widget is in a sidebar then remove it and reset sidebars widgets.
			if ( isset( $sidebar_id ) && isset( $sidebars_widgets[ $sidebar_id ] ) && ( isset( $position ) && false !== $position ) ) {
				$sidebar_length = count( $sidebars_widgets[ $sidebar_id ] ) - 1;
				// If the position of the widget is not first or last.
				if ( 0 < $position && $sidebar_length > $position ) {
					$array_chunk = array_splice( $sidebars_widgets[ $sidebar_id ], $position );
					array_shift( $array_chunk );
					$sidebars_widgets[ $sidebar_id ] = array_merge( $sidebars_widgets[ $sidebar_id ], $array_chunk );
				// If the widget is last in the array.
				} elseif ( $sidebar_length === $position ) {
					array_pop( $sidebars_widgets[ $sidebar_id ] );
				// If widget is first in the array.
				} elseif ( 0 === $position ) {
					// If there are multiple widgets in the array remove the first element.
					if ( is_array( $sidebars_widgets[ $sidebar_id ] ) ) {
						array_shift( $sidebars_widgets[ $sidebar_id ] );
					// If there is only one widget in the sidebar then set it back to an empty array.
					} else {
						$sidebars_widgets[ $sidebars_id ] = array();
					}
				}

				// Save modified sidebars widgets.
				wp_set_sidebars_widgets( $sidebars_widgets );
			}

			// Delete widget instance from options. This will run regardless of whether the widget is in a sidebar.
			$all_instances = get_option( $widget_option_name );

			if ( isset( $all_instances[ $widget_number ] ) ) {
				unset( $all_instances[ $widget_number ] );
				update_option( $widget_option_name, $all_instances );
			}

			return new WP_REST_Response( $all_instances, 200 );

		} else {
			return new WP_Error( 'cant-delete', __( 'Invalid widget ID was provided.', 'be-rest-endpoints' ), array( 'status' => 400 ) );
		}

		return new WP_Error( 'cant-delete', __( 'There was an error trying to delete the widget.', 'be-rest-endpoints' ), array( 'status' => 500 ) );
	}

	/**
	 * Check if a given request has access to create widgets.
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 * @TODO CHANGE PERMISSIONS BACK TO THE WAY THEY SHOULD BE.
	 */
	public function create_item_permissions_check( $request ) {
		/**
		 * This hook is for the permissions check of getting one widget.
		 *
		 * By default returns true for any request. Any custom functionality
		 * added needs to result in a boolean.
		 *
		 * @param WP_REST_Request $request
		 */
		// @TODO use current_user_can( 'edit_theme_options' ) instead of true;
		$return = apply_filters( 'be_rest_create_widget_permissions', true );

		if ( is_bool( $return ) ) {
			return $return;
		} else {
			return new WP_Error( 'cant-get', __( 'Custom permissions handling MUST return a boolean value. FALSE for no permissions, TRUE for permissions.', 'be-rest-endpoints' ), array( 'status' => 400 ) );
		}
	}

	/**
	 * Check if a given request has access to update a specific item.
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function update_item_permissions_check( $request ) {
		return $this->create_item_permissions_check( $request );
	}

	/**
	 * Check if a given request has access to delete a specific item
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->create_item_permissions_check( $request );
	}

	/**
	 * Prepares widget for response.
	 *
	 * @global array $wp_registered_sidebars
	 * @global array $sidebars_widgets
	 *
	 * @param  array $widget Multi-dimensional array from $wp_registered_widgets[ $widget_id ].
	 * @return array $widget Returns the new instance of $widget.
	 */
	public function prepare_widget_for_response( $widget ) {
		global $wp_registered_sidebars, $sidebars_widget;

		// Sets up arguements eventually passed into WP_Widget::widget().
		$args = array();

		// Find sidebar for widget and add to.
		$widget['in_sidebar'] = $this->get_sidebar_for_widget( $widget['id'] );

		// Sets up sidebar parameters for the respective widget if it is in a sidebar.
		$widget['sidebar_params'] = false;
		if ( false !== $widget['in_sidebar'] ) {
			$widget['sidebar_params'] = $wp_registered_sidebars[ $widget['in_sidebar'] ];
			if ( ! empty( $widget['sidebar_params']['before_widget'] ) ) {
				// Replace id and widget classname into before_widget. @see dynamic_sidebar() in includes/widgets.php.
				$widget['sidebar_params']['before_widget'] = sprintf( $widget['sidebar_params']['before_widget'], $widget['id'], $widget['classname'] );
				$args = array(
					'before_widget' => $widget['sidebar_params']['before_widget'],
					'after_widget'  => $widget['sidebar_params']['after_widget'],
					'before_title'  => $widget['sidebar_params']['before_title'],
					'after_title'   => $widget['sidebar_params']['after_title'],
				);
			}
		}

		// True if widget has an instance false if not.
		$widget['has_output'] = ( 0 < $widget['params'][0]['number'] ) ? true : false;

		// Save instance number.
		$widget['instance_number'] = $widget['params'][0]['number'];

		// Returns array of instance if there is no instance returns false.
		$widget['instance'] = $this->get_widget_instance( $widget, $widget['params'][0]['number'] );

		// Adds widget rendered output into the data array.
		$widget['widget_output'] = $this->get_the_widget( $widget, $args );

		// Remove params and callback info to make code cleaner.
		unset( $widget['params'] );
		unset( $widget['callback'] );

		return $widget;
	}

	/**
	 * Creates a hollow widget instance and saves it as a template if no other widgets have been created.
	 *
	 * @access public
	 *
	 * @param  array           $widget        Array that exists at $wp_registered_widgets[ $widget_id ].
	 * @param  int             $widget_number Widget instance number.
	 * @param  WP_REST_Request $request       Request body.
	 * @return array $all_instances Returns the instance data for the given widget type.
	 */
	public function create_widget_instance( $widget, $widget_number, $request ) {
		// Setup widget option name to be tested.
		// @TODO Use get_settings() instead of grabbing actual option.
		$widget_option_name = $widget['callback'][0]->option_name;

		if ( isset( $widget_option_name ) ) {
			$all_instances = get_option( $widget_option_name );
		}

		// Tells how many and what instances of the widget exist.
		$instance_numbers = array_keys( $all_instances );

		// Cut the array to just one key and store the key's value.
		$existing_instance_number = array_slice( $instance_numbers, 0, 1 )[0];

		// Store a copy of existing instance.  If there are no instances this will store the value to _multiwidget which is 1.
		$existing_instance = $all_instances[ $existing_instance_number ];

		$the_widget_form = '';
		// @TODO TURN FORM ANALYSIS INTO A TEMPORARY SCHEMA VALIDATION TOOL.
		// If there were no active instances set instance to empty array.
		if ( 1 === $existing_instance ) {
			$existing_instance = array();
			// Start output buffering to capture WP_Widget::form() output.
			ob_start();
			// By placing -1 as param, it ouputs an stateless version of the widget form.
			$widget['callback'][0]->form_callback( -1 );
			$the_widget_form = ob_get_contents();
			ob_end_clean();

			// Create a DOM Document for the widget form.
			$dom = new DOMDocument();
			$dom->loadHTML( $the_widget_form );

			// Create a schema inferred from the widget form.
			$this->infer_schema( $dom, $widget );

			// Check for inputs.
			$inputs = $dom->getElementsByTagName( 'input' );

			foreach ( $inputs as $input ) {
				if ( $input->hasAttribute( 'id' ) ) {
					// Get ID attributes of form fields so widget instances can be set.
					$id_value = $input->getAttribute( 'id' );

					// Find last occurence of '__i__-'. The field value follows.
					// @TODO THIS DOES NOT WORK FOR RSS FEED WIDGET.
					$chop = strrpos( $id_value, '__i__-' );

					// Move the chop position to the end of '__i__-'
					// @TODO THIS DOES NOT WORK FOR RSS FEED WIDGET.
					$chop = $chop + 6;

					// Instance option name.
					$instance_option = substr( $id_value, $chop );

					// Store instance options into array.
					$existing_instance[ $instance_option ] = '';
				}
			}

			// Check for textareas.
			$textareas = $dom->getElementsByTagName( 'textarea' );

			foreach ( $textareas as $textarea ) {
				if ( $textarea->hasAttribute( 'id' ) ) {
					// Get ID attributes of form fields so widget instances can be set.
					$id_value = $textarea->getAttribute( 'id' );

					// Find last occurence of '__i__-'. The field value follows.
					$chop = strrpos( $id_value, '__i__-' );

					// Move the chop position to the end of '__i__-'.
					$chop = $chop + 6;

					// Instance option name.
					$instance_option = substr( $id_value, $chop );

					// Store instance options into array.
					$existing_instance[ $instance_option ] = '';
				}
			}

			// Check for select boxes.
			$select_boxes = $dom->getElementsByTagName( 'select' );

			foreach ( $select_boxes as $select_box ) {
				if ( $select_box->hasAttribute( 'id' ) ) {
					// Get ID attributes of form fields so widget instances can be set.
					$id_value = $select_box->getAttribute( 'id' );

					// Find last occurence of '__i__-'. The field value follows.
					$chop = strrpos( $id_value, '__i__-' );

					// Move the chop position to the end of '__i__-'.
					$chop = $chop + 6;

					// Instance option name.
					$instance_option = substr( $id_value, $chop );

					// Store instance options into array.
					$existing_instance[ $instance_option ] = '';
				}
			}
		}

		// Set the widget ID for new instance.
		$widget_id = $request['widget_base'] . '-' . $widget_number;

		// Set instance for new widget.
		$all_instances[ $widget_number ] = $existing_instance;

		// Save new instances of the widget.
		update_option( $widget_option_name, $all_instances );

		return $all_instances;
	}

	/**
	 * Updates the sidebar location of a widget.
	 *
	 * @access public
	 *
	 * @param  array           $sidebars_widgets Equivalent to wp_get_sidebars_widgets().
	 * @param  WP_REST_Request $request          Request body.
	 * @return void
	 */
	public function update_widget_sidebar( $sidebars_widgets, $request ) {
		foreach ( $sidebars_widgets as $sidebar_id => $widgets ) {
			foreach ( $widgets as $widget_position => $widget_id ) {
				if ( $request['widget_id'] === $widget_id ) {
					$widget_exists = $widget_position;
					break;
				}
			}
			if ( isset( $widget_exists ) ) {
				$widget_in_sidebar = $sidebar_id;
				break;
			}
		}

		// Check to make sure the target sidebar is a valid array. If not then set widget position to first.
		if ( is_array( $sidebars_widgets[ $request['sidebar_id'] ] ) ) {
			$sidebar_length = count( $sidebars_widgets[ $request['sidebar_id'] ] );
			// If the specified position is greater than the number of widgets in sidebar then set position to the end of the sidebar.
			$position = ( $sidebar_length < $request['sidebar_position'] ) ? $sidebar_length : $request['sidebar_position'];

			// If the request is only one greater than the sidebar length then this will be equivalent to appending the new widget to the end.
			if ( $sidebar_length + 1 === $request['sidebar_position'] ) {
				$position = $request['sidebar_position'];
			}

			// If for some reason a request sets a widget to below first position set it back to first position.
			if ( $position < 1 ) {
				$position = 1;
			}
		} else {
			$position = 1;
		}

		// Move position to match array index.
		$position = $position - 1;

		// If widget already exists, which it should, remove its current location.
		if ( isset( $widget_exists ) ) {
			// Length of the sidebar the widget is currently in.
			$original_sidebar_length = count( $sidebars_widgets[ $widget_in_sidebar ] );
			// If widget to be updated is at beginning.
			if ( 0 === $widget_exists ) {
				array_shift( $sidebars_widgets[ $widget_in_sidebar ] );
			// If widget is in the middle.
			} elseif ( 0 < $widget_exists && $widget_exists < ( $original_sidebar_length ) - 1 ) {
				$array_chunk = array_splice( $sidebars_widgets[ $widget_in_sidebar ], $widget_exists );
				array_shift( $array_chunk );
				$sidebars_widgets[ $widget_in_sidebar ] = array_merge( $sidebars_widgets[ $widget_in_sidebar ], $array_chunk );
			// If widget is at the end.
			} elseif ( $widget_exists + 1 === $original_sidebar_length && 0 !== $widget_exists ) {
				array_pop( $sidebars_widgets[ $widget_in_sidebar ] );
			}
		}

		// If there is already a widget located in the position of the sidebar make sure to move it after the widget that will be inserted.
		if ( isset( $sidebars_widgets[ $request['sidebar_id'] ][ $position ] ) ) {
			// If the position is somewhere in the middle of the sidebar.
			if ( 0 < $position && ( $sidebar_length > ( $position ) ) ) {
				// Moves the widget into the proper place in the sidebar.
				$array_chunk = array_splice( $sidebars_widgets[ $request['sidebar_id'] ], $position );
				array_unshift( $array_chunk, $widget_id );
				$sidebars_widgets[ $request['sidebar_id'] ] = array_merge( $sidebars_widgets[ $request['sidebar_id'] ], $array_chunk );
			// If the position is at the beginning of the sidebar.
			} elseif ( 0 === $position ) {
				// Set new widget into the beginning of the sidebar.
				array_unshift( $sidebars_widgets[ $request['sidebar_id'] ], $widget_id );
			// If the position is at the end of the sidebar.
			} elseif ( $sidebar_length === $position && 0 !== $position ) {
				// Equivalent of array_push without function overhead.
				$sidebars_widgets[ $request['sidebar_id'] ][] = $widget_id;
			}
		// If the sidebar is empty or if the position is not set, just add the new value.
		} else {
			$sidebars_widgets[ $request['sidebar_id'] ][ $position ] = $widget_id;
		}

		// Set sidebars widgets.
		wp_set_sidebars_widgets( $sidebars_widgets );
	}

	/**
	 * Sets the widgets position in a sidebar.
	 *
	 * @access public
	 *
	 * @param array           $sidebars_widgets Equivalent to wp_get_sidebars_widgets().
	 * @param int             $widget_number    Instance number of the widget.
	 * @param WP_REST_Request $request          Request body.
	 * @return void
	 */
	public function set_widget_position( $sidebars_widgets, $widget_number, $request ) {
		// If request is coming from a create_item request.
		if ( isset( $request['widget_id'] ) ) {
			$widget_id = $request['widget_id'];
		} else {
			$widget_id = $request['widget_base'] . '-' . $widget_number;
		}

		// If request is coming from a update_item request.
		if ( ! isset( $request['sidebar_id'] ) && isset( $request['widget_id'] ) ) {
			$request['sidebar_id'] = $this->get_sidebar_for_widget( $request['widget_id'] );
		}

		// Check to make sure the sidebar is a valid array. If not then set widget position to first.
		if ( is_array( $sidebars_widgets[ $request['sidebar_id'] ] ) ) {
			$sidebar_length = count( $sidebars_widgets[ $request['sidebar_id'] ] );
			// If the specified position is greater than the number of widgets in sidebar then set position to the end of the sidebar.
			$position = ( $sidebar_length < $request['sidebar_position'] ) ? $sidebar_length : $request['sidebar_position'];

			// If the request is only one greater than the sidebar length then this will be equivalent to appending the new widget to the end.
			if ( $sidebar_length + 1 === $request['sidebar_position'] ) {
				$position = $request['sidebar_position'];
			}
			// If for some reason a request sets a widget to below first position set it back to first position.
			if ( $position < 1 ) {
				$position = 1;
			}
		} else {
			$position = 1;
		}

		// Move position to match array index.
		$position = $position - 1;

		// If there is already a widget located in the position of the sidebar make sure to move it after the widget that will be inserted.
		if ( isset( $sidebars_widgets[ $request['sidebar_id'] ][ $position ] ) ) {
			if ( false !== $widget_exists = array_search( $widget_id, $sidebars_widgets[ $request['sidebar_id'] ] ) ) {
				// If widget to be updated is at beginning.
				if ( 0 === $widget_exists ) {
					array_shift( $sidebars_widgets[ $request['sidebar_id'] ] );
				// If widget is in the middle.
				} elseif ( 0 < $widget_exists && $widget_exists < $sidebar_length ) {
					$array_chunk = array_splice( $sidebars_widgets[ $request['sidebar_id'] ], $widget_exists );
					array_shift( $array_chunk );
					$sidebars_widgets[ $request['sidebar_id'] ] = array_merge( $sidebars_widgets[ $request['sidebar_id'] ], $array_chunk );
				// If widget is at the end.
				} elseif ( $widget_exists + 1 === $sidebar_length && 0 !== $widget_exists ) {
					array_pop( $sidebars_widgets[ $request['sidebar_id'] ] );
				}
			}
			// If the position is somewhere in the middle of the sidebar.
			if ( 0 < $position && ( $sidebar_length > ( $position ) ) ) {
				// Moves the widget into the proper place in the sidebar.
				$array_chunk = array_splice( $sidebars_widgets[ $request['sidebar_id'] ], $position );
				array_unshift( $array_chunk, $widget_id );
				$sidebars_widgets[ $request['sidebar_id'] ] = array_merge( $sidebars_widgets[ $request['sidebar_id'] ], $array_chunk );
			// If the position is at the beginning of the sidebar.
			} elseif ( 0 === $position ) {
				// Set new widget into the beginning of the sidebar.
				array_unshift( $sidebars_widgets[ $request['sidebar_id'] ], $widget_id );
			// If the position is at the end of the sidebar.
			} elseif ( $sidebar_length === $position && 0 !== $position ) {
				// Equivalent of array_push without function overhead.
				$sidebars_widgets[ $request['sidebar_id'] ][] = $widget_id;
			}
		// If the sidebar is empty or if the position is not set, just add the new value.
		} else {
			$sidebars_widgets[ $request['sidebar_id'] ][ $position ] = $widget_id;
		}

		// Set sidebars widgets.
		wp_set_sidebars_widgets( $sidebars_widgets );
	}

	/**
	 * Get the particular widget's instance.
	 *
	 * @access private
	 *
	 * @param  array $widget          Array that exists at $wp_registered_widgets[ $widget_id ].
	 * @param  int   $instance_number Integer for widget instance number.
	 * @return array|boolean $instance|false If there is not an active instance return false.
	 */
	private function get_widget_instance( $widget, $instance_number ) {
		if ( true === $widget['has_output'] ) {
			$instances = get_option( $widget['callback'][0]->option_name );
			$instance = $instances[ $instance_number ];
			return $instance;
		}
		return false;
	}

	/**
	 * Gets the widget's rendered output relative to the sidebar it is in.
	 *
	 * @access private
	 *
	 * @see $default_args for defaults.
	 * @param array $widget Array that exists at $wp_registered_widgets[ $widget_id ].
	 * @param array $args {
	 *
	 *   Display arguments for widget.
	 *
	 *   @type string 'before_title'   HTML ouput that comes before the widget title,
	 *                                 Default '<section id="%1$s" class="widget %2$s">'.
	 *   @type string 'after_title'    HTML output that comes after the widget title,
	 *                                 Default '</section>'.
	 *   @type string 'before_widget'  HTML output that comes before widget instance,
	 *                                 Default '<h2 class="widget-title">'.
	 *   @type string 'after_widget'   HTML output that comes after widget instance
	 *                                 Default '</h2>'.
	 * }
	 * @return string Widget's output if no output returns empty string.
	 */
	private function get_the_widget( $widget, $args = array() ) {
		// The output will be an empty string if the widget has no output.
		$the_widget = '';

		// Set up default args for the widget.
		$default_args = array(
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		);

		// Parse defaults together with user passed arguments.
		$args = wp_parse_args( $args, $default_args );

		// Start output buffering to capture WP_Widget::widget() output.
		ob_start();
		$widget['callback'][0]->display_callback( $args, $widget['params'][0]['number'] );
		$the_widget = ob_get_contents();
		ob_end_clean();

		// Returns a string of the widgets output.
		return $the_widget;
	}

	/**
	 * Returns value of the sidebar id, which the widget is in. If not in sidebar returns false.
	 *
	 * @access private
	 * @global array $sidebars_widgets
	 *
	 * @param  string $widget_id Unique ID of a widget.
	 * @return boolean|string Returns the id of the sidebar that the widget is active in.  If not in a sidebar returns false
	 */
	private function get_sidebar_for_widget( $widget_id ) {
		// Array of sidebars and the widgets associated with them.
		$sidebars_widgets = wp_get_sidebars_widgets();

		foreach ( $sidebars_widgets as $sidebar_id => $sidebar ) {
			// If sidebar is not empty and is an array check to see if the widget is active in it.
			if ( is_array( $sidebar ) && ( ! empty( $sidebar ) ) && true === in_array( $widget_id, $sidebar, true ) ) {
				// Once value is found exit the loop with value of sidebar_id.
				return $sidebar_id;
			}
		}
		// If the widget is not found return NULL.
		return false;
	}

	/**
	 * Get the Post's schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {

		$schema = array(
			'$schema' => 'http://json-schema.org/draft-04/schema#',
			'title'   => 'widget',
			'type'    => 'object',
			/**
			 * Base properties for every widget.
			 */
			'properties' => array(
				'name'            => array(
					'description' => __( 'Name of the widget closely related to class name.', 'be-rest-endpoints' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'id'              => array(
					'description' => __( 'Unique ID of the widget.', 'be-rest-endpoints' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'classname'       => array(
					'description' => __( 'CSS class name for the widget.', 'be-rest-endpoints' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'description'     => array(
					'description' => __( "Description of widget's purpose.", 'be-rest-endpoints' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'in_sidebar'      => array(
					'anyOf'       => array(
						array(
							'description' => __( 'What sidebar the widget is in.', 'be-rest-endpoints' ),
							'type'        => 'string',
						),
						array(
							'description' => __( 'Widget is not in a sidebar.', 'be-rest-endpoints' ),
							'type'        => 'boolean',
						),
					),
					'context' => array( 'view', 'edit', 'embed' ),
				),
				'sidebar_params'  => array(
					'description' => __( 'Sidebar parameters used for rendering the widget.', 'be-rest-endpoints' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'has_output'      => array(
					'description' => __( 'Whether the widget will render or not.', 'be-rest-endpoints' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'instance_number' => array(
					'description' => __( 'Instance number of widget.', 'be-rest-endpoints' ),
					'type'        => 'int',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'instance'        => array(
					'description' => __( 'Instance of widget parameters.', 'be-rest-endpoints' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'widget_output'   => array(
					'description' => __( 'The rendered output of the widget in its sidebar.', 'be-rest-endpoints' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
			),
		);

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Gets the schema data for a widget instance.
	 *
	 * @TODO USE INFERRED SCHEMA DATA TO GET THIS.
	 *
	 * @param string $widget_base Base type of widget.
	 */
	public function get_instance_schema( $widget_base ) {
		return array();
	}

	/**
	 * Validate a widget based on id.
	 *
	 * @global $wp_registered_widgets
	 *
	 * @param string $widget_id The widget's id.
	 * @return boolean
	 */
	public function validate_widget( $widget_id ) {
		global $wp_registered_widgets;

		// If widget is a registered widget then it is valid.
		if ( array_key_exists( $widget_id, $wp_registered_widgets ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Returns the next widget instance number for a given widget class.
	 *
	 * Copied the function from wp-admin/includes/widgets.php
	 *
	 * @see function next_widget_id_number from wp-admin/includes/widgets.php
	 * @global array $wp_registered_widgets
	 *
	 * @param string $id_base ID base of widget i.e. text, pages, etc.
	 * @return int
	 */
	function next_widget_id_number( $id_base ) {
		global $wp_registered_widgets;
		$number = 1;

		foreach ( $wp_registered_widgets as $widget_id => $widget ) {
			if ( preg_match( '/' . $id_base . '-([0-9]+)$/', $widget_id, $matches ) ) {
				$number = max( $number, $matches[1] );
			}
		}
		$number++;

		return $number;
	}

	/**
	 * Infers schema data from the widget's form output.
	 *
	 * @param DOMDocument $dom    DOM Document containing the widget's form output.
	 * @param array       $widget Multi-dimensional array of widget from $wp_registered_widgets[ $widget_id ].
	 * @return void
	 */
	public function infer_schema( $dom, $widget ) {

		$inputs = $dom->getElementsByTagName( 'input' );

	}
	/**
	 * Get the query params for collections of attachments.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params = array();

		$params['context'] = 'view';

		return $params;
	}

	/**
	 * Get the query params for a particular type of request.
	 *
	 * @see $WP_REST_Server::CREATABLE, $WP_REST_Server::EDITABLE etc.
	 * @param string $method One of the values for the different constants in WP_REST_Server.
	 *                        Default 'POST'.
	 * @return array Params for
	 */
	public function get_endpoint_args_for_item_schema( $method = 'POST' ) {
		switch ( $method ) {
			case WP_REST_Server::CREATABLE :
				$params = array(
					'widget_base' => array(
						'description'       => __( 'The ID base for the type of widget you want to create.', 'be-rest-endpoints' ),
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'sidebar_id' => array(
						'description'       => __( 'Sidebar ID that widget should be placed into.', 'be-rest-endpoints' ),
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'sidebar_position' => array(
						'description'       => __( 'Position in the sidebar in which the widget should be placed.', 'be-rest-endpoints' ),
						'type'              => 'int',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
				);
				return $params;

			case WP_REST_Server::EDITABLE  :
				$params = array(
					'title' => array(
						'description'       => __( 'Widget title.', 'be-rest-endpoints' ),
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'position' => array(
						'description'       => __( 'Position in sidebar.', 'be-rest-endpoints' ),
						'type'              => 'int',
					),
				);
				return $params;

			case WP_REST_Server::DELETABLE :
				$params = array(
					'widget_id' => array(
						'description'       => __( 'Widget ID for the widget that you want to delete.', 'be-rest-endpoints' ),
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'force' => array(
						'description' => __( 'Whether or not to force delete method.', 'be-rest-endpoints' ),
						'type'        => 'boolean',
						'default'     => false,
					),
				);
				return $params;

			default :
				return false;
		}
	}
}
