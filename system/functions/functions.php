<?php
    spl_autoload_register(function ($class_name = null)
    {
        $path = strtolower($class_name) . ".php";
       if (file_exists($path)) {
         require_once($path);
       } else {
          echo "File {$path} is not found";
       }
        
    });