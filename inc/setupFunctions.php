<?php
/* Does User Belong Here */
if ( ! defined( 'MIDWESTLOGISTICS_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

function midwest_logistics_initialize() {


    //Do we need to update the db table?
    if( empty(get_option( 'midwest_logistics_db_version' )) || get_option( 'midwest_logistics_db_version' ) !== '1.1' ){
        //Run any updates for table or option settings here. It will be ran when the plugin updates.


    }  
    if (!in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {  
        add_action( 'admin_notices', 'midwest_logistics_no_woo' );
        $MIDWESTLOGISTICS_activated = false;
    }
}

function midwest_logistics_activate() {
    if (in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        midwest_logistics_create_table();
        midwest_logistics_create_order_table();

        //set up the scheduler stock update
        if ( ! wp_next_scheduled( 'midwest_logistics_inventory_stock_update' ) ) {
            wp_schedule_event(time(), 'hourly', 'midwest_logistics_inventory_stock_update');
        }

        //set up the scheduler for the tracking information
        if ( ! wp_next_scheduled( 'midwest_logistics_update_tracking_information' ) ) {
            wp_schedule_event(time(), 'hourly', 'midwest_logistics_update_tracking_information');
        }

        midwest_logistics_revert_update_partial_orders();

        update_option( 'midwest_logistics_db_version', '1.1' ); 
        $MIDWESTLOGISTICS_activated = true;
    }     
}

function midwest_logistics_deactivate() {
    wp_clear_scheduled_hook('midwest_logistics_inventory_stock_update');
    wp_clear_scheduled_hook('midwest_logistics_update_tracking_information');
    midwest_logistics_update_partial_orders();
}

function midwest_logistics_unistall() {
    midwest_logistics_delete_table();
    delete_option("midwest_logistics_settings");
    delete_option("midwest_logistics_db_version");
    //wp_clear_scheduled_hook('midwest_logistics_inventory_stock_update');
    //wp_clear_scheduled_hook('midwest_logistics_update_tracking_information');
}



/*
* Registers the scripts needed for the Midwest Woocommerce.
*/
add_action( 'admin_enqueue_scripts', 'midwest_logistics_admin_script' );
function midwest_logistics_admin_script($hook) {
    wp_enqueue_script( 'midwest_logistics_admin_script', plugin_dir_url( __FILE__ )   . '../scripts/admin.js', false, '1.0.0' );
}

function midwest_logistics_warn_phpversion() {
    ?>
    <div class="notice-error notice is-dismissible">
	    <p><strong><?php _e( "PHP version 5.3.0 is required to run this plugin.",MIDWESTLOGISTICS_NAME); ?></strong></p>
    </div>
    <?php
}

function midwest_logistics_no_woo() {
    if (! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        ?>
        <div class="notice-error notice is-dismissible">
	        <p><strong><?php _e( "Woocommerce is required to run the " . MIDWESTLOGISTICS_NAME ." plugin. Please install or activate Woocommerce.",MIDWESTLOGISTICS_NAME); ?></strong></p>
        </div>
        <?php
    }
        
}

function  midwest_logistics_no_api() {
    $settingsOptions = get_option( 'Midwest_Logistics_settings' );

    $apiKey = "";
    $settingOptions = get_option('Midwest_Logistics_settings','');
    if($settingOptions !== "") {
        $apiKey = $settingOptions["Midwest_Logistics_API_Key_field_0"];
    }
    if ($apiKey === "" ) {
        ?>
        <div class="notice-error notice is-dismissible">
	        <p><strong><?php _e( "An API key is required before " . MIDWESTLOGISTICS_NAME . " plugin can be used.",MIDWESTLOGISTICS_NAME); ?></strong></p>
        </div>
        <?php
    }        
}


function midwest_logistics_inventory_json_parse($json) {
    if(json_decode($json) !== null) {
        $jsonResponse = json_decode($json);
        $curl_result = $jsonResponse -> {"result"};            
        if($curl_result === "200") {
            $responseArray = array("result" => true, "message" => $jsonResponse -> {"message"}, "stockAmount" => $jsonResponse -> {"stock"}, "instock" => $jsonResponse -> {"instock"});
        } else {
            $responseArray =  array("result" => false, "message" => $jsonResponse -> {"message"}, "stockAmount" => "0", "instock" => "N");
        }
    } else {
        new WP_Error(  MIDWESTLOGISTICS_NAME . ' API',"Invalid Call" );
        $responseArray =   array("result" => false, "message" => "Invalid Call", "stockAmount" => "0", "instock" => "N");
    }

    return $responseArray;

}


function midwest_logistics_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . MIDWESTLOGISTICS_TABLE_NAME; 
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(9) NOT NULL AUTO_INCREMENT,
        dateadded datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        JSONsent text NOT NULL,
        JSONresponse text NOT NULL,
        post_id bigint NOT NULL,
        post_meta_key varchar(255),
        post_meta_value longtext,
        response varchar(255),
        PRIMARY KEY  (id)
    ) $charset_collate;";
        
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
function midwest_logistics_create_order_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . "midwest_logistics_orders";
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(9) NOT NULL AUTO_INCREMENT,
        post_id bigint,
        initial_status varchar(255),
        changed_to_status varchar(255),
        PRIMARY KEY  (id)
    ) $charset_collate;";
        
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

function midwest_logistics_delete_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . MIDWESTLOGISTICS_TABLE_NAME;
    $sql = "DROP TABLE IF EXISTS $table_name";
    $wpdb->query($sql);

    $table_name = $wpdb->prefix . "midwest_logistics_orders";
    $sql = "DROP TABLE IF EXISTS $table_name";
    $wpdb->query($sql);
}

function midwest_logistics_update_partial_orders() {
    global $wpdb;
    $table_name = $wpdb->prefix . "posts";
    $order_table_name = $wpdb->prefix . "midwest_logistics_orders";
    $orderArray = [];
    $sql = "INSERT INTO $order_table_name
            (post_id,initial_status,changed_to_status) 
            SELECT wP.ID,post_status,'wc-processing'
            FROM $table_name wP
            WHERE post_status = 'wc-partial-shipped'
            AND post_type='shop_order'; ";

    $wpdb->query($sql);

    $sql = "UPDATE wp_posts SET post_status = 'wc-processing'
            WHERE ID IN (
	            SELECT post_id
                FROM wp_midwest_logistics_orders    
            )
            AND post_status = 'wc-partial-shipped'
            AND post_type='shop_order'; ";
    $wpdb->query($sql);
}

function midwest_logistics_revert_update_partial_orders() {
    global $wpdb;
    $table_name = $wpdb->prefix . "posts";
    $order_table_name = $wpdb->prefix . "midwest_logistics_orders";
    $orderArray = [];
    $sql = "UPDATE $table_name SET post_status = 'wc-partial-shipped'
            WHERE ID IN (
	            SELECT post_id
                FROM $order_table_name    
            )
            AND post_status = 'wc-processing'
            AND post_type='shop_order';";

    $wpdb->query($sql);

    $sql = "DELETE FROM $order_table_name;";
    $wpdb->query($sql);
}


/* For debug purposes */
//function debug_me() {
//    var_dump(wp_next_scheduled( 'midwest_logistics_inventory_stock_update' ));
//}
//add_action( 'wp_loaded', 'midwest_logistics_inventory_stock_update' );

