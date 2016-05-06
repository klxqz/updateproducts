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
        error_reporting(E_ALL);
        ini_set('display_errors', 'On');
        try {
            $profile_helper = new shopImportexportHelper($this->plugin_id);
            $profile_config = waRequest::post('settings', array(), waRequest::TYPE_ARRAY);
            $profile_id = $profile_helper->setConfig($profile_config);
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

            $keys = array();
            foreach ($profile_config['map']['keys'] as $key => $checked) {
                $keys[$key] = $this->getColumnInfo($key);
            }

            $update = array();
            foreach ($profile_config['map']['update'] as $key => $checked) {
                $update[$key] = $this->getColumnInfo($key);
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


            $row_num = $profile_config['price_list']['row_num'];
            $row_count = $profile_config['price_list']['row_count'];
            $total_row = $sheet->getHighestRow();
            $max_count = $total_row - $row_num + 1;
            $count = $row_count ? min($row_count, $max_count) : $max_count;
            $to = min($row_num + 10, $total_row);

            $html = '<p>Всего строк в файле: ' . $total_row . '</p><p>Строк для обработки: ' . $count . '</p>';
            $html .='<div class="test_table_container"><table class="table zebra test_table"><thead><tr>';
            foreach ($columns as $key => $column) {
                $html .='<th>' . $column['name'] . '</th>';
            }
            $html .='</tr></thead>';

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
                    $html .='<td' . (!empty($keys[$key]) ? ' class="critical"' : '') . '>' . $val . '</td>';
                }
                $html .='</tr>';
            }

            $html .='</table></div><p style="color: red;">Искать записи для обновления по полю:</p><ul>';
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

}
