<?php
namespace Thrift\Transport;



class TStreamSocket extends TSocket
{

    public function getSocketHandle(&$errno, &$errstr)
    {
        for ($i = 0; $i < $this->tryConnectCount; $i++) {
            if ($this->persist_) {
                $this->handle_ = @stream_socket_client(
                    'tcp://'.$this->host_.':'.$this->port_,
                    $errno,
                    $errstr,
                    $this->sendTimeoutSec_ + ($this->sendTimeoutUsec_ / 1000000),
                    STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT
                );
            } else {
                $this->handle_ = @stream_socket_client(
                    'tcp://'.$this->host_.':'.$this->port_,
                    $errno,
                    $errstr,
                    $this->sendTimeoutSec_ + ($this->sendTimeoutUsec_ / 1000000)
                );
            }
            if ($this->handle_) {
                return true;
            }
        }
        return false;
    }
}
