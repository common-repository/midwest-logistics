<?php

if ( ! class_exists( 'WC_ML_Shipping_Method' ) ) {
    class WC_ML_Shipping_Method extends WC_Shipping_Method {
        /**
         * Constructor for your shipping class
         *
         * @access public
         * @return void
         */
        public $shipping_options = array();
        private $data_name = "ML_WC_Shipping_options"; //change in order functions as well
        private $error_message = "";
        private $saved_data;



        public function __construct() {
            $this->id                 = 'midwest_logistics';
            $this->method_title       = __( 'Midwest Logistics' );
            $this->method_description = __( 'Link your current shipping zone rates to Midwest Logistics shipping options.' ); // 
            $this->enabled            = "yes"; // This can be added as an setting but for this example its forced enabled                      


            $shipping_options = new WC_ML_Shipping_Options();
            $this->shipping_options = $shipping_options->get_options();

            if($shipping_options->get_api_code() <> "200") {
                $this->error_message = $shipping_options->get_api_error();
            }
            add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

            $this->init();

        }

        /**
         * Init your settings
         *
         * @access public
         * @return void
         */
        function init() {
            $this->init_settings(); //load saved settings
            $this->init_form_fields();


            // Save settings in admin if you have any defined
            add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
        }
        function init_form_fields() {

            $data = $this->get_settings();

            $shipping_options = array();
            if(is_array($this->shipping_options)) {
                foreach($this->shipping_options as $option) {
                    $shipping_options[$option["id"]] = $option["value"];
                }                                              
            }   

            if($this->error_message == "") {
                $this->form_fields = array(
                    'default_shipping' => array(
                        'title' => __( 'Default Shipping Method', 'woocommerce' ),
                        'type' => 'select',
                        'options' => $shipping_options,
                        'default' => $data["default_shipping"],
                        'description' => __( 'The default shipping used for non-mapped shipping.', 'woocommerce' ),
                    ),
                    'enable_mapping' => array(
                        'title' => __( 'Enable Rate Mapping', 'woocommerce' ),
                        'type' => 'select',
                        'options' => array(
                            'no' => __('No', 'woocommerce'),
                            'yes' => __('Yes', 'woocommerce')
                        ),
                        'description' => __( 'This allows you to map shipping methods you have set in Woocomerce to Midwest Logistics supported shipping options.', 'woocommerce' ),
                        'default' => $data["enable_mapping"],
                        'custom_attributes' => array(
                            "onchange" => "showMapTable(this)"
                        )
                    ),
                );
            }
        } // End init_form_fields()

        function process_admin_options() {
            $default_shipping = filter_input(INPUT_POST,"woocommerce_midwest_logistics_default_shipping");
            $default_shipping = sanitize_text_field($default_shipping);

            $enable_mapping = filter_input(INPUT_POST,"woocommerce_midwest_logistics_enable_mapping");
            $enable_mapping = sanitize_text_field($enable_mapping);     

            $ML_WC_rate_link = isset($_POST["ML_WC_rate_link"]) == true ? $_POST["ML_WC_rate_link"] : [];       

            $data = array(
                "default_shipping" => $default_shipping,
                "enable_mapping" => $enable_mapping,
                "rate_links" => $ML_WC_rate_link,    
            );
            update_option($this->data_name,serialize($data));
            
            $this->init_form_fields(); //reinitialize the form fields with the new data
        }
        function get_settings() {
            $option_data = array(
                "default_shipping" => MIDWESTLOGISTICS_SHIPPING_DEFAULT, // ups
                "enable_mapping" => "",
                "rate_links" => array(),

            );
            $settings = get_option($this->data_name);
            if($settings != false) {
                $settings = maybe_unserialize($settings);
            }
            if(is_array($settings)) {
                $option_data = array_merge($option_data,$settings);
            }
            return $option_data;
        }
        function generate_settings_html($form_fields = [],$echo = true) {
            if($this->error_message !== "") {
                $class = 'notice notice-error';
                $message = __("The Midwest Logisitcs API responded with the following error:", 'woocomerce' );
                $additional_message = __("Please contact Midwest Logistics to at", 'woocomerce' ) . " <a href='mailto:support@midwest-logistics.com' >support@midwest-logistics.com</a> " . __("for further assistance.", 'woocomerce' );
                printf( '<div class="%1$s"><p><strong>%2$s</strong></br >%3$s</p><p>%4$s</p></div>', esc_attr( $class ), esc_html( $message ),esc_html( $this->error_message ) ,$additional_message);
            } else {
                ?><div class="ml-shipping-setting"> <?php
                    parent::generate_settings_html($form_fields,true);
                    $this->generate_zone_table();

                    ?>
                </div>
                <script>

                    map_table = document.getElementById("ML_shipping_links") || null;                
                    showMapTable(document.getElementById("woocommerce_midwest_logistics_enable_mapping"))
                    function showMapTable(el) {
                        map_table = document.getElementById("ML_shipping_links") || null;
                        if(map_table) {
                            if(el.options[el.selectedIndex].value === "no") {
                                map_table.style.display = "none";
                            } else {
                                map_table.style.display = "block";
                            }
                        }
                    }

                </script>
                <style>
                    .ml-shipping-setting label {
                        font-weight: bold; 

                    }
                    .ml-shipping-setting .description {
                        margin-bottom:20px;
                    }
                    .ml-shipping-setting select {
                        width:100%;
                        max-width:200px;
                    }
                </style>
                <?php               
            }          
        }       


        function generate_zone_table() {
            $this->saved_data = $this->get_settings(); 
            ?><div id="ML_shipping_links"><?php
            $zone_class = new WC_Shipping_Zones();
            $zones = $zone_class::get_zones();
            if(!empty($zones) && $zones !== false && is_array($zones)) {
                foreach($zones as $zone) {
                    $shipping_methods = $zone["shipping_methods"];
                    if(is_array($shipping_methods)) {
                        //var_dump($zone);
                        ?>

                            <h2><?php _e("Zone: " .$zone["zone_name"]) ?></h2>
                            <table class="wp-list-table widefat fixed striped table-view-list">
                                <tr>
                                    <td class="column-primary"><strong><?php _e("Shipping Method")?></strong></td>
                                    <td class="column-primary" style="text-align:right"><strong><?php _e("Midwest Logisitcs Shipping Method")?></strong></td>
                                </tr>

                                <?php

                                foreach($shipping_methods as $shipping_method) {
                                    $instance_id = $shipping_method->get_instance_id();
                                    if($shipping_method instanceof WC_Shipping_UPS) {
                                        $this->generate_ups_shipping_html($shipping_method);
                                    } elseif($shipping_method instanceof WC_Shipping_USPS) {
                                        $this->generate_usps_shipping_html($shipping_method);


                                    } else {
                                        $this->generate_general_zone_shipping_html($shipping_method);
                                    }


                                }
                            ?>
                            </table>
                    <?php
                    }
                }
            } 
            ?></div><?php
        }
        private function generate_general_zone_shipping_html($shipping_method) {

            $instance_id = $shipping_method->get_instance_id();
            if(empty($this->saved_data)) {
                $this->saved_data = $this->get_settings(); 
            }
            $rates_selected = $this->saved_data["rate_links"];
            ?>
            <tr>
                <td>
                    <?php echo $shipping_method->get_title() ?>
                </td>
                <td style="text-align:right">
                    <select name="ML_WC_rate_link[<?php echo $shipping_method->get_instance_id() ?>]">
                        <option value=""><?php _e("Default","woocomerce") ?></option>
                        <?php
                        $option_selected = isset($rates_selected[$instance_id]) ? $rates_selected[$instance_id] : "";
                        if(is_array($this->shipping_options)) {
                            foreach($this->shipping_options as $option) {
                                ?><option value="<?php echo $option["id"] ?>" <?php  selected($option_selected,$option["id"]) ?> ><?php echo $option["value"] ?></option><?php
                            }                                                
                        }                                            
                        ?>
                    </select>        
                </td>
            </tr>
            <?php
        }
        private function generate_ups_shipping_html($shipping_method) {
            if(empty($this->saved_data)) {
                $this->saved_data = $this->get_settings(); 
            }
            $rates_selected = $this->saved_data["rate_links"];

            $instance_id = $shipping_method->get_instance_id();
            require_once( MIDWESTLOGISTICS_PATH . 'classes/class-ml-ups-shipping.php' );
            $ups_class = new ML_UPS_Shipping($instance_id);
            $services = $ups_class->get_services();

            ?>
            <tr>
                <td colspan="2">
                    <?php echo $shipping_method->get_title() ?>
                    <div id="ML_service_ups_links">
                    <table class="wp-list-table widefat fixed striped table-view-list">
                        <tr>
                            <td style="width:5%;text-align:center;"><?php _e("Code","textdomain") ?></td>
                            <td><?php _e("Name","textdomain") ?></td>
                            <td></td>
                        </tr>
                        <?php
                        if(is_array($services)) {
                            foreach($services as $key => $service) {
                                $code = $service[0];
                                $name = $service[1];
                                ?>
                                <tr>
                                    <td style="width:5%;text-align:center;"><?php echo $code ?></td>
                                    <td><?php echo $name ?></td>
                                    <td style="text-align:right;">
                                        <select name="ML_WC_rate_link[<?php echo $instance_id ?>][<?php echo $code ?>]">
                                            <option value=""><?php _e("Default","woocomerce") ?></option>
                                            <?php
                                            $option_selected = isset($rates_selected[$instance_id][$code]) ? $rates_selected[$instance_id][$code] : "";
                                            if(is_array($this->shipping_options)) {
                                                foreach($this->shipping_options as $option) {
                                                    ?><option value="<?php echo $option["id"] ?>" <?php  selected($option_selected,$option["id"]) ?> ><?php echo $option["value"] ?></option><?php
                                                }                                                
                                            }                                            
                                            ?>
                                        </select>   

                                    </td>
                                </tr>

                                <?php
                            }  
                        }
                        ?>
                    </table>
                </div>
                </td>
            </tr>
            <?php
        }
        private function generate_usps_shipping_html($shipping_method) {
            if(empty($this->saved_data)) {
                $this->saved_data = $this->get_settings(); 
            }
            $rates_selected = $this->saved_data["rate_links"];

            $instance_id = $shipping_method->get_instance_id();
            require_once( MIDWESTLOGISTICS_PATH . 'classes/class-ml-usps-shipping.php' );
            $ups_class = new ML_USPS_Shipping($instance_id);
            $services = $ups_class->get_services();

            ?>
            <tr>
                <td colspan="2">
                    <?php echo $shipping_method->get_title() ?>
                    <div id="ML_service_ups_links">
                    <table class="wp-list-table widefat fixed striped table-view-list">
                        <tr>
                            <td><?php _e("Name","textdomain") ?></td>
                            <td></td>
                        </tr>
                        <?php
                        if(is_array($services)) {
                            foreach($services as $key => $service) {
                                $code = $service[0];
                                $name = $service[1];
                                ?>
                                <tr>
                                    <td><?php echo $name ?></td>
                                    <td style="text-align:right;">
                                        <select name="ML_WC_rate_link[<?php echo $instance_id ?>][<?php echo $code ?>]">
                                            <option value=""><?php _e("Default","woocomerce") ?></option>
                                            <?php
                                            $option_selected = isset($rates_selected[$instance_id][$code]) ? $rates_selected[$instance_id][$code] : "";
                                            if(is_array($this->shipping_options)) {
                                                foreach($this->shipping_options as $option) {
                                                    ?><option value="<?php echo $option["id"] ?>" <?php  selected($option_selected,$option["id"]) ?> ><?php echo $option["value"] ?></option><?php
                                                }                                                
                                            }                                            
                                            ?>
                                        </select>   

                                    </td>
                                </tr>

                                <?php
                            }  
                        }
                        ?>
                    </table>
                </div>
                </td>
            </tr>
            <?php
        }

    }

}

function ML_add_your_shipping_method( $methods ) {
    $methods['WC_ML_Shipping_Method'] = 'WC_ML_Shipping_Method'; 
    return $methods;
}

add_filter( 'woocommerce_shipping_methods', 'ML_add_your_shipping_method' );


