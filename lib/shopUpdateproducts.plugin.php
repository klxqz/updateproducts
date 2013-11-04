<?php


class shopUpdateproductsPlugin extends shopPlugin
{ 
    public function updateProducts(&$list,$columns,$update_by,$row_num,$row_count = null,$stock_id = 0)
    {
      
        $_log_path = '/plugins/updateproducts/log.txt';
        $log_path = wa()->getDataPath($_log_path, 'shop');
        @unlink($log_path);
        $f = @fopen($log_path,'w+');
        if(!$f) {
            throw new waException('Ошибка. Создания файла '.$log_path);
        }

        $model_sku = new shopProductSkusModel();
        $update_by_j = $columns[$update_by]['num'];
        $update_by_name = $columns[$update_by]['name'];
        unset($columns[$update_by]);
        $updated = 0;
        $not_found = 0;
        $to = $list['numRows'];
        if($row_count>0) {
            $to = min($row_num+$row_count-1,$list['numRows']);
        }
    
        for($i = $row_num; $i <= $to; $i++) {
            
            $update_by_val = trim($list['cells'][$i][$update_by_j]);
            
            if($sku = $model_sku->getByField($update_by, $update_by_val)) {
                
                $sku_id = $sku['id'];

                $update = array();
                foreach($columns as $name => $column) {
                    $j = $column['num'];
                    if($name=='stock') {
                        $update[$name] = $this->getStocks($sku_id);
                        $update[$name][$stock_id] = $list['cells'][$i][$j];
                    } else {
                        $update[$name] = $list['cells'][$i][$j];
                    }
                }
                
                $product = new shopProduct($sku['product_id']);
                $product_model = new shopProductModel();
                
                $data = $product_model->getById($sku['product_id']);
                $data['skus'][$sku_id] = $update;
                $product->save($data, true);

                fwrite($f,"SKU \"$update_by_name\"='$update_by_val' обновлен\r\n");
                $updated++;
            } else {
                $not_found++;
                fwrite($f,"SKU \"$update_by_name\"='$update_by_val' не был найден\r\n");
            }
        }
        fclose($f);
        return array('updated' => $updated, 'not_found' => $not_found,'log_path'=>$_log_path);
    }
    
    protected function getStocks($sku_id)
    {
        $stocks = array();
        $product_stocks = new shopProductStocksModel();
        $return = $product_stocks->getBySkuId($sku_id);
        
        if(isset($return[$sku_id])) {
            foreach($return[$sku_id] as $stock_id => $stock) {
                $stocks[$stock_id] = $stock['count'];
            }
        }

        return $stocks;
    }
}