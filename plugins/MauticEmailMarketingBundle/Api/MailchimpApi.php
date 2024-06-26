<?php

namespace MauticPlugin\MauticEmailMarketingBundle\Api;

use Mautic\PluginBundle\Exception\ApiErrorException;

class MailchimpApi extends EmailMarketingApi
{
    private string $version = '3.0';

    /**
     * @param array  $parameters
     * @param string $method
     *
     * @return mixed|string
     *
     * @throws ApiErrorException
     */
    protected function request($endpoint, $parameters = [], $method = 'GET')
    {
        if (isset($this->keys['password'])) {
            // Extract the dc from the key
            $parts = explode('-', $this->keys['password']);
            if (2 !== count($parts)) {
                throw new ApiErrorException('Invalid key');
            }

            $dc                   = $parts[1];
            $apiUrl               = 'https://'.$dc.'.api.mailchimp.com';
            $parameters['apikey'] = $this->keys['password'];
        } else {
            $apiUrl               = $this->keys['api_endpoint'];
            $parameters['apikey'] = $this->keys['access_token'];
        }
        $url = sprintf('%s/%s/%s', $apiUrl, $this->version, $endpoint);

        $response = $this->integration->makeRequest($url, $parameters, $method, ['encode_parameters' => 'json']);

        if (is_array($response) && !empty($response['status']) && 'error' == $response['status']) {
            throw new ApiErrorException($response['error']);
        } elseif (is_array($response) && !empty($response['errors'])) {
            $errors = [];
            foreach ($response['errors'] as $error) {
                $errors[] = $error['message'];
            }
            throw new ApiErrorException(implode(' ', $errors));
        } else {
            return $response;
        }
    }

    public function getLists()
    {
        return $this->request('lists', ['limit' => 100]);
    }

    /**
     * @return mixed|string
     *
     * @throws ApiErrorException
     */
    public function getCustomFields($listId)
    {
        return $this->request('lists/'.$listId.'/merge-fields');
    }

    /**
     * @param array $fields
     * @param array $config
     *
     * @return mixed|string
     *
     * @throws ApiErrorException
     */
    public function subscribeLead($email, $listId, $fields = [], $config = [])
    {
        $emailStruct        = new \stdClass();
        $emailStruct->email = $email;

        $parameters = array_merge($config, [
            'id' => $listId,
        ]);
        if (!empty($fields)) {
            $parameters = array_merge($parameters, ['merge_fields' => $fields]);
        }
        $parameters['email_address'] = $email;

        return $this->request('lists/'.$listId.'/members', $parameters, 'POST');
    }
}
