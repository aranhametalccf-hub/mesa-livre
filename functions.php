<?php
/**
 * Delivery Hub — bootstrap do tema.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'DH_VERSION', '0.1.0' );
define( 'DH_DIR', get_template_directory() );
define( 'DH_URI', get_template_directory_uri() );

require_once DH_DIR . '/inc/setup.php';
require_once DH_DIR . '/inc/cpt.php';
require_once DH_DIR . '/inc/meta.php';
require_once DH_DIR . '/inc/settings.php';
require_once DH_DIR . '/inc/enqueue.php';
require_once DH_DIR . '/inc/ajax.php';
require_once DH_DIR . '/inc/whatsapp.php';
require_once DH_DIR . '/inc/helpers.php';
require_once DH_DIR . '/inc/customer.php';
require_once DH_DIR . '/inc/rest.php';
require_once DH_DIR . '/inc/routes.php';
require_once DH_DIR . '/inc/metrics.php';
require_once DH_DIR . '/inc/store-hours.php';
require_once DH_DIR . '/inc/delivery.php';
require_once DH_DIR . '/admin/pdv.php';
require_once DH_DIR . '/admin/order-columns.php';
require_once DH_DIR . '/admin/order-page.php';
require_once DH_DIR . '/admin/menu-item-page.php';
require_once DH_DIR . '/admin/kitchen.php';
require_once DH_DIR . '/admin/reports.php';
require_once DH_DIR . '/inc/roles.php';
require_once DH_DIR . '/inc/seed.php';
