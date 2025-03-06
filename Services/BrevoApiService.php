<?php

namespace Brevo\Services;

use Brevo\Brevo;
use Thelia\Exception\TheliaProcessException;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;

class BrevoApiService
{
    public function sendTrackEvent($eventName, $data = [])
    {
        $marketingAutomationKey = ConfigQuery::read(Brevo::CONFIG_AUTOMATION_KEY);
        $apikey = ConfigQuery::read(Brevo::CONFIG_API_SECRET);

        if (!$marketingAutomationKey || !$apikey) {
            return;
        }

        $this->sendRequest(
            'POST',
            'https://in-automate.brevo.com/api/v2/trackEvent',
            $data + ['event' => $eventName],
            [
                sprintf('ma-key: %s', $marketingAutomationKey),
                sprintf('api-key: %s', $apikey),
            ]
        );
    }

    public function sendPostEvent($url, $data = [])
    {
        $marketingAutomationKey = ConfigQuery::read(Brevo::CONFIG_AUTOMATION_KEY);
        $apikey = ConfigQuery::read(Brevo::CONFIG_API_SECRET);

        if (!$marketingAutomationKey || !$apikey) {
            return;
        }

        return $this->sendRequest(
            'POST',
            $url,
            $data,
            [
                sprintf('ma-key: %s', $marketingAutomationKey),
                sprintf('api-key: %s', $apikey),
            ]
        );
    }

    private function sendRequest($method, $url, $data = [], $headers = [])
    {
        try {
            $curl = curl_init();

            $defaultHeaders = [
                'content-type: application/json',
                'accept: application/json',
            ];

            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers)
            ]);

            if (null === $jsonData = json_encode($data)) {
                throw new TheliaProcessException("Failed to JSON encode Brevo request body :" . $jsonData);
            }

            if ($method === 'POST' || $method === 'PUT') {
                curl_setopt_array($curl, [CURLOPT_POSTFIELDS => json_encode($data)]);
            }

            $rawResponse = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

            $jsonResponse = json_decode($rawResponse, true);

            $response = [
                'data' => $jsonResponse,
                'status' => $status,
            ];

            $error = curl_error($curl);

            $response['success'] = !$error && substr((string)$status, 0, 1) === '2';

            if (!$response['success']) {
                $errorMessage = !empty($error) ? $error : (($jsonResponse && $jsonResponse['message']) ? $jsonResponse['message'] : 'Undefined error');

                Tlog::getInstance()->error(
                    "Brevo API call error : Status: $status, error: $errorMessage"
                );

                $response['error'] = $errorMessage;
            }

            curl_close($curl);
        } catch (\Exception $exception) {
            Tlog::getInstance()->error($exception->getMessage());

            $response = [
                'success' => false,
                'error' => $exception->getMessage(),
            ];
        }

        return $response;
    }

    public function enableEcommerce()
    {
        return $this->sendPostEvent('https://api.brevo.com/v3/ecommerce/activate');
    }
}
