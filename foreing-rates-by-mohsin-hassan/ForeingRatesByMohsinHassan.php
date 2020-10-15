<?php
/*
Plugin Name: Foreing Rates By Mohsin Hassan
Description: A task based plugin for ITcraft
Version: 1.0
Author: Mohsin Hassan
License: MIT
*/


namespace Plugin\ForeingRate;
use function Patchwork\Config\getTimestamp;

require_once 'forex-widget/ForexWidget.php';

if(!class_exists('\Plugin\ForeingRate\ForeingRatesByMohsinHassan')){

    class ForeingRatesByMohsinHassan {


        /**
         * The unique instance of the plugin.
         * @var singleton object
         */
        private static $instance;

        // Plugin vars
        public  $plugin_uri;
        public  $plugin_dir;
        public  $plugin_name;
        public  $plugin_slug;
        public  $plugin_name_space;

        public  $api_url                          = 'https://api.exchangeratesapi.io/latest';
        public  $cache_api_requests               = true;
        public  $currencies                       = array('EUR','CAD', 'HKD', 'ISK', 'PHP', 'DKK', 'HUF', 'CZK', 'GBP', 'RON', 'SEK', 'IDR', 'INR',
            'BRL', 'RUB', 'HRK', 'JPY', 'THB', 'CHF', 'MYR', 'BGN', 'TRY', 'CNY', 'NOK', 'NZD', 'ZAR', 'USD', 'MXN', 'SGD', 'AUD',
            'ILS', 'KRW', 'PLN',
        );
        public $categories_to_create = array('finance','currency' );
        public $tags_to_create       = array('EUR','CHF');




        /**
         * Gets an instance of our plugin.
         * @return object
         */
        public static function get_instance()
        {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }



        /**
         * Constructor
         */
        private function __construct(){}


        public function init(){
            //
            $this->plugin_dir           = plugin_dir_path(__FILE__);
            $this->plugin_uri           = plugin_dir_url(__FILE__);
            $this->plugin_slug          = 'foreing-rates-by-mohsin-hassan';
            $this->plugin_name          = 'Foreing Rates By Mohsin Hassan';
            $this->plugin_name_space    = '\Plugin\ForeingRate\ForeingRatesByMohsinHassan';




            register_activation_hook( __FILE__, array ( $this, 'forex_activation_setup') );

            add_shortcode('foreing_rates',[$this,'render_short_code'] );
            add_action( 'widgets_init', function(){
                register_widget( '\Plugin\ForeingRate\Widget\ForexWidget' );
            });


            add_action('forex_api_error',[$this,'forex_api_error_handling']);
            //$this->clear_api_cache();


            add_action( 'admin_menu',[$this,'forex_admin_menu_page']);
            add_action( 'admin_init',[$this,'forex_register_settings']);


            add_filter('the_content',[$this,'add_forex_to_post_content'],20);

        }

//    \Plugin\ForeingRate\ForeingRatesByMohsinHassan::forex_in_theme()

        static function forex_in_theme(){

            $one_week_condition     = '-1 week'; // this will take saturday as week reference
            $seven_days_condition   = '-7 days';

            $date_query = array(
                array(
                    'column'        => 'post_date',
                    'before'        => date('Y-m-d H:i:s',strtotime($seven_days_condition))
                )
            );

            $args = array (
                    'post_type'      => 'post',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'category_name'  => 'currency',
                    'tag'            => 'eur',
                    'meta_key'       => 'rate',
                    'meta_value'     =>  '1',
                    'meta_compare'   =>  '>',
                    'date_query'     => $date_query

            );

            return get_posts($args);
        }

        public function forex_activation_setup(){
            $finance    = wp_create_category( $this->categories_to_create[0] );
            $currency   = wp_create_category( $this->categories_to_create[1],$finance );
            $eur        = wp_create_tag($this->tags_to_create[0]);
            $chf        = wp_create_tag($this->tags_to_create[1]);
        }

        public function clear_api_cache(){
            delete_transient('foreing_api_response');
        }


        public function get_currency_data($base = 'EUR'){

            $this->cache_api_requests = apply_filters('cache_api_requests',$this->cache_api_requests);
            $base_pram = '?base='. $base;
            $URL = $this->api_url . $base_pram;
            $data = get_transient('foreing_api_response');


            if( ($data != false) && is_array($data) && isset($data[$base]) && $this->cache_api_requests != false ){
                $data[$base]['cached_data'] = true;
                return $data[$base];
            }

            try {
                if(!is_array($data)){
                    $data = [];
                }
                @$data[$base];
                $response      = wp_remote_get( $URL,array( 'timeout'=> 20) );
                $response_code = wp_remote_retrieve_response_code( $response );

                if( !is_wp_error( $response ) && $response_code == 200 ){
                    $body = wp_remote_retrieve_body( $response );
                    $data[$base] = (array) json_decode($body);
                    $data[$base]['rates'] = (array)$data[$base]['rates'];

                    if($this->cache_api_requests)
                        set_transient('foreing_api_response',$data,24 * HOUR_IN_SECONDS);
                    $data[$base]['cached_data'] = false;
                    return $data[$base];
                }else{
                    do_action('forex_api_error',array( 'function_state' => get_defined_vars(),'exception' => false) );
                }

            } catch ( \Exception $ex ) {
                do_action('forex_api_error',array( 'function_state' => get_defined_vars(),'exception' => $ex) );
            }

        }

        public function forex_api_error_handling($args){
            // we just die here otherwise a proper error handling can be performed
            //$args['function_state'] hold all the current state of defined vars
            wp_die('Unable to connect to the Exchange Rates Api');
        }


        public function render_short_code($atts = ''){
            $atts = shortcode_atts(array(
                'base'          => 'EUR',
                'currencies'    => 'USD,CHF,CAD'
                ), $atts,'foreing_rates');

            $base       = strtoupper($atts['base']);
            if(!in_array($base,$this->currencies)){
                return 'Invalid ShortCode Attributes';
            }
            $currencies = strtoupper($atts['currencies']);
            $currencies = explode(',',$currencies);
            $data = $this->get_currency_data($base);
            $html = $this->prepare_html_forex($base,$currencies,$data);
            return $html;
        }


        public function prepare_html_forex($base,$currencies,$base_data){
            ob_start();
            ?>
            <div id="forex-wrap">
                <?php foreach (@$currencies as $currency):
                    if( !isset($base_data['rates'][$currency]) || $base == $currency){
                        continue;
                    }
                $currency_rate = $base_data['rates'][$currency];
                echo "<p class='currency-col '> 1 $base = $currency_rate $currency </p>";
                endforeach; ?>
            </div>
            <?php
           return ob_get_clean();
        }


        public function add_forex_to_post_content($content){
            global $post;
            $enable_forex_on_posts = get_option( 'enable_forex_on_posts' );
            $forex_categories = get_option( 'forex_categories' );

            if($enable_forex_on_posts && (get_post_type() == 'post') && @($forex_categories > 0) ){
                $cat_array = wp_get_post_categories($post->ID);
                if(in_array($forex_categories,$cat_array)){
                    $base_curency     = get_option( 'forex_base_currency' );
                    $exchange_curency = get_option( 'forex_exchange_currencies' );
                    $base_data        = $this->get_currency_data($base_curency);
                    $html             = $this->prepare_html_forex($base_curency,$exchange_curency,$base_data);

                    return $content . $html;
                }
            }
            return $content;
        }


        public function forex_admin_menu_page(){
            add_options_page(
                'Forex Option Page',
                'Forex Options',
                'manage_options',
                'forex-options',
                array($this,'forex_admin_menu_page_call_back')
            );
        }


        public function forex_admin_menu_page_call_back(){
            ?>
            <div class="wrap">
                <h1>Foreing Rates Settings</h1>
                <form method="post" action="options.php">
                    <?php
                    settings_fields( 'forex_options' );
                    do_settings_sections( 'forex-options' );
                    submit_button();
                    ?>
                </form>
            </div>
            <?php
        }


        public function  forex_register_settings(){
            add_settings_section(
                'forex_settings_section_id',
                '',
                '',
                'forex-options'
            );

            //Base Currency
            register_setting(
                'forex_options',
                'forex_base_currency',
                'sanitize_text_field'
            );
            add_settings_field(
                'forex_base_currency',
                'Select the base currency',
                array($this,'base_field_html'),
                'forex-options',
                'forex_settings_section_id',
                array(
                    'label_for' => 'forex_base_currency',
                )
            );

            // Exchange Currencies
            register_setting(
                'forex_options',
                'forex_exchange_currencies',
                ''
            );
            add_settings_field(
                'forex_exchange_currencies',
                'Select the exchange currencies',
                array($this,'exchange_field_html'),
                'forex-options',
                'forex_settings_section_id',
                array(
                    'label_for' => 'forex_exchange_currencies',
                )
            );

            //Categories
            register_setting(
                'forex_options',
                'forex_categories',
                'sanitize_text_field'
            );
            add_settings_field(
                'forex_categories',
                'Select the categories',
                array($this,'categories_field_html'),
                'forex-options',
                'forex_settings_section_id',
                array(
                    'label_for' => 'forex_categories',
                )
            );

            // enable - disable
            register_setting(
                'forex_options',
                'enable_forex_on_posts',
                ''
            );
            add_settings_field(
                'enable_forex_on_posts',
                'Enable on posts',
                array($this,'enable_forex_field_html'),
                'forex-options',
                'forex_settings_section_id',
                array(
                    'label_for' => 'enable_forex_on_posts',
                )
            );

        }


        function base_field_html(){

            $base_curency = get_option( 'forex_base_currency' );
            ?>
            <select  id="forex_base_currency" name="forex_base_currency">
                <?php foreach ($this->currencies as $currency): ?>
                <option value="<?php echo $currency ?>" <?php selected($currency,$base_curency);?>><?php echo $currency ?></option>
                <?php endforeach; ?>
            </select>
            <?php
        }


        function exchange_field_html(){
            $exchange_curency = get_option( 'forex_exchange_currencies' );
            $base_curency = get_option( 'forex_base_currency' );
            foreach ($this->currencies as $currency):
//                if($currency == $base_curency)
//                    continue;
            ?>
                <br>
                <input
                        class="checkbox" id="forex_exchange_currencies-<?php echo $currency; ?>"
                        name="forex_exchange_currencies[]"
                        type="checkbox"
                        value="<?php echo $currency; ?>"
                    <?php @checked(in_array($currency, $exchange_curency)); ?>/>
                <span><?php echo $currency; ?></span>
            <?php endforeach;
        }


        function categories_field_html(){
            $forex_categories = get_option( 'forex_categories' );
            $categories       = get_categories(array('taxonomy' => 'category', 'hide_empty' => false));
            ?>
            <select  id="forex_categories" name="forex_categories">
                <option value="0">Select Categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category->term_id ?>" <?php selected($category->term_id,$forex_categories);?>><?php echo $category->name ?></option>
                <?php endforeach; ?>
            </select>
            <?php
        }


        function enable_forex_field_html(){
            $enable_forex_on_posts = get_option( 'enable_forex_on_posts' );
            ?>
            <input
                    class="checkbox" id="enable_forex_on_posts"
                    name="enable_forex_on_posts"
                    type="checkbox"
                    value="1"
                <?php @checked(1, $enable_forex_on_posts); ?>/>
            <?php

        }

    }//end class

}//class_exists


$forex_plugin_obj =  \Plugin\ForeingRate\ForeingRatesByMohsinHassan::get_instance();
$forex_plugin_obj->init();



