<?php

/**
 * Gravity Wiz // Gravity Forms // Advanced Merge Tags
 *
 * Adds support for several advanced merge tags:
 *   + post:id=xx&prop=xxx
 *       retrieve the desired property of the specified post (by ID)
 *   + post_meta:id=xx&meta_key=xxx
 *       retrieve the desired post meta value from the specified post and meta key
 *   + get() modifier
 *       retrieve the desired property from the query string ($_GET)
 *       Example: post_meta:id=get(xx)&meta_key=xxx
 *   + post() modifier
 *       retrieve the enclosed property from the $_POST
 *       Example: post_meta:id=post(xx)&meta_key=xxx
 *
 * Use Cases
 *
 *   + You have a multiple realtors each represented by their own WordPress page. On each page is a "Contact this
 *   Realtor" link. The user clicks the link and is directed to a contact form. Rather than creating a host of
 *   different contact forms for each realtor, you can use this snippet to populate a HTML field with a bit of text
 *   like:
 *       "You are contacting realtor Bob Smith" except instead of Bob Smith, you would use
 *       "{post:id=pid&prop=post_title}. In this example, "pid" would be passed via the query string from the contact
 *       link and "Bob Smith" would be the
 *       "post_title" of the post the user is coming from.
 *
 * @version     1.0
 * @author      David Smith <david@gravitywiz.com>
 * @license     GPL-2.0+
 * @link        http://gravitywiz.com/...
 * @copyright   2013 Gravity Wiz
 */
class GW_Advanced_Merge_Tags extends WPS_GF_Merge_Tags {
	/**
	 * @TODO:
	 *   - add support for validating based on the merge tag (to prevent values from being changed)
	 *   - add support for merge tags in dynamic population parameters
	 *   - add merge tag builder
	 */
	private $_args = null;

	public static function get_instance( $args ) {
		if ( null == self::$instance ) {
			self::$instance = new self( $args );
		}

		return self::$instance;
	}

	private function init( $args ) {
		add_action( 'gform_pre_render', array( $this, 'support_default_value_and_html_content_merge_tags' ) );
		add_action( 'gform_pre_render', array( $this, 'support_dynamic_population_merge_tags' ) );
		add_action( 'gform_replace_merge_tags', array( $this, 'replace_merge_tags' ), 10, 3 );
		if ( $this->_args['save_source_post_id'] ) {
			add_filter( 'gform_entry_created', array( $this, 'save_source_post_id' ), 10, 2 );
		}
	}

	public function get_defaults() {
		return array(
			'save_source_post_id' => false
		);
	}

	public function support_default_value_and_html_content_merge_tags( $form ) {
		$current_page = isset( GFFormDisplay::$submission[ $form['id'] ] ) ? GFFormDisplay::$submission[ $form['id'] ]['page_number'] : 1;
		$fields       = array();
		foreach ( $form['fields'] as &$field ) {
//            $default_value = rgar( $field, 'defaultValue' );
//            preg_match_all( '/{.+}/', $default_value, $matches, PREG_SET_ORDER );
//            if( ! empty( $matches ) ) {
//                if( rgar( $field, 'pageNumber' ) != $current_page ) {
//                    $field['defaultValue'] = '';
//                } else {
//                    $field['defaultValue'] = $this->replace_merge_tags( $default_value, $form, null );
//                }
//            }
// only run 'content' filter for fields on the current page
			if ( rgar( $field, 'pageNumber' ) != $current_page ) {
				continue;
			}
			$html_content = rgar( $field, 'content' );
			preg_match_all( '/{.+}/', $html_content, $matches, PREG_SET_ORDER );
			if ( ! empty( $matches ) ) {
				$field['content'] = $this->replace_merge_tags( $html_content, $form, null );
			}
		}

		return $form;
	}

	public function support_dynamic_population_merge_tags( $form ) {
		$filter_names = array();
		foreach ( $form['fields'] as &$field ) {
			if ( ! rgar( $field, 'allowsPrepopulate' ) ) {
				continue;
			}
// complex fields store inputName in the "name" property of the inputs array
			if ( is_array( rgar( $field, 'inputs' ) ) && $field['type'] != 'checkbox' ) {
				foreach ( $field['inputs'] as $input ) {
					if ( rgar( $input, 'name' ) ) {
						$filter_names[] = array( 'type' => $field['type'], 'name' => rgar( $input, 'name' ) );
					}
				}
			} else {
				$filter_names[] = array( 'type' => $field['type'], 'name' => rgar( $field, 'inputName' ) );
			}
		}
		foreach ( $filter_names as $filter_name ) {
// do standard GF prepop replace first...
			$filtered_name = GFCommon::replace_variables_prepopulate( $filter_name['name'] );
// if default prepop doesn't find anything, do our advanced replace
			if ( $filter_name['name'] == $filtered_name ) {
				$filtered_name = $this->replace_merge_tags( $filter_name['name'], $form, null );
			}
			if ( $filter_name['name'] == $filtered_name ) {
				continue;
			}
			add_filter( "gform_field_value_{$filter_name['name']}", create_function( "", "return '$filtered_name';" ) );
		}

		return $form;
	}

	public function replace_merge_tags( $text, $form, $entry ) {
// matches {Label:#fieldId#}
//         {Label:#fieldId#:#options#}
//         {Custom:#options#}
		while ( preg_match_all( '/{(\w+)(:([\w&,=)(\-]+)){1,2}}/mi', $text, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				list( $tag, $type, $args_match, $args_str ) = array_pad( $match, 4, false );
				parse_str( $args_str, $args );
				$args  = array_map( array( $this, 'check_for_value_modifiers' ), $args );
				$value = '';
				switch ( $type ) {
					case 'post':
						$value = $this->get_post_merge_tag_value( $args );
						break;
					case 'post_meta':
					case 'custom_field':
						$value = $this->get_post_meta_merge_tag_value( $args );
						break;
					case 'entry':
						$args['entry'] = $entry;
						$value         = $this->get_entry_merge_tag_value( $args );
						break;
					case 'entry_meta':
						$args['entry'] = $entry;
						$value         = $this->get_entry_meta_merge_tag_value( $args );
						break;
					case 'callback':
						$args['callback'] = array_shift( array_keys( $args ) );
						unset( $args[ $args['callback'] ] );
						$args['entry'] = $entry;
						$value         = $this->get_callback_merge_tag_value( $args );
						break;
				}
// @todo: figure out if/how to support values that are not strings
				if ( is_array( $value ) || is_object( $value ) ) {
					$value = '';
				}
				$text = str_replace( $tag, $value, $text );
			}
		}

		return $text;
	}

	public function save_source_post_id( $entry, $form ) {
		if ( is_singular() && ! rgget( 'gf_page' ) ) {
			$post_id = get_queried_object_id();
			gform_update_meta( $entry['id'], 'source_post_id', $post_id );
		}
	}

	public function check_for_value_modifiers( $text ) {
// modifier regex (i.e. "get(value)")
		preg_match_all( '/([a-z]+)\(([a-z_\-]+)\)/mi', $text, $matches, PREG_SET_ORDER );
		if ( empty( $matches ) ) {
			return $text;
		}
		foreach ( $matches as $match ) {
			list( $tag, $type, $arg ) = array_pad( $match, 3, false );
			$value = '';
			switch ( $type ) {
				case 'get':
					$value = rgget( $arg );
					break;
				case 'post':
					$value = rgpost( $arg );
					break;
			}
			$text = str_replace( $tag, $value, $text );
		}

		return $text;
	}

	public function get_post_merge_tag_value( $args ) {
		extract( wp_parse_args( $args, array(
			'id'   => false,
			'prop' => false
		) ) );
		if ( ! $id || ! $prop ) {
			return '';
		}
		$post = get_post( $id );
		if ( ! $post ) {
			return '';
		}

		return isset( $post->$prop ) ? $post->$prop : '';
	}

	public function get_post_meta_merge_tag_value( $args ) {
		extract( wp_parse_args( $args, array(
			'id'       => false,
			'meta_key' => false
		) ) );
		if ( ! $id || ! $meta_key ) {
			return '';
		}
		$value = get_post_meta( $id, $meta_key, true );

		return $value;
	}

	public function get_entry_merge_tag_value( $args ) {
		extract( wp_parse_args( $args, array(
			'id'    => false,
			'prop'  => false,
			'entry' => false
		) ) );
		if ( ! $entry ) {
			if ( ! $id ) {
				$id = rgget( 'eid' );
			}
			if ( is_callable( 'gw_post_content_merge_tags' ) ) {
				$id = gw_post_content_merge_tags()->maybe_decrypt_entry_id( $id );
			}
			$entry = GFAPI::get_entry( $id );
		}
		if ( ! $prop ) {
			$prop = key( $args );
		}
		if ( ! $entry || is_wp_error( $entry ) || ! $prop ) {
			return '';
		}
		$value = rgar( $entry, $prop );

		return $value;
	}

	public function get_entry_meta_merge_tag_value( $args ) {
		extract( wp_parse_args( $args, array(
			'id'       => false,
			'meta_key' => false,
			'entry'    => false
		) ) );
		if ( ! $id ) {
			if ( rgget( 'eid' ) ) {
				$id = rgget( 'eid' );
			} else if ( isset( $entry['id'] ) ) {
				$id = $entry['id'];
			}
		}
		if ( ! $meta_key ) {
			$meta_key = key( $args );
		}
		if ( ! $id || ! $meta_key ) {
			return '';
		}
		if ( is_callable( 'gw_post_content_merge_tags' ) ) {
			$id = gw_post_content_merge_tags()->maybe_decrypt_entry_id( $id );
		}
		$value = gform_get_meta( $id, $meta_key );

		return $value;
	}

	public function get_callback_merge_tag_value( $args ) {
		$callback = $args['callback'];
		unset( $args['callback'] );
		extract( wp_parse_args( $args, array(
			'entry' => false
		) ) );
		if ( ! is_callable( $callback ) ) {
			return '';
		}

		return call_user_func( $callback, $args );
	}
}

