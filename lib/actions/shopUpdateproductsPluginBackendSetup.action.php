<?php

class shopUpdateproductsPluginBackendSetupAction extends waViewAction {

    protected $plugin_id = array('shop', 'updateproducts');

    public function execute() {
        $app_settings_model = new waAppSettingsModel();
        $settings = $app_settings_model->get($this->plugin_id);
        $stock_model = new shopStockModel();
        $stocks = $stock_model->getAll();
        $feature_model = new shopFeatureModel();
        $features = $feature_model->select('`id`,`code`, `name`,`type`')->fetchAll('code', true);
        
        $type_model = new shopTypeModel();
        $product_types = $type_model->getAll($type_model->getTableId(), true);

        $templates = json_decode($settings['templates'], true);

        $this->view->assign('product_types', $product_types);
        $this->view->assign('templates', $templates);
        $this->view->assign('settings', $settings);
        $this->view->assign('stocks', $stocks);
        $this->view->assign('features', $features);
    }

}
