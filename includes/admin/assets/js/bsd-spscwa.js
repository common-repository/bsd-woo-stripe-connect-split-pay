var $ = jQuery;
$(document).ready(function () {

    set_select2();
    /** Bulk editor js start. */
    $(document).on("click", ".edit-item", function () {
        // Close any previously opened edit-form
        $('.edit-form', document).removeClass("open-edit-item");
        // Open the clicked edit-form
        $(this).next('.edit-form').addClass("open-edit-item");
    });

    $(document).on("click", ".edit-form-cancle-btn", function () {
        $('.edit-form', document).removeClass("open-edit-item");
    });

    $(document).on("click", ".select_account_btns .edit-form-ok-btn", function () {
        let td_parent = $(this).parents(".select_account_td_wrapper");
        let selected_account = $(td_parent).find(".sspscwsca_select option:selected").val();
        $('.edit-form', document).removeClass("open-edit-item");
        $(".selected_account_id_wrapper", td_parent).html(selected_account);
    });

    $(document).on("click", ".prod_selected_tt_btns .edit-form-ok-btn", function () {
        let td_parent = $(this).parents(".prod_selected_tt_td_wrapper");
        let selected_val = $(td_parent).find(".bsd_spscwt_type option:selected").val();
        $('.edit-form', document).removeClass("open-edit-item");
        let display_selected_val = "";
        if (selected_val == "percentage") {
            display_selected_val = "Percentage";
        } else if (selected_val == "amount") {
            display_selected_val = "Fixed Amount";
        }
        $(".selecte_tt_wrapper", td_parent).html(display_selected_val);
    });
    $(document).on("click", ".selected_tt_value_wrapper", function () {
        let connected_account_index = $(this).attr("data-connected_account_index");
        if ($("#bsd_spscwt_type_" + connected_account_index).length) {
            let selected_tt = $("#bsd_spscwt_type_" + connected_account_index + " option:selected").val();
            $(".bsd_spscwtp_input, .bsd_spscwt_amount").addClass("bsd_hidden");
            if (selected_tt == "percentage") {
                $("#bsd_split_pay_stripe_connect_woo_transfer_percentage_" + connected_account_index).removeClass("bsd_hidden");
            } else {
                $("#bsd_spscwt_amount_" + connected_account_index).removeClass("bsd_hidden");
            }
        }

    });


    $(document).on("click", ".prod_tv_btns .edit-form-ok-btn", function () {
        let td_parent = $(this).parents(".prod_tv_td_wrapper");
        let selected_val = $(td_parent).find(".edit-form input[type=number]:not(.bsd_hidden)").val();
        $('.edit-form', document).removeClass("open-edit-item");

        $(".selected_transfer_value_wrapper", td_parent).html(selected_val);
    });

    $(document).on("click", "#sync_webhooks", function () {

        var data = {
            action: "sync_webhooks",
            security: bsd_admin_plugin_vars.security
        };
        $.ajax({
            url: bsd_admin_plugin_vars.ajax_url,
            type: "post",
            data: data,
            beforeSend: function () {
                $("#sync_webhooks .bsd_loader").show();
                $("#sync_webhooks").addClass("disabled");
            },
            success: function (response) {
                if (response.success) {

                    if (response.data.error_messages.test_mode && response.data.error_messages.test_mode.length) {
                        $(".webhook_response_status_upate td .test_mode_message_wrapper").html("");
                        response.data.error_messages.test_mode.forEach(element => {
                            $(".webhook_response_status_upate td .test_mode_message_wrapper").append("❌ Test " + element + "<br/>");
                        });
                    }
                    if (response.data.error_messages.live_mode && response.data.error_messages.live_mode.length) {
                        $(".webhook_response_status_upate td .live_mode_message_wrapper").html("");
                        response.data.error_messages.live_mode.forEach(element => {
                            $(".webhook_response_status_upate td .live_mode_message_wrapper").append("❌ Live " + element + "<br/>");
                        });
                    }


                    if (response.data.successes.test_mode && response.data.successes.test_mode.length) {
                        $(".webhook_response_status_upate td .test_mode_message_wrapper").html("");
                        $(".webhook_response_status_upate td .test_mode_message_wrapper").html("✅ Test Mode Webhook Configured");
                    }
                    if (response.data.successes.live_mode && response.data.successes.live_mode.length) {
                        $(".webhook_response_status_upate td .live_mode_message_wrapper").html("");
                        $(".webhook_response_status_upate td .live_mode_message_wrapper").html("✅ Live Mode Webhook Configured");
                    }

                }

                $(".webhook_response_status_upate").removeClass("bsd_hidden");
                $("#sync_webhooks .bsd_loader").hide();
                $("#sync_webhooks").removeClass("disabled");
            }
        });

    });

    $(document).on("click", ".prod_selected_st_btns .edit-form-ok-btn", function () {
        let td_parent = $(this).parents(".prod_selected_st_td_wrapper");
        let selected_val = $(td_parent).find(".bsd_spscwt_shipping_type option:selected").val();
        $('.edit-form', document).removeClass("open-edit-item");
        let display_selected_val = "";
        if (selected_val == "percentage") {
            display_selected_val = "Percentage";
        } else if (selected_val == "amount") {
            display_selected_val = "Fixed Amount";
        }
        $(".selected_shipping_type_wrapper", td_parent).html(display_selected_val);
    });

    $(document).on("click", ".prod_stv_btns .edit-form-ok-btn", function () {
        let td_parent = $(this).parents(".prod_stv_td_wrapper");
        let selected_val = $(td_parent).find(".edit-form input[type=number]:not(.bsd_hidden)").val();
        $('.edit-form', document).removeClass("open-edit-item");

        $(".selected_shipping_digit_wrapper", td_parent).html(selected_val);
    });

    $(document).on("click", ".selected_stv_value_wrapper", function () {
        let connected_account_index = $(this).attr("data-connected_account_index");

        if ($("#bsd_spscwt_shipping_type_" + connected_account_index).length) {
            let selected_tt = $("#bsd_spscwt_shipping_type_" + connected_account_index + " option:selected", document).val();
            $(".bsd_spscwtp_shipping_input, .bsd_spscwt_shipping_amount ").addClass("bsd_hidden");
            if (selected_tt == "percentage") {
                $("#bsd_prod_shipping_percentage_" + connected_account_index).removeClass("bsd_hidden");
            } else {
                $("#bsd_prod_shipping_amount_" + connected_account_index).removeClass("bsd_hidden");
            }
        }
    });

    $(document).on("submit", "#bulk-editor-save", function (e) {

        e.preventDefault(); // avoid to execute the actual submit of the form.

        var form = $("form#bulk-editor-save")[0];

        var formData = new FormData(form);

        formData.append('action', 'save_product_bulk_edit');
        formData.append('security', bsd_admin_plugin_vars.security);

        $.ajax({
            type: "POST",
            url: ajaxurl,
            processData: false,
            contentType: false,
            data: formData, // serializes the form's elements.
            beforeSend: function () {
                $(".result-loader").css("display", "flex");
            },
            success: function (data) {
                $(".result-loader").hide();
            }
        });

    });

    /** Bulk editor js end. */

    window.current_page = 2;
    $("#sync_accounts_btn").on("click", function () {
        fetch_account_ajax();
        $("#sync_btn_text").hide();
        $("#sync_accounts_btn .bsd_loader").show();
        $("#sync_accounts_btn").addClass("disabled");

    });

    $("#csync_accounts_btn").on("click", function () {
        clear_accounts_ajax();
        $("#csync_btn_text").hide();
        $("#csync_accounts_btn .bsd_loader").show();
        $("#csync_accounts_btn").addClass("disabled");

    });



    $("#_bsd_spscwt_product_type").on("change", function () {
        var selected_type = $(this).val();
        if (selected_type == "amount") {
            $(".bsd_scsptp").hide();
            $(".bsd_spscwt_product_amount").show();

        } else {
            $(".bsd_spscwt_product_amount").hide();
            $(".bsd_scsptp").show();
        }
    });

    $(document).on("change", "._bsd_spscwt_product_type", function () {
        var selected_type = $(this).val();
        if (selected_type == "amount") {
            $(this).parent().siblings(".bsd_scsptp").hide();
            $(this).parent().siblings(".bsd_spscwt_product_amount").show();

        } else {
            $(this).parent().siblings(".bsd_spscwt_product_amount").hide();
            $(this).parent().siblings(".bsd_scsptp").show();
        }
    });

    $("#vendor_onboading").on("change", function () {
        if ($(this).is(":checked")) {
            $(".show_td_cb_row").removeClass("bsd_hidden");
        } else {
            $(".show_td_cb_row").addClass("bsd_hidden");
            $(".show_td_rows").addClass("bsd_hidden");
            $("#enable_title_description").prop("checked", false);
        }
    });

    $("#enable_title_description").on("change", function () {
        if ($(this).is(":checked")) {
            $(".show_td_rows").removeClass("bsd_hidden");
        } else {
            $(".show_td_rows").addClass("bsd_hidden");
        }
    });



    /* validation for shipping percentage */
    $("#bsd-split-pay-stripe-connect-woo-settings form").on("submit", function () {

        var field_val = 0;

        $(".bsd_shipping_type").each(function (index, value) {
            if ($(this).val() == 'percentage') {
                field_val += Number($("#bsd_global_shipping_percentage_" + index).val());
            }
        });


        if (field_val > 100) {
            alert("Global Shipping Transfer:The addition of all Percentage value shold not be greater than 100.");
            return false;
        } else {
            return true;
        }

    });

    /* validation for shipping percentage in product */
    $(".post-type-product form#post").on("submit", function () {

        var product_type = $("#product-type").val();
        console.log(product_type);
        if (product_type == "variable") { return true; }
        if (product_type == "grouped") { return true; }
        if (product_type == "external") { return true; }
        var pro_transfer_vals = 0;
        $(".bsd_spscwt_type").each(function (index, value) {

            if ($(value).val() == 'percentage') {
                percentage_field_id = $(value).attr("id");
                var lastword = percentage_field_id.split("_").pop();

                pro_transfer_vals += Number($("#bsd_split_pay_stripe_connect_woo_transfer_percentage_" + lastword).val());
            }
        });

        if (pro_transfer_vals > 100) {
            alert("When splitting payments between multiple connected accounts, your transfer percentages cannot exceed 100% in total.");
            return false;
        }



    });




    /* Bulk editor */
    if ($(".pwbe-filter-select").length) {
        $('.pwbe-filter-select').select2();
        $(".product-fields").hide();
    }

    $(document).on("change", ".filter-name", function () {
        var filter_type = $(this).val();
        var data_id = $(this).attr("data-id");
        if (filter_type == 'categories') {
            $(".category-fields-" + data_id).show();
            $(".product-fields-" + data_id).hide();
        }
        if (filter_type == 'post_title' || filter_type == 'sku' || filter_type == 'pa') {
            $(".product-fields-" + data_id).show();
            $(".category-fields-" + data_id).hide();
        }
    });


    $(document).on("click", ".serach-btn", function () {
        fetch_bulk_editor_search_result("search");
    });
    $(document).on("click", ".show-products-btn", function () {
        fetch_bulk_editor_search_result("show-all");
    });

    $(document).on("click", ".add_more", function () {
        var count_fil = $(this).attr("data-id");
        $.ajax({
            url: ajaxurl,
            type: 'post',
            dataType: 'json',
            data: {
                action: 'fetch_more_filter',
                count: count_fil
            },
            success: function (response) {
                if (response.data) {
                    var count2 = parseInt(count_fil) + 1;
                    $(".add_more").attr("data-id", count2 + 1);
                    $(".find-product-list__row-main").append(response.data);
                    $('.pwbe-filter-select').select2();
                    $("body .product-fields-" + count2).hide();
                }
            }
        });

    });

    $(document).on("click", ".remove_more", function () {
        var count = $(this).data("id");
        $(".more-" + count).remove();
    });

    /* Pagination on bulk editor */
    $(document).on("click", ".pagination a.page-numbers", function (e) {
        e.preventDefault();
        var page_num = $(this).text();
        if (!$.isNumeric($(this).text())) {
            if ($(this).hasClass("next")) {
                var page_num = parseInt($(".pagination .current").html()) + 1;
            }
            if ($(this).hasClass("prev")) {
                var page_num = parseInt($(".pagination .current").html()) - 1;
            }
        }

        fetch_bulk_editor_search_result("search", page_num);
    });

});

function fetch_bulk_editor_search_result(search_type = "search", paged = 1) {

    var filter_types = [];
    var filter_contain = [];
    var filter_value = [];
    var error = 0;
    $("p.error").remove();
    if (search_type == "search") {
        $(".filter-name").each(function () {
            var data_id = $(this).attr("data-id");
            filter_types.push($(this).val());
            if ($(this).val() == 'categories') {
                filter_contain.push($(".filter-cat-type[data-id=" + data_id + "]").val());
                if ($.trim($(".filter-categories[data-id=" + data_id + "]").val()) == '') {
                    $(".filter-categories[data-id=" + data_id + "]").closest(".category-fields").append("<p class='error'>This field is required!</p>");
                    error = 1;
                }
                filter_value.push($(".filter-categories[data-id=" + data_id + "]").val());
            } else {
                filter_contain.push($(".filter-product-type[data-id=" + data_id + "]").val());
                if ($.trim($(".pro-filter-input[data-id=" + data_id + "]").val()) == '') {
                    $(".pro-filter-input[data-id=" + data_id + "]").closest(".product-fields").append("<p class='error'>This field is required!</p>");
                    error = 1;
                }
                filter_value.push($(".pro-filter-input[data-id=" + data_id + "]").val());
            }
        });
    }

    var page = paged;
    if (error == 0) {
        $.ajax({
            url: ajaxurl,
            type: 'post',
            dataType: 'json',
            beforeSend: function () {
                $(".filter-loader").css("display", "flex");
            },
            data: {
                action: 'fetch_search_result',
                filter_type: filter_types,
                filter_contain: filter_contain,
                filter_value: filter_value,
                paged: page,
                search_type: search_type,
                security: bsd_admin_plugin_vars.security
            },
            success: function (response) {
                if (response.data) {
                    $(".filter-loader").hide();
                    $(".product-result").html(response.data);
                }
            }
        });
    }
}

function set_select2() {
    if ($(".sspscwsca_select").length) {
        $('.sspscwsca_select').select2({
            width: '100%',
            placeholder: "Select",
            allowClear: true,
            templateResult: formatOptions,
            language: {
                noResults: function () {
                    return 'No Result Found! <button class="no-results-btn">Click to add it</button>';
                },
            },
            escapeMarkup: function (markup) {
                return markup;
            },
            // closeOnSelect: false,
            templateSelection: function (data) {
                if (data.id !== "") {
                    return data.text;
                }
                return data.text;
            }

        }).on('select2-open', function () {

            // however much room you determine you need to prevent jumping
            var requireHeight = 600;
            var viewportBottom = $(window).scrollTop() + $(window).height();

            // figure out if we need to make changes
            if (viewportBottom < requireHeight) {
                // determine how much padding we should add (via marginBottom)
                var marginBottom = requireHeight - viewportBottom;

                // adding padding so we can scroll down
                $(".aLwrElmntOrCntntWrppr").css("marginBottom", marginBottom + "px");

                // animate to just above the select2, now with plenty of room below
                $('html, body').animate({
                    scrollTop: $(".sspscwsca_select").offset().top - 10
                }, 1000);
            }
        });

        $(document).on("click", ".no-results-btn", function (e) {
            e.stopImmediatePropagation();
            var no_results_btn_obj = $(this);
            // select2-results__options
            var get_select2_id = $(no_results_btn_obj).parents(".select2-results__options").attr("id");
            get_select2_id = get_select2_id.replace("select2-", "");
            get_select2_id = get_select2_id.replace("-results", "");

            var data = {
                action: "add_custom_account",
                account_id: $('.select2-search__field')[0].value,
                security: bsd_admin_plugin_vars.security
            };
            $.ajax({
                url: bsd_admin_plugin_vars.ajax_url,
                type: "post",
                data: data,
                success: function (response) { }
            });
            var option_html = '<option value="' + $('.select2-search__field')[0].value + '">' + $('.select2-search__field')[0].value + '</option>';
            $(".sspscwsca_select").prepend(option_html);
            $(".select2.select2-container--open").prev(".sspscwsca_select").val($('.select2-search__field')[0].value).change();
        });


    }
}



function formatOptions(repo) {


    var $container = $(
        "<div class='select2-result-repository clearfix'>" +
        "<div class='select2-result-repository__meta'>" +
        "<div class='select2-result-repository__title'></div>" +
        "<div class='select2-result-repository__description'></div>" +
        "</div>" +
        "</div>"
    );

    $container.find(".select2-result-repository__title").text(repo.text);
    $container.find(".select2-result-repository__description").text(repo.id);

    return $container;
}

function fetch_account_ajax() {
    var data = {
        action: "fetch_accounts",
        page: window.current_page,
        security: bsd_admin_plugin_vars.security
    };
    $.ajax({
        url: bsd_admin_plugin_vars.ajax_url,
        type: "post",
        data: data,
        success: function (response) {
            if (!response.is_finished) {
                window.current_page = response.current_page;
                if (response.new_account_ids.length > 0) {
                    var option_html = "";
                    var account_name = "";
                    response.new_account_ids.forEach(function (item, index) {
                        if (item.account_name != null) {
                            account_name = item.account_name;
                        } else {
                            account_name = item.account_id;
                        }
                        option_html += '<option value="' + item.account_id + '">' + account_name + '</option>';
                    });
                    $(".sspscwsca_select").append(option_html);
                }
                fetch_account_ajax();
            } else {
                if ("new_account_ids" in response && response.new_account_ids.length > 0) {
                    var option_html = "";
                    var account_name = "";

                    response.new_account_ids.forEach(function (item, index) {
                        if (item.account_name != null) {
                            account_name = item.account_name;
                        } else {
                            account_name = item.account_id;
                        }
                        option_html += '<option value="' + item.account_id + '">' + account_name + '</option>';
                    });
                    $(".sspscwsca_select").append(option_html);


                }
                $("#sync_btn_text").show();
                $("#sync_accounts_btn .bsd_loader").hide();
                $("#sync_accounts_btn").removeClass("disabled");
                alert(response.message);
            }

        }
    });
}

function clear_accounts_ajax() {
    var data = {
        action: "clear_accounts",
        page: window.current_page,
        security: bsd_admin_plugin_vars.security
    };
    $.ajax({
        url: bsd_admin_plugin_vars.ajax_url,
        type: "post",
        data: data,
        success: function (response) {
            $("#csync_btn_text").show();
            $("#csync_accounts_btn .bsd_loader").hide();
            $("#csync_accounts_btn").removeClass("disabled");

            window.location.reload(true);

        }
    });
}