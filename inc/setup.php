<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', [ 'search-form', 'gallery', 'caption', 'style', 'script' ] );
	add_theme_support( 'custom-logo', [
		'height'      => 200,
		'width'       => 200,
		'flex-height' => true,
		'flex-width'  => true,
	] );
	add_image_size( 'menu-item-card', 400, 300, true );
	add_image_size( 'menu-item-thumb', 120, 120, true );
	load_theme_textdomain( 'delivery-hub', DH_DIR . '/languages' );
} );

add_action( 'init', function () {
	register_nav_menus( [
		'primary' => __( 'Menu Principal', 'delivery-hub' ),
	] );
} );
