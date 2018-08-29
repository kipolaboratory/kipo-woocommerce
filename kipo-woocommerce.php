<?php
/**
 * Plugin Name: افزونه درگاه پرداخت کیپو برای ووکامرس
 * Plugin URI: kipopay.com
 * Description: افزودن درگاه پرداخت کیپو
 * Version: 0.5.2.1
 * Author: Kipo Development team
 * License: GPL2
 * Domain Path: /languages/
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

define('PLUGINAME', 'kipo-woocommerce');

add_action('init', 'kipo_woocommerce_plugin_initial');
function kipo_woocommerce_plugin_initial()
{
	require_once __DIR__ .'/kipo-initial.php';
}

/**
 * Register styles and js files
 */
function register_plugin_styles()
{
	wp_register_style('kipo-woocommerce-plugin', plugins_url(PLUGINAME . '/css/kipo-woocomerce.css'));
	wp_enqueue_style('kipo-woocommerce-plugin');
}
add_action('wp_enqueue_scripts', 'register_plugin_styles');
add_action('admin_enqueue_scripts', 'register_plugin_styles');
