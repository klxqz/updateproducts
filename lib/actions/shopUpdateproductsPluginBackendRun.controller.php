<?php

class shopUpdateproductsPluginBackendRunController extends waLongActionController {

    protected $plugin_id = array('shop', 'updateproducts');
    protected $require_path = 'plugins/updateproducts/lib/vendors/excel_reader2.php';
    protected $steps = array(
        'updateProducts' => 'Обновление товаров',
    );
    protected $sku_columns = array(
        'sku' => 'Артикул',
        'name' => 'Наименование артикула',
        'stock' => 'Количество товара',
        'price' => 'Цена',
        'purchase_price' => 'Закупочная цена',
        'compare_price' => 'Зачеркнутая цена',
    );
    protected $features;

    public function __construct() {
        $feature_model = new shopFeatureModel();
        $this->features = $feature_model->select('`id`,`code`, `name`,`type`')->fetchAll('code', true);
    }

    public function getNextStep($current_key) {
        $array_keys = array_keys($this->steps);
        $current_key_index = array_search($current_key, $array_keys);
        if (isset($array_keys[$current_key_index + 1])) {
            return $array_keys[$current_key_index + 1];
        } else {
            return false;
        }
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

    protected function finish($filename) {
        $this->info();
        if ($this->getRequest()->post('cleanup')) {
            unlink($this->data['filepath']);
            return true;
        }
        return false;
    }

    protected function init() {
        $filepath = wa()->getCachePath('plugins/updateproducts/file.xls');
        if (!file_exists($filepath)) {
            throw new waException('Ошибка загрузки файла');
        }
        $shop_updateproducts = waRequest::post('shop_updateproducts', array());
        $app_settings_model = new waAppSettingsModel();
        foreach ($shop_updateproducts as $name => $val) {
            $app_settings_model->set($this->plugin_id, $name, $val);
        }
        $product_number = intval($shop_updateproducts['product_number']) > 0 ? intval($shop_updateproducts['product_number']) : 10;
        $list_num = intval($shop_updateproducts['list_num']);
        $row_num = intval($shop_updateproducts['row_num']) > 0 ? intval($shop_updateproducts['row_num']) : 1;
        $row_count = intval($shop_updateproducts['row_count']);
        $set_product_status = $shop_updateproducts['set_product_status'];
        $set_product_null = $shop_updateproducts['set_product_null'];
        $stock_id = $shop_updateproducts['stock_id'];
        $currency = $shop_updateproducts['currency'];
        $types = $this->parseData('types_', $shop_updateproducts);
        $keysData = $this->parseData('keys_', $shop_updateproducts);
        if (!$keysData) {
            throw new waException('Ошибка. Укажите ключ для поиска соответствий.');
        }
        $keys = array();
        foreach ($keysData as $key => $checked) {
            $keys[$key] = $this->getColumnInfo($key);
        }
        $updateData = $this->parseData('update_', $shop_updateproducts);
        if (!$updateData) {
            throw new waException('Ошибка. Укажите поля для обновления.');
        }
        $update = array();
        foreach ($updateData as $key => $checked) {
            $update[$key] = $this->getColumnInfo($key);
        }
        $columnsData = $this->parseData('columns_', $shop_updateproducts);
        $columns = array();
        foreach ($columnsData as $key => $num) {
            $columns[$key] = $this->getColumnInfo($key);
            $columns[$key]['num'] = $num;
        }

        $require = wa()->getAppPath($this->require_path, 'shop');
        require($require);
        $data = new Spreadsheet_Excel_Reader();
        $data->setOutputEncoding('UTF-8');
        @$data->read($filepath);
        if ($list_num > 0 && isset($data->sheets[$list_num - 1])) {
            $list = $data->sheets[$list_num - 1];
        } else {
            throw new waException('Ошибка. Указан не верный лист в XLS.');
        }

        $params = array(
            'product_number' => $product_number,
            'list' => &$list,
            'columns' => $columns,
            'keys' => $keys,
            'update' => $update,
            'row_num' => $row_num,
            'row_count' => $row_count,
            'stock_id' => $stock_id,
            'set_product_status' => $set_product_status,
            'set_product_null' => $set_product_null,
            'types' => $types ? array_keys($types) : null,
            'currency' => $currency,
        );

        $model = new waModel();
        if ($set_product_status) {
            $sql = "UPDATE `shop_product` SET `status` = 0";
            $model->query($sql);
        }
        if ($set_product_null) {
            $sql = "UPDATE `shop_product` SET `count` = 0";
            $model->query($sql);
            $sql = "UPDATE `shop_product_skus` SET `count` = 0";
            $model->query($sql);
        }

        $max_count = $list['numRows'] - $row_num + 1;
        $count = $row_count ? min($row_count, $max_count) : $max_count;

        $this->data['params'] = $params;
        $this->data['updated'] = 0;
        $this->data['not_found'] = 0;
        $this->data['offset'] = 0;
        $this->data['count'] = $count;
        $this->data['filepath'] = $filepath;
        $this->data['memory'] = memory_get_peak_usage();
        $this->data['memory_avg'] = memory_get_usage();

        $key_names = array();
        foreach ($keys as $key) {
            $key_names[] = iconv('UTF-8', 'CP1251', $key['name']);
        }

        $this->data['not_found_file'] = wa()->getDataPath('plugins/updateproducts/not_found_file.csv', true, 'shop');
        $f = fopen($this->data['not_found_file'], 'w+');
        fputcsv($f, $key_names, ';', '"');
        fclose($f);
        $this->data['step'] = array_shift(array_keys($this->steps));

        $this->data['timestamp'] = time();
    }

    public function updateProducts() {
        $params = $this->data['params'];
        $product_number = $params['product_number'];
        $list = $params['list'];
        $columns = $params['columns'];
        $keys = $params['keys'];
        $update = $params['update'];
        $row_num = $params['row_num'];
        $row_count = $params['row_count'];
        $stock_id = $params['stock_id'];
        $set_product_status = $params['set_product_status'];
        $types = $params['types'];
        $currency = $params['currency'];

        $model_sku = new shopProductSkusModel();
        $feature_model = new shopFeatureModel();
        
        for ($i = 0; $i < $product_number; $i++) {
            if ($this->data['offset'] >= $this->data['count']) {
                return;
            }

            if (!empty($list['cells'][$row_num + $this->data['offset']])) {
                $dataRow = $list['cells'][$row_num + $this->data['offset']];
                $skus = $this->getSkuByFields($dataRow, $columns, $keys, $types);
            }

            if (!empty($skus)) {
                foreach ($skus as $sku) {
                    $product = new shopProduct($sku['product_id']);
                    $sku_id = $sku['id'];
                    $update_data = array();
                    foreach ($update as $id => $item) {
                        $field = $item['field'];
                        $value = $this->getDataValue($id, $dataRow, $columns);
                        if ($currency && ($field == 'price' || $field == 'purchase_price' || $field == 'compare_price')) {
                            $value = shop_currency($value, $currency, $product->currency, false);
                        }
                        if ($field == 'stock') {
                            $update_data[$field] = $this->getStocks($sku_id);
                            $update_data[$field][$stock_id] = ($value !== false ? $value : 0);
                        } else {
                            $update_data[$field] = ($value !== false ? $value : null);
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
                    waLog::log("\"" . $product->name . "\" обновлен", 'updateproduct.log');
                    $this->data['updated'] ++;
                }
            } else {
                $not_found_file = array();
                foreach ($keys as $id => $key) {
                    if ($key['type'] == 'sku') {
                        $value = $this->getDataValue($id, $dataRow, $columns);
                    } elseif ($key['type'] == 'feature') {
                        $feature = $feature_model->getByField('code', $key['field']);
                        $type = explode('.', $feature['type']);
                        $table = 'shop_feature_values_' . $type[0];
                        $value = $this->getDataValue($id, $dataRow, $columns);
                    }
                    $not_found_file[] = $value;
                }
                $f = fopen($this->data['not_found_file'], 'a');
                fputcsv($f, $not_found_file, ';', '"');
                fclose($f);
                $this->data['not_found'] ++;
            }
            $this->data['offset'] ++;
        }
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

    protected function isDone() {
        return $this->data['offset'] >= $this->data['count'];
    }

    protected function step() {
        switch ($this->data['step']) {
            case 'updateProducts':
                $this->updateProducts();
                break;
        }
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
            'step' => $this->steps[$this->data['step']] . ' - ' . $this->data['offset'] . ' из ' . $this->data['count'],
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
        $not_found_file = wa()->getDataUrl('plugins/updateproducts/not_found_file.csv', true, 'shop');
        $report .= '<a href="' . $not_found_file . '">Ненайденные товары</a>';
        return $report;
    }

    protected function getSkuByFields($dataRow, $columns, $keys, $types = null) {
        $model = new waModel();
        $feature_model = new shopFeatureModel();
        $where = array();
        $join = false;
        foreach ($keys as $id => $key) {
            if ($key['type'] == 'sku') {
                $value = $this->getDataValue($id, $dataRow, $columns);
                $condition = "`shop_product_skus`.`" . $key['field'] . "` = '" . $model->escape($value) . "'";
                $where[] = $condition;
            } elseif ($key['type'] == 'feature') {
                $feature = $feature_model->getByField('code', $key['field']);
                $type = explode('.', $feature['type']);
                $table = 'shop_feature_values_' . $type[0];
                $value = $this->getDataValue($id, $dataRow, $columns);
                $condition = "`shop_product_features`.`feature_value_id` IN ("
                        . "SELECT `id` FROM `" . $table . "` WHERE `value` = '" . $model->escape($value) . "'"
                        . " AND `feature_id` = '" . $feature['id'] . "'"
                        . ")";
                $where[] = $condition;
                $join = true;
            }
        }
        $sql = "SELECT `shop_product_skus`.*
                FROM `shop_product_skus`
                " . ($types ? "LEFT JOIN `shop_product` ON `shop_product_skus`.`product_id` = `shop_product`.`id`" : "") . " 
                " . ($join ? "LEFT JOIN `shop_product_features` ON `shop_product_skus`.`id` = `shop_product_features`.`sku_id`" : "") . "
                WHERE " . ($types ? "`shop_product`.`type_id` IN (" . implode(',', $types) . ") AND " : "") . " " . implode(' AND ', $where);

        $result = $model->query($sql)->fetchAll();
        return $result;
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

    protected function parseData($prefix, $array) {
        $result = array();
        foreach ($array as $key => $item) {
            if (preg_match('/' . $prefix . '(.+)/', $key, $match)) {
                $sub = $match[1];
                $result[$sub] = $item;
            }
        }
        return $result;
    }

    protected function getDataValue($key, $dataRow, $columns) {
        $column = $columns[$key];
        $num = $column['num'];
        if (isset($dataRow[$num])) {
            return $dataRow[$num];
        } else {
            return false;
        }
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

    protected function getModel($modelname) {
        if (!$this->$modelname) {
            $this->$modelname = new $modelname();
        }
        return $this->$modelname;
    }

}
