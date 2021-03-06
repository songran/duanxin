<?php
namespace Thrift\Transport;

use Thrift\Exception\TException;

if (!class_exists("\Yac")) {
    class Yac {
        public function __construct($prefix="") { return false; }
        public function set($key, $value, $ttl) { return false; }
        public function get($key) { return false; }
        public function delete($key, $delay = 0) { return false; }
        public function flush() { return false; }
        public function info() { return false; }
    }
} else {
    class Yac extends \Yac {}
}

class TStreamSocketPool extends TStreamSocket
{
    /**
     * Remote servers. Array of associative arrays with 'host' and 'port' keys
     */
    private $servers_ = array();

    /**
     * How many times to retry each host in connect
     *
     * @var int
     */
    private $numRetries_ = 1;

    /**
     * Retry interval in seconds, how long to not try a host if it has been
     * marked as down.
     *
     * @var int
     */
    private $retryInterval_ = 60;

    /**
     * Max consecutive failures before marking a host down.
     *
     * @var int
     */
    private $maxConsecutiveFailures_ = 1;

    /**
     * Try hosts in order? or Randomized?
     *
     * @var bool
     */
    private $randomize_ = true;

    /**
     * Always try last host, even if marked down?
     *
     * @var bool
     */
    private $alwaysTryLast_ = true;

    /**
     * Socket pool constructor
     *
     * @param array  $hosts        List of remote hostnames
     * @param mixed  $ports        Array of remote ports, or a single common port
     * @param bool   $persist      Whether to use a persistent socket
     * @param mixed  $debugHandler Function for error logging
     */
    public function __construct(array $servers= array(
        'localhost:9090'),
        $persist=false,
        $debugHandler=null) {
        parent::__construct(null, 0, $persist, $debugHandler);

        if (!is_array($servers)) {
            throw new \Exception("servers config is invalid");
        }

        foreach ($servers as $server) {
            list($host, $port) = explode(":", $server);
            if ($host && $port) {
                $this->servers_ []= array(
                    'host' => $host,
                    'port' => $port
                );
            }
        }

        $this->yac = new Yac("thrift");
    }

    /**
     * Add a server to the pool
     *
     * This function does not prevent you from adding a duplicate server entry.
     *
     * @param string $host hostname or IP
     * @param int $port port
     */
    public function addServer($host, $port)
    {
        $this->servers_[] = array('host' => $host, 'port' => $port);
    }

    /**
     * Sets how many time to keep retrying a host in the connect function.
     *
     * @param int $numRetries
     */
    public function setNumRetries($numRetries)
    {
        $this->numRetries_ = $numRetries;
    }

    /**
     * Sets how long to wait until retrying a host if it was marked down
     *
     * @param int $numRetries
     */
    public function setRetryInterval($retryInterval)
    {
        $this->retryInterval_ = $retryInterval;
    }

    /**
     * Sets how many time to keep retrying a host before marking it as down.
     *
     * @param int $numRetries
     */
    public function setMaxConsecutiveFailures($maxConsecutiveFailures)
    {
        $this->maxConsecutiveFailures_ = $maxConsecutiveFailures;
    }

    /**
     * Turns randomization in connect order on or off.
     *
     * @param bool $randomize
     */
    public function setRandomize($randomize)
    {
        $this->randomize_ = $randomize;
    }

    /**
     * Whether to always try the last server.
     *
     * @param bool $alwaysTryLast
     */
    public function setAlwaysTryLast($alwaysTryLast)
    {
        $this->alwaysTryLast_ = $alwaysTryLast;
    }

    /**
     * Connects the socket by iterating through all the servers in the pool
     * and trying to find one that works.
     */
    public function open()
    {
        // Check if we want order randomization
        if ($this->randomize_) {
            shuffle($this->servers_);
        }

        // Count servers to identify the "last" one
        $numServers = count($this->servers_);

        for ($i = 0; $i < $numServers; ++$i) {
            // This extracts the $host and $port variables
            extract($this->servers_[$i]);

            //check service has being down
            $failtimeKey = 'failtime:'.$host.':'.$port.'~';

            $lastFailtime = $this->yac->get($failtimeKey);

            if ($lastFailtime === false) {
                $lastFailtime = 0;
            }

            $retryIntervalPassed = false;

            // Cache hit...make sure enough the retry interval has elapsed
            if ($lastFailtime > 0) {
                $elapsed = time() - $lastFailtime;
                if ($elapsed > $this->retryInterval_) {
                    $retryIntervalPassed = true;
                    if ($this->debug_) {
                        call_user_func($this->debugHandler_,
                            'TStreamSocketPool: retryInterval '.
                            '('.$this->retryInterval_.') '.
                            'has passed for host '.$host.':'.$port);
                    }
                }
            }

            // Only connect if not in the middle of a fail interval, OR if this
            // is the LAST server we are trying, just hammer away on it
            $isLastServer = false;
            if ($this->alwaysTryLast_) {
                $isLastServer = ($i == ($numServers - 1));
            }

            if (($lastFailtime === 0) ||
                ($isLastServer) ||
                ($lastFailtime > 0 && $retryIntervalPassed)) {

                // Set underlying TSocket params to this one
                $this->host_ = $host;
                $this->port_ = $port;

                // Try up to numRetries_ connections per server
                for ($attempt = 0; $attempt < $this->numRetries_; $attempt++) {
                    try {
                        // Use the underlying TSocket open function
                        parent::open();

                        // Only clear the failure counts if required to do so
                        if ($lastFailtime > 0) {
                            $this->yac->delete($failtimeKey);
                        }
                        // Successful connection, return now
                        return;

                    } catch (TException $tx) {
                        // Connection failed
                    }
                }

                // Mark failure of this host in the cache
                $consecfailsKey = 'consecfails:'.$host.':'.$port.'~';

                // Ignore cache misses
                $consecfails = $this->yac->get($consecfailsKey);
                if ($consecfails === false) {
                    $consecfails = 0;
                }

                // Increment by one
                $consecfails++;

                // Log and cache this failure
                if ($consecfails >= $this->maxConsecutiveFailures_) {
                    if ($this->debug_) {
                        call_user_func($this->debugHandler_,
                            'TSocketPool: marking '.$host.':'.$port.
                            ' as down for '.$this->retryInterval_.' secs '.
                            'after '.$consecfails.' failed attempts.');
                    }

                    // Store the failure time
                    $this->yac->set($failtimeKey, time());

                    // Clear the count of consecutive failures
                    $this->yac->delete($consecfailsKey);
                } else {
                    $this->yac->set($consecfailsKey, $consecfails);
                }

            }
        }

        // Oh no; we failed them all. The system is totally ill!
        $error = 'TStreamSocketPool: All hosts in pool are down. ';
        $hosts = array();

        foreach ($this->servers_ as $server) {
            $hosts []= $server['host'].':'.$server['port'];
        }

        $hostlist = implode(',', $hosts);
        $error .= '('.$hostlist.')';

        if ($this->debug_) {
            call_user_func($this->debugHandler_, $error);
        }

        throw new TException($error);
    }
}
