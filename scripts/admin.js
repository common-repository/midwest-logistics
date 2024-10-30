jQuery(function () {
    midwest_logistics_product_sku_text_field_wrapper = jQuery("._midwest_logistics_product_sku_text_field_wrapper")[0];
    midwest_logistics_product_select = jQuery("#_midwest_logistics_product_select");
    midwest_logistics_product_sku_text_field = jQuery("#_midwest_logistics_product_sku_text_field");
    if (midwest_logistics_product_select.length > 0 && midwest_logistics_product_sku_text_field.length > 0) {
        midwest_logistics_product_select.on("change", function () {
            if (midwest_logistics_product_sku_text_field.length > 0) {
                if (midwest_logistics_product_select.val() === "N") {
                    jQuery(midwest_logistics_product_sku_text_field_wrapper).css('display', 'none');
                }
                if (midwest_logistics_product_select.val() === "Y") {
                    jQuery(midwest_logistics_product_sku_text_field_wrapper).css('display', 'block');
                }
            }
        });
        if (midwest_logistics_product_sku_text_field.length > 0) {
            if (midwest_logistics_product_select.val() === "N") {
                jQuery(midwest_logistics_product_sku_text_field_wrapper).css('display', 'none');
            }
            if (midwest_logistics_product_select.val() === "Y") {
                jQuery(midwest_logistics_product_sku_text_field_wrapper).css('display', 'block');
            }
        }

    }

    //communication sort tables
   
    //if (jQuery("#productTable").length > 0) {
    //    jQuery(".sortableColumn").each(function (index, item) {
    //        jQuery(item).click(function () {
    //            columnId = jQuery(this).attr("data-id");
    //            if (columnId) {
    //                newURL = replaceUrlParam(window.location.href, "sort", columnId);
    //                window.location = newURL
    //            }

    //        });
            
    //    });
            
    //}

});

function replaceUrlParam(url, paramName, paramValue) {
    if (paramValue == null)
        paramValue = '';
    var pattern = new RegExp('\\b(' + paramName + '=).*?(&|$)')
    if (url.search(pattern) >= 0) {
        return url.replace(pattern, '$1' + paramValue + '$2');
    }
    return url + (url.indexOf('?') > 0 ? '&' : '?') + paramName + '=' + paramValue
}
