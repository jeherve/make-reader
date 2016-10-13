<?php

/**
 * Plugin Name: Make WordPress Reader
 * Plugin URI: https://jeremy.hu/make-wordpress-reader/
 * Description: Display a list of the most recent blog posts on all the Make WordPress blogs.
 * Author: Jeremy Herve
 * Version: 1.0.0
 * Author URI: https://jeremy.hu
 * License: GPL2+
 * Text Domain: make-reader
 * Domain Path: /languages/
 */

/**
 * Start our class.
 */
class Jeherve_Make_WordPress_Reader {
	private static $instance;

	static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new Jeherve_Make_WordPress_Reader;
		}

		return self::$instance;
	}

	private function __construct() {
		// For debugging, for now
		add_action( 'wp_head', array( $this, 'get_posts_list' ) );
	}

	/**
	 * Build list of URLs to get posts from.
	 *
	 * @since 1.0.0
	 *
	 * @return array $urls Array of URLs.
	 */
	private function get_query_urls() {
		// Create empty array.
		$urls = array();

		// List of blog IDs to get posts from.
		$blogs = array(
			'core'          => '38254163',
			'design'        => '31759332',
			'mobile'        => '39085466',
			'accessibility' => '29901991',
			'polyglots'     => '31792945',
			'support'       => '38494741',
			'themes'        => '31759950',
			'docs'          => '31760022',
			'community'     => '42922441',
			'plugins'       => '31760039',
			'training'      => '46403572',
			'meta'          => '42105265',
			'tv'            => '94469038',
			'flow'          => '69109521',
		);

		/**
		 * Filter the list of blog IDs used for our query.
		 *
		 * Want to add your own blog, but don't know the blog ID?
		 * Check your site's source code when logged out of your account.
		 * Look for the Stats tracking code at the bottom of the page.
		 * The blog ID is in there.
		 *
		 * @since 1.0.0
		 *
		 * @param array $blogs Array of Blog IDs.
		 */
		$blogs = apply_filters( 'make_wordpress_blog_ids', $blogs );

		// Return early if we don't have blog IDs.
		if ( ! is_array( $blogs ) || empty( $blogs ) ) {
			return $urls;
		}

		// Build the API query URLs.
		foreach ( $blogs as $blog => $id ) {
			/**
			 * Filter the URL used to make the query for posts.
			 *
			 * @since 1.0.0
			 *
			 * @param string $query_url Query URL. Default is a WordPress.com REST API posts endpoint.
			 * @param int    $id        Blog ID.
			 */
			$query_url = apply_filters(
				'make_wordpress_query_url',
				sprintf(
					esc_url( 'https://public-api.wordpress.com/rest/v1.1/sites/%s/posts/' ),
					absint( $id )
				),
				$id
			);

			// Add the URL to the array.
			$urls[] = $query_url;
		}

		return $urls;
	}

	/**
	 * Get Posts Query.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url URL to query.
	 *
	 * @return null|array $response_body Response body with all recent posts for said site.
	 */
	public function get_posts( $url ) {
		// Build a hash of the query URL. We'll use it later when building the transient.
		$query_hash = substr( md5( $url ), 0, 21 );

		// Look for data in our transient. If nothing, let's get a new list.
		$data_from_cache = get_transient( 'jeherve_make_reader' . $query_hash );
		if ( false === $data_from_cache ) {
			$data = wp_remote_get( esc_url_raw( $url ) );

			if (
				is_wp_error( $data )
				|| empty( $data )
				|| 200 != $data['response']['code']
				|| empty( $data['body'] )
			) {
				return;
			}

			$response_body = json_decode( wp_remote_retrieve_body( $data ) );

			if (
				( isset( $response_body->error ) && 'jetpack_error' == $response_body->error )
				|| empty( $response_body )
				|| isset( $response_body->found ) && '0' == $response_body->found
			) {
				return;
			}

			/**
			 * Filter the amount of time each post list is cached.
			 *
			 * @since 1.0.0
			 *
			 * @param string $post_list_caching Amount of time each post list is cached. Default to 10 minutes.
			 */
			$post_list_caching = apply_filters( 'make_reader_posts_cache', 10 * MINUTE_IN_SECONDS );

			set_transient( 'jeherve_make_reader' . $query_hash, $response_body, $post_list_caching );
		} else {
			$response_body = $data_from_cache;
		}

		return (array) $response_body;
	}

	/**
	 * Store an array of posts from all sites.
	 *
	 * @since 1.0.0
	 *
	 * @return null|array $posts Array of all the posts.
	 */
	public function get_posts_list() {
		// Start an empty array.
		$posts = array();

		$urls = $this->get_query_urls();

		// Return early if we don't have a good list.
		if ( ! is_array( $urls ) || empty( $urls ) ) {
			return;
		}

		foreach ( $urls as $url ) {
			$posts[] = $this->get_posts( $url );
		}

		// Let's only keep 5 posts for now.
		$posts = array_slice( $posts, 0, 3 );

		print_r( $posts );
	}

	/**
	 * Sort 2 posts anti-chronogically.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_a Publication date of the first post.
	 * @param string $post_b Publication date of the second post.
	 */
	private function sort_date( $post_a, $post_b ) {
		return strtotime( $post_a->posts->date ) - strtotime( $post_a->posts->date );
	}
}
// And boom.
Jeherve_Make_WordPress_Reader::get_instance();
