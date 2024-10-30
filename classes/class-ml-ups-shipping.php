<?php
if(class_exists("WC_Shipping_UPS")) {
    class ML_UPS_Shipping extends WC_Shipping_UPS {
        private $services = array(
            // Domestic.
            '12' => '3 Day Select',
            '03' => 'Ground',
            '02' => '2nd Day Air',
            '59' => '2nd Day Air AM',
            '01' => 'Next Day Air',
            '13' => 'Next Day Air Saver',
            '14' => 'Next Day Air Early AM',

            // International.
            '11' => 'Standard',
            '07' => 'Worldwide Express',
            '54' => 'Worldwide Express Plus',
            '08' => 'Worldwide Expedited Standard',
            '65' => 'Worldwide Saver',

        );

        /**
         * Country considered as EU.
         *
         * @var array
         */
        private $eu_array = array( 'BE', 'BG', 'CZ', 'DK', 'DE', 'EE', 'IE', 'GR', 'ES', 'FR', 'HR', 'IT', 'CY', 'LV', 'LT', 'LU', 'HU', 'MT', 'NL', 'AT', 'PT', 'RO', 'SI', 'SK', 'FI', 'GB' );

        /**
         * Shipments Originating in the European Union.
         *
         * @var array
         */
        private $euservices = array(
            '07' => 'UPS Express',
            '08' => 'UPS ExpeditedSM',
            '11' => 'UPS Standard',
            '54' => 'UPS Express PlusSM',
            '65' => 'UPS Saver',
        );

        /**
         * Poland services.
         *
         * @var array
         */
        private $polandservices = array(
            '07' => 'UPS Express',
            '08' => 'UPS ExpeditedSM',
            '11' => 'UPS Standard',
            '54' => 'UPS Express PlusSM',
            '65' => 'UPS Saver',
            '82' => 'UPS Today Standard',
            '83' => 'UPS Today Dedicated Courier',
            '84' => 'UPS Today Intercity',
            '85' => 'UPS Today Express',
            '86' => 'UPS Today Express Saver',
        );
        function __construct( $instance_id = 0 ) {
            parent::__construct($instance_id);
        }
        public function get_services(){
            $sort = 0;
            $this->ordered_services = array();

            if ( 'PL' === $this->origin_country ) {
                $use_services = $this->polandservices;
            } elseif ( in_array( $this->origin_country, $this->eu_array ) ) {
                $use_services = $this->euservices;
            } else {
                $use_services = $this->services;
            }

            foreach ( $use_services as $code => $name ) {
                $custom_name = isset( $this->custom_services[ $code ]['name'] ) ? $this->custom_services[ $code ]['name'] : '';
                if($custom_name == "") {
                    $custom_name = $name   ;
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
