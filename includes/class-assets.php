<?php
/**
 * Script and style loading for the editors.
 *
 * @package JB_Paste_To_Media
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the editor integrations.
 */
class JBPTM_Assets {

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'block_editor' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'classic_editor' ) );
		add_filter( 'mce_external_plugins', array( __CLASS__, 'mce_plugin' ) );
	}

	/**
	 * Register the shared script, styles and localized data.
	 */
	private static function enqueue_common() {
		if ( wp_script_is( 'jbptm-common', 'enqueued' ) ) {
			return;
		}
		wp_enqueue_style( 'jbptm-editor', JBPTM_URL . 'assets/css/editor.css', array(), JBPTM_VERSION );
		wp_enqueue_script( 'jbptm-common', JBPTM_URL . 'assets/js/common.js', array( 'wp-api-fetch', 'wp-i18n' ), JBPTM_VERSION, true );
		wp_set_script_translations( 'jbptm-common', 'jb-paste-to-media', JBPTM_DIR . 'languages' );

		$settings = JBPTM_Settings::get();
		$user     = wp_get_current_user();
		$post     = get_post();

		wp_localize_script(
			'jbptm-common',
			'jbptmData',
			array(
				'mode'  => $settings['mode'],
				'image' => $settings['image'],
				'ctx'   => array(
					'user'       => $user->exists() ? $user->user_login : '',
					'site'       => get_bloginfo( 'name' ),
					'post_id'    => $post ? $post->ID : 0,
					'post_title' => $post ? $post->post_title : '',
					'post_slug'  => $post ? $post->post_name : '',
					'post_type'  => $post ? $post->post_type : '',
				),
			)
		);
	}

	/**
	 * May the current user use the plugin in this editor?
	 *
	 * @param string $editor 'block' or 'classic'.
	 * @return bool
	 */
	private static function enabled( $editor ) {
		$settings = JBPTM_Settings::get();
		return ! empty( $settings[ 'enable_' . $editor ] ) && current_user_can( 'upload_files' );
	}

	/**
	 * Block editor integration. Also loads the shared script for the Classic block.
	 */
	public static function block_editor() {
		if ( self::enabled( 'block' ) || self::enabled( 'classic' ) ) {
			self::enqueue_common();
		}
		if ( self::enabled( 'block' ) ) {
			wp_enqueue_script( 'jbptm-block-editor', JBPTM_URL . 'assets/js/block-editor.js', array( 'jbptm-common', 'wp-data', 'wp-dom-ready', 'wp-blob', 'wp-block-editor' ), JBPTM_VERSION, true );
		}
	}

	/**
	 * Classic editor screens.
	 *
	 * @param string $hook Admin page hook.
	 */
	public static function classic_editor( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! self::enabled( 'classic' ) ) {
			return;
		}
		self::enqueue_common();
	}

	/**
	 * Register the TinyMCE plugin.
	 *
	 * @param array $plugins External plugins.
	 * @return array
	 */
	public static function mce_plugin( $plugins ) {
		if ( self::enabled( 'classic' ) ) {
			$plugins['jbptm'] = JBPTM_URL . 'assets/js/tinymce-plugin.js?ver=' . JBPTM_VERSION;
		}
		return $plugins;
	}
}
