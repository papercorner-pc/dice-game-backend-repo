<?php

namespace App\Services;

use Twilio\Exceptions\ConfigurationException;
use Twilio\Rest\Client;

class TwilioService
{
    protected $twilio;

    public function __construct()
    {
        try {
            $this->twilio = new Client(env('TWILIO_SID'), env('TWILIO_AUTH_TOKEN'));
        } catch (ConfigurationException $e) {
        }
    }

    public function sendSms($to, $message)
    {
        $this->twilio->messages->create($to, [
            'from' => env('TWILIO_PHONE_NUMBER'),
            'body' => $message
        ]);
    }
}
