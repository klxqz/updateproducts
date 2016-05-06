<?php

class shopUpdateproductsPluginBackendSetupAction extends waViewAction {

    private $plugin_id = 'updateproducts';
    protected $data_columns = array(
        'sku:sku' => array('name' => 'Артикул', 'key' => true, 'update' => true),
        'sku:name' => array('name' => 'Наименование артикула', 'key' => true, 'update' => true),
        'sku:stock' => array('name' => 'Количество товара', 'key' => false, 'update' => true),
        'sku:price' => array('name' => 'Цена', 'key' => false, 'update' => true),
        'sku:purchase_price' => array('name' => 'Закупочная цена', 'key' => false, 'update' => true),
        'sku:compare_price' => array('name' => 'Зачеркнутая цена', 'key' => false, 'update' => true),
    );

    public function execute() {

        $routing = wa()->getRouting();

        $profile_helper = new shopImportexportHelper($this->plugin_id);
        $this->view->assign('profiles', $list = $profile_helper->getList());
        $profile = $profile_helper->getConfig();
        $profile['config'] += array(
            'hash' => '',
            'domain' => '',
            'lifetime' => 0,
            'stock_id' => 0,
        );
        $current_domain = &$profile['config']['domain'];


        $this->view->assign('current_domain', $current_domain);
        $this->view->assign('profile', $profile);

        $stock_model = new shopStockModel();
        $stocks = $stock_model->getAll();
        $this->view->assign('stocks', $stocks);


        $params = array();
        $this->view->assign('params', array('params' => $params));

        $feature_model = new shopFeatureModel();
        $features = $feature_model->select('`id`,`code`, `name`,`type`')->fetchAll('code', true);
        $features = $feature_model->getValues($features);
        foreach ($features as $key => $feature) {
            $this->data_columns['feature:' . $key] = array('name' => $feature['name'] . '(' . $key . ')', 'key' => true, 'update' => false);
        }

        $this->view->assign('data_columns', $this->data_columns);
        $this->view->assign('features', $features);

        $type_model = new shopTypeModel();
        $product_types = $type_model->getAll($type_model->getTableId(), true);
        $this->view->assign('product_types', $product_types);

        $set_model = new shopSetModel();
        $this->view->assign('sets', $set_model->getAll($set_model->getTableId(), true));

        $cron_str = 'php ' . wa()->getConfig()->getRootPath() . '/cli.php shop UpdateproductsPluginRun profile_id=' . $profile['id'];

        $this->view->assign('cron_str', $cron_str);

        $model = new shopCurrencyModel();
        $currencies = $model->getCurrencies();
        $this->view->assign('currencies', $currencies);

    }

}
