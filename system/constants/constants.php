<?php

// Security
define('SECRETE_KEY', 'qwertya!~qwerpoiuvfx4*$#');

// Data Types
define('BOOLEAN', '1');
define('INTEGER', '2');
define('STRING',  '3');
define('TEXT',  '4');

// Error Codes
define('REQUEST_METHOD_NOT_VALID',  100);
define('REQUEST_CONTENTTYPE_NOT_VALID',  101);
define('REQUEST_NOT_VALID',  102);
define('VALIDATE_PARAMETER_REQUIRED',  103);
define('VALIDATE_PARAMETER_DATATYPE',  104);
define('API_NAME_REQUIRED',  105);
define('API_PARAM_REQUIRED',  106);
define('API_DOES_NOT_EXIST',  107);
define('INVALID_USER_PASS',  108);
define('FAILED_QUERY',  109);
define('NOT_ACTIVE',  110);
define('SITE_DOMAIN_NAME',  "https://sonergy.io");
define('SITE_DOMAIN_NAME_STRING',  "sonergy.io");
define('ACCOUNT_VERIFICATION',  "Sonergy account verification");
define('PASSWORD_RESET',  "Sonergy password reset");
define('EMAIL_FROM',  "From: <noreply@sonergy.io>");
define('MIME_VERSION',  "MIME-Version: 1.0");
define('HTML_EMAIL_CONTENT_TYPE',  "Content-type:text/html;charset=UTF-8");




define('SUCCESS_RESPONSE',  200);

// Server Errors
define('AUTHORIZATION_HEADER_NOT_FOUND',  300);
define('ACCESS_TOKEN_ERRORS',  301);
