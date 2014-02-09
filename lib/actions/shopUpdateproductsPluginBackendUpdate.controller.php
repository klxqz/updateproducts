<?php

class shopUpdateproductsPluginBackendUpdateController extends waJsonController {

    protected $plugin_id = array('shop', 'updateproducts');

    public function execute() {
        
        try {
            $app_settings_model = new waAppSettingsModel();
            $shop_updateproducts = waRequest::post('shop_updateproducts', array());
            $template_name = waRequest::post('templates_list');
            $templates_json = $app_settings_model->get($this->plugin_id, 'templates');
            $templates = json_decode($templates_json, true);
            $templates[$template_name] = $shop_updateproducts;
            $templates = $app_settings_model->set($this->plugin_id, 'templates', json_encode($templates));
            $this->response['message'] = "Сохранено";
            $this->response['template'] = $shop_updateproducts;
        } catch (Exception $e) {
            $this->setError($e->getMessage());
        }
    }

}
