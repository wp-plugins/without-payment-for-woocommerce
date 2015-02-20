<?php
/*
  Plugin Name: Without payment woocommerce
  Plugin URI: http://www.zixn.ru/plagin-payment-woocommerce.html
  Description: Платёжный шлюз woocommeerce, без оплаты и обязательств. Оплата товара только после звонка менеджера магазина.
  Version: 1.2
  Author: Djon
  Author URI: http://zixn.ru
 */

/*  Copyright 2015  Djon  (email: izm@zixn.ru)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 * 
 */



add_action('plugins_loaded', 'init_without_shluz'); //Подключаем класс с шлюзом

/**
 * Подключиение класса с платёжной системой
 */
function init_without_shluz() {


    class WC_WITHOUTGATEWAY extends WC_Payment_Gateway {

        public function __construct() {

            $plugin_dir = plugin_dir_url(__FILE__);

            global $woocommerce;

            $this->id = 'WithOut';
            $this->icon = apply_filters('woocommerce_without_icon', '' . $plugin_dir . 'without.png');
            $this->has_fields = false;
// Загрузка настроек
            $this->init_form_fields();
            $this->init_settings(); //Получение настроек из woocommerce
// Define user set variables
            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->instructions = $this->settings['instructions'];
//$this->redirect_page_id = $this->settings['redirect_page_id'];
// Logs
            if ($this->debug == 'yes') {
                $this->log = $woocommerce->logger();
            }

// Actions
//// Payment listener/API hook
//            add_action('woocommerce_api_wc_' . $this->id, array($this, 'check_ipn_response'));
// 
//Подтверждалка заказа
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page')); // Подтверждение заказа
// Сохранение настроек
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        /**
         * Внешний вид страницы Платёжного шлюза
         * */
        public function admin_options() {
            ?>
            <h3><?php _e('WithOut', 'woocommerce'); ?></h3>
            <p><?php _e('Настройка приема электронных платежей через "Без оплаты".', 'woocommerce'); ?></p>
            <table class="form-table">

                <?php
                // Генерация HTML настроек на основе заданных параметров
                $this->generate_settings_html();
                ?>
            </table><!--/.form-table-->

            <?php
        }

// End admin_options()

        /**
         * Поля для HTML опций шлюза
         */
        function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Включить/Выключить', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Включен', 'woocommerce'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Название', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Это название, которое пользователь видит во время проверки.', 'woocommerce'),
                    'default' => __('Without', 'woocommerce')
                ),
                'without_succes' => array(
                    'title' => __('Страница перенаправления', 'woocommerce'),
                    'type' => 'select',
                    'options' => $this->get_pages('Выберите страницу...'),
                    'description' => "На эту страницу покупатель попадёт после подтверждения заказа, вашу страницу можно оформить по вашему вкусу"
                ),
                'without_сonfirm' => array(
                    'title' => __('Отключить подтверждение', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Отключить страницу подтверждения заказа. В случае если галка установлена - после выбора оплаты по данному способу, покупатель сразу будет попадать на страницу выбранную вами в опции "Страница перенаправления"', 'woocommerce'),
                    'default' => 'no'
                ),
                'debug' => array(
                    'title' => __('Режим логирования', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Включить логирование (<code>woocommerce/logs/without.txt</code>)', 'woocommerce'),
                    'default' => 'no'
                ),
                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('Описанием метода оплаты которое клиент будет видеть на вашем сайте.', 'woocommerce'),
                    'default' => 'Без оплаты.'
                )
            );
        }

        /**
         * There are no payment fields for sprypay, but we want to show the description if set.
         * */
//        function payment_fields() {
//            if ($this->description) {
//                echo wpautop(wptexturize($this->description));
//            }
//        }

        /**
         * Генерация кнопок на странице подтверждения заказа
         * */
        public function generate_form($order_id) {
            global $woocommerce;

            $order = new WC_Order($order_id);

            if ($this->testmode == 'yes') {
                $action_adr = $this->testurl;
            } else {
//                $action_adr = get_permalink($this->settings['without_succes']);  //Получаем опцию из базы woo
                $action_adr = $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
            }
            $payu_args = array(
                'amount' => $order->order_total,
                'productinfo' => $productinfo,
                'firstname' => $order->billing_first_name,
                'lastname' => $order->billing_last_name,
                'address1' => $order->billing_address_1,
                'address2' => $order->billing_address_2,
                'city' => $order->billing_city,
                'state' => $order->billing_state,
                'country' => $order->billing_country,
                'zipcode' => $order->billing_zip,
                'email' => $order->billing_email,
                'phone' => $order->billing_phone,
                'surl' => $action_adr,
                'furl' => $action_adr,
                'curl' => $action_adr,
                'InvId' => $order_id, //ID заказа
            );
            $payu_args_array = array();
            foreach ($payu_args as $key => $value) {
                $payu_args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
            }

            return
                    '<form action="' . esc_url($action_adr) . '" method="POST">' . "\n" .
                    implode("\n", $payu_args_array) .
                    '<input type="submit" class="button alt" name="without_pay" value="' . __('Подтвердить', 'woocommerce') . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Отказаться & вернуться в корзину', 'woocommerce') . '</a>' . "\n" .
                    '</form>';
        }

        /**
         * Запускает механизм после выбора оплаты на странице ввода данных о клиенте
         * */
        function process_payment($order_id) {
            $order = new WC_Order($order_id);

            return array(
                'result' => 'success',
                'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
            );
        }

        /**
         * Страница подтверждения заказ
         * */
        function receipt_page($order_id) {
            $order = new WC_Order($order_id);
            if ($this->settings['without_сonfirm'] == 'yes') { //Если галка установлена, пропускаем страницу подтверждения заказа
                wc_empty_cart($order_id);
                $action_adr = get_permalink($this->settings['without_succes']);
                $this->updateStatus($order_id);
                wp_redirect($action_adr);
            } else {
                echo '<p>' . __('Спасибо за заказ, для подтверждения заказа - нажмите на кнопку ниже!', 'woocommerce') . '</p>';
                echo $this->generate_form($order_id); //Кнопки и прочее

                if (isset($_POST['without_pay'])) {
                    wc_empty_cart();
                    $action_adr = get_permalink($this->settings['without_succes']);
                    $this->updateStatus();
                    wp_redirect($action_adr);
                }
            }
        }

        /**
         * Изменение статуса заказа
         * */
        function updateStatus($order_id = null) {
            global $woocommerce;
            if (!empty($_POST['InvId'])) {
                $inv_id = $_POST['InvId'];
            } else {

                $inv_id = $order_id;
            }
            $order = new WC_Order($inv_id);
            $order->update_status('processing', __('Платеж успешно оплачен', 'woocommerce'));
        }

// Получает страницы сайта
        function get_pages($title = false, $indent = true) {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title)
                $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
// show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .= ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
// add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }

    }

    /**
     * Функция подключения класса с платёжным шлюзом
     * */
    function add_without_gateway($methods) {
        $methods[] = 'WC_WITHOUTGATEWAY';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_without_gateway');
}

// -- конец Подключиение класса с платёжной системой
?>