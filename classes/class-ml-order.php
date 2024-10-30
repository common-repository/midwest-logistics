<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


/**
 * Description of class-ml-order
 *
 * @author mburton
 */
class ML_Order {

    public function __construct() {
        //this must be set for the wp scheduler to work.
        add_action( 'woocommerce_order_status_changed',array($this, 'midwest_logistics_process_order') );
        add_action( 'woocommerce_admin_order_data_after_order_details',array($this, 'midwest_logistics_order_status_box') );               
        add_action( 'woocommerce_process_shop_order_meta',array($this, 'midwest_logistics_order_status_box_update'), 10, 2 ); 
        add_action( 'wp_ajax_midwest_logistics_process_shop_order',array($this, 'midwest_logistics_admin_ajax_process_order'),100 );
        add_action( 'admin_notices',array($this, 'midwest_logistics_admin_order_status_notification_notice'),100 );
        add_action( 'admin_notices',array($this, 'midwest_logistics_admin_order_error_notification_notice'),100 );
        add_action( 'init',array($this, 'midwest_logisitcs_register_awaiting_shipment_order_status'));
        add_filter( 'wc_order_statuses',array($this, 'midwest_logisitcs_add_awaiting_shipment_to_order_statuses'));
        add_filter( 'woocommerce_order_item_meta_end',array($this, 'midwest_logisitcs_woocommerce_email_order_item_meta_fields'), 100, 3 );
        add_filter( 'woocommerce_order_item_get_formatted_meta_data',array($this, 'midwest_logisitcs_order_item_get_formatted_meta_data'), 10, 1 );
        add_action( 'midwest_logistics_update_tracking_information',array($this, 'midwest_logistics_order_cron_job') );
    }  
    function midwest_logistics_process_order($orderId) {
        $settingOptions = get_option('Midwest_Logistics_settings','');
        if ( empty( $settingOptions ) ) {
            return;
        }

        //Get Shipping Settings
        $shipping_settings_data = array(
            "default_shipping" => MIDWESTLOGISTICS_SHIPPING_DEFAULT,// ups
            "enable_mapping" => "",
            "rate_links" => array()

        );
        $settings = get_option("ML_WC_Shipping_options");
        if($settings != false) {
            $settings = maybe_unserialize($settings);
        }
        if(is_array($settings)) {
            $shipping_settings_data = array_merge($shipping_settings_data,$settings);
        }

        $default_shipping = $shipping_settings_data["default_shipping"];
        if($default_shipping === "") {
            $default_shipping = "100000045";
        }
        $mapped_shipping = $shipping_settings_data["enable_mapping"];
        $plugin_shipping_rates = $shipping_settings_data["rate_links"];

        $order = wc_get_order( $orderId );   
        if ( empty( $order ) ) {
            return;
        }

        $orderStatus = $order->get_meta('_midwest_logistics_CRM_order_status');
        $midwest_logistics_auto_push_select_field = $settingOptions['Midwest_Logistics_auto_push_select_field'];
        $currentCRMId = $order->get_meta('_midwest_logistics_CRM_order_id' );
        if($currentCRMId !== "" && $currentCRMId !== "0") {
            return;
        }
        if($orderStatus === "")  {
            $orderStatus = $midwest_logistics_auto_push_select_field;
        }

        if($orderStatus === "")  {
            $orderStatus = "1";
        }

        if($orderStatus != "2") { //do not send unless it is marked to do so.
            return;
        }

        $midwest_logistics_order_status_setting = $settingOptions['Midwest_Logistics_select_field_2'];
        $midwest_logistics_api_key = $settingOptions["Midwest_Logistics_API_Key_field_0"];

        // Get an instance of the WC_Order object (same as before)

        if("wc-" . $order->get_status() === $midwest_logistics_order_status_setting) {
            //proccess that order;
            //get the order items
            $jsonOrderItemsArray = [];
            $order_item = $order->get_items();
            foreach( $order_item as $item ) {
                $curr_product_id = $item -> get_product_id();  
                if(isset($item["variation_id"]) )           
                {            
                    if($item["variation_id"] !== 0) {          
                        $curr_product_id = $item["variation_id"];                    
                    }
                }
                $product = wc_get_product($curr_product_id);

                $currentSKU = $product->get_meta('_midwest_logistics_product_sku_text_field');
                $is_enabled = $product->get_meta('_midwest_logistics_product_select');

                if($is_enabled <> "Y") {
                    $currentSKU = "";
                }
                if($currentSKU !== "") {
                    array_push($jsonOrderItemsArray,Array("name" => $item -> get_name(),"QTY" => $item -> get_quantity()  ,"SKU" =>$currentSKU));
                }
            }
            if(count($jsonOrderItemsArray) > 0) {
                $ShipToName =  ($order->get_formatted_shipping_full_name() !== " ") ? $order->get_formatted_shipping_full_name() : $order->get_formatted_billing_full_name();
                $ShipToCompany = ($order->get_shipping_company() !== "") ? $order->get_shipping_company() : $order->get_billing_company();
                $ShipToStreet = ($order->get_shipping_address_1() !== "") ? $order->get_shipping_address_1() : $order->get_billing_address_1();
                $ShipToStreetTwo = ($order->get_shipping_address_2() !== "") ? $order->get_shipping_address_2() : $order->get_billing_address_2();
                $ShipToCity = ($order->get_shipping_city() !== "") ? $order->get_shipping_city() : $order->get_billing_city();
                $ShipToState = ($order->get_shipping_state() !== "") ? $order->get_shipping_state() : $order->get_billing_state();
                $ShipToCountry = ($order->get_shipping_country() !== "") ? $order->get_shipping_country() : $order->get_billing_country();
                $ShipToZip = ($order->get_shipping_postcode() !== "") ? $order->get_shipping_postcode() : $order->get_billing_postcode();
                $shipVia = "USPS";
                $shipViaCode = $default_shipping;

                //get mapped shipping.
                if($mapped_shipping === "yes") {
                    $shipping_instance_id = "0";
                    $order_shipping_methods = $order->get_shipping_methods();
                    $shipping_class_names = WC_Shipping::instance()->get_shipping_methods();
                    $shipping_name = "";
                    if(is_array($order_shipping_methods)) {                
                        //Map the shipping option to the option they selected.
                        foreach($order_shipping_methods as $order_shipping_method) {

                            //only worry about the first one we find.
                            $shipping_instance_id = $order_shipping_method->get_instance_id();
                            $shipping_name = html_entity_decode($order_shipping_method->get_method_title());
                            if(strpos($shipping_name,"(") != false) {
                                $shipping_name = rtrim(substr($shipping_name,0,strpos($shipping_name,"(")));
                            }    
                            break;  
                        }
                        //find the instance this was made from
                        $shipping_instance_class = null;
                        $zone_class = new WC_Shipping_Zones();
                        $zones = $zone_class::get_zones();
                        if(!empty($zones) && $zones !== false && is_array($zones)) {
                            foreach($zones as $zone) {
                                $shipping_methods = $zone["shipping_methods"];
                                if(is_array($shipping_methods)) {
                                    foreach($shipping_methods as $shipping_method) {
                                        $instance_id = $shipping_method->get_instance_id();
                                        if($shipping_instance_id == $instance_id) {
                                            $shipping_instance_class = $shipping_method;
                                            break;
                                        }
                                    }
                                }
                            }
                        } 

                        //If UPS or USPS handle from there
                        if(!empty($shipping_instance_class) && $shipping_name <> "") {

                            if($shipping_instance_class instanceof WC_Shipping_UPS) {
                                require_once( MIDWESTLOGISTICS_PATH . 'classes/class-ml-ups-shipping.php' );
                                $ups_class = new ML_UPS_Shipping($shipping_instance_id);
                                $services = $ups_class->get_services();
                                foreach($services as $service) {
                                    $selected_service = html_entity_decode($service[1]);
                                    if(strpos($selected_service,"(") != false) {
                                        $selected_service = rtrim(substr($selected_service,0,strpos($selected_service,"(")));
                                    }
                                    if($shipping_name === $selected_service) {
                                        $shipViaCode = isset($plugin_shipping_rates[$shipping_instance_id][$service[0]]) ? $plugin_shipping_rates[$shipping_instance_id][$service[0]] : $default_shipping;
                                        break;
                                    }
                                }                            
                            }elseif($shipping_instance_class instanceof WC_Shipping_USPS) {

                                require_once( MIDWESTLOGISTICS_PATH . 'classes/class-ml-usps-shipping.php' );
                                $usps_class = new ML_USPS_Shipping($shipping_instance_id);
                                $services = $usps_class->get_services();

                                foreach($services as $service) {
                                    $selected_service = html_entity_decode($service[1]);
                                    if(strpos($selected_service,"(") != false) {
                                        $selected_service = rtrim(substr($selected_service,0,strpos($selected_service,"(")));
                                    }
                                    if($shipping_name === $selected_service) {
                                        $shipViaCode = isset($plugin_shipping_rates[$shipping_instance_id][$service[0]]) ? $plugin_shipping_rates[$shipping_instance_id][$service[0]] : $default_shipping;
                                        break;
                                    }
                                }            
                            } else {
                                $shipViaCode = isset($plugin_shipping_rates[$shipping_instance_id]) ? $plugin_shipping_rates[$shipping_instance_id] : $default_shipping;
                            }
                        } else {
                            $shipViaCode = isset($plugin_shipping_rates[$shipping_instance_id]) ? $plugin_shipping_rates[$shipping_instance_id] : $default_shipping;

                        }
                    }
                    if(is_array($shipViaCode)) {
                        $shipViaCode = $default_shipping;
                    }
                }
                $jsonArray = [
                    "apiKey" => $midwest_logistics_api_key,
                    "request" => "addorders",
                    "orders" => [
                        [
                            "orderId" => 'WP-' . $order ->get_order_number(),
                            "billToName" => $order->get_formatted_billing_full_name(),
                            "billToStreet" => $order->get_billing_address_1(),
                            "billToStreetTwo" => $order->get_billing_address_2(),
                            "billToCity" => $order->get_billing_city(),
                            "billToState" => $order->get_billing_state(),
                            "billToCountry" => $order->get_billing_country(),
                            "billToZip" => $order->get_billing_postcode(),
                            "CustomerEmail" => $order->get_billing_email(),
                            "ShipToName" => $ShipToName,
                            "ShipToCompany" => $ShipToCompany,
                            "ShipToStreet" => $ShipToStreet,
                            "ShipToStreetTwo" => $ShipToStreetTwo,
                            "ShipToCity" => $ShipToCity,
                            "ShipToState" => $ShipToState,
                            "ShipToCountry" => $ShipToCountry,
                            "ShipToZip" => $ShipToZip,
                            "ShipToPhone" => $order->get_billing_phone(),
                            "ShipVia" => $shipVia,
                            "ShipViaCode" => $shipViaCode,
                            "shippingMethod" => $order->get_shipping_method(),
                            "orderTotal" => $order->get_total(),
                            "products" => $jsonOrderItemsArray

                        ]
                    ]
                ];

                $httpcode = "500";
                $postString = json_encode ($jsonArray);  
                
                $API = new ML_API();
                $response = $API->send($postString);
                if($response != null) {
                    $httpcode = $response["code"];
                    $response = $response["response"];
                }

                //add the log
                midwest_logistics_add_communication_log($postString,$response,$order ->get_order_number(),"order",$CRMorder,$responseText);
                
                $responseText = "";
                if($httpcode == "200") {
                    if(json_decode($response) !== null) {
                        $jsonResponse = json_decode($response);
                        $curl_result = $jsonResponse -> {"result"};  
                        $responseText = $jsonResponse -> {"message"};
                        if($curl_result === "200") {
                            $CRMorder = $jsonResponse -> {"orders"};
                            if(is_array($CRMorder)) {
                                $CRMorderId = $CRMorder[0] -> CRMOrderId;

                                if($CRMorderId !== "0" && $CRMorderId != "") {
                                    $order->update_meta_data('_midwest_logistics_CRM_order_id', esc_attr( $CRMorderId ) );
                                    $order->update_meta_data('_midwest_logistics_CRM_order_status', esc_attr( "2" ) );
                                } else {
                                    $order->update_meta_data('_midwest_logistics_CRM_order_status', esc_attr( "3" ) );
                                }
                            }
                            $responseArray = array("result" => true, "message" => $jsonResponse -> {"message"});
                        } else {
                            $order->update_meta_data('_midwest_logistics_CRM_order_status', esc_attr( "3" ) );
                            $responseArray =  array("result" => false, "message" => $jsonResponse -> {"message"});
                        }
                    } else {
                        new WP_Error(  MIDWESTLOGISTICS_NAME . ' API',"Invalid Call" );
                        $order->update_meta_data('_midwest_logistics_CRM_order_status', esc_attr( "3" ) );
                        $responseText = "Invalid Call";
                        $responseArray =   array("result" => false, "message" => "Invalid Call");
                    }
                }
                if($httpcode == "500") {
                    new WP_Error(  MIDWESTLOGISTICS_NAME . ' API',MIDWESTLOGISTICS_NAME . " API is down." );
                    $order->update_meta_data('_midwest_logistics_CRM_order_status', esc_attr( "3" ) );
                    $responseText = MIDWESTLOGISTICS_NAME . " API is down.";
                    $responseArray =  array("result" => false, "message" => MIDWESTLOGISTICS_NAME . " API is down.");
                }
               
            }
            $order->save_meta_data();
        }            
    }
    
    function midwest_logistics_order_status_box($wccm_before_checkout) {
        $orderId = get_the_ID();

        // Get an instance of the WC_Order object (same as before)
        $order = wc_get_order( $orderId );   
        if ( empty( $order ) ) {
            return;
        }

        $orderStatus = $order->get_meta('_midwest_logistics_CRM_order_status');
        $currentCRMId = $order->get_meta('_midwest_logistics_CRM_order_id' );
        $settingOptions = get_option('Midwest_Logistics_settings','');
        if ( empty( $settingOptions ) ) {
            return;
        }
        $midwest_logistics_order_status_setting = $settingOptions['Midwest_Logistics_select_field_2'];
        $midwest_logistics_auto_push_select_field = $settingOptions['Midwest_Logistics_auto_push_select_field'];

        if($orderStatus === "")  {
            $orderStatus = $midwest_logistics_auto_push_select_field;
            $order->update_meta_data('_midwest_logistics_CRM_order_status', esc_attr($orderStatus ) );
            $order->save_meta_data();
        }
        if($orderStatus === "")  {
            $orderStatus = "1";
        }
        if($currentCRMId != "") {
            ?>
            <p class="form-field form-field-wide">            
                <strong><?php _e("Midwest Logisitcs Order Id")?>:</strong> <?php echo $currentCRMId ?>
                    </p>
            <p class="form-field form-field-wide">   
                <strong><?php _e(MIDWESTLOGISTICS_NAME . " Status")?>:</strong> <?php _e("Sent")?>
            </p>
            <?php 
        } else {
            if("wc-" . $order-> get_status() === $midwest_logistics_order_status_setting) {
                ?>
                <?php wp_nonce_field( 'Midwest-logistics-save-nonce','Midwest_logistics_order_status' ); ?>
                <p class="form-field form-field-wide">            
                    <label for="midwest_order_status"><?php _e(MIDWESTLOGISTICS_NAME . " Status:")?></label>
                                <select name="midwest_order_status" id="midwest_order_status">
                        <option value='1' <?php selected( $orderStatus, 1 ); ?> >Don't send</option>
                        <option value='2' <?php selected( $orderStatus, 2 ); ?> >Send</option>
                        <?php
                        if($orderStatus === "3") {
                            ?><option value='3' <?php selected( $orderStatus, 3 ); ?> >Error</option><?php
                        }
                        ?>
                    </select>
                        </p>
                <?php
            }
        }
    }
    function midwest_logistics_order_status_box_update() {
        if(isset($_POST['Midwest_logistics_order_status'])) {
            if (wp_verify_nonce($_POST['Midwest_logistics_order_status'],'Midwest-logistics-save-nonce')) {
                $order = wc_get_order( get_the_ID() );   
                if ( empty( $order ) ) {
                    return;
                }

                $midwest_order_status = sanitize_text_field($_POST['midwest_order_status']);
                $midwest_order_status = preg_replace('/[^0-9]/','',$midwest_order_status);
                if($midwest_order_status == "") {
                    $midwest_order_status = "1";
                } 

                if( !empty( $midwest_order_status ) ) {
                    $order->update_meta_data('_midwest_logistics_CRM_order_status', esc_attr($midwest_order_status ) );
                    $order->save_meta_data();
                    //send

                    if($midwest_order_status == "2") {
                        $this->midwest_logistics_process_order(get_the_ID());
                    }
                }
            }
        }
    }
    function midwest_logistics_admin_ajax_process_order() {
        if(isset($_POST['order'])) {
            $response = array("code"=>200,"message"=>"");

            $postedOrder = $_POST['order'];
            $order = wc_get_order( $postedOrder );   
            if ( empty( $order ) ) {
                $response = array("code"=>500,"message"=>"Order Not Found.");
                echo json_encode($response);
                exit();
            }

            //mark it to be sent 
            $order->update_meta_data('_midwest_logistics_CRM_order_status', esc_attr("2" ) );
            $order->save_meta_data();
            $this->midwest_logistics_process_order($postedOrder);


            //after we process again we get the status to see if it was successful
            //get the order again becaseu the 
            $order = wc_get_order( $postedOrder );   
            $status = $order->get_meta('_midwest_logistics_CRM_order_status');


            $errorMessage = "Order Added";
            $code = 200;
            if($status === "3") {
                global $wpdb;

                $errorMessage = "Order Could not be updated. Please check the log for more information.";
                $code = 500;
            } 
            $response = array("code"=>$code,"message"=>$errorMessage);
            echo json_encode($response);
            exit();
        }
    }
    function midwest_logistics_admin_order_status_notification_notice() {
        $screen = get_current_screen();
        if (! $screen->parent_base == 'edit') {
            return;
        }

        if ($screen ->post_type !== "shop_order") {
            return;
        }  

        $orderId = get_the_ID();
        $order = wc_get_order( $orderId );   
        if ( empty( $order ) ) {
            return;
        }

        $orderStatus = $order->get_meta('_midwest_logistics_CRM_order_status'); 
        $currentCRMId = $order->get_meta('_midwest_logistics_CRM_order_id');
        $settingOptions = get_option('Midwest_Logistics_settings','');
        if ( empty( $settingOptions ) ) {
            return;
        }
        $midwest_logistics_order_status_setting = $settingOptions['Midwest_Logistics_select_field_2'];
        if($currentCRMId === "0") {
            if("wc-" . $order-> get_status() === $midwest_logistics_order_status_setting) {
                if($orderStatus == "2") {
                    ?>
                    <div class="notice notice-info is-dismissible">
                            <p><strong><?php _e("Order will be sent to " . MIDWESTLOGISTICS_NAME ."  with the next batch of orders."); ?></strong></p>
                    </div>
                    <?php          
                }
            }
        }
    }
    function midwest_logistics_admin_order_error_notification_notice() {
            

        $orderId = get_the_ID();
        $order = wc_get_order( $orderId );   
        if ( empty( $order ) ) {
            return;
        }
        $orderStatus = $order->get_meta('_midwest_logistics_CRM_order_status'); 
        $currentCRMId = $order->get_meta('_midwest_logistics_CRM_order_id');

        $mainMessage = "";

        if($orderStatus == "3") {

            global $wpdb;

            //pull from Midwest communication log
            $table_name = $wpdb->prefix . MIDWESTLOGISTICS_TABLE_NAME;
            $sql = "SELECT * 
                    FROM $table_name 
                    WHERE post_meta_key = 'order'
                    AND post_id = '" . $orderId . "'
                    ORDER BY dateadded desc";

            $results = $wpdb->get_results($sql, ARRAY_A  );
            if(count($results) > 0 ) {
                $responseString = $results[0]["JSONresponse"];
                if($responseString !== "") {

                    $jsonResponse = json_decode($responseString);

                    if($jsonResponse !== null) {

                        $orderJsonResult = $jsonResponse -> {"result"};

                        //Did the Json 500 or is it an order?

                        if($orderJsonResult === "500") { 

                            $mainMessage = $jsonResponse -> {"result"};

                        } else {

                            $ordersArray = $jsonResponse -> {"orders"};

                            if(is_array($ordersArray)) {

                                foreach ($ordersArray as $order)  {

                                    $currentOrderStatus = $order -> {"status"};

                                    //BOOM bad thing happened.

                                    if($currentOrderStatus === "500") {

                                        $orderMessage = $order -> {"message"};

                                        //did the order fail or was it a product

                                        if($orderMessage !== "") {

                                            $mainMessage = $orderMessage;

                                        } else {

                                            $productsArray = $order -> {"products"};

                                            $productMessage = "";

                                            if(is_array($ordersArray)) {

                                                foreach ($productsArray as $product)  {

                                                    $productStatus = $product -> {"status"};

                                                    if($productStatus = "500") {

                                                        if(property_exists($product,"errorMsg")) {

                                                            if($productMessage === "") {

                                                                $productMessage .= $product -> {"errorMsg"};

                                                            } else {

                                                                $productMessage .= "<br />" . $product -> {"errorMsg"};

                                                            }  

                                                        }                                                     

                                                    }

                                                }

                                            }
                                            if($productMessage !== "") {

                                                $mainMessage = $productMessage;

                                            }

                                        }

                                    }                                    

                                }                                                                

                            }

                        }

                    }

                }

            }

            if($mainMessage == "") {

                $mainMessage =  "There was a problem adding this order to the ". MIDWESTLOGISTICS_NAME . " system. Please contact " . MIDWESTLOGISTICS_NAME;

            } else {

                $mainMessage = "The order could not be added to the " . MIDWESTLOGISTICS_NAME ." system.<br /><br /> Please correct the following problems:<br /> " .$mainMessage;

            }



            ?>

            <div class="notice notice-error is-dismissible">

                    <p><strong><?php _e($mainMessage); ?></strong></p>

            </div>

            <?php                

        }

    }
    function midwest_logisitcs_register_awaiting_shipment_order_status() {

        register_post_status( 'wc-partial-shipped', array(

            'label'                     => 'Partially Shipped',

            'public'                    => true,

            'exclude_from_search'       => false,

            'show_in_admin_all_list'    => true,

            'show_in_admin_status_list' => true,

            'label_count'               => _n_noop( 'Partially Shipped <span class="count">(%s)</span>', 'Partially Shipped <span class="count">(%s)</span>' )

        ) );

    }
    function midwest_logisitcs_add_awaiting_shipment_to_order_statuses( $order_statuses ) {
        $new_order_statuses = array();
        // add new order status after processing

        foreach ( $order_statuses as $key => $status ) {
            $new_order_statuses[ $key ] = $status;
            if ( 'wc-processing' === $key ) {

                $new_order_statuses['wc-partial-shipped'] = 'Partially Shipped';

                add_action( 'woocommerce_order_status_pending_to_quote', array( WC(), 'send_transactional_email' ), 10, 10 );

            }

        }
        return $new_order_statuses;

    }
    function midwest_logisitcs_woocommerce_email_order_item_meta_fields($item_id) {

        $woocommerce_tracking_number_meta_field = MIDWESTLOGISTICS_NAME . ' Tracking Number';

        $tracking_info = wc_get_order_item_meta( $item_id,$woocommerce_tracking_number_meta_field, true );

        if($tracking_info !== "") {

           echo "<ul class='wc-item-meta'><li><span class='wc-item-meta-label'>Tracking Number:</span> <p>" . $tracking_info . "</p></li></ul>";

        }

    }
    
    function midwest_logisitcs_order_item_get_formatted_meta_data($formatted_meta){

        $woocommerce_tracking_number_meta_field = MIDWESTLOGISTICS_NAME . ' Tracking Number';

        $temp_metas = [];

        if(is_admin() === false) {



            foreach($formatted_meta as $key => $meta) {

                if ($meta->key !== $woocommerce_tracking_number_meta_field)  {

                    $temp_metas[ $key ] = $meta;

                }

            }

        } else {

            $temp_metas = $formatted_meta;

        }



        return $temp_metas;

    }

    function midwest_logistics_order_cron_job() {
        $settingOptions = get_option('Midwest_Logistics_settings','');
        if ( empty( $settingOptions ) ) {
            return;
        }
        if(isset($settingOptions['Midwest_Logistics_auto_push_select_field'])) {
            $midwest_logistics_auto_push_select_field = $settingOptions['Midwest_Logistics_auto_push_select_field'];   
            if($midwest_logistics_auto_push_select_field != "2") {
                //don't auto push.
                return;
            }
        }


        $dayLimit = "160";
        if(isset($settingOptions['Midwest_Logistics_submit_order_limit'])) {
            $dayLimit = $settingOptions['Midwest_Logistics_submit_order_limit'];
        }

        if(!is_numeric($dayLimit)) {
            $dayLimit = "30";
        }

        $order_status_setting = $settingOptions['Midwest_Logistics_select_field_2'];
        $date = date_create(date('Y-m-d H:i:s'));
        $startDate = $date->sub(new DateInterval('P' . $dayLimit . 'D'));
        $args = array(
            'limit'=> 10,
            'type'=> 'shop_order',
            'date_created'=> '>' .$startDate->format('Y-m-d H:i:s'),
            'status' => $order_status_setting,
            'order' => 'DESC',
            '_midwest_logistics_CRM_order_status' => '3'
        );
        $orders = wc_get_orders($args);
        if(count($orders) > 0 ) {
            foreach ($orders as $order) {
                $order->update_meta_data( $orderId, '_midwest_logistics_CRM_order_status', esc_attr( "2" ) );
                $order->save_meta_data();
                $this->midwest_logistics_process_order($orderId);
            }
        }
    }
}
$ML_Order = new ML_Order();