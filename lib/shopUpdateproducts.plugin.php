<?php

class shopUpdateproductsPlugin extends shopPlugin {

    protected $log_path = 'shop/plugins/updateproducts.log';

    protected function log($message) {
        return waLog::log($message, $this->log_path);
    }

    public function updateProducts($params) {
        $list = $params['list'];
        $columns = $params['columns'];
        $keys = $params['keys'];
        $update = $params['update'];
        $row_num = $params['row_num'];
        $row_count = $params['row_count'];
        $stock_id = $params['stock_id'];
        $set_product_status = $params['set_product_status'];
        $types = $params['types'];

        $model_sku = new shopProductSkusModel();

        if ($set_product_status) {
            $sql = "UPDATE `shop_product` SET `status` = 0";
            $model_sku->query($sql);
        }

        $updated = 0;
        $not_found = 0;
        $to = $list['numRows'];
        if ($row_count > 0) {
            $to = min($row_num + $row_count - 1, $list['numRows']);
        }

        for ($i = $row_num; $i <= $to; $i++) {
            $dataRow = $list['cells'][$i];
            $skus = $this->getSkuByFields($dataRow, $columns, $keys, $types);



            if ($skus) {
                foreach ($skus as $sku) {
                    $sku_id = $sku['id'];
                    $update_data = array();
                    foreach ($update as $id => $item) {
                        $field = $item['field'];
                        $value = $this->getDataValue($id, $dataRow, $columns);
                        if ($field == 'stock') {
                            $update_data[$field] = $this->getStocks($sku_id);
                            $update_data[$field][$stock_id] = ($value !== false ? $value : 0);
                        } else {
                            $update_data[$field] = ($value !== false ? $value : null);
                        }
                    }

                    $product = new shopProduct($sku['product_id']);
                    $product_model = new shopProductModel();
                    $data = $product_model->getById($sku['product_id']);
                    $data['skus'] = $model_sku->getDataByProductId($sku['product_id'], true);

                    $data['skus'] = $this->prepareSkus($data['skus']);
                    $data['skus'][$sku_id] = array_merge($data['skus'][$sku_id], $update_data);

                    if ($set_product_status) {
                        $data['status'] = $this->inStock($data['skus']) ? 1 : 0;
                    }

                    $product->save($data, true);
                    //$this->log("SKU \"$update_by_name\"='$update_by_val' обновлен");
                    $updated++;
                }
            } else {
                $not_found++;
            }
        }
        return array('updated' => $updated, 'not_found' => $not_found, 'log_path' => $this->log_path);
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
                WHERE `shop_product`.`type_id` IN (" . implode(',', $types) . ") AND " . implode(' AND ', $where);
        $result = $model->query($sql)->fetchAll();
        return $result;
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
        $instock = false;
        foreach ($skus as &$sku) {
            foreach ($sku['stock'] as &$stock) {
                if (intval($stock) > 0) {
                    $instock = true;
                    break;
                }
            }
        }
        return $instock;
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

}
