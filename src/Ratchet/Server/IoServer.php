<?php
namespace Ratchet\Server;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\LoopInterface;
use React\Socket\ServerInterface;
use React\EventLoop\Factory as LoopFactory;
use React\Socket\Server as Reactor;
use React\EventLoop\StreamSelectLoop;

/**
 * Creates an open-ended socket to listen on a port for incomming connections.  Events are delegated through this to attached applications
 */
class IoServer {
    /**
     * @var React\EventLoop\LoopInterface
     */
    public $loop;

    /**
     * @var Ratchet\MessageComponentInterface
     */
    public $app;

    /**
     * Array of React event handlers
     * @var array
     */
    protected $handlers = array();

    /**
     * @param Ratchet\MessageComponentInterface The Ratchet application stack to host
     * @param React\Socket\ServerInterface The React socket server to run the Ratchet application off of
     * @param React\EventLoop\LoopInterface The React looper to run the Ratchet application off of
     */
    public function __construct(MessageComponentInterface $app, ServerInterface $socket, LoopInterface $loop) {
        $this->loop = $loop;
        $this->app  = $app;

        $socket->on('connect', array($this, 'handleConnect'));

        $this->handlers['data']  = array($this, 'handleData');
        $this->handlers['end']   = array($this, 'handleEnd');
        $this->handlers['error'] = array($this, 'handleError');
    }

    public static function factory(MessageComponentInterface $component, $port = 80, $address = '0.0.0.0') {
        // Enable this after we fix a bug with libevent
        // $loop   = LoopFactory::create();

        $loop = new StreamSelectLoop;

        $socket = new Reactor($loop);
        $socket->listen($port, $address);
        $server = new static($component, $socket, $loop);

        return $server;
    }

    public function run() {
        $this->loop->run();
    }

    public function handleConnect($conn) {
        $conn->decor = new IoConnection($conn, $this);

        $conn->decor->resourceId    = (int)$conn->socket;
        $conn->decor->remoteAddress = '127.0.0.1'; // todo

        $this->app->onOpen($conn->decor);

        $conn->on('data', $this->handlers['data']);
        $conn->on('end', $this->handlers['end']);
        $conn->on('error', $this->handlers['error']);
    }

    public function handleData($data, $conn) {
        $this->app->onMessage($conn->decor, $data);
    }

    public function handleEnd($conn) {
        $this->app->onClose($conn->decor);
    }

    public function handleError(\Exception $e, $conn) {
        $this->app->onError($conn->decor, $e);
    }
}