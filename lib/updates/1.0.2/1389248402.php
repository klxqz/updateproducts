<?php

$plugin_id = array('shop', 'updateproducts');
$app_settings_model = new waAppSettingsModel();
$app_settings_model->set($plugin_id, 'set_product_status', '0');