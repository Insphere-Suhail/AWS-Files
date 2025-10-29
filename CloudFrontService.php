<?php

namespace frontend\components;

use Aws\CloudFront\CloudFrontClient;
use Aws\Exception\AwsException;
use Yii;
use yii\base\Component;

class CloudFrontService extends Component
{
    /**
     * Generate and set CloudFront signed cookies using AWS SDK.
     *
     * @param int $expire_seconds Optional expiration offset (default: 1800 for 30 min)
     * @return bool Success
     * @throws AwsException|\Exception
     */
    public function setSignedCookie($name, $expire_seconds = 1800)
    {
        $config = Yii::$app->params['awsCloudfront'][$name];
        $expire_time = time() + $expire_seconds;
        $resource = $config['distribution_url'] . '/*'; // e.g., https://opjsangul.com/*

        // Build custom policy JSON (matches your original)
        $policy = [
            'Statement' => [[
                'Resource' => $resource,
                'Condition' => [
                    'DateLessThan' => ['AWS:EpochTime' => $expire_time]
                ]
            ]]
        ];
        $policy_json = json_encode($policy);
        if ($policy_json === false) {
            throw new \Exception('Failed to encode policy JSON');
        }

        // Initialize CloudFrontClient (SDK auto-handles credentials)
        $client = new CloudFrontClient([
            'version' => '2020-05-31', // Latest as of 2025; check docs for updates
            'region' => $config['region'],
        ]);

        // Generate signed cookies
        $signed_cookies = [];
        try {
            $signed_cookies = $client->getSignedCookie([
                'url' => $resource, // Base for wildcard
                'policy' => $policy_json, // Custom policy
                'private_key' => file_get_contents($config['private_key_path']), // Load PEM
                'key_pair_id' => $config['key_pair_id'],
            ]);
        } catch (AwsException $e) {
            throw new \Exception('SDK signing failed: ' . $e->getAwsErrorMessage());
        }

        if (empty($signed_cookies)) {
            throw new \Exception('No signed cookies generated');
        }

        // Set cookies (matches your original options)
        $domain = $config['cookie_domain']; // Or from config
        $secure = true;
        $httpOnly = true;
        $cookie_options = [
            'expires' => $expire_time,
            'path' => '/',
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => 'None',
        ];

        setcookie('CloudFront-Policy', $signed_cookies['CloudFront-Policy'], $cookie_options);
        setcookie('CloudFront-Signature', $signed_cookies['CloudFront-Signature'], $cookie_options);
        setcookie('CloudFront-Key-Pair-Id', $signed_cookies['CloudFront-Key-Pair-Id'], $cookie_options);

        return true;
    }
}