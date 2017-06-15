<?php
/*
Plugin Name: Woocommerce upcoming Products
Plugin URI: http://shaikat.me/
Description: Manage your upcoming product easily.
Version: 1.5.4
Author: Sk Shaikat
Author URI: https://twitter.com/SK_Shaikat
Text Domain: wup
Domain Path: /languages
License: GPL2
*/

/**
 * Copyright (c) 2017 Sk Shaikat (email: sk.shaikat18@gmail.com). All rights reserved.
 *
 */

// don't call the file directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Woocommerce_Upcoming_Product class
 *
 * @class Woocommerce_Upcoming_Product The class that holds the entire Woocommerce_Upcoming_Product plugin
 */
class Woocommerce_Upcoming_Product
{

    /**
     * Constructor for the Woocommerce_Upcoming_Product class
     *
     * Sets up all the appropriate hooks and actions
     * within our plugin.
     *
     * @uses register_activation_hook()
     * @uses register_deactivation_hook()
     * @uses is_admin()
     * @uses add_action()
     */
    public function __construct()
    {
        register_activation_hook( __FILE__, array($this,'activate' ) );
        register_deactivation_hook( __FILE__, array($this,'deactivate' ) );

        // Localize our plugin
        add_action( 'init', array($this,'localization_setup' ) );
        add_action( 'init', array($this,'wup_register_daily_upcoming_delete_event' ) );

        // Loads frontend scripts and styles
        add_action( 'admin_enqueue_scripts', array($this,'admin_enqueue_scripts' ) );
        add_action( 'wp_enqueue_scripts', array($this,'enqueue_scripts' ) );


        // wup let's play option
        add_action( 'woocommerce_before_single_product', array($this,'wup_single_page_view' ) );
        // wup let's play option
        add_action( 'woocommerce_before_shop_loop_item', array($this,'wup_shop_page_view' ) );

        // Add upcoming option
        add_action( 'woocommerce_product_options_advanced', array($this,'add_upcoming_options' ),10 );
        add_action( 'woocommerce_process_product_meta_simple', array($this,'save_upcoming_options' ),10 );
        add_action( 'woocommerce_process_product_meta_variable', array($this,'save_upcoming_options' ),10 );

        // image ribbon
        add_filter( 'the_title', array($this,'upcoming_product_title' ), 2, 10 );

        // add search form option
        add_filter( 'woocommerce_catalog_orderby', array($this,'upcoming_search_option' ) );

        // custom preorder search queary
        add_action( 'woocommerce_product_query', array($this,'upcoming_custom_queary'), 2 );

        add_filter( 'woocommerce_single_product_summary', array($this,'custom_get_availability' ), 15 );

        // pre order single product view
        add_action( 'woocommerce_single_product_summary', array($this,'upcoming_single_page_view'), 30 );
        add_action( 'woocommerce_after_shop_loop_item', array($this,'upcoming_shop_page_view'), 7 );

        add_filter( 'woocommerce_get_sections_products', array( $this,'wup_wc_product_settings_section' ), 10 );

        add_filter( 'woocommerce_get_settings_products', array( $this,'wup_wc_product_settings_option' ), 10, 2 );
        add_action( 'wup_expired_upcoming_product', array( $this, 'wup_auto_delete_product_updoming_meta' ) );
    }


    /**
     * Initializes the Woocommerce_Upcoming_Product() class
     *
     * Checks for an existing Woocommerce_Upcoming_Product() instance
     * and if it doesn't find one, creates it.
     */
    public static function init()
    {
        static $instance = false;

        if ( ! $instance ) {
            $instance = new Woocommerce_Upcoming_Product();
        }

        return $instance;
    }

    /**
     * Placeholder for activation function
     *
     * Nothing being called here yet.
     */
    public function activate()
    {
        if (! wp_next_scheduled ( 'wup_expired_upcoming_product' )) {
            wp_schedule_event(time(), 'daily', 'wup_expired_upcoming_product');
        }
    }

    /**
     * Placeholder for deactivation function
     *
     * Nothing being called here yet.
     */
    public function deactivate()
    {
        wp_clear_scheduled_hook('wup_expired_upcoming_product');
    }

    /**
     * Initialize plugin for localization
     *
     * @uses load_plugin_textdomain()
     */
    public function localization_setup()
    {
        load_plugin_textdomain( 'wup', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    /**
     * Enqueue scripts
     *
     * Allows plugin assets to be loaded.
     *
     * @uses wp_enqueue_script()
     * @uses wp_localize_script()
     * @uses wp_enqueue_style
     */
    public function enqueue_scripts() {
        /**
         * All styles goes here
         */
        wp_enqueue_style( 'upcoming-styles', plugins_url( 'css/style.css', __FILE__ ), false, date( 'Ymd' ) );
    }

    // Function which will register the event
    function wup_register_daily_upcoming_delete_event() {
        // Make sure this event hasn't been scheduled
        if ( !wp_next_scheduled ( 'wup_expired_upcoming_product' ) ) {
            wp_schedule_event( time(), 'daily', 'wup_expired_upcoming_product');
        }
    }

    /**
     * Enqueue admin scripts
     *
     * Allows plugin assets to be loaded.
     *
     * @uses wp_enqueue_script()
     * @uses wp_localize_script()
     * @uses wp_enqueue_style
     */
    public function admin_enqueue_scripts() {
        global $pagenow;
        /**
         * All scripts goes here
         */
        if ( !in_array( $pagenow, array( 'post.php', 'post-new.php') ) ) {
            return;
        }
        wp_enqueue_style( 'upcoming-styles', plugins_url( 'css/admin-style.css', __FILE__ ), false, date( 'Ymd' ) );
        wp_enqueue_script( 'upcoming-scripts', plugins_url( 'js/script.js', __FILE__ ), array('jquery' ), false, true );
    }

    /**
     * Delete upcoming meta from product
     *
     * @since 1.5
     */
    function wup_auto_delete_product_updoming_meta() {
        if ( $this->is_upcoming() && WC_Admin_Settings::get_option( 'wup_auto_live', 'yes' ) == 'yes' ) {
            $this->wup_delete_product_updoming_meta();
        }
    }


    /**
     * get upcoming product
     *
     * @since 1.5.5
     */
    function wup_get_updoming_products() {
        $args = array(
            'post_type' => 'product',
            'meta_query' => array(
                array(
                    'key'    => '_upcoming',
                    'value'  => 'yes',
                    'compare'=> '='
                )
            )
         );
        return get_posts( $args );
    }

    /**
     * Delete upcoming meta from product
     *
     * @since 1.5
     */
    function wup_auto_delete_product_updoming_meta() {
        $postslist = wup_get_updoming_products();

        foreach ( $postslist as  $post ) {
            $available_on = get_post_meta( $post->ID, '_available_on', true );
            if ( '' == $available_on ) {
                return;
            }
            $today = date_i18n(get_option( 'date_format' ), strtotime( date( 'today' ) ) );
            $next_day = date('Y-m-d', strtotime( '+1 day', strtotime( $today ) ) );
            $available_on = date('Y-m-d', strtotime( $available_on ) );
            if ( $next_day > $available_on ) {
                delete_post_meta( $post->ID, '_upcoming' );
            }
        }
    }

    /**
     * Check if the product is upcoming
     *
     * @since 1.0
     *
     * @global $post
     * @return boolian
     */
    function is_upcoming() {
        global $post;
        if ( get_post_meta( $post->ID, '_upcoming', true ) == 'yes' ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Product single page view
     *
     * @since 1.0
     */
    function wup_single_page_view()
    {
        if ( $this->is_upcoming() ) {
            if ( WC_Admin_Settings::get_option( 'wup_price_hide_single', 'no' ) == 'yes' ) {
                remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
            }
            if ( WC_Admin_Settings::get_option( 'wup_button_hide_single', 'no' ) == 'yes' ) {
                remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
            }
        }
    }

    /**
     * Product shop page view
     *
     * @since 1.0
     */
    function wup_shop_page_view()
    {
        if ( WC_Admin_Settings::get_option( 'wup_price_hide_shop', 'no' ) == 'yes' && $this->is_upcoming() ) {
            remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
        } else {
            add_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
        }
        if ( WC_Admin_Settings::get_option( 'wup_button_hide_shop', 'no' ) == 'yes' && $this->is_upcoming() ) {
            remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
        } else {
            add_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
        }
    }

    /**
     * Add upcoming setting on edit product page
     *
     * @since 1.0
     *
     * @global $post
     */
    function add_upcoming_options()
    {
        global $post;
        woocommerce_wp_checkbox( array('id'         => '_upcoming','label'      => __( 'Upcoming Product', 'wup' ),'description'=> __( 'Enable for upcoming product', 'wup' ) ) );
        $available_class = ( get_post_meta( $post->ID, '_upcoming', true ) == 'yes' ) ? '' : 'wup-hide';
        woocommerce_wp_text_input( array('id'           => '_available_on','label'        => __( 'Available On', 'wup' ),'wrapper_class'=> $available_class,'description'  => __( 'Insert product available date', 'wup' ) ) );
    }

    /**
     * Save upcoming meta of product
     *
     * @since 1.0
     * 
     * @param int $post_id
     */
    function save_upcoming_options( $post_id  )
    {
        $_upcoming = ( isset( $_POST['_upcoming'] ) ) ? $_POST['_upcoming'] : '';
        $_available_on = ( isset( $_POST['_available_on'] ) ) ? mysql2date( get_option( 'date_format' ), $_POST['_available_on'] ) : '';
        update_post_meta( $post_id, '_upcoming', $_upcoming );
        update_post_meta( $post_id, '_available_on', $_available_on );
    }

    /**
     * Add text to upcoming product title
     *
     * @since 1.0
     *
     * @param string $title
     * @param int $id
     * @return string $title
     */
    function upcoming_product_title( $title, $id )
    {
        if ( is_admin() ) {
            return $title;
        }
        $label     = WC_Admin_Settings::get_option( 'wup_title_label_txt', __( 'Upcoming', 'wup' ) );
        if ( 'product' == get_post_type( $id ) && $this->is_upcoming() && WC_Admin_Settings::get_option( 'wup_title_label', 'yes' ) == 'yes' ) {
            $title .= sprintf( __( ' <span class="soon">(%s)</span>', 'wup' ), $label );
        }
        return $title;
    }

    /**
     * Add option to search upcoming product on shop page
     *
     * @since 1.0
     *
     * @param array $catalog_orderby
     * @return array $catalog_orderby
     */
    function upcoming_search_option( $catalog_orderby )
    {
        $catalog_orderby['upcoming'] = __( 'Sort by upcoming', 'wup' );
        return $catalog_orderby;
    }

    /**
     * DUpdate search queary
     *
     * @since 1.0
     *
     * @param obj
     * 
     * @return obj
     */
    function upcoming_custom_queary($q)
    {

        $meta_query = $q->query_vars['meta_query'];

        if ( isset( $_GET['orderby'] ) && $_GET['orderby'] == 'upcoming' ) {
            $meta_query[] = array(
                'key'    => '_upcoming',
                'value'  => 'yes',
                'compare'=> '='
            );

            $q->set( 'meta_query', $meta_query );
        }
    }

    /**
     * Add custom text on single product page
     *
     * @since 1.0
     */
    function custom_get_availability()
    {
        $price_label = WC_Admin_Settings::get_option( 'wup_price_label_txt', __( 'Coming Soon', 'wup' ) );
        if ( $this->is_upcoming() && WC_Admin_Settings::get_option( 'wup_price_label', 'yes' ) == 'yes' ) {
            echo sprintf( __( '<div class="product_meta"><span class="wup-price-label">%s</span></div>', 'wup' ), $price_label );
        }
    }

    /**
     * Single page view for upcoming product
     *
     * @since 1.0
     *
     * @global $post
     */
    function upcoming_single_page_view()
    {
        global $post;
        if ( WC_Admin_Settings::get_option( 'wup_show_available_date_single', 'yes' ) == 'yes' ) {
            $_available_on = get_post_meta( $post->ID, '_available_on', true );
            if ( $this->is_upcoming() ) {
                ?>
                <div class="product_meta">
                    <span class="available-from">
                        <?php _e( 'Available from: ', 'wup' );

                        if ( $_available_on == '' ) {
                            ?>
                            <strong>
                                <?php _e( 'Date not yet set.', 'wup' ); ?>
                            </strong>
                            <?php
                        }else {
                            ?>
                            <strong>
                                <?php echo date_i18n( get_option( 'date_format' ), strtotime( $_available_on ) ); ?>
                            </strong>
                            <?php
                        }
                        ?>
                    </span>
                </div>
                <?php
            }
        }
    }


    /**
     * Shop page view for upcoming product
     *
     * @since 1.0
     *
     * @global $post
     */
    function upcoming_shop_page_view()
    {
        global $post;
        if ( WC_Admin_Settings::get_option( 'wup_show_available_date_shop', 'yes' ) == 'yes' ) {
            $_available_on = get_post_meta( $post->ID, '_available_on', true );
            if ( $this->is_upcoming() ) {
                ?>
                <div class="upcoming">
                    <?php _e( 'Available from: ', 'wup' );

                    if ( $_available_on == '' ) {
                        ?>
                        <strong>
                            <?php _e( 'Date not yet set.', 'wup' ); ?>
                        </strong>
                        <?php
                    }else {
                        ?>
                        <strong>
                            <?php echo date_i18n(get_option( 'date_format' ), strtotime($_available_on)) ; ?>
                        </strong>
                        <?php
                    }
                    ?>
                </div>
                <?php
            }
        }
    }

    /**
     * Add admin setting on woocommerce settings page
     *
     * @since 1.0
     *
     * @param array $sections
     * @return array $sections
     */
    function wup_wc_product_settings_section( $sections )
    {
        $sections['wup'] = __( 'Upcoming Products', 'wup' );
        return $sections;
    }

    /**
     * Add admin setting fields on upcoming product setting page
     *
     * @since 1.0
     *
     * @param array $settings
     * @param string $current_section
     * @return array $settings
     */
    function wup_wc_product_settings_option( $settings, $current_section )
    {
        if ( 'wup' == $current_section ) {
            $settings = apply_filters( 'wup_product_settings', array(
                    array(
                        'title'=> __( 'Upcoming Product', 'wup' ),
                        'type' => 'title',
                        'desc' => '',
                        'id'   => 'wup_options'
                    ),

                    array(
                        'title'  => __( 'Product auto live', 'wup' ),
                        'desc'   => __( 'Upcoming product will automatically go online on available date', 'wup' ),
                        'id'     => 'wup_auto_live',
                        'default'=> 'yes',
                        'type'   => 'checkbox'
                    ),

                    array(
                        'title'  => __( 'Upcoming Product title label', 'wup' ),
                        'desc'   => __( 'Show label on upcoming product title', 'wup' ),
                        'id'     => 'wup_title_label',
                        'default'=> 'yes',
                        'type'   => 'checkbox'
                    ),

                    array(
                        'title'  => __( 'Title label Text', 'wup' ),
                        'desc'   => __( 'Label will show on upcoming product title', 'wup' ),
                        'id'     => 'wup_title_label_txt',
                        'default'=> 'Upcoming',
                        'type'   => 'text'
                    ),

                    array(
                        'title'  => __( 'Price Hide on Single Page', 'wup' ),
                        'desc'   => __( 'Hide price of upcoming product on single product page', 'wup' ),
                        'id'     => 'wup_price_hide_single',
                        'default'=> 'no',
                        'type'   => 'checkbox'
                    ),

                    array(
                        'title'   => __( 'Price Hide on Shop Page', 'wup' ),
                        'desc'    => __( 'Hide price of upcoming product on shop page', 'wup' ),
                        'id'      => 'wup_price_hide_shop',
                        'default' => 'no',
                        'type'    => 'checkbox'
                    ),

                    array(
                        'title'  => __( 'Upcoming Product price label', 'wup' ),
                        'desc'   => __( 'Show label under price of upcoming product', 'wup' ),
                        'id'     => 'wup_price_label',
                        'default'=> 'yes',
                        'type'   => 'checkbox'
                    ),

                    array(
                        'title'  => __( 'Text Under Price', 'wup' ),
                        'desc'   => __( 'Text under product price', 'wup' ),
                        'id'     => 'wup_price_label_txt',
                        'default'=> 'Coming Soon',
                        'type'   => 'text'
                    ),

                    array(
                        'title'  => __( '"Add to Cart" Button Hide on Single Page', 'wup' ),
                        'desc'   => __( 'Hide "Add to Cart" Button of upcoming product on single product page', 'wup' ),
                        'id'     => 'wup_button_hide_single',
                        'default'=> 'no',
                        'type'   => 'checkbox'
                    ),

                    array(
                        'title'   => __( '"Add to Cart" Button Hide on Shop Page', 'wup' ),
                        'desc'    => __( 'Hide "Add to Cart" Button of upcoming product on shop page', 'wup' ),
                        'id'      => 'wup_button_hide_shop',
                        'default' => 'no',
                        'type'    => 'checkbox'
                    ),

                    array(
                        'title'  => __( 'Available Date Show on Single Page', 'wup' ),
                        'desc'   => __( 'Show available date on single product page view', 'wup' ),
                        'id'     => 'wup_show_available_date_single',
                        'default'=> 'yes',
                        'type'   => 'checkbox'
                    ),

                    array(
                        'title'  => __( 'Available Date Show on Shop Page', 'wup' ),
                        'desc'   => __( 'Show available date on shop product page view', 'wup' ),
                        'id'     => 'wup_show_available_date_shop',
                        'default'=> 'yes',
                        'type'   => 'checkbox'
                    ),

                    array(
                        'type'=> 'sectionend',
                        'id'  => 'wup_options'
                    ),
                ));
        }
        return $settings;
    }

} // Woocommerce_Upcoming_Product

$upcoming = Woocommerce_Upcoming_Product::init();
