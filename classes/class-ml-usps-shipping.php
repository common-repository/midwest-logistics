<?php
if(class_exists("WC_Shipping_USPS")) {
    class ML_USPS_Shipping extends WC_Shipping_USPS {
        private $flat_rate_boxes;
        private $services;
        function __construct( $instance_id = 0 ) {
            parent::__construct($instance_id);
            $this->services           = include  plugin_dir_path( MIDWESTLOGISTICS_FILE ) . 'data/data-usps-services.php';
            $this->flat_rate_boxes    = include  plugin_dir_path( MIDWESTLOGISTICS_FILE ) . 'data/data-usps-flat-rate-boxes.php';
            $this->set_settings();
        }
        private function set_settings() {;
            // Define user set variables.
            $this->packing_method           = $this->get_option( 'packing_method', 'per_item' );
            $this->custom_services          = $this->get_option( 'services', array() );
            $this->boxes                    = $this->get_option( 'boxes', array() );
            $this->offer_rates              = $this->get_option( 'offer_rates', 'all' );
            $this->enable_standard_services = 'yes' === $this->get_option( 'enable_standard_services', 'no' );
            $this->enable_flat_rate_boxes   = $this->get_option( 'enable_flat_rate_boxes', 'yes' );
            $this->shippingrates            = $this->get_option( 'shippingrates', 'ALL' );
            $this->flat_rate_boxes          = apply_filters( 'usps_flat_rate_boxes', $this->flat_rate_boxes );
        }
        public function get_services(){
            $sort = 0;
            $this->ordered_services = array();
            $use_services = $this->services;

            foreach ( $use_services as $code => $service ) {
                $custom_name = isset( $this->custom_services[ $code ]['name'] ) ? $this->custom_services[ $code ]['name'] : '';
                if($custom_name == "") {
                    $custom_name = $service["name"]   ;
                }

                if ( isset( $this->custom_services[ $code ]['order'] ) ) {
                    $sort = $this->custom_services[ $code ]['order'];
                }

                while ( isset( $this->ordered_services[ $sort ] ) ) {
                    $sort++;
                }
                
                $enabled = true;
                if ( isset( $this->custom_services[ $code ]['enabled'] ) ) {
                   $enabled = $this->custom_services[ $code ]["enabled"];
                }
                if($enabled == true) {
                    $this->ordered_services[ $sort ] = array( $code, $custom_name );
                }

                $sort++;
            }

            ksort( $this->ordered_services );
            
            

            return $this->ordered_services;
        }
    }
}
