<?php

class shopUpdateproductsPluginBackendUploadFileController extends waJsonController {

    //protected $plugin_id = array('shop', 'updateproducts');
    protected $test_tpl = 'plugins/updateproducts/templates/Test.html';
    protected $require_path = 'plugins/updateproducts/lib/vendors/excel_reader2.php';
    protected $sku_columns = array(
        'sku' => 'Артикул',
        'name' => 'Наименование',
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

    public function execute() {
        try {
            $file = waRequest::file('file');
            if (!$file->uploaded()) {
                throw new waException('Ошибка загрузки файла');
            }
            if ($file->type != 'application/vnd.ms-excel') {
                throw new waException('Ошибка. Не верный формат файла. Загрузите MS Excel(XLS) версия 97-2003.');
            }
            $filepath = wa()->getCachePath('plugins/updateproducts/file.xls');
            $file->moveTo($filepath);

            $shop_updateproducts = waRequest::post('shop_updateproducts', array());
            

            $list_num = intval($shop_updateproducts['list_num']);
            $row_num = intval($shop_updateproducts['row_num']) > 0 ? intval($shop_updateproducts['row_num']) : 1;
            $row_count = intval($shop_updateproducts['row_count']);
            $set_product_status = $shop_updateproducts['set_product_status'];
            $stock_id = $shop_updateproducts['stock_id'];
            $currency = $shop_updateproducts['currency'];

            $types = $this->parseData('types_', $shop_updateproducts);
            /*
            if (!$types) {
                throw new waException('Ошибка. Укажите типы товаров для обновления.');
            }*/
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

            $max_count = $list['numRows'] - $row_num + 1;
            $count = $row_count ? min($row_count, $max_count) : $max_count;
            $to = min($row_num + 10, $list['numRows']);
            $tpl = wa()->getAppPath($this->test_tpl, 'shop');
            $view = wa()->getView();
            $params = array(
                'list' => &$list,
                'columns' => $columns,
                'keys' => $keys,
                'row_num' => $row_num,
                'to' => $to,
                'count' => $count
            );
            $view->assign($params);
            $html = $view->fetch($tpl);
            $this->response['html'] = $html;
        } catch (Exception $e) {
            $this->setError($e->getMessage());
        }
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

}
