<?php
/**
 * Network
 *
 * @package     GamiPress\Network
 * @since       1.4.0
 */
// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

/**
 * Initializes GamiPress on multisite installs
 *
 * @since 1.4.0
 */
function gamipress_init_multisite() {

    global $wpdb;

    if( is_multisite() ) {

        $blog_id = get_current_blog_id();

        // Update GamiPress database if network wide active
        if( gamipress_is_network_wide_active() ) {

            // Setup WordPress database tables
            GamiPress()->db->posts                  = $wpdb->base_prefix . 'posts';
            GamiPress()->db->postmeta               = $wpdb->base_prefix . 'postmeta';
            GamiPress()->db->users                  = $wpdb->base_prefix . 'users';
            GamiPress()->db->usermeta               = $wpdb->base_prefix . 'usermeta';
            GamiPress()->db->p2p                    = $wpdb->base_prefix . 'p2p';
            GamiPress()->db->p2pmeta                = $wpdb->base_prefix . 'p2pmeta';

            // Setup GamiPress database tables
            GamiPress()->db->logs 				    = $wpdb->base_prefix . 'gamipress_logs';
            GamiPress()->db->logs_meta 			    = $wpdb->base_prefix . 'gamipress_logs_meta';
            GamiPress()->db->user_earnings 		    = $wpdb->base_prefix . 'gamipress_user_earnings';
            GamiPress()->db->user_earnings_meta     = $wpdb->base_prefix . 'gamipress_user_earnings_meta';

        }

    }

}
add_action( 'gamipress_init', 'gamipress_init_multisite' );

/**
 * Create array of blog ids in the network if multisite setting is on
 *
 * @since  1.0.0
 *
 * @return array Array of blog_ids
 */
function gamipress_get_network_site_ids() {

    global $wpdb;

    if( is_multisite() && (bool) gamipress_get_option( 'ms_show_all_achievements', false ) ) {
        $blog_ids = $wpdb->get_results( "SELECT blog_id FROM " . $wpdb->base_prefix . "blogs" );
        foreach ($blog_ids as $key => $value ) {
            $sites[] = $value->blog_id;
        }
    } else {
        $sites[] = get_current_blog_id();
    }

    return $sites;

}

/**
 * Replace per site queries to root site when GamiPress is active network wide
 *
 * @since 1.4.0
 *
 * @param string    $request
 * @param WP_Query  $wp_query
 *
 * @return string
 */
function gamipress_network_wide_post_request( $request, $wp_query ) {

    global $wpdb;

    // If GamiPress is active network wide and we are not in main site, then filter all queries to our post types
    if(
        gamipress_is_network_wide_active()
        && ! is_main_site()
        && isset( $wp_query->query_vars['post_type'] )
    ) {

        $post_type = $wp_query->query_vars['post_type'];

        if( is_array( $post_type ) ) {
            $post_type = $post_type[0];
        }

        if(
            in_array( $post_type, array( 'points-type', 'achievement-type', 'rank-type' ) )
            || in_array( $post_type, gamipress_get_requirement_types_slugs() )
            || in_array( $post_type, gamipress_get_achievement_types_slugs() )
            || in_array( $post_type, gamipress_get_rank_types_slugs() )
        ) {

            // Replace {prefix}{site}posts to {prefix}posts
            $request = str_replace( $wpdb->posts, "{$wpdb->base_prefix}posts", $request );

            // Replace {prefix}{site}postmeta to {prefix}postmeta
            $request = str_replace( $wpdb->postmeta, "{$wpdb->base_prefix}postmeta", $request );

            // Replace {prefix}{site}p2p to {prefix}p2p
            $request = str_replace( $wpdb->p2p, "{$wpdb->base_prefix}p2p", $request );

            // Replace {prefix}{site}p2pmeta to {prefix}p2pmeta
            $request = str_replace( $wpdb->p2pmeta, "{$wpdb->base_prefix}p2pmeta", $request );
        }

    }

    return $request;

}
add_filter( 'posts_request', 'gamipress_network_wide_post_request', 10, 2 );

/**
 * Check if GamiPress is network wide active
 *
 * @since 1.4.0
 *
 * @return bool
 */
function gamipress_is_network_wide_active() {

    if( GamiPress()->network_wide_active === null ) {

        if( ! is_multisite() ) {

            // Set to false if not is a multisite install
            GamiPress()->network_wide_active = false;

        } else {

            // Normally the is_plugin_active_for_network() function is only available in the admin area
            if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
                require_once ABSPATH . '/wp-admin/includes/plugin.php';
            }

            GamiPress()->network_wide_active = is_plugin_active_for_network( 'gamipress/gamipress.php' );
        }

    }

    return GamiPress()->network_wide_active;

}

/**
 * On save settings, also update GamiPress site option to make it network wide accessible
 *
 * @since 1.4.0
 *
 * @param string    $override
 * @param mixed     $options
 * @param CMB2      $cmb
 *
 * @return mixed
 */
function gamipress_save_network_settings( $override, $options, $cmb ) {

    if( gamipress_is_network_wide_active() ) {
        // Set options as network option
        update_site_option( 'gamipress_settings', $options );
    }

    return $override;

}
add_action( 'cmb2_override_option_save_gamipress_settings', 'gamipress_save_network_settings', 10, 3 );

/**
 * Filter the post edit link to return edit link from main site
 *
 * @since 1.4.0
 *
 * @param string $link    The edit link.
 * @param int    $post_id Post ID.
 * @param string $context The link context. If set to 'display' then ampersands are encoded.
 *
 * @return string
 */
function gamipress_main_site_edit_post_link( $link, $post_id, $context ) {

    if( ! gamipress_is_network_wide_active() || is_main_site() ) {
        return $link;
    }

    $post_type = gamipress_get_post_type( $post_id );

    if(
        in_array( $post_type, array( 'points-type', 'achievement-type', 'rank-type' ) )
        || in_array( $post_type, gamipress_get_achievement_types_slugs() )
        || in_array( $post_type, gamipress_get_rank_types_slugs() )
    ) {

        $post_type_object = get_post_type_object( $post_type );

        if ( 'display' == $context )
            $action = '&amp;action=edit';
        else
            $action = '&action=edit';

        $link = get_admin_url( get_main_site_id(), sprintf( $post_type_object->_edit_link . $action, $post_id ) );
    }

    return $link;

}
add_filter( 'get_edit_post_link', 'gamipress_main_site_edit_post_link', 10, 3 );