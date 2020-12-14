<?php

class Rest
{
    protected $request;
    protected $service_name;
    protected $param;

    public function __construct()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->throw_error(REQUEST_METHOD_NOT_VALID, 'Request method not valid.');
        }
        $file_handler = fopen('php://input', 'r');
        $this->request  = stream_get_contents($file_handler);
        $this->validate_request();
    }

    public function validate_request()
    {
        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
            $this->throw_error(REQUEST_CONTENTTYPE_NOT_VALID, "Request content type is not valid");
        }

        $data = json_decode($this->request, true);

        if (!isset($data['name']) || empty($data['name'])) {
            $this->throw_error(API_NAME_REQUIRED, 'API Name is required');
        }
        $this->service_name = $data['name'];

        if (!is_array($data['param']) || sizeof($data['param']) < 1) {
            $this->throw_error(API_PARAM_REQUIRED, 'API Param is required');
        }

        $this->param = $data['param'];
    }

    public function process_api()
    {
        $api = new API;


        try {
            $r_method = new ReflectionMethod("API", $this->service_name);
            $r_method->invoke($api);
        } catch (\Throwable $th) {
            // $th->getMessage()
            $this->throw_error(API_DOES_NOT_EXIST, "File not found.");
        }
    }

    public function validate_parameter($field_name = null, $value = null, $data_type = null, $required = true)
    {
        if ($required == true && empty($value) == true) {
            $this->throw_error(VALIDATE_PARAMETER_REQUIRED, $field_name . " parameter is required");
        }

        switch ($data_type) {
            case BOOLEAN:
                if (!is_bool($value)) {
                    $this->throw_error(VALIDATE_PARAMETER_DATATYPE, "Datatype is not valid for  " . $field_name . " expecting boolean value");
                }
                break;

            case STRING:
                if (!is_string($value)) {
                    $this->throw_error(VALIDATE_PARAMETER_DATATYPE, "Datatype is not valid for  " . $field_name . " expecting string value");
                }
                break;

            case INTEGER:
                if (!is_numeric($value)) {
                    $this->throw_error(VALIDATE_PARAMETER_DATATYPE, "Datatype is not valid for  " . $field_name . " expecting integer value");
                }
                break;

            case TEXT:
                if (empty($value)) {
                    $this->throw_error(VALIDATE_PARAMETER_DATATYPE, "Datatype is not valid for  " . $field_name . " expecting string or array value");
                }
                break;


            default:
                # code...
                break;
        }

        return $value;
    }

    public function throw_error($code = null, $message = null)
    {
        header("content-type: application/json");
        $error_msg =  json_encode(['error' => ['status' => $code, 'message' => $message]]);
        echo $error_msg;
        exit;
    }

    public function return_response($code = null, $data = null)
    {
        header("content-type: application/json");
        $response =  json_encode(['response' => ['status' => $code, 'data' => $data]]);
        echo $response;
    }

    public function  get_authorization_header()
    {
        $headers = null;

        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } else if (function_exists('apache_request_headers')) {
            $request_headers = apache_request_headers();
            /* Server-side fix for bug in old Android versions (a nice 
                side-effect of this fix means we don't care about capitalization for Authorization)
                */

            $request_headers = array_combine(array_map(
                'ucwords',
                array_keys($request_headers)
            ), array_values($request_headers));

            if (isset($request_headers['Authorization'])) {
                $headers = trim(request_headers['Authorization']);
            }
        }

        return $headers;
    }
    public function get_bearer_token()
    {

        $headers = $this->get_authorization_header();
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }

        $this->throw_error(AUTHORIZATION_HEADER_NOT_FOUND, 'Access token not found.');
    }
}
