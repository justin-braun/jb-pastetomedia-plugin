<?php
/**
 * Remove plugin data on uninstall. Uploaded media is left untouched.
 *
 * @package JB_Paste_To_Media
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'jbptm_settings' );
