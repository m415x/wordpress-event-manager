<?php
/**
 * Plugin Name: WordPress Event Manager
 * Description: Gestión de invitados, eventos y control de acceso.
 * Plugin URI: https://github.com/m415x/wordpress-event-manager
 * Version: 2.2.1
 * Author: WordPress Event Manager Contributors
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * Text Domain: wordpress-event-manager
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WEM
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WEM_PATH', plugin_dir_path(__FILE__));
define('WEM_URL', plugin_dir_url(__FILE__));

spl_autoload_register(function ($class) {
    $prefix = 'WEM_';
    $base_dir = WEM_PATH . 'includes/';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $file = $base_dir . 'class-' . str_replace('_', '-', strtolower(substr($class, strlen($prefix)))) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

require_once WEM_PATH . 'includes/helpers.php';

add_action('plugins_loaded', function () {
    new WEM_CPT_Manager();
    new WEM_Taxonomy_Manager();
    $metaboxes = new WEM_Metabox_Manager();
    $metaboxes->register_metaboxes();

    $admin_columns = new WEM_Admin_Columns();
    $admin_columns->setup_columns();

    new WEM_Ajax_Handler();
    new WEM_Shortcode_Manager();

    $import_export = new WEM_Import_Export();
    $import_export->register_menu();

    new WEM_QR_Generator();
});
