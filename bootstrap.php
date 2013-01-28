<?php
Autoloader::add_core_namespace('Device');
Autoloader::add_classes(
	array(
		'Device\\Device' => __DIR__.DS.'classes'.DS.'device.php',
	)
);
