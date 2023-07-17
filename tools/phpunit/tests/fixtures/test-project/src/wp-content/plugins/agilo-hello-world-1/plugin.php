<?php
/*
Plugin Name: Agilo Hello World 1
Plugin URI: https://agilo.co
Description: Dummy plugin which outputs hello in the admin, used for testing.
Version: 1.0
Author: Agilo
Author URI: https://agilo.co
*/

namespace Agilo\Plugins\HelloWorld1;

function hello_world() {
	echo '<p>Hello World 1</p>';
}
add_action( 'admin_notices', __NAMESPACE__ . '\hello_world' );
