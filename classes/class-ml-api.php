<?php
if ( ! defined( 'MIDWESTLOGISTICS_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}
class ML_API {
    private $APIURL = 'https://connect.midwest-logistics.com/api/';
    private function get_api_settings() {
        $APIURL = $this->APIURL;
        $APIVersion = "2";
        
        return array(
            "url" => $APIURL,
            "version" => $APIVersion           
        );
    }
    public function get_api_url() {
        return $this->get_api_settings()["url"];
    }
    public function get_api_version() {
        return $this->get_api_settings()["version"];
    }
    function send($data) {
  
        $APISettings = $this->get_api_settings();
        if($APISettings == null) {
            return null;                       
        }

        $APIURL = $APISettings["url"];
        $APIVersion = $APISettings["version"];
        
        $headers = null;
        if($APIVersion == "2") {
            $headers = array(
                'content-type' => 'application/json'
            );
        }
        
        $args = array(
            'body' => $data,
            'timeout' => '60',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => $headers,
            'cookies' => array()
        );
        

        $response = array(
            "code" => "500",
            "response" => ""                   
        );
        $wp_remote_post_response = wp_remote_post($APIURL ,$args);
        if(is_wp_error($wp_remote_post_response)) {
            $response["code"] = $wp_remote_post_response->get_error_code();
            $response["response"] = $wp_remote_post_response->get_error_messages($response["code"]);
            if(is_array($response)) {
                $response = $response[0];
            }

        } else {
            $response["code"] = wp_remote_retrieve_response_code( $wp_remote_post_response );
            $response["response"] = $wp_remote_post_response["body"];
        }

        return $response;
    }
}
