<?php
/* Does User Belong Here */
if ( ! defined( 'MIDWESTLOGISTICS_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

add_action( 'admin_menu', 'Midwest_Logistics_add_communication_admin_menu' );

function Midwest_Logistics_add_communication_admin_menu(  ) { 
    add_submenu_page('midwest-logistics-options', 'Communication Log', 'Communication Log', "manage_options", "midwest-logistics-communication-log", "Midwest_Logistics_communication_log_page" );    
}



function midwest_logistics_add_communication_log($JSONsent = "",$JSONresponse ="",$post_id ="",$post_meta_key ="",$post_meta_value = "",$response = "") {
    global $wpdb;
    $table_name = $wpdb->prefix . MIDWESTLOGISTICS_TABLE_NAME;
    $data = array(
        "dateadded" => date ('Y-m-d H:i:s'),
        "JSONsent" => $JSONsent,
        "JSONresponse" => $JSONresponse,
        "post_id" => $post_id,
        "post_meta_key" => $post_meta_key,
        "post_meta_value" => $post_meta_value,
        "response" => $response
    );

    $wpdb->insert($table_name, $data);

}

function Midwest_Logistics_communication_log_page(  ) { 
    Midwest_Logistics_communication_log_product();
    Midwest_Logistics_communication_log_order();
}

function Midwest_Logistics_communication_log_product(  ) { 
    global $wpdb;
    $table_name = $wpdb->prefix . MIDWESTLOGISTICS_TABLE_NAME;
    $postedId = "0";
    if(!empty($_POST["productSearchID"])) {
        if (!isset($_POST['Midwest_logistics_log']) || !wp_verify_nonce($_POST['Midwest_logistics_log'],'Midwest-logistics-save-nonce')) {
	        header( 'Status: 403 Forbidden' );
	        header( 'HTTP/1.1 403 Forbidden' );
	        exit();
        }
        if(sanitize_text_field($_POST["productSearchID"]) != "") {
            $postedId = sanitize_text_field($_POST["productSearchID"]);
            $postedId = preg_replace('/[^0-9]/', "", $postedId);
            if($postedId === "") {
                $postedId = "0";
            }
        }
    }
    

    $sql = "SELECT * 
            FROM $table_name 
            WHERE post_meta_key = 'product'
            AND post_id = '" . $postedId . "'
            ORDER BY dateadded desc";
    $results = $wpdb->get_results($sql, ARRAY_A  );


    ?>
    <h2>Product Communication Log</h2>
    <style>
        .productTableWrapper {
            margin:0px 0px 30px 0px;
        }
        .productTableWrapper #productTable {
            width:98%;
            padding:10px;
            border-collapse:collapse;    
            background:#fff;        
        }
        .productTableWrapper #productTable tr th {
            font-weight:bold;
            font-size:18px;
            padding:10px;
            border:1px solid #ddd;
            text-align:left;
        }
        .productTableWrapper #productTable tr td {
            border:1px solid #ddd;
            padding:10px;  
            overflow-wrap: break-word; 
            max-width: 300px;   
        }
        .productTableWrapper #productTable tr td.col1,#productTable tr th.col1 {
            width:6%;
            text-align:center;
        }
        .productTableWrapper #productTable tr td.col4,#productTable tr th.col4 {
            width:13%;
            text-align:center;
        }
    </style>
    <div class="productTableWrapper">
        <form name="productSearch" action="" method="post">
            <?php wp_nonce_field( 'Midwest-logistics-save-nonce','Midwest_logistics_log' ); ?>
            <label for="productSearchID">Product ID:</label>
            <input type="text" name="productSearchID" id="productSearchID" value="<?php echo $postedId ?>" />
            <input type="submit" value="Search" />
        </form>
        <table id="productTable">
            <tr>
                <th class="col1 sortableColumn" data-id="ID">
                    ID
                </th>
                <th class="col2 sortableColumn" data-id="SKU">
                    Information Sent
                </th>
                <th class="col3 sortableColumn" data-id="Response">
                    Response
                </th>
                <th class="col4 sortableColumn" data-id="Date">
                    Date Added
                </th>
            </tr>
            <?php
            if(count($results) > 0 ) {
                foreach($results as $result) {
                   $post_id = $result["post_id"];
                   $sentString = $result["JSONsent"];
                   $dateAdded = $result["dateadded"];
                   $APIresponse = "";
                   $SKU = "";
                   if($sentString !== "") {
                        $JSONSentString = json_decode($sentString);
                        if(!empty($JSONSentString)) {
                            $SKU = $JSONSentString-> {"sku"};
                        }
                   }
                   $response = $result["JSONresponse"];

                   ?>
                    <tr>
                        <td class="col1">
                            <?php echo $post_id ?>
                        </td>
                        <td class="col2">
                            <?php echo $sentString ?>
                        </td>
                        <td class="col3">
                            <?php echo $response ?>
                        </td>
                        <td class="col4">
                            <?php echo $dateAdded ?>
                        </td>
                    </tr>
                    <?php 
                }
            } else {
                ?>
                <tr>
                    <td colspan="100%">No Log found.</td>           
                </tr>
                <?php 
            }
        ?>
        </table>    
    </div>
    <?php
}
function Midwest_Logistics_communication_log_order(  ) { 
    global $wpdb;
    $table_name = $wpdb->prefix . MIDWESTLOGISTICS_TABLE_NAME;
    $postedId = "0";

    if(!empty($_POST["orderSearchID"])) {
        if (!isset($_POST['Midwest_logistics_log']) || !wp_verify_nonce($_POST['Midwest_logistics_log'],'Midwest-logistics-save-nonce')) {
	        header( 'Status: 403 Forbidden' );
	        header( 'HTTP/1.1 403 Forbidden' );
	        exit();
        }

        if(sanitize_text_field($_POST["orderSearchID"]) != "") {
            $postedId = sanitize_text_field($_POST["orderSearchID"]);
            $postedId = preg_replace('/[^0-9]/', "", $postedId);
            if($postedId === "") {
                $postedId = "0";
            }
        }
    }

    $sql = "SELECT * 
            FROM $table_name 
            WHERE post_meta_key = 'order'
            AND post_id = '" . $postedId . "'
            ORDER BY dateadded desc";
    $results = $wpdb->get_results($sql, ARRAY_A  );


    ?>
    <h2>Order Communication Log</h2>
    <style>
        .orderTableWrapper {

        }
        .orderTableWrapper #orderTable {
            width:98%;
            padding:10px;
            border-collapse:collapse;    
            background:#fff;        
        }
        .orderTableWrapper #orderTable tr th {
            font-weight:bold;
            font-size:18px;
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
        .orderTableWrapper #orderTable tr td.col1,#productTable tr th.col1 {
            width:8%;
            text-align:center;
        }
        .orderTableWrapper #orderTable tr td.col4,#productTable tr th.col4 {
            width:13%;
            text-align:center;
        }
    </style>
    <div class="orderTableWrapper">
        <form name="productSearch" action="" method="post">
            <?php wp_nonce_field( 'Midwest-logistics-save-nonce','Midwest_logistics_log' ); ?>
            <label for="orderSearchID">Order ID:</label>
            <input type="text" name="orderSearchID" id="orderSearchID" value="<?php echo $postedId ?>"/>
            <input type="submit" value="Search" />
        </form>
        <table id="orderTable">
            <tr>
                <th class="col1 sortableColumn" data-id="ID">
                    Order Id
                </th>
                <th class="col2 sortableColumn" data-id="SKU">
                    Information Sent
                </th>
                <th class="col3 sortableColumn" data-id="Response">
                    Information Recieved
                </th>
                <th class="col4 sortableColumn" data-id="Date">
                    Date Added
                </th>
            </tr>
            <?php
            if(count($results) > 0 ) {
                foreach($results as $result) {
                   $post_id = $result["post_id"];
                   $sentString = $result["JSONsent"];
                   $dateAdded = $result["dateadded"];
                   $APIresponse = "";
                   $SKU = "";
                   $response = $result["JSONresponse"];              

                   ?>
                    <tr>
                        <td class="col1">
                            <?php echo $post_id ?>
                        </td>
                        <td class="col2">
                            <?php echo $sentString ?>
                        </td>
                        <td class="col3">
                            <?php echo $response ?>
                        </td>
                        <td class="col4">
                            <?php echo $dateAdded ?>
                        </td>
                    </tr>
                    <?php 
                }
            } else {
                ?>
                <tr>
                    <td colspan="100%">No Log found.</td>           
                </tr>
                <?php 
            }
            ?>
        </table>
    </div>
    <?php
}
/* Remove all logs from 90 days ago */
function Midwest_Logistics_clear_log() {    
    global $wpdb;    
    
    $options = get_option( 'Midwest_Logistics_settings' );  
    $value = "90";
    if(isset($options['Midwest_Logistics_clear_log'])) {
        $value = $options['Midwest_Logistics_clear_log'];
    }
    if($value == null) {
        $value = "90";
    }
            
    $sql = "DELETE FROM ". $wpdb->prefix . "midwest_logistics_communication_log where dateadded < DATE_SUB(NOW(), INTERVAL " . $value . " DAY) ; ";    
    $results = $wpdb->get_results($sql, ARRAY_A  );
    
    $sql = "DELETE FROM ". $wpdb->prefix . "midwest_logistics_communication_log where dateadded < DATE_SUB(NOW(), INTERVAL 1 DAY) AND post_meta_key = 'product' ; ";    
    $results = $wpdb->get_results($sql, ARRAY_A  );
    
}
add_action( 'midwest_logistics_update_tracking_information', 'Midwest_Logistics_clear_log' );
