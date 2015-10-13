<?php

class shopUpdateproductsPluginUploadController extends waJsonController {

    private $plugin_id = 'updateproducts';
    private $sku_columns = array(
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

    public function execute() {
        try {
            $profile_helper = new shopImportexportHelper($this->plugin_id);
            $profile_config = (array) waRequest::post('settings', array());
            $profile_id = $profile_helper->setConfig($profile_config);


            $filepath = shopUpdateproductsPlugin::getFilePath($profile_id, $profile_config);

            $list_num = intval($profile_config['list_num']);
            $row_num = intval($profile_config['row_num']) > 0 ? intval($profile_config['row_num']) - 1 : 0;
            $row_count = intval($profile_config['row_count']);

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
            if ($profile_config['file_format'] == 'csv') {
                $objReader->setInputEncoding($profile_config['csv_encoding']);
                $objReader->setDelimiter($profile_config['csv_delimiter']);
                $objReader->setEnclosure($profile_config['csv_enclosure']);
            }
            $objPHPExcel = @$objReader->load($filepath);

            if (!$list_num) {
                throw new waException('Ошибка. Указан неверный «Номер листа». ');
            }
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
            $to = min($row_num + 10, $total_row);

            $html = '<p>Всего строк в файле: ' . $total_row . '</p><p>Строк для обработки: ' . $count . '</p>';
            $html .='<table class="test_table"><tr>';
            foreach ($columns as $key => $column) {
                $html .='<td' . (!empty($keys[$key]) ? ' style="border-color:red;"' : '') . '>' . $column['name'] . '</td>';
            }
            $html .='</tr>';

            for ($i = $row_num; $i <= $to; $i++) {
                $html .='<tr>';
                foreach ($columns as $key => $column) {
                    $val = '';
                    try {
                        if (is_numeric($column['num'])) {
                            $val = trim($sheet->getCellByColumnAndRow($column['num'] - 1, $i)->getValue());
                        } else {
                            $cell = $column['num'] . $i;
                            $val = trim($sheet->getCell($cell)->getValue());
                        }
                    } catch (Exception $ex) {
                        
                    }
                    $html .='<td' . (!empty($keys[$key]) ? ' style="border-color:red;"' : '') . '>' . $val . '</td>';
                }
                $html .='</tr>';
            }

            $html .='</table><p style="color: red;">Искать записи для обновления по полю:</p><ul>';
            foreach ($keys as $key) {
                $html .='<li style="color: red;">' . $key['name'] . '</li>';
            }
            $html .='</ul>';

            $this->response['html'] = $html;
        } catch (Exception $ex) {
            $this->setError($ex->getMessage());
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

}
