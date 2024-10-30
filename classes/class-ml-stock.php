<?php
class ML_Stock {
    private $metaKeySKU = "_midwest_logistics_product_sku_text_field";
    private $metaStockUpdated = "_midwest_logistics_stock_updated"; //used in product fucntions as well.
    private $metaStockCheck = "_midwest_logistics_check_stock";
    private $metaStockCounterOption = "mw_stock_counter";
    public function __construct() {
        /*
         * How does stock work? 
         * IMPORTANT NOTE. If the product or variation does not have a price the product query does not return it. 
         * Basically it gets the products that have been marked to be shipped by Midwest. Limitied by the $productLimit varaible.          
         * It then loops though each producy and makes sure the product has a SKU. If it does it adds it to the list and marks meta metaStockUpdated data as Y
         * It adds it to a list and send returns it to the function that called it. (get_inventory)
         * That loops through each product found and combines them into a API call and makes the call
         * After the call it loops through each result and begins updating the product with the correct SKU. 
         * It finds the product in the original list of products returned from the above steps . 
         * It then updates the stock and adds a log to the communication log.
         * If no products are found then it runs the reset which resets all the product that are marked to be shipped by Midwest to "N" so that the cyle can begin again.
         */
        
        //this must be set for the wp scheduler to work.
        add_action( 'midwest_logistics_inventory_stock_update',array($this, 'get_inventory') );
        add_filter( 'woocommerce_product_data_store_cpt_get_products_query',array($this, 'handling_custom_meta_query_keys') , 10, 2 );
    }      
    public function get_SKUs() {
        $productLimit = 200;
        $settingOptions = get_option('Midwest_Logistics_settings','');
        
        //This option keeps a record of when we reset the inventory.
        //Basically if no items are found then the option is incremented and then used to find products that have changed.
        //The reset function allows us increment the option.
        $pull_stock_setting = true;
        
        
        if($settingOptions !== "") {
            if(isset($settingOptions["Midwest_Logistics_stock_pull"])) {
                $productLimit = $settingOptions["Midwest_Logistics_stock_pull"];
                if(!is_numeric($productLimit)) {
                    $productLimit = 200;
                }
            }
            if(isset($settingOptions["Midwest_Logistics_select_field_1"])) {
                $pull_stock_setting_value = $settingOptions["Midwest_Logistics_select_field_1"];
                if($pull_stock_setting_value == "2") {
                    $pull_stock_setting = false;
                }
            }
        }
        
        //They do not want to keep track of the stock
        if($pull_stock_setting == false) {
            return [];
        }

        $skuArray = [];
        /* First we check if there are products we have never checked. If so we do those first.
         * We only pull products that we have designated as pulling stock.
         * 
         * Spoke to Jed. It was also decide that we would check stock even if the product isn't marked to check stock.
         */
        $args = array(
            'status' => 'publish', 
            'limit' => $productLimit,
            'type' => array('simple','variable'),
            $this->metaStockUpdated => "Y",
            $this->metaStockCheck => 'Y'
        );

        $products = wc_get_products($args);
        if(count($products) == 0) {
            //we must have updated them all so reset the field
            $this->reset();
        }
        if(count($products) > 0) {
            foreach($products as $product) {
                //$product->update_meta_data('_midwest_logistics_stock_updated', "Y" );
                //$product->save_meta_data();
                $updated = update_post_meta($product->get_id(),"_midwest_logistics_stock_updated","Y"); //using the Wordpress meta table becase that is where the prodcut meta is stored.
                if ($product->is_type('variable')) {
                    $variations = $product->get_available_variations();
                    foreach($variations as $variation) {
                        $variableProduct = wc_get_product($variation["variation_id"]);
                         if($variableProduct != null) {

                             $id = $variableProduct->get_id();
                             $midwestSKU = $variableProduct->get_meta($this->metaKeySKU);
                             if($midwestSKU != "") {
                                 array_push($skuArray, array(
                                     "post_id" => $id,
                                     "midwestSKU" => $midwestSKU,
                                     "parent_id" => $variableProduct->get_parent_id()
                                 ));
                             }
                         }                            
                     }

                } else {
                     $id = $product->get_id();
                     $midwestSKU = $product->get_meta($this->metaKeySKU);     
                     if($midwestSKU != "") {
                         array_push($skuArray,array(
                                 "post_id" => $id,
                                 "midwestSKU" => $midwestSKU,
                                 "parent_id" => $id
                             ));
                     }
                     
                }
             }
         }

        return $skuArray;

        
            /*
        global $wpdb;
        $skus = false;
        if($wpdb != null) {
            $sql =  "SELECT wpM.post_id
                    ,IFNULL(( 
                        SELECT wpM2.meta_value 
                        FROM ". $wpdb->prefix . "postmeta wpM2 
                        WHERE wpM2.post_id = wpM.post_id 
                        AND wpM2.meta_key = '" . $this->metaKeySKU . "'
                        LIMIT 1 
                    ),'')  as midwestSKU
                    FROM `". $wpdb->prefix . "postmeta` wpM 
                    JOIN ". $wpdb->prefix . "posts wpP on wpP.ID = wpM.post_id 
                    WHERE meta_key = '" . $this->metaKeySelect . "' 
                        AND meta_value = 'Y' 
                        AND wpP.post_status = 'publish' 
                        AND (wpP.post_type = 'product' OR wpP.post_type = 'product_variation')
                        AND (SELECT COUNT(ID) FROM " . $wpdb->prefix . "posts wP3 WHERE wP3.post_parent = wpP.ID AND wP3.post_type = 'product_variation') = 0 
                        AND IFNULL((SELECT meta_value from " . $wpdb->prefix . "postmeta WHERE meta_key = '" . $this->metaStockUpdated . "' AND post_id= wpM.post_id),'N') = 'N' 
                    LIMIT " . $productLimit;    
   		//var_dump($sql);
			//die();
            return  $wpdb->get_results($sql,ARRAY_A );
        }
        */
  
    }
    public function get_inventory($debugout = false) {
        $pull_stock_setting = true;
        $settingOptions = get_option('Midwest_Logistics_settings','');
        if($settingOptions !== "") {
            if(isset($settingOptions["Midwest_Logistics_select_field_1"])) {
                $pull_stock_setting_value = $settingOptions["Midwest_Logistics_select_field_1"];
                if($pull_stock_setting_value == "2") {
                    $pull_stock_setting = false;
                }
            }
        }
        
        //They do not want to keep track of the stock
        if($pull_stock_setting == false) {
            if($debugout == true) {
                echo "Sync Inventory is turned off in Midwest settings: <br>";
                echo "<br><br><br>";
                die();
            }

            return;
        }
        
        $skusResults = $this->get_SKUs();

        $skus = false;
        $skusArray = array();
        $skusJSONArray = array();
        
        if($debugout == true) {
            echo "Products being ran: <br>";
            var_dump($skusResults);
            echo "<br><br><br>";
        }

        if(count($skusResults) > 0) {

            foreach ($skusResults as $singleSKU) {                
                $skus .= $singleSKU["midwestSKU"] . ",";
                $skusJSONArray[] = $singleSKU["midwestSKU"];
                $skusArray[$singleSKU["midwestSKU"]] = array("id"=>$singleSKU["post_id"]);
				
                //Set the product as checked since we are sending it.
                $productId = $singleSKU["post_id"];
                $parentId = $singleSKU["parent_id"];
                $product = wc_get_product($parentId);
              
            }
        } 

        $apiKey = "";
        $settingOptions = get_option('Midwest_Logistics_settings','');
        if($settingOptions !== "") {
            $apiKey = $settingOptions["Midwest_Logistics_API_Key_field_0"];
        }

        if($skus != false) {
            $skus = substr($skus, 0, -1);
            $jsonArray = [
                "apiKey" => $apiKey,
                "sku" => $skusJSONArray,
                "request" => "productcheckbylist"
            ];   


            $API = new ML_API();
            $APIVersion = $API->get_api_version();

            $httpcode = "500";
            $postString = json_encode ($jsonArray);    
            
            $response = $API->send($postString);
            if($response != null) {
                $httpcode = $response["code"];
                $response = $response["response"];
            }            
            if($debugout == true) {
                echo "API Response: <br>";
                echo $response;
                echo "<br><br>";
            }
            if($httpcode == "200") {

                if(json_decode($response,true) !== null) {
                    $jsonResponse = json_decode($response,true);
                    $curl_result = $jsonResponse["result"];  
                    $products = $jsonResponse["products"];  
                    
     
                    if(is_array($products)) {
                        
                        foreach($products as $product) {
                            $stockAmount = $product["stock"];
                            $inStock = $product["instock"];	
                            $sku = $product["sku"];
                            
                            if(is_numeric($stockAmount) ) {   
                                if(isset($skusArray[$sku])) {
                                    $productId = $skusArray[$sku]["id"];

                                    //Update the product. If it a variation thats okay. 
                                    $currentProduct = wc_get_product($productId);
                                    if($currentProduct != null) {
                                        wc_update_product_stock($currentProduct,$stockAmount,'set',false); //It updates later on.                                      
                                    }
                                    if($debugout == true) {
                                        echo "Product " . $productId . " stock updated to " . $stockAmount ,"<br>";
                                    } 
                                    
                                    $responseText = array(
                                        "result" => "200",
                                        "response" => true,                                        
                                        "message" => "Product Stock",
                                        "stock" => $stockAmount,
                                        "instock" => $inStock,
                                    );
                                    
                                    
                                    midwest_logistics_add_communication_log($postString,json_encode($responseText),$productId,"product","stock_update", json_encode($responseText));
                                    
                                }                                                                
                            }	                                                                 
                        }
                    }
                }
            }

            if($httpcode == "500") {
                new WP_Error(  MIDWESTLOGISTICS_NAME . ' API',MIDWESTLOGISTICS_NAME . " API is down." );
            } 
        }
        if($debugout == true) {
            echo "<br><br><br>";
            die("manually ran");
        }
    }
    
    public function handling_custom_meta_query_keys( $wp_query_args, $query_vars ) {
        if (isset( $query_vars[$this->metaStockUpdated] ) ) {
            $wp_query_args['meta_query'][] = array(
                'key'     => $this->metaStockUpdated,
                'value'   => esc_attr( $query_vars[$this->metaStockUpdated] ),
                'compare' => '!=', 
            );

        }
        if (isset( $query_vars[$this->metaStockCheck] ) ) {
            $wp_query_args['meta_query'][] = array(
                'key'     => $this->metaStockCheck,
                'value'   => esc_attr( $query_vars[$this->metaStockCheck] ),
                'compare' => '=', 
            );

        } 
        
        //adds check if the key does not exists
        if (isset( $query_vars["_midwest_logistics_check_stock_not_exists"] ) ) {
            $wp_query_args['meta_query'][] = array(
                'key'     => $this->metaStockCheck,
                'compare' => 'NOT EXISTS', 
            );

        } 
        
        if (isset( $query_vars[$this->metaKeySKU] ) ) {
            $wp_query_args['meta_query'][] = array(
                'key'     => $this->metaKeySKU,
                'value'   => "",
                'compare' => array('!=','NOT EXISTS'), // 
            );

        }
        return $wp_query_args;
    }
    private function reset() {
        $args = array(
            'status' => 'publish', 
            'limit' => -1,
            'type' => array('simple','variable'),
            $this->metaStockCheck => 'Y'
        ); 
       $products = wc_get_products($args);
        if(count($products) > 0) {
           foreach($products as $product) {
               update_post_meta($product->get_id(),$this->metaStockUpdated,"N"); //using the Wordpress meta table becase that is where the prodcut meta is stored.
            }
        } 

    }

}
$ML_Stock = new ML_Stock();

add_action( 'wp_loaded', 'mw_stock_manual_run' );
function mw_stock_manual_run() {  
    if(is_admin()) {
        $run_mw_stock = filter_input( INPUT_GET, 'run_mw_stock', FILTER_SANITIZE_URL );
        if(isset($run_mw_stock)) {        
            if($run_mw_stock == "Y") {
                $ML_Stock = new ML_Stock();
                $ML_Stock->get_inventory(true);

            }
        }
    }
    
    
    
}