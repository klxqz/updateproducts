<?php

class shopUpdateproductsPluginBackendUploadController extends waJsonController {

    protected $plugin_id = array('shop', 'updateproducts');
    protected $require_path = 'plugins/updateproducts/lib/vendors/excel_reader2.php';
    protected $test_tpl = 'plugins/updateproducts/templates/Test.html';
    protected $update_tpl = 'plugins/updateproducts/templates/Update.html';

    public function execute() {
        try {
            $app_settings_model = new waAppSettingsModel();
            $shop_updateproducts = waRequest::post('shop_updateproducts');
            foreach ($shop_updateproducts as $name => $val) {
                $app_settings_model->set($this->plugin_id, $name, $val);
            }

            $require = wa()->getAppPath($this->require_path, 'shop');
            require($require);

            $test = null;
            $update = null;


            if (waRequest::file('test')->uploaded()) {
                $file = waRequest::file('test');
                $test = true;
            } elseif (waRequest::file('update')->uploaded()) {
                $file = waRequest::file('update');
                $update = true;
            } else {
                throw new waException('Ошибка. Файл не был загружен.');
            }

            if ($file->type != 'application/vnd.ms-excel') {
                throw new waException('Ошибка. Не верный формат файла. Загрузите MS Excel(XLS) версия 97-2003.');
            }

            $list_num = intval($shop_updateproducts['list_num']);
            $row_num = intval($shop_updateproducts['row_num']) > 0 ? intval($shop_updateproducts['row_num']) : 1;
            $row_count = intval($shop_updateproducts['row_count']);
            $update_by = $shop_updateproducts['update_by'];
            $stock_id = $shop_updateproducts['stock_id'];


            $columns = array(
                'sku' => array('name' => 'Артикул', 'num' => 0),
                'name' => array('name' => 'Наименование артикула', 'num' => 0),
                'stock' => array('name' => 'Количество', 'num' => 0),
                'price' => array('name' => 'Цена', 'num' => 0),
                'purchase_price' => array('name' => 'Закупочная цена', 'num' => 0),
                'compare_price' => array('name' => 'Зачеркнутая цена', 'num' => 0),
            );
            foreach ($columns as $name => &$column) {
                if (isset($shop_updateproducts[$name]) && intval($shop_updateproducts[$name]) > 0) {
                    $column['num'] = intval($shop_updateproducts[$name]);
                } else {
                    unset($columns[$name]);
                }
            }

            $data = new Spreadsheet_Excel_Reader();
            $data->setOutputEncoding('UTF-8');
            $data->read($file->tmp_name);

            if ($list_num > 0 && isset($data->sheets[$list_num - 1])) {
                $list = $data->sheets[$list_num - 1];
            } else {
                throw new waException('Ошибка. Указан не верный лист в XLS.');
            }

            if (!isset($columns['sku'])) {
                throw new waException('Ошибка. Не задан столбец с артикулом.');
            }

            if ($test) {
                $to = min(10, $list['numRows']);
                $tpl = wa()->getAppPath($this->test_tpl, 'shop');
                $view = wa()->getView();


                $view->assign(array('list' => $list, 'row_num' => $row_num, 'columns' => $columns, 'to' => $to, 'update_by' => $update_by));
                $html = $view->fetch($tpl);
                $this->response['html'] = $html;
                //print_r($list);
            }

            if ($update) {

                $plugin = wa()->getPlugin('updateproducts');
                $result = $plugin->updateProducts($list, $columns, $update_by, $row_num, $row_count, $stock_id);

                $tpl = wa()->getAppPath($this->update_tpl, 'shop');
                $view = wa()->getView();
                $view->assign('result', $result);
                $html = $view->fetch($tpl);
                $this->response['html'] = $html;
            }

            $this->response['message'] = $file->name . " загружен.";
        } catch (Exception $e) {

            $this->setError($e->getMessage());
        }
    }

}
