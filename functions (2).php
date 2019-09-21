<?php
/** *
 * Sets up the theme and provides some helper functions. Some helper functions
 * are used in the theme as custom template tags. Others are attached to action and
 * filter hooks in WordPress to change core functionality.
 *
 * When using a child theme (see http://codex.wordpress.org/Theme_Development and
 * http://codex.wordpress.org/Child_Themes), you can override certain functions
 * (those wrapped in a function_exists() call) by defining them first in your child theme's
 * functions.php file. The child theme's functions.php file is included before the parent
 * theme's file, so the child theme functions would be used.
 *
 * Functions that are not pluggable (not wrapped in function_exists()) are instead attached
 * to a filter or action hook. The hook can be removed by using remove_action() or
 * remove_filter() and you can attach your own function to the hook.
 *
 * For more information on hooks, actions, and filters, see http://codex.wordpress.org/Plugin_API.
 * Code found here https://indieweb.org/Wordpress_Webmention_Plugin and here https://gist.github.com/gRegorLove/8215cb9c9584b364aaf4ef2999416f56
 */

function unspam_webmentions($approved, $commentdata) {
  return $commentdata['comment_type'] == 'webmention' ? 1 : $approved;
}

add_filter('pre_comment_approved', 'unspam_webmentions', '99', 2);


if ( !function_exists('indieweb_check_webmention') ) {
	/**
	 * Using the webmention_source_url, approve webmentions that have been received from previously-
	 * approved domains. For example, once you approve a webmention from http://example.com/post,
	 * future webmentions from http://example.com will be automatically approved.
	 * Recommend placing in your theme's functions.php
	 *
	 * Based on check_comment()
	 * @see https://core.trac.wordpress.org/browser/tags/4.9/src/wp-includes/comment.php#L113
	 */
	function indieweb_check_webmention($approved, $commentdata) {
		global $wpdb;
		if ( 1 == get_option('comment_whitelist')) {
			if ( !empty($commentdata['comment_meta']['webmention_source_url']) ) {
				$like_domain = sprintf('%s://%s%%', parse_url($commentdata['comment_meta']['webmention_source_url'], PHP_URL_SCHEME), parse_url($commentdata['comment_meta']['webmention_source_url'], PHP_URL_HOST));
				$ok_to_comment = $wpdb->get_var( $wpdb->prepare( "SELECT comment_approved FROM $wpdb->comments WHERE comment_author = %s AND comment_author_url LIKE %s AND comment_approved = '1' LIMIT 1", $commentdata['comment_author'], $like_domain ) );
				if ( 1 == $ok_to_comment ) {
					return 1;
				}
			}
		}
		return $approved;
	}
	add_filter('pre_comment_approved', 'indieweb_check_webmention', '99', 2);
}

/**
 * Extend WordPress search to include custom fields
 *
 * https://adambalee.com
 * https://adambalee.com/search-wordpress-by-custom-fields-without-a-plugin/
 */

/**
 * Join posts and postmeta tables
 *
 * http://codex.wordpress.org/Plugin_API/Filter_Reference/posts_join
 */
function cf_search_join( $join ) {
    global $wpdb;

    if ( is_search() ) {    
        $join .=' LEFT JOIN '.$wpdb->postmeta. ' ON '. $wpdb->posts . '.ID = ' . $wpdb->postmeta . '.post_id ';
    }

    return $join;
}
add_filter('posts_join', 'cf_search_join' );

/**
 * Modify the search query with posts_where
 *
 * http://codex.wordpress.org/Plugin_API/Filter_Reference/posts_where
 */
function cf_search_where( $where ) {
    global $pagenow, $wpdb;

    if ( is_search() ) {
        $where = preg_replace(
            "/\(\s*".$wpdb->posts.".post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
            "(".$wpdb->posts.".post_title LIKE $1) OR (".$wpdb->postmeta.".meta_value LIKE $1)", $where );
    }

    return $where;
}
add_filter( 'posts_where', 'cf_search_where' );

/**
 * Prevent duplicates
 *
 * http://codex.wordpress.org/Plugin_API/Filter_Reference/posts_distinct
 */
function cf_search_distinct( $where ) {
    global $wpdb;

    if ( is_search() ) {
        return "DISTINCT";
    }

    return $where;
}
add_filter( 'posts_distinct', 'cf_search_distinct' );