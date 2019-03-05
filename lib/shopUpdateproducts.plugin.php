<?php

class shopUpdateproductsPlugin extends shopPlugin {

    private static function uuid() {
        $uuid = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x', // 32 bits for "time_low"
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), // 16 bits for "time_mid"
                mt_rand(0, 0xffff), // 16 bits for "time_hi_and_version",
                // four most significant bits holds version number 4
                mt_rand(0, 0x0fff) | 0x4000, // 16 bits, 8 bits for "clk_seq_hi_res",
                // 8 bits for "clk_seq_low",
                // two most significant bits holds zero and one for variant DCE1.1
                mt_rand(0, 0x3fff) | 0x8000, // 48 bits for "node"
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        return $uuid;
    }

    public function getHash($profile = 0) {
        $uuid = $this->getSettings('uuid');
        if (!is_array($uuid)) {
            if ($uuid) {
                $uuid = array(
                    0 => $uuid,
                );
            } else {
                $uuid = array();
            }
        }

        if ($profile) {
            $updated = false;
            if ((count($uuid) == 1) && isset($uuid[0])) {
                $uuid[$profile] = $uuid[0];
                $updated = true;
            } elseif (!isset($uuid[$profile])) {
                $uuid[$profile] = self::uuid();
                $updated = true;
            }
            if ($updated) {
                $this->saveSettings(array('uuid' => $uuid));
            }
        }
        return ifset($uuid[$profile]);
    }

    private static function uploadFile($filepath, $url) {
        if (empty($url)) {
            throw new waException(_wp('Empty URL for YML'));
        } else {
            try {
                waFiles::upload($url, $filepath);
            } catch (waException $ex) {
                throw new waException(sprintf('Ошибка загрузки файла: %s', $ex->getMessage()));
            }
        }

        return true;
    }

    public static function getFilePath($profile_id, $profile_config) {
        $filepath = wa()->getCachePath('plugins/updateproducts/profile' . $profile_id . '/upload_file', 'shop');
        switch ($profile_config['upload_type']) {
            case 'local':
                $file = waRequest::file('files');
                if (!file_exists($filepath) && !$file->uploaded()) {
                    throw new waException('Загрузите файл');
                }
                if($file->uploaded()) {
                    $file->moveTo($filepath);
                }
                break;
            case 'url':
                if ($profile_config['file_url']) {
                    self::uploadFile($filepath, $profile_config['file_url']);
                } else {
                    throw new waException('Укажите ссылку для скачивания');
                }
                break;
        }

        return $filepath;
    }

    protected static function parseData($prefix, $array = array()) {
        $result = array();
        foreach ($array as $key => $item) {
            if (preg_match('/' . $prefix . '(.+)/', $key, $match)) {
                $sub = $match[1];
                $result[$sub] = $item;
            }
        }
        return $result;
    }

    public static function getAttachments($eml_file_path) {
        static $mail_decode = null;
        if (empty($mail_decode)) {
            $mail_decode = new waMailDecode();
        }
        $mail = $mail_decode->decode($eml_file_path);

        $attachments = array();
        if (isset($mail['attachments']) && $mail['attachments']) {
            $mail_path = dirname($eml_file_path);
            foreach ($mail['attachments'] as $a) {
                $attachments[] = array(
                    'file' => $mail_path . '/files/' . $a['file'],
                    'name' => ifempty($a['name'], $a['file']),
                    'cid' => ifempty($a['content-id']),
                );
            }
        }
        return $attachments;
    }

}
