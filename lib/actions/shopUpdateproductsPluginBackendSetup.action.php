<?php

class shopUpdateproductsPluginBackendSetupAction extends waViewAction {

    protected $plugin_id = array('shop', 'updateproducts');
    protected $data_columns = array(
        'sku:sku' => array('name' => 'Артикул', 'key' => true, 'update' => true),
        'sku:name' => array('name' => 'Наименование артикула', 'key' => true, 'update' => true),
        'sku:stock' => array('name' => 'Количество товара', 'key' => false, 'update' => true),
        'sku:price' => array('name' => 'Цена', 'key' => false, 'update' => true),
        'sku:purchase_price' => array('name' => 'Закупочная цена', 'key' => false, 'update' => true),
        'sku:compare_price' => array('name' => 'Зачеркнутая цена', 'key' => false, 'update' => true),
    );

    public function execute() {
        $app_settings_model = new waAppSettingsModel();
        $settings = $app_settings_model->get($this->plugin_id);
        $stock_model = new shopStockModel();
        $stocks = $stock_model->getAll();
        $feature_model = new shopFeatureModel();
        $features = $feature_model->select('`id`,`code`, `name`,`type`')->fetchAll('code', true);

        foreach ($features as $key => $feature) {
            $this->data_columns['feature:' . $key] = array('name' => $feature['name'] . '(' . $key . ')', 'key' => true, 'update' => false);
        }

        $type_model = new shopTypeModel();
        $product_types = $type_model->getAll($type_model->getTableId(), true);


        $templates = isset($settings['templates']) ? json_decode($settings['templates'], true) : array();
        

        $model = new shopCurrencyModel();
        $currencies = $model->getCurrencies();

        $this->view->assign('currencies', $currencies);
        $this->view->assign('data_columns', $this->data_columns);
        $this->view->assign('product_types', $product_types);
        $this->view->assign('templates', $templates);
        $this->view->assign('settings', $settings);
        $this->view->assign('stocks', $stocks);
        $this->view->assign('features', $features);
    }

}
