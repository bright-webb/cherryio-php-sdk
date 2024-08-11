<?php
namespace Cherryio;

use GuzzleHttp\Client;
use WebSocket\Client as WebSocketClient;
class Cherryio {
    private $url = "http://localhost:6001/publish";
    private $wsUrl = "ws://localhost:6001/ws";
    private $client;
    private $apiKey;
    private $secretKey;
    private $channel;
    private $ws;
    public $timeout = 600;
    public $maxRetries = 5;
    private $pingInterval = 60;
    public function __construct($apiKey, $secretKey){
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->client = new Client();
    }


    // Send message to connected channels
    public function fire($channels, $event, array $data){
        if(is_array($channels)){
            foreach($channels as $channel){
                $this->publish($channel, $event, $data);
            }
        }
        else{
            $this->publish($channels, $event, $data);
        }
    }
    private function publish($channel, $event, array $data){
        $response = $this->client->post("{$this->url}", [
            'headers' => [
                'X-API-KEY' => $this->apiKey,
                'X-SECRET-KEY' => $this->secretKey,
            ],
            'json' => [
                'channel' => $channel,
                'event' => $event,
                'data' => $data,
            ],
        ]);
        

        return json_decode($response->getBody(), true);
    }


    // Subscribe to channel
    public function subscribe($channel, $isPrivate = false){
        $this->channel = $channel;
        $message = [
            'action' => 'subscribe',
            'channel' => $channel,
            'private' => $isPrivate
        ];

        $this->ws->send(json_encode($message));
        $response = $this->ws->receive();

        if(isset($response['error'])){
            throw new \Exception("Subscription error: " . $response['error']);
        }

        return $response;
    }

    // Broadcast messages to connected channels
    public function broadcast($event, $data){
        $message = [
            'channel' => $this->channel,
            'event' => $event,
            'data' => $data
        ];

        $this->ws->send(json_encode($message));
    }
    public function open(){
       try{
        $this->ws = new WebSocketClient($this->wsUrl, [
            'headers' => [
                'X-API-KEY' => $this->apiKey,
                'X-SECRET-KEY' => $this->secretKey
            ],
            'timeout' => $this->timeout,
        ]);
        $this->keepAlive();
       }
       catch(\Exception $e){
        $this->reconnect();
        return $e->getMessage();
       }
    }

    public function listen(callable $callback){
        while(true){
            $response = $this->ws->receive();

            try {
                if ($response !== false) {
                   $callback(json_decode($response, true));
                }
            } catch (\Exception $e) {
                echo "Reconnecting...";
                $this->reconnect();
                $this->keepAlive();
            }
            sleep(1); 
        }
    }

    private function keepAlive() {
        while ($this->ws->isConnected()) {
            try {
                $this->ws->send(json_encode(['action' => 'ping']));
                sleep($this->pingInterval);
            } catch (\Exception $e) {
                echo "Ping error: " . $e->getMessage();
                break;
            }
        }
    }
    
    private function reconnect() {
        $retryCount = 0;
        $backOffInterval = 1;

        while($retryCount < $this->maxRetries){
            try{
                $this->ws = new WebSocketClient($this->wsUrl, [
                    'headers' => [
                        'X-API-KEY' => $this->apiKey,
                        'X-SECRET-KEY' => $this->secretKey
                    ]
                ]);
                break;
            } catch(\Exception $e){
                echo "Connection error: " .$e->getMessage() . "\n";
                sleep($backOffInterval);
                $retryCount++;
                $backOffInterval *= 2;
            }
        }

        if($retryCount == $this->maxRetries){
            echo "Max retries reached, unable to connect";
        }
    }

    public function close(){
        if ($this->ws) {
            $this->ws->close();
        }
    }
    
}

