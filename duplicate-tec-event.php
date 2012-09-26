<?php
/*
 Plugin Name: Duplicate TEC Event
 Plugin URI: https://github.com/FreshMuseDesign/duplicate-tec-event
 Description: Adds the ability to duplicate an event created by Modern Tribe's The Event Calendar plugin. This plugin utilizes the TEC functions to ensure that the new event gets passed through all proper filters
 Version: 1.3
 Author: Ben Lobaugh
 Author URI: http://ben.lobaugh.net
 Text Domain: duplicate-tec-event
 */

define( 'DTE_TEXT_DOMAIN', 'duplicate-tec-event' );

define( 'DTE_PLUGIN_DIR', dirname( plugin_basename( __FILE__ ) ) );

load_plugin_textdomain( DTE_TEXT_DOMAIN, false, trailingslashit( DTE_PLUGIN_DIR ) . 'lang' );


// Ensure we are in wp-admin before performing any additional actions
if( is_admin() ) {
    add_action( 'admin_init', 'dte_init' );
}

/**
 * Gets the plugin rolling
 * 
 * - Ensures TEC is available
 * - Adds the 'Duplicate' action to the TEC list page
 * - Checks to see if duplication should be performed
 */
function dte_init() {
    // Ensure the TEC plugin exists
    if( class_exists( 'TribeEventsAPI' ) ) {
        // Setup links on events listing to duplicate
        add_filter( 'post_row_actions', 'dte_row_actions', 10, 2);

        if( isset( $_GET['action'] ) 
                && 'duplicate_tribe_event' == $_GET['action'] 
                && isset( $_GET['post'] )
                && is_numeric( $_GET['post'] )
        ) {
            dte_duplicate_tribe_event();
            //add_action( 'admin_init', 'dte_duplicate_tribe_event' );
        }
    } else {
        // Show alert telling user to install TEC
        //add_thickbox();
        add_action( 'admin_enqueue_scripts', 'add_thickbox' );
        add_action( 'admin_notices', 'dte_admin_notice_install_tec' );
    }
}

/**
 * Displays a notification in wp-admin informing the user to install TEC.
 * Provides a link to the installer 
 */
function dte_admin_notice_install_tec() {    
    $url = admin_url('plugin-install.php?tab=plugin-information&plugin=the-events-calendar&TB_iframe=true&width=640&height=517');
    
    echo '<div class="error">
       <p>You must install <a href="'.$url.'" class="thickbox onclick">The Events Calendar</a> plugin before enabling the Duplicate TEC Event plugin!</p>
    </div>';
}


/**
 * Adds additional actions to the TEC listing page
 * @param array $actions
 * @param type $post
 * @return string 
 */
function dte_row_actions( $actions, $post ) {
    // Before altering the available actions, ensure we are on the tribe events page
    if( $post->post_type != 'tribe_events' ) return $actions;

    $nonce = wp_create_nonce( 'dte_duplicate_event' );
    $actions['duplicate_tribe_event'] = '<a href=\''.admin_url('?post_type=tribe_events&action=duplicate_tribe_event&post='.$post->ID).'&_nonce=' . $nonce . '\'>Duplicate</a>';;
    
    return $actions;
}

/**
 * Performs the work of duplicating the TEC event
 * @return boolean 
 */
function dte_duplicate_tribe_event() {
    if( !isset( $_GET['_nonce'] ) || !wp_verify_nonce( $_GET['_nonce'], 'dte_duplicate_event' ) )
            return false;
    
    $event_id = $_GET['post'];
    
    if ( !class_exists( 'TribeEventsAPI' ) ) 
        return false;
    
    
    $event = (array)get_post( $event_id );
    unset( $event['ID'] ); // Remove ID to prevent an update from happening
    $event['post_status'] = 'draft';
        
    $meta = get_post_custom( $event_id );
    
    // Flatten out the meta array (WTF?)
    $fmeta = array();
    foreach( $meta AS $k => $v ) {
        $fmeta[$k] = $v[0];
    }
    
    
    // TEC expects a couple fields to exist without the _ upon creation
    $event['EventStartDate'] = date( 'Y-m-d', strtotime( $fmeta['_EventStartDate'] ) );
    $event['EventStartHour'] = date( 'h', strtotime( $fmeta['_EventStartDate'] ) );
    $event['EventStartMinute'] = date( 'i', strtotime( $fmeta['_EventStartDate'] ) );
    $event['EventEndDate'] = date( 'Y-m-d', strtotime( $fmeta['_EventEndDate'] ) );
    $event['EventEndHour'] = date( 'h', strtotime( $fmeta['_EventEndDate'] ) );
    $event['EventEndMinute'] = date( 'j', strtotime( $fmeta['_EventEndDate'] ) );
    
    // Unset recurrence to prevent potentially thousands of new events being created
    // This will also unlink an individual recurrence from its parent
    unset( $fmeta['_EventRecurrence'] );
    
    $event = array_merge( $event, $fmeta );
    
    $new_event_id = TribeEventsAPI::createEvent( $event );
    
    // Merge in any additional meta that may have been missed by createEvent
    foreach( $fmeta AS $k => $v ) {
        update_post_meta( $new_event_id, $k, $v );
    }
    
    // Copy the taxonomies
    $taxonomies = get_object_taxonomies( 'tribe_events' );
    
    foreach( $taxonomies AS $tax ) {
        $terms = wp_get_object_terms( $event_id, $tax );
        $term = array();
        foreach( $terms AS $t ) {
            $term[] = $t->slug;
        } 
       
        wp_set_object_terms( $new_event_id, $term, $tax );
    }
    
    // Send back to the original page
    wp_redirect(admin_url("edit.php?post_type=tribe_events" ) ); exit;
}