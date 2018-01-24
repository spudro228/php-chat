<?php

require __DIR__ . '/../vendor/autoload.php';

use Amp\Loop;
use Amp\Socket\ServerSocket;
use function Amp\asyncCall;

Loop::run(function () {

    $server = new class
    {
        private $uri = 'tcp://127.0.0.1:1337';

        /**
         * @var array | ServerSocket[]
         */
        private $clients = [];

        /**
         * @throws TypeError
         */
        public function listen(): void
        {
            asyncCall(function () {
                print 'Start: ' . PHP_EOL;
                $server = Amp\Socket\listen($this->uri);

                print 'Listening on ' . $server->getAddress() . '...' . PHP_EOL;

                while ($socket = yield $server->accept()) {
                    $this->handleClient($socket);
                }
            });
        }

        /**
         * @param ServerSocket $socket
         * @throws TypeError
         */
        public function handleClient(ServerSocket $socket): void
        {
            asyncCall(function () use ($socket) {

                $prettyPrintArray = function (?string $string, ?string $varName) {
                    return $varName . ': [' . implode(',', str_split($string)) . ']' . PHP_EOL;
                };

                print 'Accept new client: ' . $socket->getRemoteAddress() . PHP_EOL;
                $this->broadCast($socket->getRemoteAddress() . ' joined the chat.' . PHP_EOL);

                $this->clients[$socket->getRemoteAddress()] = $socket;

                $buffer = '';
                while (null !== $chunk = yield $socket->read()) {
                    $buffer .= $chunk;
                    while (($pos = strpos($buffer, '\n')) !== false) {
                        $prettyPrintArray($pos, '$pos');
                        $this->handleMessage($socket, substr($buffer, 0, $pos));
                        $buffer = substr($buffer, $pos + 1);
                    }
                }

                unset($this->clients[$socket->getRemoteAddress()]);

                print 'Client has disconected: ' . $socket->getRemoteAddress() . PHP_EOL;
                $this->broadCast($socket->getRemoteAddress() . " left the chat." . PHP_EOL);
            });
        }

        public function handleMessage(ServerSocket $socket, string $message)
        {
            if ($message === '') {
                return;
            }

            if ($message[0] === '/') {
                $message = substr($message, 1); //remove slash
                $args = explode(' ', $message);
                $name = strtolower(array_shift($args));
                $name = str_replace("\n", "", $name);
                switch ($name) {
                    case 'time':
                        $socket->write(date("l jS \of F Y h:i:s A") . PHP_EOL);
                        break;
                    case 'reply':
                        print "Reply...";
                        $recipient = array_shift($args);
                        $message = array_shift($args);
                        $this->sendMessageTo($recipient, 'From: ' . $socket->getRemoteAddress() . ' to:' . $recipient . ' message: ' . $message);
                        break;
                    case 'exit':
                        $socket->end("Bye." . PHP_EOL);
                        break;
                    default:
                        $socket->write("Unknown command: {$name}" . PHP_EOL);
                        break;
                }
                return;
            }

            $this->broadCast($socket->getRemoteAddress() . ' says!!: ' . $message . PHP_EOL);

        }

        public function broadCast(string $message): void
        {
            foreach ($this->clients as $client) {
                $client->write($message);
            }
        }

        public function sendMessageTo(string $recipient, string $message): void
        {
            $client = $this->clients[$recipient];
            $client->write($message);
        }
    };

    $server->listen();
});