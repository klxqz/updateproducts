<?php

class shopUpdateproductsPluginSaveController extends waJsonController {

    public function execute() {
        $params = array(
            'antispam' => 0,
            'workflow' => 1,
            'email' => 'test@wa-plugins.ru',
            'server' => 'mail.wa-plugins.ru',
            'port' => 110,
            'login' => 'test@wa-plugins.ru',
            'password' => '5N0o8E9h',
            'ssl' => null,
            'check_interval' => 57,
            'tls' => null,
            'antispam_mail_template' => null,
            'new_request_state_id' => null,
            'new_request_action_id' => null,
            'new_request_assign_contact_id' => null,
            'locale' => 'en_US',
            'reply_to_reply' => 'default',
            'messages' => null,
            'last_timestamp' => 1444628885,
        );
        $mail_reader = new waMailPOP3($params);
        $n = $mail_reader->count();

        if (!$n || !$n[0]) {
            // no new messages
            $mail_reader->close();
            return true;
        }

        $temp_path = wa('helpdesk')->getTempPath('mail', 'helpdesk');

        for ($i = 1; $i <= $n[0]; $i++) {

            $unique_id = uniqid(true);
            $cron_job_log_filename = $unique_id . '.log';

            $this->logCronJob("Start cycle. Iteration step = {$i}", $cron_job_log_filename);

            // This ensures that two checking loops won't run simultaneously
            if (time() > $source->params->last_timestamp + $interval / 5) {
                $spm->updateLastDatetime($source->id);
                $source->params->last_timestamp = time(); // keep in sync with the DB
            }

            $message = null;
            $message_id = null;
            $mail_path = $temp_path . '/' . $unique_id;
            waFiles::create($mail_path);

            $this->logCronJob("Create path: {$mail_path}", $cron_job_log_filename);

            try {

                $this->logCronJob("Try mail reader get mail: {$mail_path}", $cron_job_log_filename);

                // read mail to temporary file
                $mail_reader->get($i, $mail_path . '/mail.eml');

                $this->logCronJob("Try process eml", $cron_job_log_filename);

                // Process the file
                $this->processEml($source, $mail_path . '/mail.eml', $message, $cron_job_log_filename);

                $this->logCronJob("Try delete mail path: {$mail_path}", $cron_job_log_filename);

                // Clean up
                waFiles::delete($mail_path);
            } catch (Exception $e) {

                if ($message) {
                    $message_id = $message['message_id'];
                }
                $msg = $e->getMessage();

                $this->logCronJob("Catch exception: {$msg}", $cron_job_log_filename);

                if (strpos($msg, 'Duplicate entry') !== false) {
                    // Message with this message_id already exists.
                    // Delete from mailbox, write to log and forget it (blindly hoping it wasn't a collision).
                    $msg = 'Unable to save message ' . $message_id . ' from source ' . $source->name . ': ' . strstr($msg, 'Duplicate entry');
                } else {
                    if (waSystemConfig::isDebug()) {
                        echo $e;
                    }

                    // Save the mail file for later inspectation
                    $trouble_path = null;
                    if (is_readable($mail_path . '/mail.eml')) {
                        $trouble_filename = md5(ifset($message_id, uniqid('77*77asdf'))) . '.eml';
                        $trouble_path = wa()->getDataPath('requests/trouble/' . $trouble_filename, false, 'helpdesk');
                        waFiles::move($mail_path . '/mail.eml', $trouble_path);
                    }

                    $msg = "\n==================================================================================\n" .
                            'Unable to save message ' . $message_id . ' from source ' . $source->name . ': ' . $msg;
                    if ($trouble_path) {
                        $msg .= "\nFile: " . $trouble_path;
                    }
                    if ($message) {
                        $message['source'] = $message['source']->id;
                        $msg .= "\nMessage: " . print_r($message, true);
                    }
                    $msg .= "\n==================================================================================";
                }
                waLog::log($msg, 'helpdesk.log');
            }

            // remove processed mail message from the mailbox
            try {
                $mail_reader->delete($i);
            } catch (Exception $e) {
                waLog::log('Unable to delete message from mailbox ' . $source->name . ': ' . $e->getMessage(), 'helpdesk.log');
            }
        }
        $mail_reader->close();
        return true;
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
