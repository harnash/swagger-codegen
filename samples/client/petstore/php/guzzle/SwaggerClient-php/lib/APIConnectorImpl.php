<?php
/**
 *  Copyright 2015 SmartBear Software
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 */

namespace Swagger\Client;

use GuzzleHttp\Client;

/**
 * APIConnectorImpl Class Doc Comment
 *
 * @category Class
 * @package  Swagger\Client
 * @author   http://github.com/swagger-api/swagger-codegen
 * @license  http://www.apache.org/licenses/LICENSE-2.0 Apache Licene v2
 * @link     https://github.com/swagger-api/swagger-codegen
 */
class APIConnectorImpl implements APIConnector {
    const PATCH = "PATCH";
    const POST = "POST";
    const GET = "GET";
    const PUT = "PUT";
    const DELETE = "DELETE";

    /**
     * Instance of the client
     * @var GuzzleHttp\Client
     */
    private $client;

    /**
     * Returns Client instance. Instance is lazy initialized and should be called after setConfig().
     * @return GuzzleHttp\Client
     */

    public function getClient() {
        if ( empty( $this->client ) ) {
            $this->client = new Client([
                'base_uri' => $this->getHost(),
                'timeout'  => $this->getTimeout(),
            ]);
        }

        return $this->client;
    }

    /**
     * Object Serializer
     * @var ObjectSerializer
     */
    protected $serializer;

    /**
     * Sets data serializer.
     *
     * @param ObjectSerializer $serializer data serializer/sanitizer
     */
    public function setSerializer(ObjectSerializer $serializer) {
        $this->serializer = $serializer;
    }

    /**
     * Returns the data serializer.
     *
     * @return ObjectSerializer
     */
    public function getSerializer() {
        return $this->serializer;
    }

    /*
     * @var string timeout (micro second) of the HTTP request, by default set to 0, no timeout
     */
    private $request_timeout = 0;

    /**
     * set the HTTP timeout value
     *
     * @param integer $micro_seconds Number of micro seconds before timing out [set to 0 for no timeout]
     */
    public function setTimeout($micro_seconds) {
        if (!is_numeric($micro_seconds) || $micro_seconds < 0)
            throw new \InvalidArgumentException('Timeout value must be numeric and a non-negative number.');

        $this->request_timeout = $micro_seconds;
    }

    /**
     * get the HTTP timeout value
     *
     * @return string HTTP timeout value
     */
    public function getTimeout() {
        return $this->request_timeout;
    }

    /**
     * Returns the API host.
     *
     * @return string
     */
    public function getHost() {
        return $this->getConfig()->getHost();
    }

    /**
     * get the user agent of the api client
     *
     * @return string $user_agent user agent
     */
    public function getUserAgent() {
        return $this->getConfig()->getUserAgent();
    }

    /**
     * set the configuration
     *
     *  @param Configuration $config new configuration
     */
    public function setConfig($config) {
        $this->config = $config;
    }

    /**
     * get the current configuration
     *
     * @return Configuration
     */
    public function getConfig() {
        return $this->config;
    }

    /**
     * @var Configuration current configuration object
     */
    private $config;

    /**
     * Sends API request to a given resource.
     *
     * @param string $uri path to a resource (i.e. '/pet/2')
     * @param string $method request method ($this->GET, $this->POST, etc.)
     * @param array $queryParams list of parameters (optional)
     * @param string $postData request payload (optional)
     * @param string $acceptTypes preferable response content types
     * @param string $contentTypes content type of request being sent
     * @param array $headers additional headers
     * @param string $responseType expected response type of the endpoint
     * @return mixed list containing decoded response and response headers
     * @throws ApiException
     */
    public function send($uri, $method, $queryParams, $postData, $acceptTypes, $contentTypes,
                         $headers, $responseType)
    {
        $options = array();

        foreach ($headers as $key => $val) {
            $options['headers'][$key] = $val;
        }

        if (! empty($acceptTypes)) {
            $options['headers']['Accept'] = $acceptTypes;
        }

        if (! empty($contentTypes)) {
            $options['headers']['Content-Type'] = $contentTypes;
        }

        // form data
        if ($postData and $contentTypes == 'application/x-www-form-urlencoded') {
            $options['form_params'] = $postData;
        }
        else if ((is_object($postData) or is_array($postData)) and $contentTypes != 'multipart/form-data') { // json model
            $options['json'] = $postData;
        }

        // Set user agent
        $options['headers']['User-Agent'] = $this->getUserAgent();

        if (! empty($queryParams)) {
            $options['query'] = $queryParasm;
        }

        // debugging
        if ($this->getConfig()->getDebug() ) {
            $options['debug'] = True;
        }

        $url = $this->getHost() . $uri;
        $response = null;
        // Make the request
        try {
            $response = $this->getClient()->request($method, $url, $options);
        } catch (RequestException $e) {
            $headers = "";
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                foreach ($message->getHeaders() as $name => $values) {
                    foreach ($values as $value) {
                        $headers .= sprintf('%s: %s; ', $name, $value);
                    }
                }
            }
            throw ApiException(
                "Error making request to $url: " . print_r($e->getRequest(), true),
                ($response ? $response->getStatusCode() : 0),
                $headers,
                ($response ? $response->getBody() : "")
            );
        }

        $http_body = $response->getBody();
        $http_headers = "";
        foreach ($message->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $http_headers .= sprintf('%s: %s; ', $name, $value);
            }
        }

        // debug HTTP response body
        if ($this->getConfig()->getDebug()) {
            error_log("[DEBUG] HTTP Response body ~BEGIN~\n".$response->getBody()."\n~END~\n", 3, $this->getConfig()->getDebugFile());
        }

        // Handle the response
        if ($response->getStatusCode() == 0) {
            throw new ApiException("API call to $url timed out: ".serialize($response), 0, null, null);
        } else if ($response->getStatusCode() >= 200 && $response->getStatusCode() <= 299 ) {
            // return raw body if response is a file
            if ($responseType == '\SplFileObject') {
                return array($http_body, $http_header);
            }

            $data = json_decode($http_body);
            if (json_last_error() > 0) { // if response is a string
                $data = $http_body;
            }
        } else {
            throw new ApiException("[".$response->getStatusCode()."] Error connecting to the API ($url)",
                $response->getStatusCode(), $http_headers, $http_body);
        }

        return array($http_data, $http_headers);
    }
}
