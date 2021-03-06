<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use App\Tweet;

class TweetsController extends Controller
{
    public $reply;
    public $replyArray 
        = [ 
            'you mentioned me, beautiful day it is today. Do have a great day!',
            'always at your service!', 
            'Your picture looks really nice',
            'What would you have me do boss!'
        ];
   
  
    protected $client;

    public function index()
    {
        $tweetData =$this->getTweets();
        $result = $this->postTweets($tweetData);
        print_r($result);
        return view('tweets', compact('tweetData'));
    }


    function __construct()
    {
        //creates a middleware for authenticating requests
        $stack = HandlerStack::create();

        $middleware = new Oauth1(
            [
            'consumer_key'    => config('services.twitter.consumer_key'),
            'consumer_secret' => config('services.twitter.consumer_secret'),
            'token'           => config('services.twitter.access_token'),
            'token_secret'    => config('services.twitter.access_token_secret'),
            ]
        );

        $stack->push($middleware);

        $this->client = new Client(
            [
            'base_uri' => 'https://api.twitter.com/1.1/',
            'handler' => $stack,
            'auth' => 'oauth',
            ]
        );
    }

    public function getTweets()
    {
        $sinceId = Redis::get('TweetId') ?? 1;
          // Set the "auth" request option to "oauth" to sign using oauth
        $res = $this->client->get(
            'statuses/mentions_timeline.json',
            [
                'query'=> [
                    'count'=>'20',
                    'since_id'=>  $sinceId //retrieves tweets since this particular id
                ]
            ]

        );

        $data = json_decode($res->getBody());
       


        $tweets = [];
        foreach ($data as $index => $mentions) {

            // pushes the data retrieved into an array

            array_push($tweets,
                [
                  'id' => !empty($mentions->id) ? $mentions->id : '',
                  'username' => !empty($mentions->user) ? $mentions->user->screen_name : '',
                ]
            );


        };
        return $tweets;
    }


    public function postTweets($data)
    {
        //gets random values from the replyArray and sets it as the value  for reply
        $this->reply = array_rand($this->replyArray);
     
        foreach ($data as $tweet) {
          
           
            $res = $this->client->post(
                'statuses/update.json',
                [
                  'query' => [
                    'status' => '@'.$tweet['username'].' Hi there, ' . $this->replyArray[$this->reply],
                    'in_reply_to_status_id' => $tweet['id'],
                  ]
                ]
            );
            //sets the Id of the tweet we're replying to as the since ID
            $sinceId = $tweet['id'];
            //caches the since ID,so we can access it when were making a new request
            Redis::set('TweetId', $sinceId);

            $data = json_decode($res->getBody());

            //print something to the screen 

            $responseCode = $res->getStatusCode();
            if ($responseCode==200) {
                echo('your tweet was sent successfully');
               
            } else {
                return;
            };
        };
        
        
        
    }




}
