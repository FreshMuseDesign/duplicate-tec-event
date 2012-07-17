<?php
/*
 Plugin Name: Duplicate TEC Event
 Plugin URI: 
 Description: Adds the ability to duplicate an event created by Modern Tribe's The Event Calendar plugin. This plugin utilizes the TEC functions to ensure that the new event gets passed through all proper filters
 Version: 1.0
 Author: Ben Lobaugh
 Author URI: http://ben.lobaugh.net
 Text Domain: duplicate-tec-event
 */

define( 'DTE_TEXT_DOMAIN', 'duplicate-tec-event' );

define( 'DTE_PLUGIN_DIR', dirname( plugin_basename( __FILE__ ) ) );

load_plugin_textdomain( DTE_TEXT_DOMAIN, false, trailingslashit( DTE_PLUGIN_DIR ) . 'lang' );


// Ensure we are in wp-admin before performing any additional actions
if( is_admin() ) {
    
    // Setup links on events listing to duplicate
    add_filter( 'post_row_actions', 'dte_row_actions', 10, 2);
    
    if( isset( $_GET['action'] ) 
            && 'duplicate_tribe_event' == $_GET['action'] 
            && isset( $_GET['post'] )
            && is_numeric( $_GET['post'] )
      ) {
          dte_duplicate_tribe_event( $_GET['post'] );
    }
        
        
}

function dte_row_actions( $actions, $post ) {
    // Before altering the available actions, ensure we are on the tribe events page
    if( $post->post_type != 'tribe_events' ) return $actions;
    
    $actions['duplicate_tribe_event'] = '<a href=\''.admin_url('?post_type=tribe_events&action=duplicate_tribe_event&post='.$post->ID).'\'>Duplicate</a>';;
    
    return $actions;
}

function dte_duplicate_tribe_event( $event_id ) {
    if ( !class_exists( 'TribeEventsAPI' ) ) 
                require_once( WP_PLUGIN_DIR . "/the-events-calendar/lib/tribe-event-api.class.php" );
    
    
    $event = (array)get_post( $event_id );
    unset( $event['ID'] ); // Remove ID to prevent an update from happening
    $event['post_status'] = 'draft';
    
    $meta = get_post_custom( $event_id );
    
    // Flatten out the meta array (WTF?)
    $fmeta = array();
    foreach( $meta AS $k => $v ) {
        $fmeta[$k] = $v[0];
    }
    
    $event = array_merge( $event, $fmeta );
    
    $new_event_id = TribeEventsAPI::createEvent( $event );
    
    // Merge in any additional meta that may have been missed by createEvent
    foreach( $fmeta AS $k => $v ) {
        update_post_meta( $new_event_id, $k, $v );
    }
    
    // Send back to the original page
    header("Location: " . admin_url("edit.php?post_type=tribe_events" ) );
}

function d($w) {
    echo '<pre>'; var_dump($w); echo '</pre>';
}