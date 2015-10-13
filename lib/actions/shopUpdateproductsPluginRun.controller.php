<?php

class shopUpdateproductsPluginRunController extends waLongActionController {

    const STAGE_UPDATEPRODUCTS = 'updateproduct';

    protected $sheet;
    protected $features;

    public function __construct() {
        $feature_model = new shopFeatureModel();
        $this->features = $feature_model->select('`id`,`code`, `name`,`type`')->fetchAll('code', true);
    }

    protected function preExecute() {
        $this->getResponse()->addHeader('Content-type', 'application/json');
        $this->getResponse()->sendHeaders();
    }

    private $sku_columns = array(
        'sku' => 'Артикул',
        'name' => 'Наименование артикула',
        'stock' => 'Количество товара',
        'price' => 'Цена',
        'purchase_price' => 'Закупочная цена',
        'compare_price' => 'Зачеркнутая цена',
    );
    protected $steps = array(
        self::STAGE_UPDATEPRODUCTS => 'Обновление товаров',
    );

    /**
     *
     * @return shopUpdateproductsPlugin
     */
    private function plugin() {
        static $plugin;
        if (!$plugin) {
            $plugin = wa()->getPlugin('updateproducts');
        }
        return $plugin;
    }

    public function execute() {
        try {
            parent::execute();
        } catch (waException $ex) {
            if ($ex->getCode() == '302') {
                echo json_encode(array('warning' => $ex->getMessage()));
            } else {
                echo json_encode(array('error' => $ex->getMessage()));
            }
        }
    }

    protected function isDone() {
        return $this->data['offset'] >= $this->data['count'];
    }

    protected function step() {

        switch ($this->data['stage']) {
            case self::STAGE_UPDATEPRODUCTS:
                $this->updateProducts();
                break;
        }

        return true;
    }

    protected function finish($filename) {
        $this->info();
        if ($this->getRequest()->post('cleanup')) {
            //unlink($this->data['filepath']);
            return true;
        }
        return false;
    }

    protected function report() {

        $report = '<div class="successmsg"><i class="icon16 yes"></i> ' .
                'Обновлено товаров ' . $this->data['updated'] . ' из ' . $this->data['count'] . '. Товаров не найдено ' . $this->data['not_found'];
        $interval = 0;
        if (!empty($this->data['timestamp'])) {
            $interval = time() - $this->data['timestamp'];
            $interval = sprintf(_w('%02d hr %02d min %02d sec'), floor($interval / 3600), floor($interval / 60) % 60, $interval % 60);
            $report .= ' ' . sprintf(_w('(total time: %s)'), $interval);
        }
        $report .= '&nbsp;</div>';

        $params = $this->data['params'];
        if (!empty($params['report']) && !empty($params['report_email'])) {
            $subject = 'Обновление товаров';
            $body = $report;
            $to = explode(',', $params['report_email']);
            $message = new waMailMessage($subject, $body);
            $general = wa('shop')->getConfig()->getGeneralSettings();
            $message->setFrom($general['email'], $general['name']);
            $file = wa()->getTempPath('plugins/updateproducts/download/' . $params['profile_id'] . '/not_found_file.csv');
            $message->addAttachment($file);
            $message->setTo($to);
            $message->send();
        }

        return $report;
    }

    protected function info() {

        $interval = 0;
        if (!empty($this->data['timestamp'])) {
            $interval = time() - $this->data['timestamp'];
        }
        $response = array(
            'time' => sprintf('%d:%02d:%02d', floor($interval / 3600), floor($interval / 60) % 60, $interval % 60),
            'processId' => $this->processId,
            'progress' => 0.0,
            'ready' => $this->isDone(),
            'offset' => $this->data['offset'],
            'count' => $this->data['count'],
            'stage_name' => $this->steps[$this->data['stage']] . ' - ' . $this->data['offset'] . ' из ' . $this->data['count'],
            'memory' => sprintf('%0.2fMByte', $this->data['memory'] / 1048576),
            'memory_avg' => sprintf('%0.2fMByte', $this->data['memory_avg'] / 1048576),
        );
        if ($this->data['count']) {
            $response['progress'] = ($this->data['offset'] / $this->data['count']) * 100;
        }

        $response['progress'] = sprintf('%0.3f%%', $response['progress']);

        if ($this->getRequest()->post('cleanup')) {
            $response['report'] = $this->report();
        }

        echo json_encode($response);
    }

    public function getStageName($stage) {
        $name = '';
        switch ($stage) {
            case self::STAGE_UPDATEPRODUCTS:
                $name = 'Обновление товаров';
                break;
        }

        return $name;
    }

    protected function getColumnInfo($column) {

        list($type, $field) = explode(':', $column);
        if ($type == 'sku') {
            $name = $this->sku_columns[$field];
        } else {
            $name = $this->features[$field]['name'];
        }
        return array('field' => $field, 'type' => $type, 'name' => $name);
    }

    protected function parseData($prefix, $array = array()) {
        $result = array();
        foreach ($array as $key => $item) {
            if (preg_match('/' . $prefix . '(.+)/', $key, $match)) {
                $sub = $match[1];
                $result[$sub] = $item;
            }
        }
        return $result;
    }

    protected function restore() {
        $autoload = waAutoload::getInstance();
        $autoload->add('PHPExcel', "wa-apps/shop/plugins/updateproducts/lib/vendors/PHPExcel.php");
        $autoload->add('PHPExcel_IOFactory', "wa-apps/shop/plugins/updateproducts/lib/vendors/PHPExcel/IOFactory.php");

        $params = $this->data['params'];
        $filepath = $this->data['filepath'];

        $list_num = intval($params['list_num']);


        $inputFileType = PHPExcel_IOFactory::identify($filepath);
        $objReader = PHPExcel_IOFactory::createReader($inputFileType);
        $objPHPExcel = $objReader->load($filepath);
        try {
            $sheet = $objPHPExcel->getSheet($list_num - 1);
        } catch (Exception $ex) {
            $sheet_warning = '';
            if ($objPHPExcel->getSheetCount() == 1) {
                $sheet_warning = 'Доступен только лист №1';
            } elseif ($objPHPExcel->getSheetCount() > 1) {
                $sheet_warning = 'Доступены номера листов в диапазоне 1-' . $objPHPExcel->getSheetCount();
            }
            throw new waException('Ошибка. Указан неверный «Номер листа». ' . $sheet_warning);
        }

        $this->sheet = $sheet;
    }

    protected function init() {
        try {
            $backend = (wa()->getEnv() == 'backend');
            $profiles = new shopImportexportHelper('updateproducts');
            $default_export_config = array();
            if ($backend) {
                $profile_config = (array) waRequest::post('settings', array()) + $default_export_config;
                $profile_id = $profiles->setConfig($profile_config);
                $this->plugin()->getHash($profile_id);
                $options = $profile_config;
            } else {
                $profile_id = waRequest::param('profile_id');
                if (!$profile_id || !($profile = $profiles->getConfig($profile_id))) {
                    throw new waException('Profile not found', 404);
                }
                $profile_config = $profile['config'];
                $profile_config += $default_export_config;
                $options = $profile_config;
            }

            $filepath = shopUpdateproductsPlugin::getFilePath($profile_id, $profile_config);
            if (!file_exists($filepath)) {
                throw new waException('Ошибка загрузки файла');
            }

            $list_num = intval($profile_config['list_num']);
            $row_num = intval($profile_config['row_num']) > 0 ? intval($profile_config['row_num']) : 1;
            $row_count = intval($profile_config['row_count']);
            $set_product_status = $profile_config['set_product_status'];
            $set_product_null = $profile_config['set_product_null'];
            $stock_id = $profile_config['stock_id'];
            $currency = $profile_config['currency'];
            $types = $this->parseData('types_', $profile_config);
            $sets = $this->parseData('sets_', $profile_config);
            $features = $this->parseData('features_', $profile_config);

            $keysData = $this->parseData('keys_', $profile_config);
            if (!$keysData) {
                throw new waException('Ошибка. Укажите ключ для поиска соответствий.');
            }
            $keys = array();
            foreach ($keysData as $key => $checked) {
                $keys[$key] = $this->getColumnInfo($key);
            }
            $updateData = $this->parseData('update_', $profile_config);
            if (!$updateData) {
                throw new waException('Ошибка. Укажите поля для обновления.');
            }
            $update = array();
            foreach ($updateData as $key => $checked) {
                $update[$key] = $this->getColumnInfo($key);
            }

            $columnsData = $this->parseData('columns_', $profile_config);
            $columns = array();
            foreach ($columnsData as $key => $num) {
                $columns[$key] = $this->getColumnInfo($key);
                $columns[$key]['num'] = $num;
            }

            $autoload = waAutoload::getInstance();
            $autoload->add('PHPExcel', "wa-apps/shop/plugins/updateproducts/lib/vendors/PHPExcel.php");
            $autoload->add('PHPExcel_IOFactory', "wa-apps/shop/plugins/updateproducts/lib/vendors/PHPExcel/IOFactory.php");

            $inputFileType = PHPExcel_IOFactory::identify($filepath);
            $objReader = PHPExcel_IOFactory::createReader($inputFileType);
            $objPHPExcel = $objReader->load($filepath);
            try {
                $sheet = $objPHPExcel->getSheet($list_num - 1);
            } catch (Exception $ex) {
                $sheet_warning = '';
                if ($objPHPExcel->getSheetCount() == 1) {
                    $sheet_warning = 'Доступен только лист №1';
                } elseif ($objPHPExcel->getSheetCount() > 1) {
                    $sheet_warning = 'Доступены номера листов в диапазоне 1-' . $objPHPExcel->getSheetCount();
                }
                throw new waException('Ошибка. Указан неверный «Номер листа». ' . $sheet_warning);
            }

            $total_row = $sheet->getHighestRow();
            $max_count = $total_row - $row_num + 1;
            $count = $row_count ? min($row_count, $max_count) : $max_count;


            $params = array(
                'list_num' => $list_num,
                'columns' => $columns,
                'keys' => $keys,
                'update' => $update,
                'row_num' => $row_num,
                'row_count' => $row_count,
                'stock_id' => $stock_id,
                'set_product_status' => $set_product_status,
                'set_product_null' => $set_product_null,
                'types' => $types ? array_keys($types) : null,
                'sets' => $sets ? array_keys($sets) : null,
                'features' => $features ? $features : null,
                'currency' => $currency,
                'margin_type' => $profile_config['margin_type'],
                'margin' => $profile_config['margin'],
                'rounding' => $profile_config['rounding'],
                'round_precision' => $profile_config['round_precision'],
                'replace_count_search' => $profile_config['replace_count_search'],
                'replace_count_replace' => $profile_config['replace_count_replace'],
                'replace_count_infinity' => $profile_config['replace_count_infinity'],
                'report' => $profile_config['report'],
                'report_email' => $profile_config['report_email'],
                'profile_id' => $profile_id,
                'calculation_purchase_price' => $profile_config['calculation_purchase_price'],
                'purchase_margin_type' => $profile_config['purchase_margin_type'],
                'purchase_margin' => $profile_config['purchase_margin'],
            );

            if (!empty($params['sets'])) {
                $set_product_ids = array();
                foreach ($params['sets'] as $set_id) {
                    $set_product_ids = array_merge($set_product_ids, $this->getSetIds($set_id));
                }
                $set_product_ids = array_unique($set_product_ids);
            }

            $model = new waModel();
            if ($set_product_status) {
                $where = array();
                if (!empty($params['types'])) {
                    $where[] = "`type_id` IN (" . implode(',', $params['types']) . ")";
                }
                if (!empty($set_product_ids)) {
                    $where[] = "`product_id` IN (" . implode(',', $set_product_ids) . ")";
                }
                $sql = "UPDATE `shop_product` SET `status` = 0" . ($where ? " WHERE " . implode(" AND ", $where) : '');
                $model->query($sql);
            }
            if ($set_product_null) {
                $where = array();
                if (!empty($params['types'])) {
                    $where[] = "`type_id` IN (" . implode(',', $params['types']) . ")";
                }
                if (!empty($set_product_ids)) {
                    $where[] = "`product_id` IN (" . implode(',', $set_product_ids) . ")";
                }
                $sql = "UPDATE `shop_product` SET `count` = 0" . ($where ? " WHERE " . implode(" AND ", $where) : '');
                $model->query($sql);


                $where = array();
                if (!empty($params['types'])) {
                    $where[] = "`product_id` IN (SELECT `id` FROM `shop_product` WHERE `type_id` IN (" . implode(',', $params['types']) . "))";
                }
                if (!empty($set_product_ids)) {
                    $where[] = "`product_id` IN (" . implode(',', $set_product_ids) . ")";
                }
                $sql = "UPDATE `shop_product_skus` SET `count` = 0" . ($where ? " WHERE " . implode(" AND ", $where) : '');
                $model->query($sql);
            }


            $this->data['updated'] = 0;
            $this->data['not_found'] = 0;
            $this->data['params'] = $params;
            $this->data['filepath'] = $filepath;
            $this->data['offset'] = 0;
            $this->data['timestamp'] = time();
            $this->data['count'] = $count;
            $stages = array(self::STAGE_UPDATEPRODUCTS);
            $this->data['current'] = array_fill_keys($stages, 0);
            $this->data['processed_count'] = array_fill_keys($stages, 0);
            $this->data['stage'] = reset($stages);

            $this->data['error'] = null;
            $this->data['stage_name'] = $this->getStageName($this->data['stage']);
            $this->data['memory'] = memory_get_peak_usage();
            $this->data['memory_avg'] = memory_get_usage();

            $key_names = array();
            foreach ($keys as $key) {
                $key_names[] = iconv('UTF-8', 'CP1251', $key['name']);
            }
            $this->data['not_found_file'] = wa()->getTempPath('plugins/updateproducts/download/' . $profile_id . '/not_found_file.csv');
            $f = fopen($this->data['not_found_file'], 'w+');
            fputcsv($f, $key_names, ';', '"');
            fclose($f);
        } catch (waException $ex) {
            echo json_encode(array('error' => $ex->getMessage(),));
            exit;
        }
    }

    public function updateProducts() {
        $model_sku = new shopProductSkusModel();
        $params = $this->data['params'];

        $columns = $params['columns'];
        $keys = $params['keys'];
        $update = $params['update'];
        $stock_id = $params['stock_id'];
        $set_product_status = $params['set_product_status'];
        $currency = $params['currency'];
        $margin = $params['margin'];
        $margin_type = $params['margin_type'];
        $replace_count_search = !empty($params['replace_count_search']) ? $params['replace_count_search'] : array();
        $replace_count_replace = !empty($params['replace_count_replace']) ? $params['replace_count_replace'] : array();
        $replace_count_infinity = !empty($params['replace_count_infinity']) ? $params['replace_count_infinity'] : array();

        $options = array(
            'types' => $params['types'],
            'sets' => $params['sets'],
            'features' => $params['features'],
        );

        $sku = $this->getSkuByFields($columns, $keys, $options);

        if (!empty($sku)) {
            $product = new shopProduct($sku['product_id']);
        }

        if (!empty($product) && $product->id) {
            $sku_id = $sku['id'];
            $update_data = array();

            foreach ($update as $id => $item) {
                $field = $item['field'];
                $value = $this->getDataValue($id, $columns);

                if ($field == 'price' || $field == 'purchase_price' || $field == 'compare_price') {
                    $value = str_replace(' ', '', $value);
                    $value = str_replace(',', '.', $value);
                    if ($currency) {
                        $value = shop_currency($value, $currency, $product->currency, false);
                    }
                }
                if ($field == 'price') {
                    if ($margin_type == 'percent') {
                        $value += round($value * $margin / 100.00);
                    } else {
                        $value += $margin;
                    }
                }
                if ($field == 'price' && $params['calculation_purchase_price']) {
                    $purchase_price = $value;
                    if ($params['purchase_margin_type'] == 'percent') {
                        $purchase_price += round($value * $params['purchase_margin'] / 100.00);
                    } else {
                        $purchase_price += $params['purchase_margin'];
                    }
                    $update_data['purchase_price'] = $purchase_price;
                }
                
                if (!empty($params['rounding']) && ($field == 'price' || $field == 'purchase_price' || $field == 'compare_price')) {
                    switch ($params['rounding']) {
                        case 'round':
                            $value = round($value, $params['round_precision']);
                            break;
                        case 'ceil':
                            $value = ceil($value);
                            break;
                        case 'floor':
                            $value = floor($value);
                            break;
                    }
                }

                if ($field == 'stock') {
                    foreach ($replace_count_search as $replace_id => $replace_search) {
                        if (strpos($value, $replace_search) !== false) {
                            if ($replace_count_infinity[$replace_id]) {
                                $value = null;
                            } else {
                                $value = str_replace($replace_search, $replace_count_replace[$replace_id], $value);
                            }
                        }
                    }
                    $update_data[$field] = $this->getStocks($sku_id);
                    $update_data[$field][$stock_id] = $value;
                } else {
                    $update_data[$field] = $value;
                }
            }

            $product_model = new shopProductModel();
            $data = $product_model->getById($sku['product_id']);
            $data['skus'] = $model_sku->getDataByProductId($sku['product_id'], true);
            $data['skus'] = $this->prepareSkus($data['skus']);
            $data['skus'][$sku_id] = array_merge($data['skus'][$sku_id], $update_data);
            if ($set_product_status) {
                $data['status'] = $this->inStock($data['skus']) ? 1 : 0;
            }
            $product->save($data, true);
            if ($this->isDebug()) {
                waLog::log("\"" . $product->name . "\" обновлен", 'updateproduct.log');
            }
            $this->data['updated'] ++;
        } else {
            $not_found_file = array();
            foreach ($keys as $id => $key) {
                if ($key['type'] == 'sku') {
                    $value = $this->getDataValue($id, $columns);
                }
                if (trim($value)) {
                    $not_found_file[] = $value;
                }
            }
            if ($not_found_file) {
                $f = fopen($this->data['not_found_file'], 'a');
                fputcsv($f, $not_found_file, ';', '"');
                fclose($f);
            }
            if ($this->isDebug()) {
                waLog::log("\"" . implode(';', $not_found_file) . "\" не найден", 'updateproduct.log');
            }
            $this->data['not_found'] ++;
        }

        $this->data['offset'] ++;
    }

    protected function getSetIds($set_id) {
        $collection = new shopProductsCollection('set/' . $set_id);
        $products = $collection->getProducts('*', 0, null, true);
        return array_keys($products);
    }

    protected function getSkuByFields($columns, $keys, $options = null) {
        $feature_model = new shopFeatureModel();
        $types = !empty($options['types']) ? $options['types'] : null;
        $sets = !empty($options['sets']) ? $options['sets'] : null;
        $features = !empty($options['features']) ? $options['features'] : null;

        $model = new waModel();
        $where = array();

        foreach ($keys as $id => $key) {
            $value = $this->getDataValue($id, $columns);
            if (empty($value)) {
                continue;
            }
            if ($key['type'] == 'sku') {
                $where[] = "`shop_product_skus`.`" . $key['field'] . "` = '" . $model->escape($value) . "'";
            } elseif ($key['type'] == 'feature') {
                $feature = $feature_model->getByField('code', $key['field']);
                $type = explode('.', $feature['type']);
                $table = 'shop_feature_values_' . $type[0];
                $where[] = "`shop_product_skus`.`product_id` IN (SELECT `product_id` FROM `shop_product_features` WHERE `shop_product_features`.`feature_value_id` IN (
                                SELECT `id` FROM `" . $table . "` WHERE `value` = '" . $model->escape($value) . "'
                                AND `feature_id` = '" . $feature['id'] . "'
                                ))";
            }
        }
        if ($this->isDebug()) {
            waLog::log("Where " . implode(' AND ', $where), 'updateproduct.log');
        }

        $result = array();
        if ($where) {
            if ($sets) {
                $set_product_ids = array();
                foreach ($sets as $set_id) {
                    $set_product_ids = array_merge($set_product_ids, $this->getSetIds($set_id));
                }
                $set_product_ids = array_unique($set_product_ids);
                if ($set_product_ids) {
                    $where[] = "`shop_product_skus`.`product_id` IN (" . implode(',', $set_product_ids) . ")";
                }
            }
            if ($types) {
                $where[] = "`shop_product_skus`.`product_id` IN (
                                SELECT `id` FROM `shop_product` 
                                WHERE `shop_product`.`type_id` IN (" . implode(',', $types) . ")
                            )";
            }

            if ($features) {
                foreach ($features as $feature_id => $values) {
                    $where[] = "`shop_product_skus`.`product_id` IN (
                                    SELECT `product_id`
                                    FROM `shop_product_features`
                                    WHERE  `feature_id` = '" . $feature_id . "' AND `feature_value_id` IN (" . implode(',', $values) . ")
                            )";
                }
            }
            $sql = "SELECT `shop_product_skus`.*
                FROM `shop_product_skus`
                WHERE " . implode(' AND ', $where) . " LIMIT 1";

            $result = $model->query($sql)->fetch();
            if ($this->isDebug()) {
                waLog::log("SQL " . $sql, 'updateproduct.log');
                waLog::log("Count Products: " . count($result), 'updateproduct.log');
            }
        }

        return $result;
    }

    protected function getDataValue($id, $columns) {
        $params = $this->data['params'];
        $row_num = $params['row_num'];
        $column = $columns[$id];

        $iteration = $row_num + $this->data['offset'];

        $value = null;
        try {
            if (is_numeric($column['num'])) {
                $value = trim($this->sheet->getCellByColumnAndRow($column['num'] - 1, $iteration)->getValue());
            } else {
                $cell = $column['num'] . $iteration;
                $value = trim($this->sheet->getCell($cell)->getValue());
            }
        } catch (Exception $ex) {
            
        }
        return $value;
    }

    protected function getStocks($sku_id) {
        $stocks = array();
        $product_stocks = new shopProductStocksModel();
        $return = $product_stocks->getBySkuId($sku_id);

        if (isset($return[$sku_id])) {
            foreach ($return[$sku_id] as $stock_id => $stock) {
                $stocks[$stock_id] = is_null($stock['count']) ? '' : $stock['count'];
            }
        }

        return $stocks;
    }

    protected function prepareSkus($skus) {
        foreach ($skus as &$sku) {
            if (!$sku['stock']) {
                $sku['stock'][0] = is_null($sku['count']) ? '' : $sku['count'];
            } else {
                foreach ($sku['stock'] as &$stock) {
                    $stock = is_null($stock) ? '' : $stock;
                }
            }
        }
        return $skus;
    }

    protected function inStock($skus) {
        $in_stock = false;
        foreach ($skus as $sku) {
            foreach ($sku['stock'] as $stock) {
                if (is_null($stock) || $stock > 0) {
                    $in_stock = true;
                }
            }
        }
        return $in_stock;
    }

    protected function isDebug() {
        return waSystemConfig::isDebug();
    }

}
