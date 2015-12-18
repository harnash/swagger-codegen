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
    * Return an array of HTTP response headers
    *
    * @param string $raw_headers A string of raw HTTP response headers
    *
    * @return string[] Array of HTTP response heaers
    */
    protected function http_parse_headers($raw_headers)
    {
        // ref/credit: http://php.net/manual/en/function.http-parse-headers.php#112986
        $headers = array();
        $key = ''; // [+]

        foreach(explode("\n", $raw_headers) as $i => $h)
        {
            $h = explode(':', $h, 2);

            if (isset($h[1]))
            {
                if (!isset($headers[$h[0]]))
                    $headers[$h[0]] = trim($h[1]);
                elseif (is_array($headers[$h[0]]))
                {
                    // $tmp = array_merge($headers[$h[0]], array(trim($h[1]))); // [-]
                    // $headers[$h[0]] = $tmp; // [-]
                    $headers[$h[0]] = array_merge($headers[$h[0]], array(trim($h[1]))); // [+]
                }
                else
                {
                    // $tmp = array_merge(array($headers[$h[0]]), array(trim($h[1]))); // [-]
                    // $headers[$h[0]] = $tmp; // [-]
                    $headers[$h[0]] = array_merge(array($headers[$h[0]]), array(trim($h[1]))); // [+]
                }

                $key = $h[0]; // [+]
            }
            else // [+]
            { // [+]
                if (substr($h[0], 0, 1) == "\t") // [+]
                    $headers[$key] .= "\r\n\t".trim($h[0]); // [+]
                elseif (!$key) // [+]
                    $headers[0] = trim($h[0]);trim($h[0]); // [+]
            } // [+]
        }

        return $headers;
    }

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
        $request_headers = array();

        foreach ($headers as $key => $val) {
            $request_headers[] = "$key: $val";
        }

        if (! empty($acceptTypes)) {
            $request_headers[] = "Accept: $acceptTypes";
        }

        if (! empty($contentTypes)) {
            $request_headers[] = "Content-Type: $contentTypes";
        }

        // form data
        if ($postData and $contentTypes == 'application/x-www-form-urlencoded') {
            $postData = http_build_query($postData);
        }
        else if ((is_object($postData) or is_array($postData)) and $contentTypes != 'multipart/form-data') { // json model
            $postData = json_encode($this->getSerializer()->sanitizeForSerialization($postData));
        }

        $curl = curl_init();
        // set timeout, if needed
        if ($this->getTimeout() != 0) {
            curl_setopt($curl, CURLOPT_TIMEOUT, floor($this->getTimeout()/1000));
        }
        // return the result on success, rather than just TRUE
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($curl, CURLOPT_HTTPHEADER, $request_headers);

        $url = $this->getHost() . $uri;
        if (! empty($queryParams)) {
            $url = ($url . '?' . http_build_query($queryParams));
        }

        if ($method == self::POST) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        } else if ($method == self::PATCH) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PATCH");
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        } else if ($method == self::PUT) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        } else if ($method == self::DELETE) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        } else if ($method != self::GET) {
            throw new ApiException('Method ' . $method . ' is not recognized.');
        }
        curl_setopt($curl, CURLOPT_URL, $url);

        // Set user agent
        curl_setopt($curl, CURLOPT_USERAGENT, $this->getUserAgent());

        // debugging for curl
        if ($this->getConfig()->getDebug()) {
            error_log("[DEBUG] HTTP Request body  ~BEGIN~\n".print_r($postData, true)."\n~END~\n", 3, $this->getConfig()->getDebugFile());

            curl_setopt($curl, CURLOPT_VERBOSE, 1);
            curl_setopt($curl, CURLOPT_STDERR, fopen($this->getConfig()->getDebugFile(), 'a'));
        } else {
            curl_setopt($curl, CURLOPT_VERBOSE, 0);
        }

        // obtain the HTTP response headers
        curl_setopt($curl, CURLOPT_HEADER, 1);

        // Make the request
        $response = curl_exec($curl);
        $http_header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $http_header = $this->http_parse_headers(substr($response, 0, $http_header_size));
        $http_body = substr($response, $http_header_size);
        $response_info = curl_getinfo($curl);

        // debug HTTP response body
        if ($this->getConfig()->getDebug()) {
            error_log("[DEBUG] HTTP Response body ~BEGIN~\n".print_r($http_body, true)."\n~END~\n", 3, $this->getConfig()->getDebugFile());
        }

        // Handle the response
        if ($response_info['http_code'] == 0) {
            throw new ApiException("API call to $url timed out: ".serialize($response_info), 0, null, null);
        } else if ($response_info['http_code'] >= 200 && $response_info['http_code'] <= 299 ) {
            // return raw body if response is a file
            if ($responseType == '\SplFileObject') {
                return array($http_body, $http_header);
            }

            $data = json_decode($http_body);
            if (json_last_error() > 0) { // if response is a string
                $data = $http_body;
            }
        } else {
            throw new ApiException("[".$response_info['http_code']."] Error connecting to the API ($url)",
                                   $response_info['http_code'], $http_header, $http_body);
        }

        return array($data, $response_info['http_code'], $http_header);
    }

  /**
   * Build a JSON POST object
   *
   * @param mixed $data value to be sanitized before encoding
   * @return string sanitized data ready to be encoded
   */
  protected function sanitizeForSerialization($data)
  {
    if (is_scalar($data) || null === $data) {
      $sanitized = $data;
    } else if ($data instanceof \DateTime) {
      $sanitized = $data->format(\DateTime::ISO8601);
    } else if (is_array($data)) {
      foreach ($data as $property => $value) {
        $data[$property] = $this->sanitizeForSerialization($value);
      }
      $sanitized = $data;
    } else if (is_object($data)) {
      $values = array();
      foreach (array_keys($data::$swaggerTypes) as $property) {
        if ($data->$property !== null) {
          $values[$data::$attributeMap[$property]] = $this->sanitizeForSerialization($data->$property);
        }
      }
      $sanitized = $values;
    } else {
      $sanitized = (string)$data;
    }

    return $sanitized;
  }
}