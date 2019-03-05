<?php

try {
    $files = array(
        'plugins/updateproducts/lib/vendors/pclzip/',
    );

    foreach ($files as $file) {
        waFiles::delete(wa()->getAppPath($file, 'shop'), true);
    }
} catch (Exception $e) {
    
}