<?php
/**
 * HTTP Caching Headers for Public REST Endpoints
 *
 * Adds Cache-Control and ETag headers to public GET endpoints
 * to enable browser-side caching and conditional requests.
 *
 * @since 2.13.0
 * @package Ambrosia_Dosage_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ADC_HTTP_Cache class.
 *
 * @package Ambrosia_Dosage_Calculator
 */
class ADC_HTTP_Cache {

	/**
	 * Public routes that should receive caching headers.
	 * Patterns are matched against the REST route attribute.
	 */
	private const CACHEABLE_ROUTES = array(
		'/adc/v1/strains',
		'/adc/v1/edibles',
		'/adc/v1/categories',
		'/adc/v1/product-types',
		'/adc/v1/compounds',
		'/adc/v1/settings',
		'/adc/v1/lookup/(?P<code>[a-zA-Z0-9-]+)',
		'/adc/v1/strains/(?P<code>[a-zA-Z0-9-]+)',
		'/adc/v1/edibles/(?P<code>[a-zA-Z0-9-]+)',
	);

	/** Browser cache lifetime in seconds (5 minutes). */
	private const MAX_AGE = 300;

	/**
	 * Register the rest_post_dispatch filter.
	 *
	 * @since 2.13.0
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'add_cache_headers' ), 10, 3 );
	}

	/**
	 * Attach Cache-Control and ETag headers to cacheable responses.
	 *
	 * @param WP_REST_Response $response The API response.
	 * @param WP_REST_Server   $server   The REST server instance.
	 * @param WP_REST_Request  $request  The original request.
	 * @return WP_REST_Response
	 */
	public static function add_cache_headers(
		WP_REST_Response $response,
		WP_REST_Server $server,
		WP_REST_Request $request
	): WP_REST_Response {
		// Cache successful GET and HEAD requests (BUG-011 fix)
		$method = $request->get_method();
		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			return $response;
		}

		if ( $response->get_status() < 200 || $response->get_status() >= 300 ) {
			return $response;
		}

		// Match the request route against our cacheable list.
		$route = $request->get_route();
		if ( ! self::is_cacheable_route( $route ) ) {
			return $response;
		}

		// Build ETag from response body.
		$etag = self::generate_etag( $response );

		// Set caching headers.
		$response->header( 'Cache-Control', 'public, max-age=' . self::MAX_AGE );
		$response->header( 'ETag', $etag );

		// Handle conditional request (If-None-Match).
		$if_none_match = $request->get_header( 'if_none_match' );
		if ( null !== $if_none_match ) {
			$server_etag = trim( $etag, '"' );
			// If-None-Match can contain comma-separated ETags; check each one.
			$client_etags = array_map(
				function ( $e ) {
					return trim( trim( $e ), ' "' );
				},
				explode( ',', $if_none_match )
			);

			if ( in_array( $server_etag, $client_etags, true ) || in_array( '*', $client_etags, true ) ) {
				$not_modified = new WP_REST_Response( null, 304 );
				$not_modified->header( 'Cache-Control', 'public, max-age=' . self::MAX_AGE );
				$not_modified->header( 'ETag', $etag );
				return $not_modified;
			}
		}

		return $response;
	}

	/**
	 * Check whether a route matches one of the cacheable patterns.
	 */
	private static function is_cacheable_route( string $route ): bool {
		foreach ( self::CACHEABLE_ROUTES as $pattern ) {
			// Build a full regex from the WordPress-style route pattern.
			$regex = '#^' . preg_replace( '/\(\?P<[^>]+>[^)]+\)/', '[^/]+', $pattern ) . '$#';
			if ( preg_match( $regex, $route ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Generate a deterministic ETag from the response data.
	 */
	private static function generate_etag( WP_REST_Response $response ): string {
		$data = $response->get_data();
		$hash = md5( wp_json_encode( $data ) );
		return '"' . $hash . '"';
	}
}
