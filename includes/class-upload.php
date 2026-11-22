<?php
/**
 * Routes pasted uploads into the configured uploads subfolder.
 *
 * @package JB_Paste_To_Media
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hooks into the core REST media endpoint for uploads flagged as pasted.
 */
class JBPTM_Upload {

	/**
	 * Rendered subfolder for the current request, or null.
	 *
	 * @var string|null
	 */
	private static $subdir = null;

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'before' ), 10, 3 );
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'after' ), 10, 3 );
	}

	/**
	 * Is this a pasted upload to POST /wp/v2/media?
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	private static function is_paste_upload( $request ) {
		return $request instanceof WP_REST_Request
			&& 'POST' === $request->get_method()
			&& '/wp/v2/media' === untrailingslashit( $request->get_route() )
			&& $request->get_param( 'jbptm_paste' )
			&& current_user_can( 'upload_files' );
	}

	/**
	 * Install the upload_dir filter before core handles the upload.
	 *
	 * @param mixed           $response Response (passed through).
	 * @param array           $handler  Route handler.
	 * @param WP_REST_Request $request  Request.
	 * @return mixed
	 */
	public static function before( $response, $handler, $request ) {
		if ( ! self::is_paste_upload( $request ) ) {
			return $response;
		}
		$settings = JBPTM_Settings::get();
		$type     = 'image';
		$template = isset( $settings[ $type ]['folder'] ) ? $settings[ $type ]['folder'] : '';
		if ( '' === $template ) {
			return $response;
		}
		$post_id = absint( $request->get_param( 'post' ) );
		$folder  = JBPTM_Settings::sanitize_folder( self::render( $template, $post_id ) );
		if ( '' !== $folder ) {
			self::$subdir = '/' . $folder;
			add_filter( 'upload_dir', array( __CLASS__, 'upload_dir' ), 20 );
		}
		return $response;
	}

	/**
	 * Remove the filter once the request is done.
	 *
	 * @param mixed $response Response (passed through).
	 * @return mixed
	 */
	public static function after( $response ) {
		if ( null !== self::$subdir ) {
			remove_filter( 'upload_dir', array( __CLASS__, 'upload_dir' ), 20 );
			self::$subdir = null;
		}
		return $response;
	}

	/**
	 * Point uploads at the configured subfolder.
	 *
	 * @param array $dirs Upload dir data.
	 * @return array
	 */
	public static function upload_dir( $dirs ) {
		if ( null === self::$subdir ) {
			return $dirs;
		}
		$dirs['subdir'] = self::$subdir;
		$dirs['path']   = $dirs['basedir'] . self::$subdir;
		$dirs['url']    = $dirs['baseurl'] . self::$subdir;
		return $dirs;
	}

	/**
	 * Resolve tokens on the server (the folder is never taken from the client).
	 *
	 * @param string $template Template.
	 * @param int    $post_id  Post the upload is attached to, if any.
	 * @return string
	 */
	public static function render( $template, $post_id = 0 ) {
		$post = $post_id ? get_post( $post_id ) : null;
		$user = wp_get_current_user();
		$now  = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- local time wanted for folder names.
		$slug = '';
		if ( $post ) {
			$slug = $post->post_name ? $post->post_name : sanitize_title( $post->post_title );
		}

		$map = array(
			'{post_id}'    => $post ? (string) $post->ID : '',
			'{post_title}' => $post ? sanitize_title( $post->post_title ) : '',
			'{post_slug}'  => $slug,
			'{post_type}'  => $post ? $post->post_type : '',
			'{user}'       => $user && $user->exists() ? sanitize_title( $user->user_login ) : '',
			'{site}'       => sanitize_title( get_bloginfo( 'name' ) ),
			'{date}'       => gmdate( 'Y-m-d', $now ),
			'{time}'       => gmdate( 'His', $now ),
			'{year}'       => gmdate( 'Y', $now ),
			'{month}'      => gmdate( 'm', $now ),
			'{day}'        => gmdate( 'd', $now ),
			'{rand}'       => strtolower( wp_generate_password( 6, false ) ),
		);
		return strtr( $template, $map );
	}
}
