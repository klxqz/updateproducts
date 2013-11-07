<?php
$plugin_id = array('shop', 'updateproducts');
$app_settings_model = new waAppSettingsModel();
$app_settings_model->set($plugin_id, 'list_num', '1');
$app_settings_model->set($plugin_id, 'row_num', '2');
$app_settings_model->set($plugin_id, 'row_count', '');
$app_settings_model->set($plugin_id, 'sku', '1');
$app_settings_model->set($plugin_id, 'name', '2');
$app_settings_model->set($plugin_id, 'stock', '3');
$app_settings_model->set($plugin_id, 'price', '4');
$app_settings_model->set($plugin_id, 'purchase_price', '');
$app_settings_model->set($plugin_id, 'compare_price', '');
$app_settings_model->set($plugin_id, 'update_by', 'sku');
$app_settings_model->set($plugin_id, 'stock_id', '');
