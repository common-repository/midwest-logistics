<?php
class ML_Settings_Orders {
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
    }
    function get_orders() {
        global $wpdb;
        
        $options = get_option( 'Midwest_Logistics_settings' );  
        $value = "wc-processing";
        if(isset($options['Midwest_Logistics_select_field_2'])) {
            $value = $options['Midwest_Logistics_select_field_2'];
        }
        if($value == null) {
            $value = "wc-processing";
        }
            
            
        $results = false;
        if($wpdb != null) {
            
            $sql = "SELECT p.ID,p.post_title
                    ,IFNull((
                        SELECT mlCL.JSONresponse 
                        FROM ". $wpdb->prefix . "midwest_logistics_communication_log mlCL
                        WHERE mlCL.post_id = p.ID
                        ORDER BY dateadded DESC
                        LIMIT 1

                    ),'') as JSONresponse
                    FROM `". $wpdb->prefix . "posts` p
                        JOIN ". $wpdb->prefix . "postmeta pM on pM.post_id = p.ID
                    WHERE post_type = 'shop_order'
                    AND post_status = '" . $value . "'
                    AND pM.meta_key = '_midwest_logistics_CRM_order_status'
                    AND pM.meta_value = '3' ";
            $results = $wpdb->get_results($sql, ARRAY_A  );
        }
        return $results;
    }
    function settings_page() {
        $this->display_orders();
    }
    function display_orders(  ) { 
        $results = $this->get_orders();
        ?>
        <h2>Orders With Errors</h2>
        <style>
            .orderTableWrapper {
                width:98%;
            }
            .orderTableWrapper .notice  {
                margin:0px;
            }
            .orderTableWrapper #orderTable {
                width:100%;
                padding:10px;
                border-collapse:collapse;    
                background:#fff;        
            }
            .orderTableWrapper .bulk-send {
                float:right;
                margin:20px 0px;
            }
            .orderTableWrapper #orderTable tr th {
                font-weight:bold;
                padding:10px;
                border:1px solid #ddd;
                text-align:left;
            }
            .orderTableWrapper #orderTable tr td {
                border:1px solid #ddd;
                padding:10px;     
                overflow-wrap: break-word;    
                max-width: 300px;
            }
            .orderTableWrapper #orderTable tr td .message > div {
                margin:5px 0px;
            }
            .orderTableWrapper #orderTable tr td.col1,.orderTableWrapper #orderTable tr th.col1 {
                width:10px;
                text-align:center;
            }
            .orderTableWrapper #orderTable tr td.col2,.orderTableWrapper #orderTable tr th.col2 {
                width:5%;
                text-align:center;
            }
            .orderTableWrapper #orderTable tr td.col4,.orderTableWrapper #orderTable tr th.col4 {
                width:12%;
                text-align:center;
                min-width: 200px;
            }
            @media (max-width:780px) {
                .orderTableWrapper #orderTable tr td.col4,.orderTableWrapper #orderTable tr th.col4  {
                    width: 130px;
                    min-width: 0px;
                } 
                .orderTableWrapper #orderTable tr td:last-child a {
                    
                    width: 100%;
                    margin: 5px 0px;
                    
                } 
            }
            
        </style>
        <div class="orderTableWrapper">
            <div class="notice notice-info">
                <p><?php  _e("Below are orders that have recieved error when trying to add the order to Midwest Logistics system. ","MidewestLogistics")?><br />
                <?php  _e("Only orders that are set for Midwest Logistics to process are shown.","MidewestLogistics")?><br />
                <?php  _e("To change the status that Midwest Logistics process orders for please visit the settings page.","MidewestLogistics")?></p>
            </div>
            <div class="bulk-send button button-primary button-large" onClick="midwestSendBulk()">Bulk Send</div>
            <table id="orderTable">
                <tr>
                    <th class="col1">
                        <input type="checkbox" id="select_all" onchange="midwestSelectAll(this.checked);" />
                        <label for="select_all"></label>
                    </th>
                    <th class="col2">
                        Order Id
                    </th>
                    <th class="col3">
                        Error
                    </th>
                    <th class="col4">
                        Options
                    </th>
                </tr>
                <?php
                $mainMessage = "There was a problem adding this order to the Midwest Logistics system. Please contact Midwest Logistics";
                if(count($results) > 0 ) {
                    foreach($results as $result) {
                       $post_id = $result["ID"];
                       $message = $result["JSONresponse"]; 
                       
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


                       ?>
                        <tr>
                            <td class="col1">
                                <input type="checkbox" id="order_<?php echo $post_id; ?>" name="order_<?php echo $post_id; ?>" value="<?php echo $post_id; ?>" />
                                <label for="order_<?php echo $post_id; ?>"></label>
                            </td>
                            <td class="col2">
                                <?php echo $post_id ?>
                            </td>
                            <td class="col3">
                                <?php echo $mainMessage ?>
                                <span class="message"></span>
                            </td>
                            <td class="col4">
                                <a href="<?php echo get_admin_url() ."post.php?post=" . $post_id . "&action=edit"; ?>" target="_blank" class="button button-secondary button-large" title="Click to view order <?php echo $post_id ?>">View</a>
                                <a href="javascript:void(0)" class="button button-secondary button-large" onClick="resendMidwestOrder(<?php echo $post_id?>,this.parentNode.parentNode)" title="Click to send order <?php echo $post_id ?>">Re-Send Order</a>
                            </td>
                        </tr>
                        <?php 
                    }
                } else {
                    ?>
                    <tr>
                        <td colspan="100%">No Orders found.</td>           
                    </tr>
                    <?php 
                }
                
                ?>
            </table>
        </div>
        <script>
        function resendMidwestOrder(order,row) {
            messageElList = row.getElementsByClassName("message");
            rowMessage = null;
            if(messageElList.length > 0) {
                rowMessage = messageElList[0];
                rowMessage.innerHTML = "";
                div = document.createElement("div");    
                div.className = "update-message notice inline notice-warning notice-alt updating-message";
                div.innerHTML = "<p> Updating</p>"

                rowMessage.appendChild(div);
            }

            jQuery.ajax({
                type: "POST",
                url: ajaxurl,
                data: { 
                    action: 'midwest_logistics_process_shop_order' ,
                    order: order 
                }
            }).done(function( msg ) {
                rowIndex = row.rowIndex;

                JsonOB = JSON.parse(msg);
                code = JsonOB.code;
                message = JsonOB.message;

                if(messageElList.length > 0) {
                    rowMessage = messageElList[0];
                    rowMessage.innerHTML = "";
                }
            
                div = document.createElement("div");    
                div.className = "update-message notice inline notice-warning notice-alt ";

                if(code === 500) {
                    div.className = div.className + " notice-error ";
                } else {                     
                    div.className = div.className + " updated-message notice-success ";
                }

                if(rowMessage != null) {
                    div.innerHTML = "<p> " + message + "</p>";
                    rowMessage.innerHTML = "";
                    rowMessage.appendChild(div);
                } else {
                    alert(order + ":" + message);
                }
            });
        }
        function midwestSelectAll(checked) {
            orderTable = document.getElementById("orderTable");
            if(orderTable !== null) {
                inputs = orderTable.getElementsByTagName("input");
                for(i = 0; i < inputs.length; i++) {
                    inputs[i].checked = checked;
                }
            }
        }
        function midwestSendBulk() {
            hasChecked = false;
            orderTable = document.getElementById("orderTable");
            if(orderTable !== null) {
                inputs = orderTable.getElementsByTagName("input");
                for(i = 0; i < inputs.length; i++) {
                    if(inputs[i].name.indexOf("order_") !== -1) {
                        if(inputs[i].checked === true) {                       
                            hasChecked = true;
                            resendMidwestOrder(inputs[i].value,inputs[i].parentNode.parentNode);
                        }
                    }

                }
            }
            if(hasChecked === false) {
                alert("Please select an order");
            }
        }
        
        </script>
        <?php
    }
}
add_action( 'admin_menu', 'Midwest_Logistics_add_order_error_page' );
function Midwest_Logistics_add_order_error_page(  ) { 
    $settings_order_errrors = new ML_Settings_Orders();
    $orders = $settings_order_errrors->get_orders();
    
    
    $menuText = 'Orders With Errors ';
    if(count($orders) > 0) {
        $menuText .= '<span class="update-plugins"><span class="plugin-count">' . count($orders) . '</span></span>';
    }
            
       
    add_submenu_page('midwest-logistics-options', 'Orders With Errors', $menuText , "manage_options", "midwest-logistics-order_errors", array($settings_order_errrors,"settings_page") );
}


