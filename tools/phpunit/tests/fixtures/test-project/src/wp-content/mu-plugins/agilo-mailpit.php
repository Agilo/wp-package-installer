<?php
/**
 * Agilo Mailpit plugin.
 *
 * Plugin Name: Agilo Mailpit
 * Version: 1.0.0
 * Plugin URI: https://agilo.co
 * Description: Setup Mailpit SMTP integration.
 * Author: Agilo (Ivo Limić)
 * Author URI: https://agilo.co
 * Text Domain: agilo-mailpit
 * License: MIT
 * Requires at least: 6.0
 * Requires PHP: 5.6
 */

namespace AgiloMailpit;

if ( ! function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

function phpmailer_init( \PHPMailer\PHPMailer\PHPMailer $phpmailer ) {
	$phpmailer->Host = 'mailpit';
	$phpmailer->Port = 1025;
	// $phpmailer->Username = 'username';
	// $phpmailer->Password = 'password';
	$phpmailer->SMTPAuth = false;
	$phpmailer->isSMTP();
}
add_action( 'phpmailer_init', 'AgiloMailpit\\phpmailer_init' );
