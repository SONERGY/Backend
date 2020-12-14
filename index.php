<?php
    require_once('system/model/init/init.php');
    
    require_once('system/functions/functions.php');
    require_once('system/constants/constants.php');
    require_once('system/email/email_builder.php');
    require_once('system/core/api.php');
   
    $api = new Api;
    $api->process_api();
   