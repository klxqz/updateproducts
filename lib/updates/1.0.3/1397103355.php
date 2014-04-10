<?php

$plugin_id = array('shop', 'updateproducts');
$app_settings_model = new waAppSettingsModel();
$app_settings_model->set($plugin_id, 'templates', '[]');
$app_settings_model->set($plugin_id, 'currency', '');
