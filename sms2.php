<?php

require_once __DIR__.'/Lib/Thrift/ClassLoader/ThriftClassLoader.php';

$tcl = new \Thrift\ClassLoader\ThriftClassLoader();
$tcl->registerNamespace('Thrift', array(__DIR__.'/Lib'));
$tcl->register();

use Thrift\Transport\TStreamSocketPool;
use Thrift\Transport\TFramedTransport;
use Thrift\Protocol\TCompactProtocol;
use Thrift\Service\ThriftServerClient;
use Thrift\Service\Request;

class ClientService
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

$client = new ClientService(array(
    '115.231.106.226:29090',
));
$client->setServer('go.micro.srv.sms');


$responseObj = $client->setMethod('Sms.SendSMS')
    ->setParams([
        "type"      => 1,
        "phone"     => '18660126860',
        "tplId"     => '2',
        "tplParams" => json_encode(array('helloword')) ,
 
    ])
    ->read();

//print_r($responseObj);
// 获取code
// 0-成功
// 400-错误的请求
// 401-未授权
// 403-禁止访问
// 404-服务或者方法不存在
// 500-内部错误
var_dump($responseObj->getCode());
// 获取返回结果
var_dump($responseObj->getData());
