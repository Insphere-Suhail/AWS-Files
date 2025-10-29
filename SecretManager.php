<?php

namespace common\components;

use Yii;
use Aws\Exception\AwsException;
use Aws\SecretsManager\SecretsManagerClient;
use yii\redis\Connection as RedisConnection;

/**
 * SecretManager class fetches AWS Secrets Manager data and caches it in Redis
 * 
 * author Pawan Kumar
 */
class SecretManager
{

    private string $secretName;
    private string $region;
    private string $redisHost;
    private int $redisDb = 10;
    private int $cacheTtl = 3600; // seconds

    private ?array $data = null;

    public function __construct()
    {
        $this->secretName = getenv('SECRET_VOULT') ?: '';
        $this->region = getenv('AWS_REGION') ?: '';
        $this->redisHost = getenv('REDIS_HOST') ?: '';

        // You can initialize Redis here, but fetching secrets is lazy
        $this->redis = new RedisConnection([
            'hostname' => $this->redisHost,
            'port' => 6379,
            'database' => $this->redisDb,
        ]);
    }

    /**
     * Get secrets data
     *
     * @return array
     * @throws \Exception
     */
    public function get(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        // Try Redis first
        $cachedSecret = $this->redis->get('aws:secret:' . $this->secretName);
        if ($cachedSecret !== false) {
            $this->data = json_decode($cachedSecret, true);
            if (!empty($this->data)) {
                return $this->data;
            }
        }

        // Fallback to AWS Secrets Manager
        $this->data = $this->fetchFromSecretsManager();

        // Cache in Redis
        $this->redis->setex('aws:secret:' . $this->secretName, $this->cacheTtl, json_encode($this->data));

        return $this->data;
    }

    /**
     * Fetch secret from AWS Secrets Manager
     *
     * @return array
     * @throws \Exception
     */
    private function fetchFromSecretsManager(): array
    {
        $client = new SecretsManagerClient([
            'version' => 'latest',
            'region' => $this->region,
        ]);

        try {
            $result = $client->getSecretValue(['SecretId' => $this->secretName]);
            $secret = $result['SecretString'] ?? base64_decode($result['SecretBinary'] ?? '');
            $data = json_decode($secret, true);

            if (empty($data)) {
                throw new \RuntimeException("AWS Secrets Manager returned empty data for '{$this->secretName}'");
            }

            return $data;
        } catch (AwsException $e) {
            throw new \RuntimeException('AWS Secrets Manager error: ' . $e->getMessage());
        }
    }
}

