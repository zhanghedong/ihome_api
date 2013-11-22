<?php
class JSON_API_Cart_Controller
{
    /**
     * 获取购物车列表内容
     * @param bool $query
     * @param bool $wp_posts
     * @return array
     */
    public function get_cart($query = false, $wp_posts = false)
    {
        global $woocommerce;
        $carts = $woocommerce->cart->get_cart();
        $ret = array();
        if (sizeof($carts) > 0) {
            foreach ($carts as $cart_item_key => $values) {
                $_product = $values['data'];
                if ($_product->exists() && $values['quantity'] > 0) {
                    array_push($ret, array(
                        "key" => $cart_item_key,
                        "product_id" => $values['product_id'],
                        "variation_id" => $values['variation_id'],
                        "variation" => $values['variation'],
                        "quantity" => $values['quantity'],
                        "data" => $_product
                    ));
                }
            }
        }
        return array(data => $ret);
    }


    /**
     * @return array
     */
    //已实现 simple product
    // Variable product handling   与 Grouped Products 未实现 参考 woocommerce-functions.php  woocommerce_add_to_cart_action 实现
    public function add_to_cart()
    {
        global $json_api, $woocommerce;
        $product_id = $json_api->query->product_id;
        $quantity = $json_api->query->quantity;
        $product_id = apply_filters('woocommerce_add_to_cart_product_id', absint($product_id));
        $was_added_to_cart = false;
        $added_to_cart = array();
        // $adding_to_cart      = get_product( $product_id );
        $quantity = empty($quantity) ? 1 : apply_filters('woocommerce_stock_amount', $quantity);
        // Add to cart validation
        $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity);

        if ($passed_validation) {
            // Add the product to the cart
            if ($woocommerce->cart->add_to_cart($product_id, $quantity)) {
                woocommerce_add_to_cart_message($product_id);
                $was_added_to_cart = true;
                $added_to_cart[] = $product_id;
            }
        }
        return array(
            data => array(
                addedToCart => $was_added_to_cart
            )
        );
    }


    /**
     * 从购物车中移除
     */
    public function remove_item()
    {
        global $json_api, $woocommerce;

        $key = $json_api->query->key;
        // Remove from cart
        if (isset($key) && $key) {
            $woocommerce->cart->set_quantity($key, 0);
            // $woocommerce->add_message( __( 'Cart updated.', 'woocommerce' ) );
            // $referer = ( wp_get_referer() ) ? wp_get_referer() : $woocommerce->cart->get_cart_url();
            return array(
                data => array(
                    removed => true
                )
            );

        }
    }


    /**
     * 更新购物车
     * 参考：woocommerce_update_cart_action
     * 更新购物车或者下定单
     */
    public function update_cart()
    {
        global $json_api, $woocommerce;
        $cart_totals = $json_api->query->cart;
        $proceed = $json_api->query->proceed;
        if (sizeof($woocommerce->cart->get_cart()) > 0) {
            foreach ($woocommerce->cart->get_cart() as $cart_item_key => $values) {
                $_product = $values['data'];

                // Skip product if no updated quantity was posted
                if (!isset($cart_totals[$cart_item_key]['qty'])) {
                    continue;
                }
                // Sanitize
                $quantity = apply_filters('woocommerce_stock_amount_cart_item', apply_filters('woocommerce_stock_amount', preg_replace("/[^0-9\.]/", "", $cart_totals[$cart_item_key]['qty'])), $cart_item_key);

                if ("" === $quantity || $quantity == $values['quantity']) {
                    continue;
                }

                // Update cart validation
                $passed_validation = apply_filters('woocommerce_update_cart_validation', true, $cart_item_key, $values, $quantity);

                // is_sold_individually
                if ($_product->is_sold_individually() && $quantity > 1) {
                    $woocommerce->add_error(sprintf(__('You can only have 1 %s in your cart.', 'woocommerce'), $_product->get_title()));
                    $passed_validation = false;
                }

                if ($passed_validation) {
                    //  var_dump($cart_item_key.'=================='.$quantity);
                    $woocommerce->cart->set_quantity($cart_item_key, $quantity, false);
                }
            }
            $woocommerce->cart->calculate_totals();
        }
        //跳转到下定单页面
//        echo $_POST['proceed'].'aaaaaa';
        if ($proceed) {
//            wp_safe_redirect( $woocommerce->cart->get_checkout_url() ); //    woocommerce-template.php woocommerce_form_field($key, $args, $value = null)
//            exit;

            ///配送地址
            $checkout = $woocommerce->checkout();
//            do_action('woocommerce_before_checkout_billing_form', $checkout);
//            $billingFields = array();
//            $field = array();
//            foreach ($checkout->checkout_fields['billing'] as $key => $field) : //遍历地址字段内容
//                $value = $checkout->get_value($key);
//                $field = array('key' => $key, 'value' => (isset($value) ? $value : ''));
//                array_push($billingFields, $field);
//            endforeach;
//            do_action('woocommerce_after_checkout_billing_form', $checkout);

            //备注信息 //form-shipping
            $shippingFields = array();
            $field = array();
            do_action('woocommerce_before_checkout_shipping_form', $checkout);
            foreach ($checkout->checkout_fields['shipping'] as $key => $field) :
                $value = $checkout->get_value($key);
                $field = array('key' => $key, 'value' => (isset($value) ? $value : ''));
                array_push($shippingFields, $field);
            endforeach;


            //定单备注信息
            $orderFields = array();
            do_action('woocommerce_before_order_notes', $checkout);
            foreach ($checkout->checkout_fields['order'] as $key => $field) :
                $value = $checkout->get_value($key);
                $field = array('key' => $key, 'value' => (isset($value) ? $value : ''));
                array_push($orderFields, $field);
            endforeach;

            do_action('woocommerce_after_order_notes', $checkout);

            do_action('woocommerce_after_checkout_shipping_form', $checkout);

            //定单详细信息 从review-order.php中获取字段信息
            $orderTotal = $woocommerce->cart->get_cart_subtotal();
            if ($woocommerce->cart->get_discounts_before_tax()) :
//                $discount = $woocommerce->cart->get_discounts_before_tax();
            endif;
//            if ( $woocommerce->cart->needs_shipping() && $woocommerce->cart->show_shipping() ) :
//
//            endif;



            do_action('woocommerce_review_order_before_cart_contents');
            $cartFields = array();
            $cartField = array();
            if (sizeof($woocommerce->cart->get_cart()) > 0) :
                foreach ($woocommerce->cart->get_cart() as $cart_item_key => $values) :
                    $_product = $values['data'];
                    if ($_product->exists() && $values['quantity'] > 0) :
//                        echo '
//								<tr class="' . esc_attr( apply_filters('woocommerce_checkout_table_item_class', 'checkout_table_item', $values, $cart_item_key ) ) . '">
//									<td class="product-name">' .
//                            apply_filters( 'woocommerce_checkout_product_title', $_product->get_title(), $_product ) . ' ' .
//                            apply_filters( 'woocommerce_checkout_item_quantity', '<strong class="product-quantity">&times; ' . $values['quantity'] . '</strong>', $values, $cart_item_key ) .
//                            $woocommerce->cart->get_item_data( $values ) .
//                            '</td>
//                            <td class="product-total">' . apply_filters( 'woocommerce_checkout_item_subtotal', $woocommerce->cart->get_product_subtotal( $_product, $values['quantity'] ), $values, $cart_item_key ) . '</td>
//								</tr>';
                        $cartField = array('product_name' => apply_filters('woocommerce_checkout_product_title', $_product->get_title(), $_product), 'product_quantity' => apply_filters('woocommerce_checkout_item_quantity', $values['quantity'], $values, $cart_item_key), 'product_total' => apply_filters('woocommerce_checkout_item_subtotal', $woocommerce->cart->get_product_subtotal($_product, $values['quantity']), $values, $cart_item_key));
                        array_push($cartFields, $cartField);
                    endif;
                endforeach;
            endif;
            $orderDetailFields = array('orderTotal' => $orderTotal,'cartFields'=>$cartFields);
//            array_push($orderDetailFields, $cartFields);

            do_action('woocommerce_review_order_after_cart_contents');

            $available_gateways = $woocommerce->payment_gateways->get_available_payment_gateways();
            $paymentMethod = array();
            if ( ! empty( $available_gateways ) ) {

                // Chosen Method
                if ( isset( $woocommerce->session->chosen_payment_method ) && isset( $available_gateways[ $woocommerce->session->chosen_payment_method ] ) ) {
                    $available_gateways[ $woocommerce->session->chosen_payment_method ]->set_current();
                } elseif ( isset( $available_gateways[ get_option( 'woocommerce_default_gateway' ) ] ) ) {
                    $available_gateways[ get_option( 'woocommerce_default_gateway' ) ]->set_current();
                } else {
                    current( $available_gateways )->set_current();
                }
                foreach ( $available_gateways as $gateway ) {

                    $payment  = array("gatewayId"=>$gateway->id,"chosen"=>$gateway->chosen,"title"=> $gateway->get_title(),"icon"=> $gateway->get_icon(),"description"=>$gateway->get_description());
                    array_push($paymentMethod,$payment);
                }

            }
            return array('data' => array('msg' => '购物车更新成功', 'shippingFields' => $shippingFields, 'orderFields' => $orderFields, 'orderDetailFields' => $orderDetailFields,'payMethod'=>$paymentMethod));

        } else {
            return array('data' => array('msg' => '购物车更新成功'));
        }
    }
}
?>






