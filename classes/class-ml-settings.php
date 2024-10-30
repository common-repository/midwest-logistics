<?php
if ( ! class_exists( 'ML_Settings' ) ) {
    class ML_Settings {

        public function add_admin_menu(  ) { 
            add_menu_page(
                "Settings",
                "Midwest Logistics",
                "manage_options",
                "midwest-logistics-options",
                array($this,"options_page"),
                "", 
                99
            );              
            add_submenu_page(
                "midwest-logistics-options",
                "Settings",
                "Settings",
                "manage_options",
                "midwest-logistics-options",
                array($this,"options_page"),
                0
            );   
        }

        public function settings_init(  ) { 
            register_setting( 'Midwest_Logistics_plugin_Page', 'Midwest_Logistics_settings','settings_validation' );
            add_settings_section(
                'Midwest_Logistics_Midwest_Logistics_plugin_Page_section', 
                __( 'Settings for the ' . MIDWESTLOGISTICS_NAME . ' plugin', 'Midwest_Logistics' ), 
                array($this,'settings_section_callback'), 
                'Midwest_Logistics_plugin_Page'
            );
            /*
            add_settings_field( 
                'Midwest_Logistics_api_version', 
                __( 'API Version:', 'Midwest_Logistics' ), 
                array($this,'Midwest_Logistics_api_version_render'), 
                'Midwest_Logistics_plugin_Page', 
                'Midwest_Logistics_Midwest_Logistics_plugin_Page_section' 
            );
             * 
             */
            add_settings_field( 
                'Midwest_Logistics_text_field_0', 
                __( 'Api Key:', 'Midwest_Logistics' ), 
                array($this,'text_field_0_render'), 
                'Midwest_Logistics_plugin_Page', 
                'Midwest_Logistics_Midwest_Logistics_plugin_Page_section' 
            );

            add_settings_field( 
                'Midwest_Logistics_select_field_1', 
                __( 'Sync Inventory:', 'Midwest_Logistics' ), 
                array($this,'select_field_1_render'), 
                'Midwest_Logistics_plugin_Page', 
                'Midwest_Logistics_Midwest_Logistics_plugin_Page_section' 
            );
            add_settings_field( 
                'Midwest_Logistics_select_field_2', 
                __( 'Order Status to import into Midwest Logistics:', 'Midwest_Logistics' ), 
                array($this,'select_field_2_render'), 
                'Midwest_Logistics_plugin_Page', 
                'Midwest_Logistics_Midwest_Logistics_plugin_Page_section' 
            );
            add_settings_field( 
                'Midwest_Logistics_auto_push_select_field', 
                __( 'Automatically Push Orders to Midwest Logistics:', 'Midwest_Logistics' ), 
                array($this,'auto_push_select_field_render'), 
                'Midwest_Logistics_plugin_Page', 
                'Midwest_Logistics_Midwest_Logistics_plugin_Page_section' 
            );
            add_settings_field( 
                'Midwest_Logistics_send_tracking_email', 
                __( 'Automatically send tracking email to customer:', 'Midwest_Logistics' ), 
                array($this,'send_tracking_email_select_field_render'), 
                'Midwest_Logistics_plugin_Page', 
                'Midwest_Logistics_Midwest_Logistics_plugin_Page_section' 
            );
            add_settings_field( 
                'Midwest_Logistics_send_tracking_email_text', 
                __( 'Custom Shipment Message:', 'Midwest_Logistics' ), 
                array($this,'send_tracking_email_text_render'),
                'Midwest_Logistics_plugin_Page', 
                'Midwest_Logistics_Midwest_Logistics_plugin_Page_section' 
            );
            add_settings_field( 
                'Midwest_Logistics_check_order_limit', 
                __( 'Do not update tracking on orders older than:', 'Midwest_Logistics' ), 
                array($this,'check_order_limit_render'),
                'Midwest_Logistics_plugin_Page', 
                'Midwest_Logistics_Midwest_Logistics_plugin_Page_section' 
            );
            add_settings_field( 
                'Midwest_Logistics_clear_log', 
                __( 'Clear the communication log for entries older than:', 'Midwest_Logistics' ), 
                array($this,'check_order_clear_log_render'),
                'Midwest_Logistics_plugin_Page', 
                'Midwest_Logistics_Midwest_Logistics_plugin_Page_section' 
            );
            add_settings_field( 
                'Midwest_Logistics_submit_order_limit', 
                __( 'Do not try and re-submit invalid orders older than:', 'Midwest_Logistics' ), 
                array($this,'check_order_send_render'),
                'Midwest_Logistics_plugin_Page', 
                'Midwest_Logistics_Midwest_Logistics_plugin_Page_section' 
            );          
            /*
             * We decided to not atually let them choose since all the stock really does is update if Midwest adds stock otherwise Woocomerce reduces it when an order is placed. 
            add_settings_field( 
                'Midwest_Logistics_stock_pull', 
                __( 'Pull stock updates for products in chunks of:', 'Midwest_Logistics' ), 
                array($this,'stock_pull_render'),
                'Midwest_Logistics_plugin_Page', 
                'Midwest_Logistics_Midwest_Logistics_plugin_Page_section' 
            );
             * 
             */
            
        }

        public function text_field_0_render(  ) { 
            $options = get_option( 'Midwest_Logistics_settings' );
            $value = "";
            if(isset($options['Midwest_Logistics_API_Key_field_0'])) {
                $value = $options['Midwest_Logistics_API_Key_field_0'];
            }            
            ?>
            <input type='text' name='Midwest_Logistics_settings[Midwest_Logistics_API_Key_field_0]' value='<?php echo $value ?>'>
            <?php
        }
        public function Midwest_Logistics_api_version_render() {
            $options = get_option( 'Midwest_Logistics_settings' );
            ?>
            <select name='Midwest_Logistics_settings[Midwest_Logistics_api_version]'>
                <option value='1' <?php selected( $options['Midwest_Logistics_api_version'], 1 ); ?>>API Version 1.0.0 (Current)</option>
                <option value='2' <?php selected( $options['Midwest_Logistics_api_version'], 2 ); ?>>API Version 2.0.0 (Beta)</option>
            </select>
            <?php
        }

        public function select_field_1_render(  ) { 
            $options = get_option( 'Midwest_Logistics_settings' );
            if($options == null) {
                $options["Midwest_Logistics_select_field_1"] = 1;
            }
            ?>
            <select name='Midwest_Logistics_settings[Midwest_Logistics_select_field_1]'>
                <option value='1' <?php selected( $options['Midwest_Logistics_select_field_1'], 1 ); ?>>Yes</option>
                <option value='2' <?php selected( $options['Midwest_Logistics_select_field_1'], 2 ); ?>>No</option>
            </select>
            <?php
        }

        public function select_field_2_render(  ) { 
            $options = get_option( 'Midwest_Logistics_settings' );
            if($options == null) {
                $options["Midwest_Logistics_select_field_2"] = "1";
            }
            $orderStatusArray = wc_get_order_statuses();   
            ?>
            <select name='Midwest_Logistics_settings[Midwest_Logistics_select_field_2]'>
                <?php
                foreach ($orderStatusArray as $key => $value) {
                    if($key !== 'wc-pending') {
                        ?><option value='<?php echo $key ?>' <?php selected( $options['Midwest_Logistics_select_field_2'], $key ); ?> ><?php echo $value ?></option><?php
                    }
                }
                ?>
            </select>
        <?php
        }

        public function auto_push_select_field_render(  ) { 
            $options = get_option( 'Midwest_Logistics_settings' );  
            if($options == null) {
                $options["Midwest_Logistics_auto_push_select_field"] = "";
            }
            ?>
            <select name='Midwest_Logistics_settings[Midwest_Logistics_auto_push_select_field]'>
                <option value='1' <?php selected( $options['Midwest_Logistics_auto_push_select_field'], 1 ); ?>>No</option>
                <option value='2' <?php selected( $options['Midwest_Logistics_auto_push_select_field'], 2 ); ?>>Yes</option>
            </select>
            <?php

        }
        public function send_tracking_email_select_field_render(  ) { 
            $options = get_option( 'Midwest_Logistics_settings' );  
            $value = "";
            if(isset($options['Midwest_Logistics_send_tracking_email_select_field'])) {
                $value = $options['Midwest_Logistics_send_tracking_email_select_field'];
            }
            ?>
            <select name='Midwest_Logistics_settings[Midwest_Logistics_send_tracking_email_select_field]'>
                <option value='N' <?php selected( $value, "N" ); ?>>No</option>
                <option value='Y' <?php selected( $value, "Y" ); ?>>Yes</option>
            </select>
            <?php
        }
        public function send_tracking_email_text_render(  ) { 
            $options = get_option( 'Midwest_Logistics_settings' );
            $value = "Your order was shipped on [DATE] via [SERVICE]. To track shipment, please follow the link of shipment ID(s) [ID]";
            if(isset($options['Midwest_Logistics_send_tracking_email_text'])) {
                $value = $options['Midwest_Logistics_send_tracking_email_text'];
            }
            ?>
            <input type='text' name='Midwest_Logistics_settings[Midwest_Logistics_send_tracking_email_text]' value='<?php echo sanitize_text_field($value); ?>' placeholder='Your order was shipped on [DATE] via [SERVICE]. To track shipment, please follow the link of shipment ID(s) [ID]' title="Define you own custom shipping message. Use the tags [DATE],[SERVICE] and [ID] to output the date,carrier adn the tracking id&quots"><br />
            You can use the following short codes in your email message:
            <oL>
                <li>[DATE] - The date the order was shipped.</li>
                <li>[Service] - The name of the carrier used to ship the package (UPS, USPS, DHL) .</li>
                <li>[ID] - The tracking link(s).</li>
            </oL>
            <?php
        }
        public function check_order_limit_render(  ) { 
            $options = get_option( 'Midwest_Logistics_settings' );  
            $value = "";
            if(isset($options['Midwest_Logistics_check_order_limit'])) {
                $value = $options['Midwest_Logistics_check_order_limit'];
            }
            ?>
            <select name='Midwest_Logistics_settings[Midwest_Logistics_check_order_limit]'>
                <option value='30' <?php selected( $value, "30" ); ?>>30 Days</option>
                <option value='60' <?php selected( $value, "60" ); ?>>60 Days</option>
                <option value='90' <?php selected( $value, "90" ); ?>>90 Days</option>
                <option value='120' <?php selected( $value, "120" ); ?>>120 Days</option>
                <option value='150' <?php selected( $value, "150" ); ?>>150 Days</option>
                <option value='180' <?php selected( $value, "180" ); ?>>180 Days</option>
            </select>
            <?php
        }
        public function check_order_clear_log_render(  ) { 
            $options = get_option( 'Midwest_Logistics_settings' );  
            $value = "90";
            if(isset($options['Midwest_Logistics_clear_log'])) {
                $value = $options['Midwest_Logistics_clear_log'];
            }
            ?>
            <select name='Midwest_Logistics_settings[Midwest_Logistics_clear_log]'>
                <option value='1' <?php selected( $value, "1" ); ?>>Daily</option>
                <option value='5' <?php selected( $value, "5" ); ?>>5 Days</option>
                <option value='10' <?php selected( $value, "10" ); ?>>10 Days</option>
                <option value='15' <?php selected( $value, "15" ); ?>>15 Days</option>
                <option value='20' <?php selected( $value, "20" ); ?>>20 Days</option>
                <option value='30' <?php selected( $value, "30" ); ?>>30 Days</option>
                <option value='60' <?php selected( $value, "60" ); ?>>60 Days</option>
                <option value='90' <?php selected( $value, "90" ); ?>>90 Days</option>
                <option value='120' <?php selected( $value, "120" ); ?>>120 Days</option>
                <option value='150' <?php selected( $value, "150" ); ?>>150 Days</option>
                <option value='180' <?php selected( $value, "180" ); ?>>180 Days</option>
            </select>
            <?php
        }
        public function stock_pull_render() {
            $options = get_option( 'Midwest_Logistics_settings' );  
            $value = "200";
            if(isset($options['Midwest_Logistics_stock_pull'])) {
                $value = $options['Midwest_Logistics_stock_pull'];
            }
            ?>
            <select name='Midwest_Logistics_settings[Midwest_Logistics_stock_pull]'>
                <option value='50' <?php selected( $value, "1" ); ?>>50 products</option>
                <option value='100' <?php selected( $value, "100" ); ?>>100 Products</option>
                <option value='200' <?php selected( $value, "200" ); ?>>200 Products</option>
                <option value='300' <?php selected( $value, "300" ); ?>>300 Products</option>
            </select>
            <?php
        }
        public function check_order_send_render(  ) { 
            $options = get_option( 'Midwest_Logistics_settings' );  
            $value = "";
            if(isset($options['Midwest_Logistics_submit_order_limit'])) {
                $value = $options['Midwest_Logistics_submit_order_limit'];
            }
            ?>
            <select name='Midwest_Logistics_settings[Midwest_Logistics_submit_order_limit]'>
                <option value='30' <?php selected( $value, "30" ); ?>>30 Days</option>
                <option value='60' <?php selected( $value, "60" ); ?>>60 Days</option>
                <option value='90' <?php selected( $value, "90" ); ?>>90 Days</option>
                <option value='120' <?php selected( $value, "120" ); ?>>120 Days</option>
                <option value='150' <?php selected( $value, "150" ); ?>>150 Days</option>
                <option value='180' <?php selected( $value, "180" ); ?>>180 Days</option>
            </select>
            <?php
        }
        public function settings_section_callback(  ) { 
            echo __( 'Settings for the Midwest Logistics plugin', 'Midwest_Logistics' );
        }

        public function log_section_callback(  ) { 
            echo __( 'Communication Log for the Midwest Logistics plugin', 'Midwest_Logistics' );
        }

        public function settings_validation($input) {
            //check to make sure it came from the right place
            if (!isset($_POST['Midwest_logistics_settings']) || !wp_verify_nonce($_POST['Midwest_logistics_settings'],'Midwest-logistics-save-nonce')) {
                header( 'Status: 403 Forbidden' );
                header( 'HTTP/1.1 403 Forbidden' );
                exit();
            } else {
                // Create our array for storing the validated options
                $output = array();
                // Loop through each of the incoming options
                foreach( $input as $key => $value ) {
                    // Check to see if the current option has a value. If so, process it.
                    if( isset( $input[$key] ) ) {
                        // Strip all HTML and PHP tags and properly handle quoted strings
                        switch ($key) {
                            case "Midwest_Logistics_select_field_1" :
                                if(is_numeric( $input[$key])) {
                                    $output[$key] = strip_tags( stripslashes( $input[ $key ] ) );
                                } else {
                                    $output[$key] = "";
                                    add_settings_error(
                                        'Midwest_Logistics_validation_error',
                                        esc_attr( 'Midwest_Logistics_select_field_1_validate' ),
                                        "Select a valid field for Sync Inventory ",
                                        "error"
                                    );
                                }
                                break;
                            case "Midwest_Logistics_auto_push_select_field" :
                                if(is_numeric( $input[$key])) {
                                    $output[$key] = strip_tags( stripslashes( $input[ $key ] ) );
                                } else {
                                    $output[$key] = "";
                                    add_settings_error(
                                        'Midwest_Logistics_validation_error',
                                        esc_attr( 'Midwest_Logistics_auto_push_select_field_validate' ),
                                        "Select a valid field for automatically pushing notifications.",
                                        "error"
                                    );
                                }
                                break;
                            default:
                                $output[$key] = strip_tags( stripslashes( $input[ $key ] ) );
                        }
                    } // end if
                } // end foreach
            }
            // Return the array processing any additional functions filtered by this action
            return apply_filters( 'midwest_logistics_validate_settings', $output, $input );
        }
        public function options_page(  ) { 
            ?>
            <form action='options.php' method='post'>
                <style>
                    .form-table th {
                        width: 23%;    
                    }
                    .form-table input {
                        width: 96%;
                        padding: 5px 0px;                
                    }
                    .form-table select {
                        width: 96%;             
                    }
                    .midwestlogistics-help-tip {
                        margin: -7px -24px 0 0;
                        position: relative;
                    }
                    .midwestlogistics-help-tip::after {
                        font-family: Dashicons;
                        speak: none;
                        font-weight: 400;
                        text-transform: none;
                        line-height: 1;
                        -webkit-font-smoothing: antialiased;
                        text-indent: 0px;
                        position: absolute;
                        top: 0px;
                        left: 0px;
                        width: 100%;
                        height: 100%;
                        text-align: center;
                        content: "";
                        cursor: help;
                        font-variant: normal;
                        margin: 0px;
                    }
                </style>

                <h2>Midwest Logistics Settings</h2>
                <?php
                wp_nonce_field( 'Midwest-logistics-save-nonce','Midwest_logistics_settings' );
                settings_fields( 'Midwest_Logistics_plugin_Page' );
                do_settings_sections( 'Midwest_Logistics_plugin_Page' );
                
                ?>
                <h2>Looking for shipping options? Go to WooCommerce->Settings->Shipping->Midwest Logistics or click <a href="/wp-admin/admin.php?page=wc-settings&tab=shipping&section=midwest_logistics" title="Click to view shipping settings">here</a></h2>
                <?php
                submit_button();
                ?>
            </form>
            <?php
        }
        function print_errors(){
            settings_errors( 'Midwest_Logistics_validation_error' );
        }

    }
}

$ML_Settings = new ML_Settings();
add_action( 'admin_menu', array($ML_Settings,'add_admin_menu' ));
add_action( 'admin_init', array($ML_Settings,'settings_init' ) );
add_action( 'admin_notices',array($ML_Settings,'print_errors' ));