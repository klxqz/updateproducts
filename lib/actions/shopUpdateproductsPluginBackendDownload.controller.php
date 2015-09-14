<?php

class shopUpdateproductsDownloadController extends waController {

    public function execute() {
        $name = basename(waRequest::get('file', 'not_found_file.csv'));
        $profile = waRequest::get('profile', 0, waRequest::TYPE_INT);

        $file = wa()->getTempPath('plugins/updateproducts/download/' . $profile . '/' . $name);
        waFiles::readFile($file, $name);
    }

}
