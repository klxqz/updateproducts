<?php

class shopUpdateproductsPluginRunController extends waLongActionController {

    const STAGE_RECALCULATION = 'recalculation';
    const STAGE_UPDATEPRODUCTS = 'updateproduct';

    protected $sheet;

    protected function preExecute() {
        $this->getResponse()->addHeader('Content-type', 'application/json');
        $this->getResponse()->sendHeaders();
    }

    protected $steps = array(
        self::STAGE_RECALCULATION => 'Пересчет количества товаров',
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
        error_reporting(E_ALL);
        ini_set('display_errors', 'On');
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
        $done = true;
        foreach ($this->data['current'] as $stage => $current) {
            if ($current < $this->data['count'][$stage]) {
                $done = false;
                break;
            }
        }
        return $done;
    }

    private function getNextStep($current_key) {
        $array_keys = array_keys($this->steps);
        $current_key_index = array_search($current_key, $array_keys);
        if (isset($array_keys[$current_key_index + 1])) {
            return $array_keys[$current_key_index + 1];
        } else {
            return false;
        }
    }

    protected function step() {
        $stage = $this->data['stage'];
        if ($this->data['current'][$stage] >= $this->data['count'][$stage]) {
            $this->data['stage'] = $this->getNextStep($this->data['stage']);
        }

        switch ($this->data['stage']) {
            case self::STAGE_RECALCULATION:
                $this->recalculationProducts();
                break;
            case self::STAGE_UPDATEPRODUCTS:
                $this->updateProducts();
                break;
        }

        return true;
    }

    protected function finish($filename) {
        $this->info();
        if ($this->getRequest()->post('cleanup')) {
            @unlink($this->data['filepath']);
            $sql = "DROP TABLE IF EXISTS `shop_updateproduct_tmp_" . $this->data['profile_config']['profile_id'] . "`";
            $model = new waModel();
            $model->exec($sql);
            return true;
        }
        return false;
    }

    protected function report() {
        $report = '<div class="successmsg"><i class="icon16 yes"></i> ' .
                'Обновлено товаров ' . $this->data['updated'] . ' из ' . $this->data['count'][self::STAGE_UPDATEPRODUCTS] . '. Товаров не найдено ' . $this->data['not_found'];
        $interval = 0;
        if (!empty($this->data['timestamp'])) {
            $interval = time() - $this->data['timestamp'];
            $interval = sprintf(_w('%02d hr %02d min %02d sec'), floor($interval / 3600), floor($interval / 60) % 60, $interval % 60);
            $report .= ' ' . sprintf(_w('(total time: %s)'), $interval);
        }
        $report .= '&nbsp;</div>';

        $profile_config = $this->data['profile_config'];
        if (!empty($profile_config['report']['report']) && !empty($profile_config['report']['report_email'])) {
            $subject = 'Обновление товаров. Профиль №' . $profile_config['profile_id'];
            $body = $report;
            $to = explode(',', $profile_config['report']['report_email']);
            $message = new waMailMessage($subject, $body);
            $general = wa('shop')->getConfig()->getGeneralSettings();
            $message->setFrom($general['email'], $general['name']);
            $file = wa()->getTempPath('plugins/updateproducts/download/' . $profile_config['profile_id'] . '/not_found_file.csv');
            if (file_exists($file)) {
                $message->addAttachment($file);
            }
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
        $stage = $this->data['stage'];
        $response = array(
            'time' => sprintf('%d:%02d:%02d', floor($interval / 3600), floor($interval / 60) % 60, $interval % 60),
            'processId' => $this->processId,
            'progress' => 0.0,
            'ready' => $this->isDone(),
            'offset' => $this->data['current'][$stage],
            'count' => $this->data['count'][$stage],
            'stage_name' => $this->steps[$this->data['stage']] . ' - ' . $this->data['current'][$stage] . ' из ' . $this->data['count'][$stage],
            'memory' => sprintf('%0.2fMByte', $this->data['memory'] / 1048576),
            'memory_avg' => sprintf('%0.2fMByte', $this->data['memory_avg'] / 1048576),
        );

        if ($this->data['count'][$stage]) {
            $response['progress'] = ($this->data['current'][$stage] / $this->data['count'][$stage]) * 100;
        }

        $response['progress'] = sprintf('%0.3f%%', $response['progress']);

        if ($this->getRequest()->post('cleanup')) {
            $response['report'] = $this->report();
        }

        echo json_encode($response);
    }

    protected function restore() {
        $this->sheet = $this->getExcelSheet($this->data['filepath'], $this->data['profile_config']);
    }

    private function getExcelSheet($filepath, $profile_config) {
        if (!file_exists($filepath)) {
            throw new waException('Ошибка загрузки файла');
        }

        $autoload = waAutoload::getInstance();
        $autoload->add('PHPExcel_IOFactory', "wa-apps/shop/plugins/updateproducts/lib/vendors/PHPExcel/IOFactory.php");

        if ($profile_config['file']['file_format'] == 'csv') {
            $objReader = PHPExcel_IOFactory::createReader('CSV');
            $objReader->setInputEncoding($profile_config['file']['csv_encoding']);
            $objReader->setDelimiter($profile_config['file']['csv_delimiter']);
            $objReader->setEnclosure($profile_config['file']['csv_enclosure']);
        } else {
            $inputFileType = PHPExcel_IOFactory::identify($filepath);
            $objReader = PHPExcel_IOFactory::createReader($inputFileType);
        }
        $objPHPExcel = $objReader->load($filepath);
        try {
            $sheet = $objPHPExcel->getSheet($profile_config['price_list']['list_num'] - 1);
        } catch (Exception $ex) {
            $sheet_warning = '';
            if ($objPHPExcel->getSheetCount() == 1) {
                $sheet_warning = 'Доступен только лист №1';
            } elseif ($objPHPExcel->getSheetCount() > 1) {
                $sheet_warning = 'Доступены номера листов в диапазоне 1-' . $objPHPExcel->getSheetCount();
            }
            throw new waException('Ошибка. Указан неверный «Номер листа». ' . $sheet_warning);
        }
        return $sheet;
    }

    protected function init() {
        try {
            $backend = (wa()->getEnv() == 'backend');
            $profiles = new shopImportexportHelper('updateproducts');
            if ($backend) {
                $profile_config = (array) waRequest::post('settings', array());
                $profile_id = $profiles->setConfig($profile_config);
                $this->plugin()->getHash($profile_id);
            } else {
                $profile_id = waRequest::param('profile_id');
                if (!$profile_id || !($profile = $profiles->getConfig($profile_id))) {
                    throw new waException('Profile not found', 404);
                }
                $profile_config = $profile['config'];
            }
            $profile_config['profile_id'] = $profile_id;

            $filepath = shopUpdateproductsPlugin::getFilePath($profile_id, $profile_config['file']);
            if (!file_exists($filepath)) {
                throw new waException('Ошибка загрузки файла');
            }

            if (empty($profile_config['map']['keys'])) {
                throw new waException('Ошибка. Укажите ключ для поиска соответствий.');
            }
            if (empty($profile_config['map']['update'])) {
                throw new waException('Ошибка. Укажите поля для обновления.');
            }

            $columns = array();
            foreach ($profile_config['map']['columns'] as $key => $num) {
                if ($num) {
                    $columns[$key] = $this->getColumnInfo($key);
                    $columns[$key]['num'] = $num;
                }
            }
            if (empty($columns)) {
                throw new waException('Ошибка. Укажите номера столбцов.');
            }
            $profile_config['map']['columns'] = $columns;

            $keys = array();
            foreach ($profile_config['map']['keys'] as $key => $checked) {
                $keys[$key] = $this->getColumnInfo($key);
            }
            $profile_config['map']['keys'] = $keys;

            $update = array();
            foreach ($profile_config['map']['update'] as $key => $checked) {
                $update[$key] = $this->getColumnInfo($key);
            }
            $profile_config['map']['update'] = $update;


            $sheet = $this->getExcelSheet($filepath, $profile_config);

            $total_row = $sheet->getHighestRow();
            $max_count = $total_row - $profile_config['price_list']['row_num'] + 1;
            $count = $profile_config['price_list']['row_count'] ? min($profile_config['price_list']['row_count'], $max_count) : $max_count;


            $this->data['profile_config'] = $profile_config;

            $this->createTmpTable();

            $model = new waModel();
            if (!empty($profile_config['update']['set_product_status'])) {
                $sql = "UPDATE `shop_product` SET `status` = 0 WHERE `id` IN (SELECT `id` FROM `shop_updateproduct_tmp_" . $profile_config['profile_id'] . "`)";
                $model->exec($sql);
            }

            if (!empty($profile_config['update']['set_product_null'])) {
                $sql = "SELECT COUNT(`id`) as `count` FROM `shop_product_skus` WHERE `product_id` IN (SELECT `id` FROM `shop_updateproduct_tmp_" . $profile_config['profile_id'] . "`)";
                $recal_count = $model->query($sql)->fetchField();
            }

            $this->data['updated'] = 0;
            $this->data['not_found'] = 0;

            $this->data['filepath'] = $filepath;
            $this->data['timestamp'] = time();
            $this->data['count'] = array(
                self::STAGE_RECALCULATION => !empty($recal_count) ? $recal_count : 0,
                self::STAGE_UPDATEPRODUCTS => $count,
            );
            $stages = array_keys($this->steps);
            $this->data['current'] = array_fill_keys($stages, 0);
            $this->data['processed_count'] = array_fill_keys($stages, 0);
            $this->data['stage'] = reset($stages);

            $this->data['error'] = null;
            $this->data['stage_name'] = $this->steps[$this->data['stage']];
            $this->data['memory'] = memory_get_peak_usage();
            $this->data['memory_avg'] = memory_get_usage();

            $this->data['not_found_file'] = wa()->getTempPath('plugins/updateproducts/download/' . $profile_id . '/not_found_file.csv');
            if (file_exists($this->data['not_found_file'])) {
                @unlink($this->data['not_found_file']);
            }
            $f = fopen($this->data['not_found_file'], 'w+');
            fclose($f);
        } catch (waException $ex) {
            echo json_encode(array('error' => $ex->getMessage(),));
            exit;
        }
    }

    private function createTmpTable() {
        $profile_config = $this->data['profile_config'];

        $model = new waModel();
        $sql = "DROP TABLE IF EXISTS `shop_updateproduct_tmp_" . $profile_config['profile_id'] . "`";
        $model->exec($sql);

        $sql = "CREATE TABLE `shop_updateproduct_tmp_" . $profile_config['profile_id'] . "` SELECT `id` FROM `shop_product`";

        $where = array();
        if (!empty($profile_config['filter']['types'])) {
            $where[] = "`type_id` IN (" . implode(',', array_keys($profile_config['filter']['types'])) . ")";
        }
        if (!empty($profile_config['filter']['sets'])) {
            $sets = array_keys($profile_config['filter']['sets']);
            $set_product_ids = array();
            foreach ($sets as $set_id) {
                $set_product_ids = array_merge($set_product_ids, $this->getSetIds($set_id));
            }
            $set_product_ids = array_unique($set_product_ids);
            $where[] = "`id` IN (" . implode(',', $set_product_ids) . ")";
        }
        if (!empty($profile_config['filter']['features'])) {
            foreach ($profile_config['filter']['features'] as $feature_id => $values) {
                $where[] = "`id` IN (
                                    SELECT `product_id`
                                    FROM `shop_product_features`
                                    WHERE  `feature_id` = '" . $feature_id . "' AND `feature_value_id` IN (" . implode(',', $values) . ")
                            )";
            }
        }
        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $model->exec($sql);
    }

    public function recalculationProducts() {
        $model = new waModel();
        $profile_config = $this->data['profile_config'];
        $stock_id = $profile_config['update']['stock_id'];

        $offet = $this->data['current'][self::STAGE_RECALCULATION];
        $sql = "SELECT * FROM `shop_product_skus` WHERE `product_id` IN (SELECT `id` FROM `shop_updateproduct_tmp_" . $profile_config['profile_id'] . "`) LIMIT $offet,1";
        $sku = $model->query($sql)->fetchAssoc();


        $product_stocks_model = new shopProductStocksModel();
        $update = false;
        if ($stock_id && $product_stocks_model->hasAnyStocks($sku['id'])) {
            $data = array(
                'sku_id' => $sku['id'],
                'stock_id' => $stock_id,
                'product_id' => $sku['product_id'],
                'count' => 0,
            );
            $product_stocks_model->set($data);

            if (!$this->isStockInfinity($sku['id'])) {
                $sql = "UPDATE `shop_product_skus` sk JOIN (
                            SELECT sk.id, SUM(st.count) AS count FROM `shop_product_skus` sk
                            JOIN `shop_product_stocks` st ON sk.id = st.sku_id
                            WHERE sk.id = " . $sku['id'] . "
                            GROUP BY sk.id
                            ORDER BY sk.id
                        ) r ON sk.id = r.id
                        SET sk.count = r.count";
                $model->exec($sql);
                $update = true;
            }
        } elseif (!$stock_id && !$product_stocks_model->hasAnyStocks($sku['id'])) {
            $sql = "UPDATE `shop_product_skus` SET `count` = 0 WHERE `id` = '" . $sku['id'] . "'";
            $model->exec($sql);
            $update = true;
        }
        $sql = "SELECT `id` FROM `shop_product_skus` WHERE `product_id` = '" . $sku['product_id'] . "' AND `count` IS NULL";
        if ($update && !$model->query($sql)->fetchField()) {
            $sql = "UPDATE `shop_product` p JOIN (
                            SELECT p.id, SUM(sk.count) AS count FROM `shop_product` p
                            JOIN `shop_product_skus` sk ON p.id = sk.product_id
                            WHERE p.id = " . $sku['product_id'] . "
                            GROUP BY p.id
                            ORDER BY p.id
                        ) r ON p.id = r.id
                        SET p.count = r.count";
            $model->exec($sql);
        }

        $this->data['current'][self::STAGE_RECALCULATION] ++;
    }

    private function isStockInfinity($sku_id) {
        $product_stocks_model = new shopProductStocksModel();
        $stocks = $product_stocks_model->getBySkuId($sku_id);
        if (!empty($stocks[$sku_id])) {
            foreach ($stocks[$sku_id] as $stock) {
                if ($stock['count'] === null) {
                    return TRUE;
                }
            }
        }
        return FALSE;
    }

    private function getMarginPrice($value) {
        $profile_config = $this->data['profile_config'];
        if (!empty($profile_config['update']['margin_sum'])) {
            arsort($profile_config['update']['margin_sum']);
            foreach ($profile_config['update']['margin_sum'] as $margin_id => $margin_sum) {
                if ($value >= $margin_sum) {
                    $margin_type = $profile_config['update']['margin_type'][$margin_id];
                    $margin_value = $profile_config['update']['margin_value'][$margin_id];
                    if ($margin_type == 'absolute') {
                        $value += $margin_value;
                    } elseif ($margin_type == 'percent') {
                        $value += round($value * $margin_value / 100.00, 4);
                    }
                    break;
                }
            }
        }
        return $value;
    }

    public function updateProducts() {
        $model_sku = new shopProductSkusModel();
        $profile_config = $this->data['profile_config'];

        $keys = $profile_config['map']['keys'];
        $update = $profile_config['map']['update'];

        $sku = $this->getSkuByFields();

        if (!empty($sku)) {
            $product = new shopProduct($sku['product_id']);
        }

        if (!empty($product) && $product->id) {
            $sku_id = $sku['id'];
            $update_data = array();

            foreach ($update as $id => $item) {
                $field = $item['field'];
                $value = $this->getDataValue($id);

                if ($field == 'price' || $field == 'purchase_price' || $field == 'compare_price') {
                    $value = str_replace(' ', '', $value);
                    $value = str_replace(',', '.', $value);
                    if ($profile_config['update']['currency']) {
                        $value = shop_currency($value, $profile_config['update']['currency'], $product->currency, false);
                    }
                }
                if ($field == 'price') {
                    $value = $this->getMarginPrice($value);
                }
                if ($field == 'price' && $profile_config['update']['calculation_purchase_price']) {
                    $purchase_price = $value;
                    if ($profile_config['update']['purchase_margin_type'] == 'percent') {
                        $purchase_price += round($value * $profile_config['update']['purchase_margin'] / 100.00, 4);
                    } else {
                        $purchase_price += $profile_config['update']['purchase_margin'];
                    }
                    $update_data['purchase_price'] = $purchase_price;
                }

                if (!empty($profile_config['update']['rounding']) && ($field == 'price' || $field == 'purchase_price' || $field == 'compare_price')) {
                    switch ($profile_config['update']['rounding']) {
                        case 'round':
                            $value = round($value, $profile_config['update']['round_precision']);
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
                    if (!empty($profile_config['update']['replace_count_search'])) {
                        foreach ($profile_config['update']['replace_count_search'] as $replace_id => $replace_search) {
                            if ($replace_search && strpos($value, $replace_search) !== false) {
                                if (!empty($profile_config['update']['replace_count_infinity'][$replace_id])) {
                                    $value = null;
                                } elseif (isset($profile_config['update']['replace_count_replace'][$replace_id])) {
                                    $value = str_replace($replace_search, $profile_config['update']['replace_count_replace'][$replace_id], $value);
                                }
                            }
                        }
                    }
                    $update_data[$field] = $this->getStocks($sku_id);
                    $stock_id = $profile_config['update']['stock_id'];
                    $update_data[$field][$stock_id] = $value;
                } elseif ($item['type'] == 'sku') {
                    $update_data[$field] = $value;
                } elseif ($item['type'] == 'feature') {
                    $update_data['feature'][$field] = $value;
                }
            }

            $product_model = new shopProductModel();
            $data = $product_model->getById($sku['product_id']);
            $data['skus'] = $model_sku->getDataByProductId($sku['product_id'], true);
            $data['skus'] = $this->prepareSkus($data['skus']);
            $data['skus'][$sku_id] = array_merge($data['skus'][$sku_id], $update_data);
            if ($profile_config['update']['set_product_status']) {
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
                $value = $this->getDataValue($id);
                if (trim($value)) {
                    $not_found_file[] = $value;
                }
            }
            if ($not_found_file) {
                $f = fopen($this->data['not_found_file'], 'a');
                fputcsv($f, $this->getDataRow(), ';', '"');
                fclose($f);
            }
            if ($this->isDebug()) {
                waLog::log("\"" . implode(';', $not_found_file) . "\" не найден", 'updateproduct.log');
            }
            $this->data['not_found'] ++;
        }
        $this->data['current'][self::STAGE_UPDATEPRODUCTS] ++;
    }

    protected function getSetIds($set_id) {
        $collection = new shopProductsCollection('set/' . $set_id);
        $products = $collection->getProducts('*', 0, 99999, true);
        return array_keys($products);
    }

    protected function getColumnInfo($column) {
        list($type, $field) = explode(':', $column);
        return array('field' => $field, 'type' => $type);
    }

    private function getSkuByFields() {
        $profile_config = $this->data['profile_config'];
        $keys = $profile_config['map']['keys'];

        $feature_model = new shopFeatureModel();

        $model = new waModel();
        $where = array();

        foreach ($keys as $id => $key) {
            $value = $this->getDataValue($id);
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
                                ) AND `shop_product_features`.`feature_id` = '" . $feature['id'] . "')";
            }
        }

        $result = array();
        if ($where) {
            if (!empty($profile_config['filter'])) {
                $where[] = "`shop_product_skus`.`product_id` IN (SELECT `id` FROM `shop_updateproduct_tmp_" . $profile_config['profile_id'] . "`)";
            }

            $sql = "SELECT `shop_product_skus`.*
                FROM `shop_product_skus`
                WHERE " . implode(' AND ', $where) . " LIMIT 1";

            $result = $model->query($sql)->fetchAssoc();
        }

        return $result;
    }

    protected function getDataRow() {
        $row = array();
        $profile_config = $this->data['profile_config'];

        $iteration = $profile_config['price_list']['row_num'] + $this->data['current'][self::STAGE_UPDATEPRODUCTS];
        $highestColumn = $this->sheet->getHighestColumn();
        $highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);

        for ($i = 0; $i < $highestColumnIndex; $i++) {
            $row[] = iconv('UTF-8', 'CP1251', trim($this->sheet->getCellByColumnAndRow($i, $iteration)->getValue()));
        }
        return $row;
    }

    protected function getDataValue($key) {
        $profile_config = $this->data['profile_config'];
        $column = $profile_config['map']['columns'][$key];

        $iteration = $profile_config['price_list']['row_num'] + $this->data['current'][self::STAGE_UPDATEPRODUCTS];

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
