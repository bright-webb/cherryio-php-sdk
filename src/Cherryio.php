<?php
namespace Cherryio;

use GuzzleHttp\Client;
class Cherryio {
    private $url = "http://3.83.240.184:6001/publish";
    private $client;
    private $apiKey;
    private $secretKey;
    private $channel;
    private $ws;
    public $timeout = 600;
    public function __construct($apiKey, $secretKey){
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->client = new Client();
    }

    public function broadcast($channels, $event, array $data){
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
    public function subscribe($channel){
        $this->channel = $channel;
        return $this;
    }

    public function fire($event, $data){
        $this->publish($this->channel, $event, $data);
    }
    
}

