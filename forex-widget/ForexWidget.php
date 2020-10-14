<?php

namespace Plugin\ForeingRate\Widget;

use Plugin\ForeingRate\ForeingRatesByMohsinHassan;

class ForexWidget extends \WP_Widget
{

    public $ForeingRatesByMohsinHassan;
    public  $currencies;
    public  $defaults = array(
        'base' => 'EUR',
        'currencies' => array('USD','CHF','CAD')
    );


    function __construct()
    {
        parent::__construct(
            'forex_widget',
            'Foreing Rates Widget',
            array('description' => 'This widget will display exchange rate between selected currencies')
        );

        $this->ForeingRatesByMohsinHassan = ForeingRatesByMohsinHassan::get_instance();
        $this->currencies = $this->ForeingRatesByMohsinHassan->currencies;
    }


    /**
     * Outputs the content of the widget
     *
     * @param array $args
     * @param array $instance
     */
    public function widget($args, $instance)
    {
        echo $args['before_widget'];
        if(isset($instance['base'])){
            $base_data = $this->ForeingRatesByMohsinHassan->get_currency_data($instance['base']);
            $html      = $this->ForeingRatesByMohsinHassan->prepare_html_forex($instance['base'],$instance['currencies'],$base_data);
            $html      = apply_filters('forex_widget_html',$html);
            echo $html;
        }
        echo $args['after_widget'];
    }



    /**
     * Outputs the options form on admin
     *
     * @param array $instance The widget options
     *
     * @return void
     */
    public function form($instance)
    {

        $base                   = isset($instance['base']) ? $instance['base'] : $this->defaults['base'];
        $selected_currencies    = isset($instance['currencies']) ? $instance['currencies'] : $this->defaults['currencies'];
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('base'); ?>"><?php _e('Select Base Currency:'); ?></label>
            <select class="widefat" id="<?php echo $this->get_field_id('base'); ?>" name="<?php echo $this->get_field_name('base'); ?>">
                <?php foreach ($this->currencies as $currency): ?>
                <option value='<?php echo $currency;?>' <?php selected($currency,$base);?>><?php echo $currency;?></option>";
                <?php endforeach; ?>
            </select>
        </p>
        <p>
        <p>Select Exchange Currencies:</p>
            <br>
        <?php foreach ($this->currencies as $key => $currency):
            if($currency == $base) continue; ?>
            <input class="checkbox" id="<?php echo $this->get_field_id("currencies") . $currency; ?>" name="<?php echo $this->get_field_name("currencies"); ?>[]" type="checkbox" value="<?php echo $currency; ?>" <?php @checked(in_array($currency, $selected_currencies)); ?> />
            <label for="<?php echo $this->get_field_id("currencies") . $currency; ?>"><?php echo $currency; ?></label>
            <br>
        <?php endforeach; ?>
        </p>
        <?php
    }

    /**
     * Processing widget options on save
     *
     * @param array $new_instance The new options
     * @param array $old_instance The previous options
     *
     * @return array
     */
    public function update($new_instance, $old_instance)
    {
        $instance = array();
        $instance['base']       = (!empty($new_instance['base'])) ? ($new_instance['base']) : '';
        $instance['currencies'] = is_array($new_instance['currencies']) ? $new_instance['currencies'] : array();
        return $instance;
    }


}