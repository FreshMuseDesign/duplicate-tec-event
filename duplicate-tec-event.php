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

define( 'DTE_TRIBE_API' , WP_PLUGIN_DIR . "/the-events-calendar/lib/tribe-event-api.class.php" );

load_plugin_textdomain( DTE_TEXT_DOMAIN, false, trailingslashit( DTE_PLUGIN_DIR ) . 'lang' );

$eid = 0;
// Ensure we are in wp-admin before performing any additional actions
if( is_admin() ) {
    // Ensure the TEC plugin exists
    if( file_exists( DTE_TRIBE_API ) ) {
        // Setup links on events listing to duplicate
        add_filter( 'post_row_actions', 'dte_row_actions', 10, 2);

        if( isset( $_GET['action'] ) 
                && 'duplicate_tribe_event' == $_GET['action'] 
                && isset( $_GET['post'] )
                && is_numeric( $_GET['post'] )
        ) {
            //dte_duplicate_tribe_event( $_GET['post'] );
            $eid = $_GET['post'];
            add_action( 'init', 'dte_duplicate_tribe_event' );
        }
    } else {
        // Show alert telling user to install TEC
        add_thickbox();
        add_action( 'admin_notices', 'dte_admin_notice_install_tec' );
    }
}

function dte_admin_notice_install_tec() {
    $url = 'plugin-install.php?tab=search&s=the+events+calendar&plugin-search-input=Search+Plugins';
    $url = admin_url('plugin-install.php?tab=plugin-information&plugin=the-events-calendar&TB_iframe=true&width=640&height=517');
    echo '<div class="error">
       <p>You must install <a href="'.$url.'" class="thickbox onclick">The Events Calendar</a> plugin before enabling the Duplicate TEC Event plugin!</p>
    </div>';
}


function dte_row_actions( $actions, $post ) {
    // Before altering the available actions, ensure we are on the tribe events page
    if( $post->post_type != 'tribe_events' ) return $actions;
    
    $actions['duplicate_tribe_event'] = '<a href=\''.admin_url('?post_type=tribe_events&action=duplicate_tribe_event&post='.$post->ID).'\'>Duplicate</a>';;
    
    return $actions;
}

function dte_duplicate_tribe_event( /*$event_id*/ ) {
    $event_id = $_GET['post'];
    
    if ( !class_exists( 'TribeEventsAPI' ) ) 
        if( !file_exists( DTE_TRIBE_API ))
                return false;
        require_once( DTE_TRIBE_API );
    
    
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
    header("Location: " . admin_url("edit.php?post_type=tribe_events" ) );
}


function d($w) {
    echo '<pre>'; var_dump($w); echo '</pre>';
}