<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$s = dh_get_settings();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="theme-color" content="<?php echo esc_attr( $s['primary_color'] ); ?>">
	<link rel="preconnect" href="https://cdn.jsdelivr.net">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="dh-app" x-data="deliveryApp()" x-cloak>
