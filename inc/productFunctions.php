<?php
/* Does User Belong Here */
if ( ! defined( 'MIDWESTLOGISTICS_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

 	/** Function List

	**/ 
  
// Display Fields
add_action( 'woocommerce_product_options_general_product_data', 'midwest_logistics_woocommerce_fields' );  
function midwest_logistics_woocommerce_fields( $post_id) {
    $product = wc_get_product(get_the_ID());
    if($product != null) {          
        $midwest_logistics_product_sku_text_field_value = $product->get_meta('_midwest_logistics_product_sku_text_field', true ); 
        $midwest_logistics_product_select_value = $product->get_meta('_midwest_logistics_product_select', true ); 
        woocommerce_wp_select( 
            array( 
                'id'      => '_midwest_logistics_product_select', 
                'label'   => __( 'Shipped by Midwest Logistics:', 'woocommerce' ), 
                'desc_tip'    => 'true',
                'description' => __( 'Marks this product so that it can be shipped by '. MIDWESTLOGISTICS_NAME, 'woocommerce' ),
                'value' => $midwest_logistics_product_select_value,
                'options' => array(
                    'N'   => __( 'No', 'woocommerce' ),
                    'Y'   => __( 'Yes', 'woocommerce' )
                )
            )
        );
        woocommerce_wp_text_input( 
            array( 
                    'id'          => '_midwest_logistics_product_sku_text_field', 
                    'label'       => __( 'Midwest Logistics SKU:', 'woocommerce' ), 
                    'placeholder' => 'Enter SKU here',
                    'desc_tip'    => 'true',
                    'description' => __( 'Enter the Midwest Logistics Product SKU.', 'woocommerce' ),
            'wrapper_class' => '_midwest_logistics_product_sku_text_field_wrapper',
            'value' => $midwest_logistics_product_sku_text_field_value
            )
        );       
    }
}

//Add The fileds to the variation products as well.
// Add Variation Settings
add_action( 'woocommerce_product_after_variable_attributes','midwest_logistics_variation_settings_fields', 10, 3 );
function midwest_logistics_variation_settings_fields( $loop, $variation_data, $variation ) {
    $product = wc_get_product($variation->ID);
    if($product != null) {          
        $midwest_logistics_product_sku_text_field_value = $product->get_meta('_midwest_logistics_product_sku_text_field', true ); 
        $midwest_logistics_product_select_value = $product->get_meta('_midwest_logistics_product_select', true ); 
        woocommerce_wp_select( 
            array( 
                'id'      => '_midwest_logistics_product_select[' . $variation->ID . ']', 
                'class'   => '_midwest_logistics_product_select', 
                'label'   => __( 'Shipped by Midwest Logistics:', 'woocommerce' ), 
                'desc_tip'    => 'true',
                'description' => __( 'Marks this product so that it can be shipped by '. MIDWESTLOGISTICS_NAME, 'woocommerce' ),
                'value' => $midwest_logistics_product_select_value,
                'options' => array(
                    'N'   => __( 'No', 'woocommerce' ),
                    'Y'   => __( 'Yes', 'woocommerce' )
                )
            )
        );
        woocommerce_wp_text_input( 
            array( 
                'id' => '_midwest_logistics_product_sku_text_field[' . $variation->ID . ']', 
                'class'   => '_midwest_logistics_product_sku_text_field', 
                'label'       => __( 'Midwest Logistics SKU:', 'woocommerce' ), 
                'placeholder' => 'Enter SKU here',
                'desc_tip'    => 'true',
                'description' => __( 'Enter the Midwest Logistics Product SKU.', 'woocommerce' ),
                'wrapper_class' => '_midwest_logistics_product_sku_text_field_wrapper',
                'value' => $midwest_logistics_product_sku_text_field_value
            )
        );
    }
}
// Save Variation Settings
add_action( 'woocommerce_save_product_variation', 'midwest_logistics_save_variation_settings_fields', 10, 2 );
function midwest_logistics_save_variation_settings_fields($post_id) {
    if(isset($_POST)) {
        $midwest_logistics_product_select = "N";
        if(!empty( $_POST['_midwest_logistics_product_select'][$post_id])) {
            $midwest_logistics_product_select = sanitize_text_field($_POST['_midwest_logistics_product_select'][$post_id]);
        }

        if( $midwest_logistics_product_select !== "" ) {
            $product = wc_get_product($post_id);
            if($product != null) {          
                if($midwest_logistics_product_select == "Y") {
                // Text Field
                    $midwest_logistics_product_sku_text_field = sanitize_text_field($_POST['_midwest_logistics_product_sku_text_field'][$post_id]);                    
                    $product->update_meta_data('_midwest_logistics_product_sku_text_field', esc_attr( $midwest_logistics_product_sku_text_field ) );
                    $product->update_meta_data('_midwest_logistics_product_select', "Y" );   
                    $product->update_meta_data('_midwest_logistics_check_stock', "Y" );                                    
                } else {
                    $product->update_meta_data('_midwest_logistics_product_select', "N" ); 
                    $product->delete_meta_data('_midwest_logistics_product_sku_text_field'); 
                    $product->update_meta_data('_midwest_logistics_check_stock', "N" ); 
                }
                $product->save_meta_data();
                
                $parentProduct =  wc_get_product($product->get_parent_id());
                if($parentProduct != null) {
                    $product->update_meta_data('_midwest_logistics_check_stock', "N" ); 
                    if($midwest_logistics_product_select == "Y") {
                        $parentProduct->update_meta_data('_midwest_logistics_check_stock', "Y" ); 
                        
                    } else {
                        $parentProduct->update_meta_data('_midwest_logistics_check_stock', "N" ); 
                    }
                    $parentProduct->save_meta_data();                    
                }
            }
        }	      
    }    
}
// Save Fields
//add_action( 'save_post', 'midwest_logistics_woocommerce_fields_save' );
add_action( 'woocommerce_new_product', 'midwest_logistics_woocommerce_fields_save', 10, 1 );
add_action( 'woocommerce_update_product', 'midwest_logistics_woocommerce_fields_save', 10, 1 );
function midwest_logistics_woocommerce_fields_save($product_id) {
    //only update if they posted a save. not if the product was saved in another way.
    if(isset($_POST)) {
        $midwest_logistics_product_select = !empty( $_POST['_midwest_logistics_product_select'] ) ? sanitize_text_field($_POST['_midwest_logistics_product_select']) : "";

        if( $midwest_logistics_product_select !== "" ) {
            $product = wc_get_product( $product_id );

            if($product != null) {
                if ($product->is_type( 'variable' )) {

                    $setProductasStockUpdate = false;
                    $variations = $product->get_available_variations();
                    if(count($variations) > 0) {
                         foreach($variations as $variation) {
                             $variableProduct = wc_get_product($variation["variation_id"]);
                             if($variableProduct != null) {
                                 $id = $variableProduct->get_id();
                                 $midwestSKU = $variableProduct->get_meta("_midwest_logistics_product_sku_text_field");
                                 if($midwestSKU != "" && $setProductasStockUpdate == false) {
                                     //set it to be checked by Midwest
                                     $setProductasStockUpdate = true;
                                 }
                             }                            
                         }
                    }

                    if($setProductasStockUpdate == true) {
                         $product->update_meta_data('_midwest_logistics_check_stock', "Y" ); 
                         $product->update_meta_data('_midwest_logistics_stock_updated', "N" );  //sets the stock to be udpated on the next run 
                         $product->save_meta_data();
                    }

                } else {
                    if($midwest_logistics_product_select == "Y") {
                    // Text Field
                        $midwest_logistics_product_sku_text_field = sanitize_text_field($_POST['_midwest_logistics_product_sku_text_field']);                    
                        $product->update_meta_data('_midwest_logistics_product_sku_text_field', esc_attr( $midwest_logistics_product_sku_text_field ) );
                        $product->update_meta_data('_midwest_logistics_product_select', "Y" ); 
                        $product->update_meta_data('_midwest_logistics_check_stock', "Y" );                        
                    } else {
                        $product->update_meta_data('_midwest_logistics_product_select', "N" ); 
                        $product->delete_meta_data('_midwest_logistics_product_sku_text_field'); 
                        $product->update_meta_data('_midwest_logistics_check_stock', "N" );
                    }

                    $product->update_meta_data('_midwest_logistics_stock_updated', "N" );  //sets the stock to be udpated on the next run 
                    $product->save_meta_data();
                }
            }
        }
        
        
    }
    
}




/*
* checks to make sure the Midwest Sku exists in thier current system
*/
function midwest_logisitcs_verify_sku_number($SKU) {
    if($SKU === "") {
        //return false;
    }
    $apiKey = "";
    $settingOptions = get_option('Midwest_Logistics_settings','');
    if($settingOptions !== "") {
        $apiKey = $settingOptions["Midwest_Logistics_API_Key_field_0"];
    }

    $jsonArray = [
        "apiKey" => $apiKey,
        "sku" => $SKU,
        "request" => "productcheck"
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
                $responseText = $jsonResponse -> {"message"};
                $responseArray = array("result" => true, "message" => $jsonResponse -> {"message"});
            } else {
                $responseText = $jsonResponse -> {"message"};
                $responseArray =  array("result" => false, "message" => $jsonResponse -> {"message"});
                
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

    //add the log
    midwest_logistics_add_communication_log($postString,$response,get_the_ID(),"product",get_the_title(),$responseText);
    return $responseArray;
}

add_action( 'admin_notices', 'midwest_logistics_admin_error_notice',100 );
function midwest_logistics_admin_error_notice() {
    $product = null;
    $screen = get_current_screen();
    if (! $screen->parent_base == 'edit' ) {
        return;
    }

    if ($screen ->post_type !== "product") {
        return;
    } else {
        $product = wc_get_product(get_the_ID());
    }

    //the main product
    $midwest_logistics_product_sku_text_field_value = get_post_meta(get_the_ID(), '_midwest_logistics_product_sku_text_field', true ); 
    if( !empty( $midwest_logistics_product_sku_text_field_value ) ) {
        if($midwest_logistics_product_sku_text_field_value !== "") {

            $responseArray = midwest_logisitcs_verify_sku_number($midwest_logistics_product_sku_text_field_value);
            if(is_array($responseArray)) {

                if($responseArray["result"] === false) {

                    if($responseArray["message"] == "The API key is invalid") {
                        ?>
                        <div class="notice-error notice is-dismissible">
	                        <p><strong><?php _e( "Your ". MIDWESTLOGISTICS_NAME . " API Key is invalid.",MIDWESTLOGISTICS_NAME); ?></strong></p>
                        </div>
                        <?php
                    } else {
                        ?>
                        <div class="notice-error notice is-dismissible">
	                        <p><strong><?php _e( "The ". MIDWESTLOGISTICS_NAME . " product SKU you entered is not valid for product: " . get_the_title(),MIDWESTLOGISTICS_NAME); ?></strong></p>
                        </div>
                        <?php
                    }
                }
            }          
        }
    }

    if(!empty($product)) {
        // test if product is variable
        if ($product->is_type( 'variable' )) 
        {
            $available_variations = $product->get_available_variations();
            foreach ($available_variations as $key => $attribute_details) 
            { 
                $midwest_logistics_product_sku_text_field_value =  get_post_meta($attribute_details["variation_id"], '_midwest_logistics_product_sku_text_field', true );
                $variation_attributes = $attribute_details["attributes"];
                $variation_name = "unknown";
                if(is_array($variation_attributes)) {
                    $variation_name = "";
                    foreach ($variation_attributes as $key => $value) {
                        //The name is the first in the array
                        $variation_name .= " " . $value;
                    }
                }
                
                if( !empty( $midwest_logistics_product_sku_text_field_value ) ) {
                    if($midwest_logistics_product_sku_text_field_value !== "") {
                        $responseArray = midwest_logisitcs_verify_sku_number($midwest_logistics_product_sku_text_field_value);
                        if(is_array($responseArray)) {

                            if($responseArray["result"] === false) {
                                if($responseArray["message"] == "The API key is invalid") {
                                    ?>
                                    <div class="notice-error notice is-dismissible">
	                                    <p><strong><?php _e( "Your ". MIDWESTLOGISTICS_NAME . " API Key is invalid.",MIDWESTLOGISTICS_NAME); ?></strong></p>
                                    </div>
                                    <?php
                                    break;
                                } else {
                                    ?>
                                    <div class="notice-error notice is-dismissible">
	                                    <p><strong><?php _e( "The ". MIDWESTLOGISTICS_NAME . " product SKU you entered for product: " .  get_the_title(). " (" . $variation_name . " ) is not valid." ,MIDWESTLOGISTICS_NAME); ?></strong></p>
                                    </div>
                                    <?php
                                }
                            }
                        }          
                    }
                }
            }
        }
    }
}








