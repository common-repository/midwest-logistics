<?php
    /*
    * Plugin Name: Midwest Logistics
    * Plugin URI:  https://plugins.skynet-solutions.net/
    * Description: Midwest Wholesale Plugin allows you to automatically add Woocommerce orders into the Midwest Logistics order system.
    * Version:     1.1.25
    * WC requires at least: 6.0.0
    * WC tested up to: 8.2.0
    * Author:      Skynet Solutions Inc.
    * Author URI:  http://www.skynet-solutions.net/
    * License:     GPLv3
    * License URI: https://www.gnu.org/licenses/gpl.html  
    * 
    * 
    * Midwest Logistics Import for Wordpress is free software: you can redistribute it and/or modify
    * it under the terms of the GNU General Public License as published by
    * the Free Software Foundation, either version 2 of the License, or
    * any later version.
    * Midwest Logistics Import for Wordpress is distributed in the hope that it will be useful,
    * but WITHOUT ANY WARRANTY; without even the implied warranty of
    * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
    * GNU General Public License for more details.
    * You should have received a copy of the GNU General Public License
    * along with Midwest Logistics Import for Wordpress. If not, see https://www.gnu.org/licenses/gpl.html.
    */
    if ( !function_exists( 'add_action' ) ){
	    header( 'Status: 403 Forbidden' );
	    header( 'HTTP/1.1 403 Forbidden' );
	    exit();
    }

    if ( !function_exists( 'add_filter' ) ){
	    header( 'Status: 403 Forbidden' );
	    header( 'HTTP/1.1 403 Forbidden' );
	    exit();
    }
    if ( ! defined( 'MIDWESTLOGISTICS_FILE' ) ) {
	    define( 'MIDWESTLOGISTICS_FILE', __FILE__ );
    }
    if ( !defined( 'MIDWESTLOGISTICS_URL' ) ) {
	    define( 'MIDWESTLOGISTICS_URL', plugin_dir_url( MIDWESTLOGISTICS_FILE ) );
    }
    if ( !defined( 'MIDWESTLOGISTICS_PATH' ) ) {
	    define( 'MIDWESTLOGISTICS_PATH', plugin_dir_path( MIDWESTLOGISTICS_FILE ) );
    }
    register_activation_hook( MIDWESTLOGISTICS_FILE, 'midwest_logistics_activate' );
	register_deactivation_hook( MIDWESTLOGISTICS_FILE, 'midwest_logistics_deactivate' );
    register_uninstall_hook( MIDWESTLOGISTICS_FILE, 'midwest_logistics_unistall' );
    add_action( 'init', 'midwest_logistics_initialize' );
    /* ***************************** Check/Set Globals *************************** */
    /*Host & Version should not be able to be overridden, so no if ( ! defined() ) */
    define( 'MIDWESTLOGISTICS_VERSION', '1.1' );
    //define( 'MIDWESTLOGISTICS_HOST', '' );
    if ( !defined( 'MIDWESTLOGISTICS_NAME' ) ) {
	    define( 'MIDWESTLOGISTICS_NAME', 'Midwest Logistics');
    }
   if ( !defined( 'MIDWESTLOGISTICS_SHIPPING_DEFAULT' ) ) {
	    define( 'MIDWESTLOGISTICS_SHIPPING_DEFAULT', '5');
    }
    if ( !defined( 'MIDWESTLOGISTICS_VALID' ) ) {
	    define( 'MIDWESTLOGISTICS_VALID', false);
    }
    if ( !defined( 'MIDWESTLOGISTICS_TABLE_NAME' ) ) {
	    define( 'MIDWESTLOGISTICS_TABLE_NAME', 'midwest_logistics_communication_log');
    }
    if ( !defined( 'MIDWESTLOGISTICS_URL' ) ) {
	    define( 'MIDWESTLOGISTICS_URL', plugin_dir_url( MIDWESTLOGISTICS_FILE ) );
    }
    if ( !defined( 'MIDWESTLOGISTICS_PATH' ) ) {
	    define( 'MIDWESTLOGISTICS_PATH', plugin_dir_path( MIDWESTLOGISTICS_FILE ) );
    }
    if ( !defined( 'MIDWESTLOGISTICS_LOGO' ) ) {
	    define( 'MIDWESTLOGISTICS_LOGO', MIDWESTLOGISTICS_URL . '/images/logo.png' );
    }

    if ( !defined( 'MIDWESTLOGISTICS_CACHE_DURATION' ) ) {
	    define( 'MIDWESTLOGISTICS_CACHE_DURATION', 10);
    }
    if ( !defined( 'MIDWESTLOGISTICS_CACHE_KEY' ) ) {
	    define( 'MIDWESTLOGISTICS_CACHE_KEY', '' );

    }
    
    //This is being faded out but left for right now. Setting is now in class-ML-API
    if ( !defined( 'MIDWESTLOGISTICS_API_URL' ) ) {
	    //define( 'MIDWESTLOGISTICS_API_URL', 'http://midwestlogistics.windev.skynet-solutions.net/api/json/' );
        define( 'MIDWESTLOGISTICS_API_URL', 'https://api.midwest-logistics.com/json/' );
    }

    //Check/Set WP Debug, Log and Display
    if( !defined( 'WP_DEBUG' ) ){
	    define( 'WP_DEBUG', false ); 
    }
    if( !defined( 'WP_DEBUG_LOG' ) ){
	    define( 'WP_DEBUG_LOG', false );
    }

    if( !defined( 'WP_DEBUG_DISPLAY' ) ){
	    define( 'WP_DEBUG_DISPLAY', false );
    }
    global $MIDWESTLOGISTICS, $MIDWESTLOGISTICS_activated, $MIDWESTLOGISTICS_capable;
    $MIDWESTLOGISTICS_capable = true;

    $MIDWESTLOGISTICS_activated = false;

    /* ***************************** Include Function Files *************************** */
    require_once( MIDWESTLOGISTICS_PATH . 'inc/setupFunctions.php' );
    if (in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {  
        require_once( MIDWESTLOGISTICS_PATH . 'classes/class-ml-settings.php' ); 
        /******************************** Check if API key exists yet ***********************/
        $apiKey = "";
        $settingOptions = get_option('midwest_logistics_settings','');
        if($settingOptions !== "") {
            $apiKey = $settingOptions["Midwest_Logistics_API_Key_field_0"];
        }
        
        if($apiKey <> "") {
            require_once( MIDWESTLOGISTICS_PATH . 'classes/class-ml-api.php' ); 
            require_once( MIDWESTLOGISTICS_PATH . 'classes/class-wc-ml-shipping-options.php' ); 
            require_once( MIDWESTLOGISTICS_PATH . 'classes/class-ml-settings-orders.php' ); 
            require_once( MIDWESTLOGISTICS_PATH . 'classes/class-ml-stock.php' ); 
            require_once( MIDWESTLOGISTICS_PATH . 'classes/class-ml-order.php' ); 
            
            require_once( MIDWESTLOGISTICS_PATH . 'inc/logFunctions.php' );  

            require_once( MIDWESTLOGISTICS_PATH . 'inc/trackingFunctions.php' ); 

            require_once( MIDWESTLOGISTICS_PATH . 'inc/productFunctions.php' ); 
            
            
            add_action( 'woocommerce_shipping_init', 'midwest_logistics_shipping_method_init' );
        }

        /* ***************************** HouseKeeping Processes *************************** */
        //Perform a single check to determine if MIDWESTLOGISTICS may be used
        if( version_compare( PHP_VERSION, '5.3.0' ) < 0 ){
	        $MIDWESTLOGISTICS_capable = false;
        }

        if( !$MIDWESTLOGISTICS_capable ){
	        add_action( 'admin_notices', 'midwest_logistics_warn_phpversion' );
        }
        function midwest_logistics_plugin_add_settings_link( $links ) {
            $mylinks = array(
                '<a href="' . admin_url( 'admin.php?page=midwest-logistics-options' ) . '">Settings</a>',
            );
            return array_merge( $links, $mylinks );
        }
        add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'midwest_logistics_plugin_add_settings_link' );
        add_action( 'admin_notices', 'midwest_logistics_no_api' );
        
        
        function midwest_logistics_shipping_method_init() {
            require_once( MIDWESTLOGISTICS_PATH . 'classes/class-wc-ml-shipping-method.php' ); 
        }
        
        //add support for Woocommerce HPOS system
        add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

        
    }
?>