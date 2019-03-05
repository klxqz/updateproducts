<?php

class shopUpdateproductsPluginBackendSetupAction extends waViewAction
{

    private $data_columns = array(
        'sku:sku' => array('name' => 'Артикул', 'key' => true, 'update' => true),
        'sku:name' => array('name' => 'Наименование артикула', 'key' => true, 'update' => true),
        'sku:stock' => array('name' => 'Количество товара', 'key' => false, 'update' => true),
        'sku:price' => array('name' => 'Цена', 'key' => false, 'update' => true),
        'sku:purchase_price' => array('name' => 'Закупочная цена', 'key' => false, 'update' => true),
        'sku:compare_price' => array('name' => 'Зачеркнутая цена', 'key' => false, 'update' => true),
    );

    public function execute()
    {
        $profile_helper = new shopImportexportHelper('updateproducts');
        $list = $profile_helper->getList();

        $profile = $profile_helper->getConfig();
        $profile['config'] += array(
            'hash' => '',
            'domain' => '',
            'lifetime' => 0,
            'stock_id' => 0,
        );
        $current_domain = $profile['config']['domain'];

        $stock_model = new shopStockModel();
        $stocks = $stock_model->getAll();

        $feature_model = new shopFeatureModel();
        $features = $feature_model->select('*')->where('`count` > 0')->fetchAll('code', true);
        $feature_values = array();
        foreach ($features as $key => $feature) {
            if (!empty($profile['config']['filter']['features'][$feature['id']])) {
                $feature_value_model = $feature_model::getValuesModel($feature['type']);
                foreach ($profile['config']['filter']['features'][$feature['id']] as $value_id) {
                    $feature_values[$value_id] = $feature_value_model->getFeatureValue($value_id);
                }
            }
            $this->data_columns['feature:' . $key] = array('name' => $feature['name'] . '(' . $key . ')', 'key' => true, 'update' => true);
        }

        $type_model = new shopTypeModel();
        $product_types = $type_model->getAll('id', true);

        $set_model = new shopSetModel();
        $sets = $set_model->getAll($set_model->getTableId(), true);

        $cron_str = 'php ' . wa()->getConfig()->getRootPath() . '/cli.php shop UpdateproductsPluginRun profile_id=' . $profile['id'];

        $model = new shopCurrencyModel();
        $currencies = $model->getCurrencies();

        $this->view->assign(array(
            'profiles' => $list,
            'current_domain' => $current_domain,
            'profile' => $profile,
            'stocks' => $stocks,
            'params' => array('params' => array()),
            'data_columns' => $this->data_columns,
            'features' => $features,
            'feature_values' => $feature_values,
            'product_types' => $product_types,
            'sets' => $sets,
            'cron_str' => $cron_str,
            'plugin_version' => wa()->getPlugin('updateproducts')->getVersion(),
            'currencies' => $currencies,
        ));

    }

}
