<?php
/**
 * Plugin Name: Gravity Forms - Merge Tags
 * Plugin URI: http://wpsmith.net/
 * Description: Post Content Merge Tags Support
 * Version: 1.0.0
 * Author: Travis Smith, WP Smith
 * Author URI: http://wpsmith.net
 * Text Domain: gravityforms-mergetags
 *
 * @copyright 2015
 * @author Travis Smith <t@wpsmith.net>
 * @link http://wpsmith.net/
 * @author    David Smith <david@gravitywiz.com>
 * @link      http://gravitywiz.com/
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'GFMT_VERSION', '1.0.0' );
define( 'GFMT_SLUG', 'gravityforms-mergetags' );
define( 'GFMT_FILE', __FILE__ );


function gw_advanced_merge_tags( $args = array() ) {
	return GW_Advanced_Merge_Tags::get_instance( $args );
}

function gw_post_content_merge_tags( $args = array() ) {
	return GW_Post_Content_Merge_Tags::get_instance( $args );
}

gw_post_content_merge_tags();