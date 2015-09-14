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


        $profile_map = ifset($profile['config']['map'], array());
        $export = ifset($profile['config']['export'], array());
        $set_model = new shopSetModel();
        $map = array(); //$this->plugin()->map(array(), null, true);


        $params = array();
        if ($profile_map) {
            foreach ($map as $type => &$type_map) {
                foreach ($type_map['fields'] as $field => &$info) {
                    $info['source'] = ifempty($profile_map[$type][$field], 'skip:');
                    unset($profile_map[$type][$field]);
                    unset($info);
                }
                if (!empty($type_map['fields']['param.*'])) {
                    $params[$type] = -1;
                }
                unset($type_map);
            }
            foreach ($profile_map as $type => $fields) {
                foreach ($fields as $field => $source) {
                    $info_field = (strpos($field, 'param.') === 0) ? 'param.*' : $field;
                    if (isset($map[$type]['fields'][$info_field])) {
                        $info = $map[$type]['fields'][$info_field];
                        $info['source'] = ifempty($source, 'skip:');

                        $map[$type]['fields'][$field] = $info;
                        $params[$type] = max(ifset($params[$type], -1), intval(preg_replace('@\D+@', '', $field)));
                    }
                }
            }
        }

        $stock_model = new shopStockModel();
        $stocks = $stock_model->getAll();
        $this->view->assign('stocks', $stocks);



        $this->view->assign('params', array('params' => $params));


        $this->view->assign('data_columns', $this->data_columns);

        $type_model = new shopTypeModel();
        $product_types = $type_model->getAll($type_model->getTableId(), true);
        $this->view->assign('product_types', $product_types);

        $cron_str = 'php ' . wa()->getConfig()->getRootPath() . '/cli.php shop UpdateproductsPluginRun profile_id=' . $profile['id'];

        $this->view->assign('cron_str', $cron_str);

        $model = new shopCurrencyModel();
        $currencies = $model->getCurrencies();
        $this->view->assign('currencies', $currencies);
    }

}
