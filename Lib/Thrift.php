<?php

require_once __DIR__.'/Thrift/ClassLoader/ThriftClassLoader.php';

$tcl = new \Thrift\ClassLoader\ThriftClassLoader();
$tcl->registerNamespace('Thrift', array(__DIR__));
$tcl->register();

use Thrift\Transport\TStreamSocketPool;
use Thrift\Transport\TFramedTransport;
use Thrift\Protocol\TCompactProtocol;
use Thrift\Service\ThriftServerClient;
use Thrift\Service\Request;

class Thrift
{

    private $_client;

    private $_request;

    public function __construct($servers)
    {
        $socket = new TStreamSocketPool($servers, true);

        $transport = new TFramedTransport($socket, 1024, 1024);
        $protocol = new TCompactProtocol($transport);
        $client = new ThriftServerClient($protocol);

        $transport->open();

        $this->_client = $client;
        $this->_request = new Request();
    }

    public function setHeader($header)
    {
        $this->_request->header = $header;
        return $this;
    }

    public function setServer($server)
    {
        $this->_request->server = $server;
        return $this;
    }

    public function setMethod($method)
    {
        $this->_request->method = $method;
        return $this;
    }

    public function setParams($params = array())
    {
        $params = json_encode($params);
        $this->_request->body = $params;
        return $this;
    }

    public function read()
    {
        $this->response = $this->_client->Call($this->_request);

        return $this;
    }

    public function getCode()
    {
        if (!$this->response) {
            return;
        }

        return $this->response->getCode();
    }

    public function getData()
    {
        return $this->response->getData();
    }
}