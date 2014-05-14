<?php
function autoload($class) {
	require "app/$class.php";
}

spl_autoload_register('autoload');

