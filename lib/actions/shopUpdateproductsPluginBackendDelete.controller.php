<?php

class shopUpdateproductsPluginBackendDeleteController extends waJsonController {

    protected $plugin_id = array('shop', 'updateproducts');

    public function execute() {
        try {
            $app_settings_model = new waAppSettingsModel();
            $template_name = waRequest::post('template_name');

            $templates_json = $app_settings_model->get($this->plugin_id, 'templates');
            $templates = json_decode($templates_json, true);
            unset($templates[$template_name]);
            $templates = $app_settings_model->set($this->plugin_id, 'templates', json_encode($templates));
            $this->response['message'] = "Сохранено";
        } catch (Exception $e) {

            $this->setError($e->getMessage());
        }
    }

}
