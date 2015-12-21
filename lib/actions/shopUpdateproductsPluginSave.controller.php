<?php

class shopUpdateproductsPluginSaveController extends waJsonController {

    public function execute() {
        try {
            $profiles = new shopImportexportHelper('updateproducts');
            $profile_config = (array) waRequest::post('settings', array());
            $profile_id = $profiles->setConfig($profile_config);
            $this->response['html'] = 'Сохранено';
        } catch (Exception $ex) {
            $this->setError($ex->getMessage());
        }
    }

}
