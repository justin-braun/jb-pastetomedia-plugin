<?php
/**
 * Plugin Name:       JB Paste to Media
 * Description:       Paste images from the clipboard directly into posts and pages at the cursor, with templated filenames, alt text, captions and upload folders.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Justin Braun
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       jb-paste-to-media
 * Domain Path:       /languages
 *
 * @package JB_Paste_To_Media
 */

defined( 'ABSPATH' ) || exit;

define( 'JBPTM_VERSION', '0.1.0' );
define( 'JBPTM_FILE', __FILE__ );
define( 'JBPTM_DIR', plugin_dir_path( __FILE__ ) );
define( 'JBPTM_URL', plugin_dir_url( __FILE__ ) );

require_once JBPTM_DIR . 'includes/class-settings.php';
require_once JBPTM_DIR . 'includes/class-upload.php';
require_once JBPTM_DIR . 'includes/class-assets.php';

add_action(
	'plugins_loaded',
	static function () {
		JBPTM_Settings::init();
		JBPTM_Upload::init();
		JBPTM_Assets::init();
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( $links ) {
		$url = admin_url( 'options-general.php?page=jb-paste-to-media' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'jb-paste-to-media' ) . '</a>' );
		return $links;
	}
);
