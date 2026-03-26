<?php
/**
 * GitHub Updater for Ambrosia Dosage Calculator.
 *
 * Hooks into WordPress's plugin update system to check GitHub Releases
 * for new versions. Requires the GitHub repo to be public and releases
 * to be tagged (e.g. v2.25.0) with a zip asset named
 * ambrosia-dosage-calculator-v{version}.zip.
 *
 * @package Ambrosia_Dosage_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ADC_GitHub_Updater
 *
 * Checks the GitHub Releases API and injects update data into the
 * WordPress plugin update transient when a newer version is available.
 */
class ADC_GitHub_Updater {

	/**
	 * GitHub username / org.
	 *
	 * @var string
	 */
	private string $github_user = 'daveambrosia';

	/**
	 * GitHub repository slug.
	 *
	 * @var string
	 */
	private string $github_repo = 'mushroom-dosage-calc';

	/**
	 * WordPress plugin basename (folder/file.php).
	 *
	 * @var string
	 */
	private string $plugin_basename;

	/**
	 * Current installed version.
	 *
	 * @var string
	 */
	private string $current_version;

	/**
	 * Transient key for caching the remote release data.
	 *
	 * @var string
	 */
	private string $transient_key = 'adc_github_release_cache';

	/**
	 * How long to cache the API response (seconds).
	 *
	 * @var int
	 */
	private int $cache_ttl = 43200; // 12 hours

	/**
	 * Constructor.
	 *
	 * @param string $plugin_basename Plugin basename (e.g. ambrosia-dosage-calculator/ambrosia-dosage-calculator.php).
	 * @param string $current_version Currently installed version string.
	 */
	public function __construct( string $plugin_basename, string $current_version ) {
		$this->plugin_basename = $plugin_basename;
		$this->current_version = $current_version;
	}

	/**
	 * Register WordPress hooks.
	 */
	public function init(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'post_install' ), 10, 3 );
	}

	/**
	 * Fetch the latest release data from GitHub.
	 *
	 * Results are cached in a transient to avoid hitting the API on
	 * every page load. Returns null on error.
	 *
	 * @return array<string,mixed>|null Decoded release object or null.
	 */
	private function get_latest_release(): ?array {
		$cached = get_transient( $this->transient_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$api_url = sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			$this->github_user,
			$this->github_repo
		);

		$response = wp_remote_get(
			$api_url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			return null;
		}

		set_transient( $this->transient_key, $data, $this->cache_ttl );

		return $data;
	}

	/**
	 * Extract a clean version string from a GitHub tag name.
	 *
	 * Strips a leading "v" so "v2.25.0" becomes "2.25.0".
	 *
	 * @param string $tag Raw tag name from GitHub.
	 * @return string Clean version number.
	 */
	private function parse_version( string $tag ): string {
		return ltrim( $tag, 'v' );
	}

	/**
	 * Find the download URL for the plugin zip in a release.
	 *
	 * Looks for an asset named ambrosia-dosage-calculator-v{version}.zip.
	 * Falls back to the GitHub-generated zipball URL if no matching asset
	 * is found.
	 *
	 * @param array<string,mixed> $release Decoded GitHub release object.
	 * @param string              $version Clean version string.
	 * @return string Download URL.
	 */
	private function get_zip_url( array $release, string $version ): string {
		$expected_asset = 'ambrosia-dosage-calculator-v' . $version . '.zip';

		if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if (
					isset( $asset['name'], $asset['browser_download_url'] ) &&
					$asset['name'] === $expected_asset
				) {
					return $asset['browser_download_url'];
				}
			}
		}

		// Fallback: GitHub-generated source zip. Note this contains the
		// repo contents in a subdirectory named {repo}-{tag}/, so post_install
		// must handle the rename.
		return $release['zipball_url'] ?? '';
	}

	/**
	 * Hook: pre_set_site_transient_update_plugins
	 *
	 * Injects update info into the WP plugin updates transient when a
	 * newer version is available on GitHub.
	 *
	 * @param \stdClass $transient The current update_plugins transient.
	 * @return \stdClass Modified transient.
	 */
	public function check_for_update( \stdClass $transient ): \stdClass {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( null === $release ) {
			return $transient;
		}

		$remote_version = $this->parse_version( $release['tag_name'] );

		if ( version_compare( $remote_version, $this->current_version, '>' ) ) {
			$zip_url = $this->get_zip_url( $release, $remote_version );

			if ( ! empty( $zip_url ) ) {
				$update_obj                = new \stdClass();
				$update_obj->id            = $this->plugin_basename;
				$update_obj->slug          = dirname( $this->plugin_basename );
				$update_obj->plugin        = $this->plugin_basename;
				$update_obj->new_version   = $remote_version;
				$update_obj->url           = sprintf(
					'https://github.com/%s/%s',
					$this->github_user,
					$this->github_repo
				);
				$update_obj->package       = $zip_url;
				$update_obj->tested        = get_bloginfo( 'version' );
				$update_obj->requires_php  = '8.0';
				$update_obj->compatibility = new \stdClass();

				$transient->response[ $this->plugin_basename ] = $update_obj;
			}
		} else {
			// Tell WordPress the plugin is current. Without this entry in no_update,
			// WP treats the plugin as unchecked and the update screen shows nothing.
			$no_update_obj                = new \stdClass();
			$no_update_obj->id            = $this->plugin_basename;
			$no_update_obj->slug          = dirname( $this->plugin_basename );
			$no_update_obj->plugin        = $this->plugin_basename;
			$no_update_obj->new_version   = $this->current_version;
			$no_update_obj->url           = sprintf(
				'https://github.com/%s/%s',
				$this->github_user,
				$this->github_repo
			);
			$no_update_obj->package       = '';
			$no_update_obj->tested        = get_bloginfo( 'version' );
			$no_update_obj->requires_php  = '8.0';
			$no_update_obj->compatibility = new \stdClass();

			$transient->no_update[ $this->plugin_basename ] = $no_update_obj;
		}

		return $transient;
	}

	/**
	 * Hook: plugins_api
	 *
	 * Provides plugin info for the "View details" popup in WordPress
	 * when the user clicks on our plugin in the updates screen.
	 *
	 * @param false|\stdClass|\WP_Error $result  Existing result.
	 * @param string                    $action  API action requested.
	 * @param \stdClass                 $args    Request arguments.
	 * @return false|\stdClass Modified result.
	 */
	public function plugin_info( $result, string $action, \stdClass $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || dirname( $this->plugin_basename ) !== $args->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( null === $release ) {
			return $result;
		}

		$remote_version = $this->parse_version( $release['tag_name'] );
		$zip_url        = $this->get_zip_url( $release, $remote_version );
		$changelog      = isset( $release['body'] ) ? wp_kses_post( $release['body'] ) : '';

		$info                = new \stdClass();
		$info->name          = 'Ambrosia Dosage Calculator';
		$info->slug          = dirname( $this->plugin_basename );
		$info->version       = $remote_version;
		$info->author        = '<a href="https://ambrosia.church">Church of Ambrosia</a>';
		$info->homepage      = sprintf( 'https://github.com/%s/%s', $this->github_user, $this->github_repo );
		$info->requires      = '6.0';
		$info->tested        = get_bloginfo( 'version' );
		$info->requires_php  = '8.0';
		$info->download_link = $zip_url;
		$info->sections      = array(
			'description' => 'Psilocybin dosage calculator with strain &amp; edible management, QR codes, and customizable templates.',
			'changelog'   => ! empty( $changelog ) ? nl2br( $changelog ) : 'See GitHub releases for details.',
		);

		return $info;
	}

	/**
	 * Hook: upgrader_post_install
	 *
	 * After WordPress installs the update zip, ensures the plugin folder
	 * is named correctly (ambrosia-dosage-calculator). GitHub zipball
	 * archives unpack into a randomly-named subdirectory; built release
	 * zips should already have the correct structure.
	 *
	 * @param bool                $response   Install result (passed through).
	 * @param array<string,mixed> $hook_extra  Extra hook data from the upgrader.
	 * @param array<string,mixed> $result      Result data from WP_Upgrader.
	 * @return bool|\WP_Error
	 */
	public function post_install( $response, array $hook_extra, array $result ) {
		global $wp_filesystem;

		// Only act when this is our plugin being updated.
		if (
			! isset( $hook_extra['plugin'] ) ||
			$hook_extra['plugin'] !== $this->plugin_basename
		) {
			return $response;
		}

		$plugin_dir    = WP_PLUGIN_DIR . '/' . dirname( $this->plugin_basename );
		$installed_dir = $result['destination'] ?? '';

		// If WP already put files in the right place, nothing to do.
		if ( trailingslashit( $installed_dir ) === trailingslashit( $plugin_dir ) ) {
			return $response;
		}

		// Move from wherever WP extracted to the correct directory name.
		if ( $wp_filesystem instanceof \WP_Filesystem_Base ) {
			if ( $wp_filesystem->exists( $plugin_dir ) ) {
				$wp_filesystem->delete( $plugin_dir, true );
			}
			$wp_filesystem->move( $installed_dir, $plugin_dir, true );
		}

		// Re-activate the plugin after move.
		activate_plugin( $this->plugin_basename );

		return $response;
	}

	/**
	 * Clear the cached release data.
	 *
	 * Call this after pushing a new release if you want the update to
	 * appear immediately without waiting for the cache to expire.
	 */
	public function clear_cache(): void {
		delete_transient( $this->transient_key );
	}
}
