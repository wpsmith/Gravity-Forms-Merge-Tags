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
abstract class WPS_GF_Merge_Tags {

	protected $_args = null;
	protected $_merge_tags = array();

	public static $instance = null;

	protected function __construct( $args ) {
		// Require Gravity Forms
		require_once( plugin_dir_path( GFMT_FILE ) . 'includes/WPS_Extend_Plugin.php' );
		new WPS_Extend_Plugin( 'gravityforms/gravityforms.php', PE_FILE, '1.9', PE_SLUG );

		// Merge Arguments
		$this->_args = wp_parse_args( $args, $this->get_defaults() );

		// Auto-replace tags
		add_action( 'gform_replace_merge_tags', array( $this, 'replace_merge_tags' ), 10, 7 );
		add_action( 'gform_custom_merge_tags', array( $this, 'add_custom_merge_tags' ), 10, 4 );

		// Add constructor to be extended
		if ( method_exists( $this, 'init' ) ) {
			$this->init();
		}
	}

	abstract public static function get_instance( $args );

	abstract protected function get_defaults();

	/**
	 * Replace custom merge tags in notifications.
	 *
	 * @param string $text
	 * @param array $form
	 * @param array $entry
	 * @param bool $url_encode
	 * @param bool $esc_html
	 * @param bool $nl2br
	 * @param string $format
	 * @return string
	 */
	abstract public function replace_merge_tags( $text, $form, $entry, $url_encode, $esc_html, $nl2br, $format );

	/**
	 * Add a custom merge tags.
	 *
	 * @param array $merge_tags
	 * @param int $form_id
	 * @param array $fields
	 * @param int $element_id
	 * @return array
	 */
	public function add( $label, $tag ) {
		if ( !isset( $this->_merge_tags ) && preg_match("/^{([a-zA-Z0-9]*)}$/", $tag, $matches) ) {
			$this->_merge_tags[] = array(
				'label' => $label,
				'tag'   => $tag,
			);
		}
	}

	/**
	 * Add custom merge tags.
	 *
	 * @param array $merge_tags
	 * @param int $form_id
	 * @param array $fields
	 * @param int $element_id
	 * @return array
	 */
	public function add_custom_merge_tags( $merge_tags, $form_id, $fields, $element_id ) {
		if ( !empty( $this->_merge_tags ) ) {
			foreach( $this->_merge_tags as $mt ) {
				$merge_tags[] = $mt;
			}
		}

		return $merge_tags;
	}
}


