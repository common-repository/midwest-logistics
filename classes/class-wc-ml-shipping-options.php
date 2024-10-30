<?php


if ( ! class_exists( 'WC_ML_Shipping_Options' ) ) {
    class WC_ML_Shipping_Options {
        private $api_response = null;
        private $api_error = "";
        private $api_code = "200";
        public function __construct() {
 
        }
        public function get_options() {
           if(empty($this->api_response)) {
               $this->get();
           }
           if(!empty($this->api_response)) {
               return $this->api_response["shipping_options"];
           }
           return false;
           
        }
        public function get_api_code() {
            return $this->api_code;
        }
        public function get_api_error() {
            return $this->api_error;
        }        
        private function get() {
            $settingOptions = get_option('Midwest_Logistics_settings','');
            if ( empty( $settingOptions ) ) {
                return;
            }
            
            $api_response = array(
                "shipping_options" => array(
                    array(
                        "id" => MIDWESTLOGISTICS_SHIPPING_DEFAULT,
                        "value" => "USPS"                    
                    )
                )
            );
            
            $midwest_logistics_api_key = $settingOptions["Midwest_Logistics_API_Key_field_0"];
            
            $jsonArray = [
                "apiKey" => $midwest_logistics_api_key,
                "request" => "get_shipping_options"                
            ];
            
            $postString = json_encode ($jsonArray);
            
            $httpcode = "500";
            $API = new ML_API();
            $response = $API->send($postString);
            if($response != null) {
                $httpcode = $response["code"];
                $response = $response["response"];
            }
            
            $responseText = "";
            
            if($httpcode == "200") {                
                if(json_decode($response) !== null) {
                    $jsonResponse = json_decode($response,true); //as associated array
                    $response_result = "200";
                    if(isset($jsonResponse["result"])) {
                        $response_result = $jsonResponse["result"];
                        $this->api_code = $response_result;
                    }
                    if(isset($jsonResponse["message"])) {
                        $response_result = $jsonResponse["result"];
                        $this->api_code = $response_result;
                    }
                    
                    if($this->api_code === "200") {
                        $api_response = $jsonResponse;
                    } else {
                        $this->api_error = $jsonResponse["message"];
                    }
                    
                }       
            } 

            $this->api_response = $api_response;
  
        }
    }
    
    
    
}
