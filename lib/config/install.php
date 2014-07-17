<?php

$plugin_id = array('shop', 'updateproducts');
$app_settings_model = new waAppSettingsModel();
$app_settings_model->set($plugin_id, 'list_num', '');
$app_settings_model->set($plugin_id, 'row_num', '');
$app_settings_model->set($plugin_id, 'row_count', '');
$app_settings_model->set($plugin_id, 'stock_id', '');
$app_settings_model->set($plugin_id, 'templates', '[]');
$app_settings_model->set($plugin_id, 'set_product_status', '0');
$app_settings_model->set($plugin_id, 'set_product_null', '0');
$app_settings_model->set($plugin_id, 'currency', '');
$app_settings_model->set($plugin_id, 'product_number', '');