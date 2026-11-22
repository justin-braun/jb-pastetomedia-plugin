<?php
/**
 * Updates from GitHub Releases.
 *
 * The plugin header sets "Update URI" to the GitHub repository, so WordPress
 * never asks WordPress.org about this plugin and instead runs the
 * update_plugins_github.com filter, which this class answers from the latest
 * GitHub Release. The release must have the plugin zip attached.
 *
 * The repository is public, so no token is needed. Optional wp-config.php
 * constants: JBPTM_DISABLE_GITHUB_UPDATES (true) switches this off, and
 * JBPTM_GITHUB_TOKEN (a read-only token) raises GitHub's rate limit or allows
 * a private repository.
 *
 * Remove this file, its require and the Update URI header for a WordPress.org
 * listing, which does not allow plugins to update themselves.
 *
 * @package JB_Paste_To_Media
 */

defined( 'ABSPATH' ) || exit;

/**
 * Checks GitHub for new releases and downloads them.
 */
class JBPTM_Updater {

	const REPO      = 'justin-braun/jb-pastetomedia-plugin';
	const SLUG      = 'jb-paste-to-media';
	const CACHE     = 'jbptm_github_release';
	const CACHE_TTL = 6 * HOUR_IN_SECONDS;
	const ERROR_TTL = HOUR_IN_SECONDS;
	const API       = 'https://api.github.com/repos/';

	/**
	 * Hook everything up, only when updates from GitHub are switched on.
	 */
	public static function init() {
		if ( defined( 'JBPTM_DISABLE_GITHUB_UPDATES' ) && JBPTM_DISABLE_GITHUB_UPDATES ) {
			return;
		}
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'check' ), 10, 3 );
		add_filter( 'plugins_api', array( __CLASS__, 'info' ), 10, 3 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'pre_download' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'flush' ), 10, 0 );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
	}

	/**
	 * The optional token, or an empty string.
	 *
	 * @return string
	 */
	private static function token() {
		return defined( 'JBPTM_GITHUB_TOKEN' ) && is_string( JBPTM_GITHUB_TOKEN ) ? trim( JBPTM_GITHUB_TOKEN ) : '';
	}

	/**
	 * Headers for GitHub API requests. The User-Agent is generic on purpose.
	 *
	 * @param string $accept Accept header.
	 * @return array
	 */
	private static function headers( $accept = 'application/vnd.github+json' ) {
		$headers = array(
			'Accept'               => $accept,
			'User-Agent'           => 'jb-paste-to-media-updater',
			'X-GitHub-Api-Version' => '2022-11-28',
		);
		if ( '' !== self::token() ) {
			$headers['Authorization'] = 'Bearer ' . self::token();
		}
		return $headers;
	}

	/**
	 * GET a GitHub API path.
	 *
	 * @param string $path   Path after /repos/{owner}/{repo}/.
	 * @param string $accept Accept header.
	 * @return string|WP_Error Response body.
	 */
	private static function api_get( $path, $accept = 'application/vnd.github+json' ) {
		$response = wp_remote_get(
			self::API . self::REPO . '/' . $path,
			array(
				'headers' => self::headers( $accept ),
				'timeout' => 15,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			/* translators: %d: HTTP status code. */
			return new WP_Error( 'jbptm_github_http', sprintf( __( 'GitHub returned HTTP %d.', 'jb-paste-to-media' ), $code ) );
		}
		return wp_remote_retrieve_body( $response );
	}

	/**
	 * The latest release, cached. Holds either release data or an 'error' key.
	 *
	 * @return array
	 */
	private static function release() {
		// Dashboard > Updates > "Check again" skips the cache.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag, as in core.
		$force  = ! empty( $_GET['force-check'] ) && current_user_can( 'update_plugins' );
		$cached = get_site_transient( self::CACHE );
		if ( is_array( $cached ) && ! $force ) {
			return $cached;
		}

		$release = self::fetch();
		if ( is_wp_error( $release ) ) {
			$release = array( 'error' => $release->get_error_message() );
			set_site_transient( self::CACHE, $release, self::ERROR_TTL );
		} else {
			set_site_transient( self::CACHE, $release, self::CACHE_TTL );
		}
		return $release;
	}

	/**
	 * Fetch the latest release and the readme.txt headers at its tag.
	 *
	 * @return array|WP_Error
	 */
	private static function fetch() {
		$body = self::api_get( 'releases/latest' );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			return new WP_Error( 'jbptm_github_json', __( 'GitHub sent an unexpected response.', 'jb-paste-to-media' ) );
		}

		$version = ltrim( (string) $data['tag_name'], 'vV' );
		if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
			/* translators: %s: Git tag name. */
			return new WP_Error( 'jbptm_github_tag', sprintf( __( 'The latest release tag "%s" is not a version number.', 'jb-paste-to-media' ), $data['tag_name'] ) );
		}

		$package = '';
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			$name = (string) ( $asset['name'] ?? '' );
			if ( 0 === strpos( $name, self::SLUG . '-' ) && '.zip' === substr( $name, -4 ) ) {
				// A private repository's files are only reachable through the API URL.
				$package = '' !== self::token() ? (string) $asset['url'] : (string) $asset['browser_download_url'];
				break;
			}
		}
		if ( '' === $package ) {
			/* translators: %s: Release version. */
			return new WP_Error( 'jbptm_github_asset', sprintf( __( 'Release %s has no plugin zip attached.', 'jb-paste-to-media' ), $version ) );
		}

		$readme = self::api_get( 'contents/readme.txt?ref=' . rawurlencode( $data['tag_name'] ), 'application/vnd.github.raw+json' );

		return array(
			'version'      => $version,
			'package'      => $package,
			'url'          => (string) ( $data['html_url'] ?? '' ),
			'published'    => (string) ( $data['published_at'] ?? '' ),
			'notes'        => (string) ( $data['body'] ?? '' ),
			'tested'       => is_string( $readme ) ? self::readme_field( $readme, 'Tested up to' ) : '',
			'requires'     => is_string( $readme ) ? self::readme_field( $readme, 'Requires at least' ) : '',
			'requires_php' => is_string( $readme ) ? self::readme_field( $readme, 'Requires PHP' ) : '',
		);
	}

	/**
	 * Read one "Field: value" header from readme.txt.
	 *
	 * @param string $readme Readme contents.
	 * @param string $field  Field name.
	 * @return string
	 */
	private static function readme_field( $readme, $field ) {
		if ( preg_match( '/^' . preg_quote( $field, '/' ) . ':\s*([0-9.]+)\s*$/mi', $readme, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * Answer core's update check for this plugin.
	 *
	 * @param array|false $update      Update data from earlier filters.
	 * @param array       $plugin_data Plugin header data.
	 * @param string      $plugin_file Plugin basename.
	 * @return array|false
	 */
	public static function check( $update, $plugin_data, $plugin_file ) {
		if ( plugin_basename( JBPTM_FILE ) !== $plugin_file ) {
			return $update;
		}
		$release = self::release();
		if ( isset( $release['error'] ) ) {
			return $update;
		}
		return array(
			'slug'         => self::SLUG,
			'version'      => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'tested'       => $release['tested'],
			'requires'     => $release['requires'],
			'requires_php' => $release['requires_php'],
		);
	}

	/**
	 * The "View details" dialog.
	 *
	 * @param false|object|array $result Result from earlier filters.
	 * @param string             $action API action.
	 * @param object             $args   Request arguments.
	 * @return false|object|array
	 */
	public static function info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}
		$release = self::release();
		if ( isset( $release['error'] ) ) {
			return $result;
		}
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin = get_plugin_data( JBPTM_FILE, false, true );

		return (object) array(
			'name'          => $plugin['Name'],
			'slug'          => self::SLUG,
			'version'       => $release['version'],
			'author'        => $plugin['Author'],
			'homepage'      => $release['url'],
			'requires'      => $release['requires'],
			'tested'        => $release['tested'],
			'requires_php'  => $release['requires_php'],
			'last_updated'  => $release['published'],
			'download_link' => $release['package'],
			'sections'      => array(
				'description' => '<p>' . esc_html( $plugin['Description'] ) . '</p>',
				'changelog'   => self::markdown( $release['notes'] ),
			),
		);
	}

	/**
	 * Turn release notes (headings and bullet lists) into simple HTML.
	 *
	 * @param string $text Markdown.
	 * @return string
	 */
	private static function markdown( $text ) {
		$html    = '';
		$in_list = false;
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
			$line = trim( $line );
			$item = preg_match( '/^[-*]\s+(.*)$/', $line, $m );
			if ( $in_list && ! $item ) {
				$html   .= '</ul>';
				$in_list = false;
			}
			if ( $item ) {
				if ( ! $in_list ) {
					$html   .= '<ul>';
					$in_list = true;
				}
				$html .= '<li>' . esc_html( $m[1] ) . '</li>';
			} elseif ( preg_match( '/^#{1,6}\s+(.*)$/', $line, $m ) ) {
				$html .= '<h4>' . esc_html( $m[1] ) . '</h4>';
			} elseif ( '' !== $line ) {
				$html .= '<p>' . esc_html( $line ) . '</p>';
			}
		}
		if ( $in_list ) {
			$html .= '</ul>';
		}
		return '' !== $html ? $html : '<p>' . esc_html__( 'No release notes.', 'jb-paste-to-media' ) . '</p>';
	}

	/**
	 * Download a private release asset. The API answers with a redirect to a
	 * signed URL, which must be fetched without the token.
	 *
	 * @param bool|string|WP_Error $reply   Result from earlier filters.
	 * @param string               $package Package URL.
	 * @return bool|string|WP_Error Path to the downloaded file, or $reply.
	 */
	public static function pre_download( $reply, $package ) {
		$prefix = self::API . self::REPO . '/releases/assets/';
		if ( false !== $reply || '' === self::token() || 0 !== strpos( (string) $package, $prefix ) ) {
			return $reply;
		}

		$response = wp_remote_get(
			$package,
			array(
				'headers'     => self::headers( 'application/octet-stream' ),
				'redirection' => 0,
				'timeout'     => 30,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code     = (int) wp_remote_retrieve_response_code( $response );
		$location = wp_remote_retrieve_header( $response, 'location' );
		if ( $code >= 300 && $code < 400 && is_string( $location ) && '' !== $location ) {
			return download_url( $location, 300 );
		}
		/* translators: %d: HTTP status code. */
		return new WP_Error( 'jbptm_github_download', sprintf( __( 'Could not download the update from GitHub (HTTP %d).', 'jb-paste-to-media' ), $code ) );
	}

	/**
	 * Forget the cached release after any update, so the next check is fresh.
	 */
	public static function flush() {
		delete_site_transient( self::CACHE );
	}

	/**
	 * Show a failed update check on the Plugins screen.
	 *
	 * @param array  $meta        Row meta links.
	 * @param string $plugin_file Plugin basename.
	 * @return array
	 */
	public static function row_meta( $meta, $plugin_file ) {
		if ( plugin_basename( JBPTM_FILE ) !== $plugin_file || ! current_user_can( 'update_plugins' ) ) {
			return $meta;
		}
		$cached = get_site_transient( self::CACHE );
		if ( is_array( $cached ) && isset( $cached['error'] ) ) {
			/* translators: %s: Error message. */
			$meta[] = '<span style="color:#b32d2e">' . esc_html( sprintf( __( 'Update check failed: %s', 'jb-paste-to-media' ), $cached['error'] ) ) . '</span>';
		}
		return $meta;
	}
}
