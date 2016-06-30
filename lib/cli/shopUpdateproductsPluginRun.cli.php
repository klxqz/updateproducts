<?php

class shopUpdateproductsPluginRunCli extends waCliController {

    public function execute() {

        $profile_helper = new shopImportexportHelper('updateproducts');

        $argv = waRequest::server('argv');

        if (!empty($argv[3])) {
            list($text, $profile_id) = explode('=', $argv[3]);
        }

        if ($profile_id) {
            $profile = $profile_helper->getConfig($profile_id);

            //print_r($profile);

            if (!$profile) {
                throw new waException('Profile not found', 404);
            }

            waRequest::setParam('profile_id', $profile_id);

            $runner = new shopUpdateproductsPluginRunController();
            $_POST['processId'] = null;

            $moved = false;
            $ready = false;
            do {
                ob_start();
                if (empty($_POST['processId'])) {
                    $_POST['processId'] = $runner->processId;
                } else {
                    sleep(1);
                }
                if ($ready) {
                    $_POST['cleanup'] = true;
                    $moved = true;
                }
                $runner->execute();
                $out = ob_get_clean();
                $result = json_decode($out, true);
                $ready = !empty($result) && is_array($result) && ifempty($result['ready']);
            } while (!$ready || !$moved);
            //TODO check errors
        }
    }

}
