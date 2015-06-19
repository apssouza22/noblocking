<?php

interface Worker {

    public function done($output);

    public function fail($output);

    public function getRequest();

    public function getServerAndPort();
}

class AsyncStream implements \Countable {

    const TIMEOUT = 10;
    const READ_BLOCK = 8192;

    private $result = array();
    public $sockets = array();
    private $workers = array();

    public function attach(Worker $worker) {
        $stream = stream_socket_client($worker->getServerAndPort(), $errno, $errstr, null, STREAM_CLIENT_ASYNC_CONNECT | STREAM_CLIENT_CONNECT);
        if (!$stream) {
            throw new \RuntimeException($errstr, $errno);
        }
        $delay = 1;
        $http_message = "GET /meusprojetos/noblocking/delay.php?delay=" .
                $delay . " HTTP/1.0\r\nHost: localhost\r\n\r\n";
        fwrite($stream, $http_message);
//        fwrite($stream, $worker->getRequest());

        $this->sockets[] = $stream;
        $this->workers[] = $worker;
    }

   

    public function detach(Worker $worker) {
        $i = array_search($worker, $this->workers, true);

        if (false === $i) {
            throw new \RuntimeException();
        }

        unset($this->workers[$i]);
        $status = fclose($this->sockets[$i]);
        unset($this->sockets[$i]);
        return $status;
    }

    public function count() {
        return count($this->workers);
    }

    public function listen($timeout = 200000) {
        $read = $this->sockets;
        $changed_num = @stream_select($read, $write = null, $expect = null, 0, $timeout);
//        var_dump($read);
        if (!count($read)) {
//            throw new \RuntimeException('Timeout!'); /* A time-out means that *all* streams have failed to receive a response. */
        }

//        if (false === $changed_num) {
//            throw new \RuntimeException();
//        }

//        if (0 === $changed_num) {
//            return;
//        }

        /* stream_select generally shuffles $read, so we need to compute from which socket(s) we're reading. */
        foreach ($read as $stream) {
            $i = array_search($stream, $this->sockets, true);
            if (false === $i) {
                continue;
            }

            /* In this class we are just reading, but we can write(pass stdin) as well
              ex. fwrite($pipes[0], $content);
              http://www.phpit.com.br/artigos/proc_open-comunicando-se-com-o-mundo-la-fora.phpit
              . */

            /* A socket is readable either because it has  data to read, OR because it's at EOF. */
            $data = fread($this->sockets[$i], self::READ_BLOCK);
            if (strlen($data) == 0) {
                $worker = $this->workers[$i];
                $status = $this->detach($worker);
                $worker->done($this->result[$i]);
//                
//                if (0 === $status) {
//                    $worker->done($this->result[$i]);
//                } 
//                
//                if (0 < $status) {
////                    $worker->fail('Close connection error');
//                }
                
            } else {
                $this->result[$i] = isset($this->result[$i]) ? $this->result[$i] . $data : $data;
            }
        }
    }

}


class Delay implements Worker {
    private $delay;
    
    public function __construct($delay) {
        $this->delay = $delay;
    }
    
    public function getRequest() {
        return "GET /meusprojetos/noblocking/delay.php?delay=" .
                $this->delay . " HTTP/1.0\r\nHost: localhost\r\n\r\n";
    }
    
    public function getServerAndPort() {
        return "localhost:80";
    }
    
    public function done($output) {
        var_dump($output);
        echo "Stream  closes at " . date('h:i:s') . ".<br> \n";
    }
    
    public function fail($output) {
        var_dump($output);
    }
}

$async = new AsyncStream();

for ($i = 1; $i < 4; $i++) {
    $async->attach(new Delay($i));
}

while (count($async)) {
    $async->listen();
}
