<?php
namespace Chrisbjr\ApiGuard;

class ApiController extends Controller
{

    protected $apiMethods;

    public function __construct()
    {
        $this->beforeFilter(function () {

            $key = Request::header('X-API-KEY');

            if (empty($key)) {
                return $this->response(null, 401, 'You do not have access to this API.');
            }

            Str::parseCallback(Route::currentRouteAction(), null);
            $routeArray = Str::parseCallback(Route::currentRouteAction(), null);

            if (last($routeArray) == null) {
                return $this->response(null, 403, 'Invalid route.');
            }

            $apiMethods = $this->getBeforeFilters()[0]['options']['apiMethods'];
            $method = last($routeArray);

            $apiKeyQuery = ApiKey::where('key', '=', $key)->limit(1)->get();

            if (count($apiKeyQuery) <= 0) {
                return $this->response(null, 401, 'You do not have access to this API.');
            }

            $apiKey = $apiKeyQuery->get(0);

            // API key exists
            // Check level of API
            if (!empty($apiMethods[$method]['level'])) {
                if ($apiKey->level < $apiMethods[$method]['level']) {
                    return $this->response(null, 403, 'You do not have access to this API method.');
                }
            }

            // Then check the limits of this method
            if (!empty($apiMethods[$method]['limit'])) {
                if (!$apiKey->ignore_limits) {
                    // Count the number of requests for this method using this api key
                    $apiLogCount = ApiLog::where('api_key_id', '=', $apiKey->id)
                        ->where('route', '=', Route::currentRouteAction())
                        ->where('method', '=', Request::getMethod())
                        ->where('created_at', '>=', date('Y-m-d H:i:s', mktime(date('H') - 1)))
                        ->where('created_at', '<=', date('Y-m-d H:i:s', mktime(date('H'))))
                        ->count();

                    if ($apiLogCount >= $apiMethods[$method]['limit']) {
                        return $this->response(null, 403, 'You have reached the limit for using this API.');
                    }
                }
            }

            // Log this API request
            $apiLog = new ApiLog;
            $apiLog->api_key_id = $apiKey->id;
            $apiLog->route = Route::currentRouteAction();
            $apiLog->method = Request::getMethod();
            $apiLog->params = http_build_query(Input::all());
            $apiLog->ip_address = Request::getClientIp();
            $apiLog->save();

        }, ['apiMethods' => $this->apiMethods]);
    }

    public function response($data = null, $httpStatusCode = 200, $error = null)
    {
        $status = [
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            307 => 'Temporary Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported'
        ];

        $output['code'] = $httpStatusCode;
        $output['status'] = $status[$httpStatusCode];
        if ($error !== null) $output['error'] = $error;

        if ($data != null) {
            $output['data'] = $data;
        }

        return Response::make($output, $httpStatusCode, ['Content-type' => 'application/json']);
    }

}