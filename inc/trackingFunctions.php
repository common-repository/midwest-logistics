<?php
if ( ! defined( 'MIDWESTLOGISTICS_VERSION' ) ) {
    header( 'Status: 403 Forbidden' );
    header( 'HTTP/1.1 403 Forbidden' );
    exit();
    
}

//add the initial meta value
add_action('woocommerce_add_order_item_meta', 'midwest_logistics_add_order_item_meta', 10, 2);

function midwest_logistics_add_order_item_meta($item_id, $values) {    
    $woocommerce_tracking_number_meta_field = MIDWESTLOGISTICS_NAME . ' Tracking Number';
    $key = $woocommerce_tracking_number_meta_field; // Define your key here
    $value = ''; // Get your value here
    woocommerce_add_order_item_meta($item_id, $key, $value);
}

//this must be set for the wp scheduler to work.
add_action( 'midwest_logistics_update_tracking_information', 'midwest_logistics_update_tracking_information' );

//add_action( 'wp_loaded', 'midwest_logistics_update_tracking_information' );
function midwest_logistics_update_tracking_information() {
        global $wpdb;
        $woocommerce_tracking_number_meta_field = MIDWESTLOGISTICS_NAME . ' Tracking Number';
        $table_name = $wpdb->prefix . "woocommerce_order_items";
        $order_meta_table_name = $wpdb->prefix . "woocommerce_order_itemmeta";
        $order_table_name = $wpdb->prefix . "posts";
        $order_table_meta_name = $wpdb->prefix . "postmeta";
        $orderArray = [];
        
        $dayLimit = "160";
        $options = get_option( 'Midwest_Logistics_settings' );
        if(isset($options['Midwest_Logistics_check_order_limit'])) {
            $dayLimit = $options['Midwest_Logistics_check_order_limit'];
        }
        
        if(!is_numeric($dayLimit)) {
            $dayLimit = "160";
        }
        
        /* Original SQL statement
    $sql = "SELECT distinct t1.order_id,t4.meta_value as CRMID 
                   FROM $table_name t1
                       JOIN $order_meta_table_name t2 on t2.order_item_id = t1.order_item_id
                       JOIN $order_table_name t3 on t3.id = t1.order_id
                       JOIN $order_table_meta_name t4 on t4.post_id = t3.id
                   WHERE post_type = 'shop_order'
                   AND t2.meta_key = '" . $woocommerce_tracking_number_meta_field . "' 
                   AND t4.meta_key= '_midwest_logistics_CRM_order_id'
                   AND t2.meta_value = ''
                   AND post_status <> 'trash' 
                   AND (
                           t4.meta_value <> '0'
                       OR t4.meta_value <> ''
                   )
                   AND (
                       SELECT wppostmeta2.meta_value
                       FROM $order_table_meta_name wppostmeta2
                       WHERE wppostmeta2.meta_key = '_midwest_logistics_processed'
                       AND wppostmeta2.post_id = t4.post_id
                       LIMIT 1
                   ) IS NULL
                   AND DATEDIFF(NOW(),t3.post_date) < ". $dayLimit . "
                   ORDER BY t1.order_id DESC
                   LIMIT 1000";
        */
        $date = date_create(date('Y-m-d H:i:s'));
        $startDate = $date->sub(new DateInterval('P' . $dayLimit . 'D'));
        
        //Down below in this file two fields are declare for meta query midwest_logistics_CRM_order_id and midwest_logistics_processed
        //The new HPOS system does not seem to use the meta query for wc_get_orders. If they add support(which they should) then the query below will pull correctly. 
        //For now I check it as well before adding it to the list. 
        $args = array(
            'limit'=> 1000,
            'type'=> 'shop_order',
            'date_created'=> '>' .$startDate->format('Y-m-d H:i:s'),
            'order' => 'DESC',
            'midwest_logistics_CRM_order_id' => '',
            'midwest_logistics_processed' => '',
        );
        $orders = wc_get_orders($args);
        if(count($orders) > 0 ) {
            foreach ($orders as $order) {
                if($order->get_status() == "cancelled" ) {
                    //set it as processed and never touch again
                    $order->update_meta_data("_midwest_logistics_processed","Y");
                    $order->save_meta_data();
                }
                if($order->get_status() == "completed" ) {
                    //set it as processed and never touch again
                    $order->update_meta_data("_midwest_logistics_processed","Y");
                    $order->save_meta_data();
                }
                
                $orderId = $order->get_id();
                $currentCRMId = $order->get_meta('_midwest_logistics_CRM_order_id' );
                $alreadyChecked = $order->get_meta('_midwest_logistics_processed' );

                if($currentCRMId != "" && $alreadyChecked != "Y") {
                    array_push($orderArray,array("CRMID" => $currentCRMId,"order_id" => $orderId));    
                }  
            }
        }
        
        if(count($orderArray) > 0) {
            get_midwest_logistics_tracking_information($orderArray);
        }
}

function get_midwest_logistics_tracking_information($orderArray) {
    $woocommerce_tracking_number_meta_field = MIDWESTLOGISTICS_NAME . ' Tracking Number';
    if(!is_array($orderArray) === "") {
        $responseArray =  array("result" => false, "message" => "Invalid CRM order id");
    }
    $apiKey = "";
    $orderString = "";
    $settingOptions = get_option('Midwest_Logistics_settings','');
    if($settingOptions !== "") {
        $apiKey = $settingOptions["Midwest_Logistics_API_Key_field_0"];
    }
    
    $orders = [];
    foreach ($orderArray as $orderArraySingle) {
        $orders[] = array(
            "orderId" => "",
            "CRMID" => $orderArraySingle["CRMID"]            
        );
    }
    $jsonArray = [
        "apiKey" => $apiKey,
        "request" => "orderStatus",
        "orders" => $orders
    ];   

    $httpcode = "500";
    $postString = json_encode ($jsonArray);    
    $API = new ML_API();
    $response = $API->send($postString);
    if($response != null) {
        $httpcode = $response["code"];
        $response = $response["response"];
    }

    $responseText = "";
    if($httpcode == "200") {
        if(json_decode($response) !== null) {
            $jsonResponse = json_decode($response);
            $curl_result = $jsonResponse -> {"result"};            
            if($curl_result === "200") {
                $ordersArray = $jsonResponse -> {"orders"};
                if(is_array($ordersArray)) {
                    foreach ($ordersArray as $order)  {
                        $CRMOrderId = $order -> {"CRMOrderId"};
                        $trackingArray = $order -> {"tracking"};
                        $shippingMethod = $order -> {"shippingCarrier"};
                        $orderStatus = $order -> {"midwestStatus"};
                        //04/12/2021 Midwest decided that the order should be marked Fulfilled-Complete before adding the tracking.
                        if(is_array($trackingArray) && $orderStatus === "Fulfilled-Complete") {

                            foreach ($trackingArray as $tracking)  {
                                if($tracking->{"tracking"} !== "") {
                                    $orderFound = false;
                                    //find the product it belongs to. sucks to loop through it
                                    foreach ($orderArray as $orderArraySingle) {
                                        $currentOrderId = $orderArraySingle["CRMID"];
                                        if($currentOrderId === $CRMOrderId) {
                                            $orderFound = true;
                                            //first one we find that is not blank
                                            $currentTrackingNumber = $tracking->{"tracking"};
                                            if($shippingMethod !== "") {
                                                $currentTrackingNumber = $shippingMethod . ": " .$tracking->{"tracking"};
                                            }
                                            $currentTrackingNumber = $shippingMethod . ": " .$tracking->{"tracking"};                                            
                                            $order = wc_get_order( $orderArraySingle["order_id"] );     
                                            $isAllMidwest = true;
                                            if ( !empty( $order ) ) {
                                                $items = $order->get_items();
                                                $trackingUpdated = false;
                                                foreach ( $items as $item_id => $item_data ) {
                                                    $product_id = $item_data ->get_product_id();
                                                    if(isset($item_data["variation_id"]) )           
                                                    {            
                                                        if($item_data["variation_id"] !== 0) {      
                                                            $product_id = $item_data["variation_id"];
                                                        }
                                                    }
                                                    $product = wc_get_product($product_id);
                                                    if($product != null) {
                                                        $midwest_status = $product->get_meta('_midwest_logistics_product_select');
                                                        if($midwest_status === "Y") {
                                                            $trackingUpdated = true;
                                                            $tracking_array = midwest_logistics_parse_shipping($currentTrackingNumber);
                                                            $parsedTrackingNumber = $tracking_array["tracking"];

                                                            wc_update_order_item_meta($item_id,$woocommerce_tracking_number_meta_field,$parsedTrackingNumber);                                                                                                          
                                                        } else {

                                                            $isAllMidwest = false;
                                                        }
                                                    }

                                                    
                                                }       

                                                if($trackingUpdated == true) { 
                                                    $emailSent = $order->get_meta('_midwest_logistics_tracking_email_sent', true );
                                                    if ( ! empty( $emailSent ) ) {
                                                        if($emailSent <> 'Y') {
                                                            send_midwest_logistics_tracking_email($orderArraySingle["order_id"] );  
                                                        }
                                                    } else {
                                                        //not sent yet
                                                        send_midwest_logistics_tracking_email($orderArraySingle["order_id"] );  
                                                    }
                                                    if($order->get_status() !== "completed") {
                                                        if($isAllMidwest === true) {
                                                            $order ->update_status("completed","Order Status changed by " . MIDWESTLOGISTICS_NAME);
                                                        } else {
                                                            $order ->update_status("partial-shipped","Order Status changed by " . MIDWESTLOGISTICS_NAME);
                                                        }                                                         

                                                    }
                                                    //set it as processed and never touch again
                                                    $order->update_meta_data("_midwest_logistics_processed","Y");
                                                    $order->save_meta_data();
                                                }                                                
                                            } else {
                                                //add the log
                                                midwest_logistics_add_communication_log($postString,$response,$orderArraySingle["order_id"],"order","","Order Not Found to update tracking on.");
                                            }
                                            //add the log
                                            midwest_logistics_add_communication_log($postString,$response,$orderArraySingle["order_id"],"order","","");

                                            //break;
                                        }
                                    }
                                    if($orderFound === true) {
                                        break;
                                    }                                    
                                    die();
                                }
                            }
                        }                         
                    }  
                }
            } else {

            }

        } else {

            new WP_Error(  MIDWESTLOGISTICS_NAME . ' API',"Invalid Call" );
            $responseText = "Invalid Call";
            $responseArray =   array("result" => false, "message" => "Invalid Call");
        }
    }

    if($httpcode == "500") {
        new WP_Error(  MIDWESTLOGISTICS_NAME . ' API',MIDWESTLOGISTICS_NAME . " API is down." );
        $responseText = MIDWESTLOGISTICS_NAME . " API is down.";
        $responseArray =  array("result" => false, "message" => MIDWESTLOGISTICS_NAME . " API is down.");
    }
    //var_dump($responseArray);
    //return $responseArray;
}
//Send email when the status cahnges 
function send_midwest_logistics_tracking_email($order_id) {
    $woocommerce_tracking_number_meta_field = MIDWESTLOGISTICS_NAME . ' Tracking Number';
    $shipping_subject = "Shipping Details For Order#$order_id";
    $order = new WC_Order( $order_id );
    $date = getdate();
    $current_date = $date["mon"]."/".$date["mday"]."/".$date["year"];
    if ( !empty( $order ) ) {
        $customer_email = "";
        if(isset($order)) {
            $customer_email = $order->get_billing_email();
        }

        $options = get_option( 'Midwest_Logistics_settings' );
        $send_email = 'N';
        if(isset($options['Midwest_Logistics_send_tracking_email_select_field'])) {
            $send_email = $options['Midwest_Logistics_send_tracking_email_select_field'];
        }
        $emailMessage = "";
        if(isset($options['Midwest_Logistics_send_tracking_email_text'])) {
            $emailMessage = $options['Midwest_Logistics_send_tracking_email_text'];
        }

        if($order->get_status() === "completed" || $order->get_status() === "partial-shipped") {
            if($send_email === "Y" && $customer_email !== "") {
                $items = $order->get_items();
                $trackingUpdated = false;
                foreach ( $items as $item_id => $item_data ) {
                    $product_id = $item_data ->get_product_id();
                    if(isset($item_data["variation_id"]) )           
                    {            
                        if($item_data["variation_id"] !== 0) {      
                            $product_id = $item_data["variation_id"];
                        }
                    }
                    //We only need to get the first one. It is the same for all items right now. 
                    $tracking_id = wc_get_order_item_meta($item_id,$woocommerce_tracking_number_meta_field,true);
                    if($tracking_id !== "")                                                                                                           {
                        break;
                    }
                }
                if($tracking_id !== "") {  
                    $did_send = true;
                    //parse the tracking id
                    $tracking_array = midwest_logistics_parse_shipping($tracking_id);
                    if($emailMessage !== "") {
                        $emailMessage = str_replace("[DATE]",$current_date,$emailMessage);      
                        $emailMessage = str_replace("[SERVICE]",$tracking_array["carrier"],$emailMessage);                
                        $emailMessage = str_replace("[ID]","<br />". $tracking_array["tracking"],$emailMessage);                
                    }

                    $headers = array(
                        'Content-Type: text/html; charset=UTF-8'
                    );

                    $did_send = wp_mail( $customer_email,$shipping_subject, $emailMessage,$headers);
                    if($did_send === false) {
                        $order->update_meta_data('_midwest_logistics_tracking_email_sent', 'N' );  
                        
                        midwest_logistics_add_communication_log("Tried to send tracking email to $customer_email for order#$order_id" ,"wp_mail returned false",$order_id,"order","","");
                    } else {
                        $order->update_meta_data($order_id, '_midwest_logistics_tracking_email_sent', 'Y' );  
                        midwest_logistics_add_communication_log("Sent tracking email to $customer_email for order#$order_id. Message: " . $emailMessage ,"",$order_id,"order","","");
                    }
                    $order->save_meta_data();
                }                                                
            }
        }
    }
}
add_action("woocommerce_order_status_changed","send_midwest_logistics_tracking_email","100,","1");

function midwest_logistics_parse_shipping($shipping) {
    $carrier = "";
    $tracking_number = "";
    if($shipping !== "") {
        $colonPos = strpos($shipping,":");
        if($colonPos !== false) {
            $carrier = substr($shipping,0,$colonPos);
            $tracking_number = substr($shipping,$colonPos +2,strlen($shipping) );
        }
    }

    if($carrier !== "" && $tracking_number !== "") {
        $carrier_parsed = strtolower($carrier);
        if(strpos ($carrier_parsed,"ups") !== false ) {
            $tracking_number = "<a target='_blank' href='http://wwwapps.ups.com/WebTracking/track?trackNums=$tracking_number'>$shipping</a>";
        } elseif(strpos ($carrier_parsed,"usps") !== false ) {
            $tracking_number = "<a target='_blank' href='https://tools.usps.com/go/TrackConfirmAction.action?tLabels=$tracking_number'>$shipping</a>";
        } elseif(strpos ($carrier_parsed,"fedex") !== false ) {
            $tracking_number = "<a target='_blank' href='https://www.fedex.com/apps/fedextrack/?action=track&cntry_code=us&trackingnumber=$tracking_number'>$shipping</a>";
        } elseif(strpos ($carrier_parsed,"dhl") !== false ) {
            $tracking_number = "<a target='_blank' href='http://www.dhl.com/en/express/tracking.html?brand=DHL&AWB=$tracking_number'>$shipping</a>";
        } else {
            $tracking_number = $shipping;
        }
    }

    return array(
        "carrier" => $carrier,
        "tracking" => $tracking_number
    );
}

add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', 'mw_tracking_custom_CRM_query_var', 10, 2 );
function mw_tracking_custom_CRM_query_var( $query, $query_vars ) {
    if (isset( $query_vars['midwest_logistics_CRM_order_id'] ) ) {      

            $query['meta_query'][] = array(
                    'key' => '_midwest_logistics_CRM_order_id',
                    'value' => esc_attr( $query_vars['midwest_logistics_CRM_order_id'] ),
                    'compare' => '!='
            );
    }
    if (isset( $query_vars['midwest_logistics_processed'] ) ) {         
            $query['meta_query'][] = array(
                    'key' => '_midwest_logistics_processed',
                    'compare' => 'NOT EXISTS'
            );
    }

    return $query;
}

add_action( 'init', 'mw_tracking_manual_run' );
function mw_tracking_manual_run() {   
    $run_mw_tracking = filter_input( INPUT_GET, 'run_mw_tracking', FILTER_SANITIZE_URL );
    if(isset($run_mw_tracking)) {        
        if($run_mw_tracking == "Y") {
            midwest_logistics_update_tracking_information();

        }
    }
    
    
}