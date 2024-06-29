<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\FirebaseCloudMessaging;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendNotification
{

    private $configKey = 'customConfig.google.firebase';
    private $fcmConfigKey = 'customConfig.google.firebase.fcm';
    private $useLegacyHttpMode = '';
    private $fcmServerKey = '';
    private $firebaseProjectId = '';
    private $firebaseServiceConfigType = '';
    private $firebaseServiceAccountClientId = '';
    private $firebaseServiceAccountClientEmail = '';
    private $firebaseServiceAccountPrivateKey = '';
    private $firebaseServiceAccountJsonPath = '';
    private $fcmDomainProtocol = '';
    private $fcmDomainUrl = '';
    private $fcmSendUri = '';
    private $fcmLegacySendApiUrl = '';
    private $fcmSendV1Prefix = '';
    private $fcmSendV1Uri = '';
    private $fcmSendV1ApiUrl = '';

    public function __construct()
    {
        $this->setFirebaseServiceVariables();
    }

    /**
     * Send Push Notifications using Firebase Cloud Messaging (FCM).
     *
     * @param string|array|null $deviceTokens The array of FCM Device Tokens.
     * @param array|null $notificationData The array of Extra Data for the Notification.
     * @param array|null $notificationConfigs The array of Configurations for the Notification.
     * @param string|null $priority The priority of the Notification: 'High' or 'Normal'. Default: 'High'.
     * @param int|null $timeToLive The Lifespan of an Undelivered Notification in seconds: 0 to 2419200. Default: null.
     *
     * @return mixed|null
     */
    public function sendPushNotification($deviceTokens = null, array $notificationData = null, array $notificationConfigs = null, ?string $priority = 'high', ?int $timeToLive = null)
    {
        return $this->sendNormalHttpPushNotification($deviceTokens, $notificationData, $notificationConfigs, $priority, $timeToLive);
    }

    private function setFirebaseServiceVariables()
    {
        $mainConfigs = config($this->configKey);
        $fcmConfigs = config($this->fcmConfigKey);
        $this->firebaseProjectId = $mainConfigs['projectId'];
        $this->fcmDomainProtocol = $fcmConfigs['domainProtocol'];
        $this->fcmDomainUrl = $fcmConfigs['domainUrl'];
        $this->fcmSendUri = $fcmConfigs['sendUri'];
        $this->fcmLegacySendApiUrl = $fcmConfigs['sendApiUrl'];
        $this->fcmSendV1Prefix = $fcmConfigs['sendV1Prefix'];
        $this->fcmSendV1Uri = $fcmConfigs['sendV1Uri'];
        $this->firebaseServiceAccountJsonPath = $fcmConfigs['authKeysJsonV1'];
        $this->fcmSendV1ApiUrl = $fcmConfigs['sendV1ApiUrl'];
    }


    private function sendNormalHttpPushNotification($deviceToken = null, $notificationData = null, $notificationConfigs = null, $priority = 'high', $timeToLive = 0)
    {

        try {

            if (is_null($deviceToken) || ((is_array($deviceToken) && (count($deviceToken) == 0)) || (!is_array($deviceToken) && (trim($deviceToken) == '')))) {
                return null;
            }

            $registrationIds = [];
            if (is_array($deviceToken)) {
                foreach ($deviceToken as $item) {
                    if (trim($item) != '') {
                        $registrationIds[] = trim($item);
                    }
                }
            } else {
                $registrationIds[] = trim($deviceToken);
            }
            if (count($registrationIds) == 0) {
                return null;
            }

            if (is_null($notificationData) || !is_array($notificationData) || (count($notificationData) == 0)) {
                return null;
            }

            if (is_null($notificationConfigs) || !is_array($notificationConfigs) || (count($notificationConfigs) == 0)) {
                Log::info('We have entered notificationConfigs Negative impact.');
                return null;
            }

            if (trim($this->firebaseServiceAccountJsonPath) == '') {
                return null;
            }

            if (trim($this->fcmSendV1ApiUrl) == '') {
                return null;
            }

            $availablePriorities = [
                'normal' => [
                    'android' => 'normal',
                    'apn' => '5',
                    'webpush' => 'normal'
                ],
                'high' => [
                    'android' => 'high',
                    'apn' => '10',
                    'webpush' => 'high'
                ],
            ];

            $priorityClean = (!is_null($priority) && (trim($priority) != '') && array_key_exists(strtolower(trim($priority)), $availablePriorities)) ? strtolower(trim($priority)) : 'high';
            $timeToLiveClean = (!is_null($timeToLive) && is_numeric($timeToLive) && ((int)trim($timeToLive) >= 0)) ? (int)trim($timeToLive) : null;
            if (!is_null($timeToLiveClean) && ((int)$timeToLiveClean > 2419200)) {
                $timeToLiveClean = 2419200;
            }

            $notifyDataTemp = $notificationData;
            $notificationData = [];
            foreach ($notifyDataTemp as $currentDataKey => $currentDataValue) {
                $notificationData[$currentDataKey] = is_numeric($currentDataValue) ? (string)$currentDataValue : $currentDataValue;
            }

            $soundFileName = null;
            if (array_key_exists('sound', $notificationConfigs)) {
                $soundFileName = $notificationConfigs['sound'];
                unset($notificationConfigs['sound']);
            }

            $androidChannelId = null;
            if (array_key_exists('android_channel_id', $notificationConfigs)) {
                $androidChannelId = $notificationConfigs['android_channel_id'];
                unset($notificationConfigs['android_channel_id']);
            }

            $soundPlay = null;
            if (array_key_exists('soundPlay', $notificationConfigs)) {
                $soundPlay = $notificationConfigs['soundPlay'];
                unset($notificationConfigs['soundPlay']);
            }

            $showInForeground = null;
            if (array_key_exists('show_in_foreground', $notificationConfigs)) {
                $showInForeground = $notificationConfigs['show_in_foreground'];
                unset($notificationConfigs['show_in_foreground']);
            }

            $clickAction = null;
            if (array_key_exists('click_action', $notificationConfigs)) {
                $clickAction = $notificationConfigs['click_action'];
                unset($notificationConfigs['click_action']);
            }

            $postData = [
                'message' => [
                    /*'content_available' => true,*/
                    'android' => [
                        'priority' => $availablePriorities[$priorityClean]['android'],
                    ],
                    'apns' => [
                        'headers' => [
                            'apns-priority' => $availablePriorities[$priorityClean]['apn'],
                        ],
                    ],
                    'webpush' => [
                        'headers' => [
                            'Urgency' => $availablePriorities[$priorityClean]['webpush'],
                        ],
                    ],
                    'data' => $notificationData,
                    'notification' => $notificationConfigs,
                ],
            ];

            if (!is_null($timeToLiveClean)) {
                $postData['message']['android']['ttl'] = $timeToLiveClean . 's';
                $postData['message']['apns']['headers']['apns-expiration'] = strtotime('+' . $timeToLiveClean . 'secs');
                $postData['message']['webpush']['headers']['TTL'] = (string)$timeToLiveClean;
            }

            if (!is_null($soundFileName)) {
                $postData['message']['android']['notification']['sound'] = $soundFileName;
            } else {
                $postData['message']['android']['notification']['default_sound'] = true;
            }

            if (!is_null($androidChannelId)) {
                $postData['message']['android']['notification']['channel_id'] = $androidChannelId;
            }

            if (!is_null($clickAction)) {
                $postData['message']['data']['click_action'] = $clickAction;
                $postData['message']['android']['notification']['click_action'] = $clickAction;
            }

            $returnResponses = [];
            foreach ($registrationIds as $currentDeviceToken) {
                $postData['message']['token'] = $currentDeviceToken;

                Log::info('current device token '.$currentDeviceToken);
                $googleApiClient = new GoogleClient();
                /*$googleApiClient->setAuthConfig($serviceAccountConfig);*/
                $googleApiClient->setAuthConfig(public_path(trim($this->firebaseServiceAccountJsonPath)));
                $googleApiClient->addScope(FirebaseCloudMessaging::FIREBASE_MESSAGING);
                $httpClient = $googleApiClient->authorize();
                $returnResponse = $httpClient->post($this->fcmSendV1ApiUrl, ['json' => $postData]);
                $returnResponses[] = $returnResponse->getBody()->getContents();

                Log::info('Notification send successfully');
                unset($googleApiClient);
            }

            return $returnResponses;

        } catch (\Exception $ex) {
            Log::info('Firebase Push Notification Error : ' . $ex->getMessage());
            return null;
        } catch (GuzzleException $gEx) {
            Log::info('Firebase Push Notification Guzzle Exception Error : ' . $gEx->getMessage());
            return null;
        }

    }

}
