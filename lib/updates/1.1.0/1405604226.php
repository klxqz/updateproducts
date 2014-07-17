<?php

$plugin_id = array('shop', 'updateproducts');
$app_settings_model = new waAppSettingsModel();
$app_settings_model->set($plugin_id, 'set_product_null', '0');
$app_settings_model->set($plugin_id, 'product_number', '10');

