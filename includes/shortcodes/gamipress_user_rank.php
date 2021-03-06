<?php
/**
 * GamiPress User Rank Shortcode
 *
 * @package     GamiPress\Shortcodes\Shortcode\GamiPress_User_Rank
 * @since       1.3.9.3
 */
// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

/**
 * Register [gamipress_user_rank] shortcode
 *
 * @since 1.0.0
 */
function gamipress_register_user_rank_shortcode() {

    // Setup a custom array of points types
    $rank_types = array();

    foreach ( gamipress_get_rank_types() as $slug => $data ) {
        $rank_types[$slug] = $data['singular_name'];
    }

    // Setup the rank fields
    $rank_fields = GamiPress()->shortcodes['gamipress_rank']->fields;

    unset( $rank_fields['id'] );

    gamipress_register_shortcode( 'gamipress_user_rank', array(
        'name'            => __( 'User Rank', 'gamipress' ),
        'description'     => __( 'Output previous, current and/or next rank of an user.', 'gamipress' ),
        'output_callback' => 'gamipress_user_rank_shortcode',
        'tabs' => array(
            'general' => array(
                'icon' => 'dashicons-admin-generic',
                'title' => __( 'General', 'gamipress' ),
                'fields' => array(
                    'type',
                    'prev_rank',
                    'current_rank',
                    'next_rank',
                    'current_user',
                    'user_id',
                    'columns',
                ),
            ),
            'rank' => array(
                'icon' => 'dashicons-awards',
                'title' => __( 'Rank', 'gamipress' ),
                'fields' => array_keys( $rank_fields ),
            ),
        ),
        'fields'      => array_merge( array(
            'type' => array(
                'name'        => __( 'Rank Type', 'gamipress' ),
                'description' => __( 'Choose the rank type to display.', 'gamipress' ),
                'type'        => 'select',
                'options'     => $rank_types,
            ),
            'prev_rank' => array(
                'name'        => __( 'Show Previous Rank', 'gamipress' ),
                'description' => __( 'Show the previous user rank.', 'gamipress' ),
                'type' 		  => 'checkbox',
                'classes' 	  => 'gamipress-switch',
                'default'     => 'yes'
            ),
            'current_rank' => array(
                'name'        => __( 'Show Current Rank', 'gamipress' ),
                'description' => __( 'Show the current user rank.', 'gamipress' ),
                'type' 		  => 'checkbox',
                'classes' 	  => 'gamipress-switch',
                'default'     => 'yes'
            ),
            'next_rank' => array(
                'name'        => __( 'Show Next Rank', 'gamipress' ),
                'description' => __( 'Show the next user rank.', 'gamipress' ),
                'type' 		  => 'checkbox',
                'classes' 	  => 'gamipress-switch',
                'default'     => 'yes'
            ),
            'current_user' => array(
                'name'        => __( 'Current User', 'gamipress' ),
                'description' => __( 'Show the current logged in user ranks.', 'gamipress' ),
                'type' 		  => 'checkbox',
                'classes' 	  => 'gamipress-switch',
                'default'     => 'yes'
            ),
            'user_id' => array(
                'name'        => __( 'User', 'gamipress' ),
                'description' => __( 'Show a specific user ranks.', 'gamipress' ),
                'type'        => 'select',
                'default'     => '',
                'options_cb'  => 'gamipress_options_cb_users'
            ),
            'columns' => array(
                'name'        => __( 'Columns', 'gamipress' ),
                'description' => __( 'Columns to divide ranks.', 'gamipress' ),
                'type' 	=> 'select',
                'options' => array(
                    '1' => __( '1 Column', 'gamipress' ),
                    '2' => __( '2 Columns', 'gamipress' ),
                    '3' => __( '3 Columns', 'gamipress' ),
                ),
                'default' => '1'
            ),
        ), $rank_fields ),
    ) );

}
add_action( 'init', 'gamipress_register_user_rank_shortcode' );

/**
 * User Rank Shortcode
 *
 * @since  1.3.9.3
 *
 * @param  array $atts Shortcode attributes
 *
 * @return string 	   HTML markup
 */
function gamipress_user_rank_shortcode( $atts = array () ) {

    global $gamipress_template_args;

    // Initialize GamiPress template args global
    $gamipress_template_args = array();

    $atts = shortcode_atts( array_merge( array(

        // User rank atts
        'type'        	=> '',
        'prev_rank'     => 'yes',
        'current_rank'  => 'yes',
        'next_rank' 	=> 'yes',
        'current_user' 	=> 'yes',
        'user_id' 		=> '0',
        'columns'       => '1',

    ), gamipress_rank_shortcode_defaults() ), $atts, 'gamipress_user_rank' );

    gamipress_enqueue_scripts();

    // On network wide active installs, we need to switch to main blog mostly for posts permalinks and thumbnails
    if( gamipress_is_network_wide_active() && ! is_main_site() ) {
        $blog_id = get_current_blog_id();
        switch_to_blog( get_main_site_id() );
    }

    // Not type provided
    if( $atts['type'] === '') {
        return '';
    }

    // Wrong rank
    if( ! in_array( $atts['type'], gamipress_get_rank_types_slugs() ) ) {
        return '';
    }

    // Nothing to show
    if( $atts['prev_rank'] === 'no' && $atts['current_rank'] === 'no' && $atts['next_rank'] === 'no' ) {
        return '';
    }

    // Force to set current user as user ID
    if( $atts['current_user'] === 'yes' ) {
        $atts['user_id'] = get_current_user_id();
    }

    // Guests not supported
    if( absint( $atts['user_id'] ) === 0 ) {
        return '';
    }

    // GamiPress template args global
    $gamipress_template_args = $atts;

    // Setup template vars
    $template_args = array(
        'user_id' 		=> $atts['user_id'], // User ID on rank is used to meet to which user apply earned checks
    );

    $rank_fields = GamiPress()->shortcodes['gamipress_rank']->fields;

    unset( $rank_fields['id'] );

    // Loop rank shortcode fields to pass to the rank template
    foreach( $rank_fields as $field_id => $field_args ) {

        if( isset( $atts[$field_id] ) ) {
            $template_args[$field_id] = $atts[$field_id];
        }
        
    }

    $gamipress_template_args['template_args'] = $template_args;

    // Try to load user-rank-{type}.php, if not exists then load user-rank.php
    ob_start();
    gamipress_get_template_part( 'user-rank', $atts['type'] );
    $output = ob_get_clean();

    // If switched to blog, return back to que current blog
    if( isset( $blog_id ) ) {
        switch_to_blog( $blog_id );
    }

    return $output;

}