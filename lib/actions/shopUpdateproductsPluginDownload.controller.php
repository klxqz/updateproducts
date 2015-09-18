<?php

class shopUpdateproductsPluginDownloadController extends waController {

    public function execute() {
        $profile = waRequest::get('profile', 0, waRequest::TYPE_INT);

        $file = wa()->getTempPath('plugins/updateproducts/download/' . $profile . '/not_found_file.csv');
        waFiles::readFile($file, 'not_found_file.csv');
    }

}
