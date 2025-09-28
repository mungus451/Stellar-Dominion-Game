<?php

namespace StellarDominion\Services;

use Aws\SecretsManager\SecretsManagerClient;
use Aws\Exception\AwsException;

/**
 * AWS Secrets Manager Service for Stellar Dominion
 * 
 * Handles secure retrieval of database credentials from AWS Secrets Manager
 */
class SecretsManagerService
{
    private $secretsClient;
    private $region;
    private static $credentialsCache = [];

    /**
     * Constructor
     * 
     * @param string $region AWS region
     */
    public function __construct(string $region = 'us-east-2')
    {
        $this->region = $region;
        $this->secretsClient = new SecretsManagerClient([
            'region' => $region,
            'version' => 'latest'
        ]);
    }

    /**
     * Get database credentials from Secrets Manager with extension optimization
     * 
     * @param string $secretArn The ARN of the secret containing database credentials
     * @param string $versionStage The version stage to retrieve (AWSCURRENT or AWSPENDING)
     * @return array Database credentials array with 'username' and 'password' keys
     * @throws \Exception If unable to retrieve or parse credentials
     */
    public function getDatabaseCredentials(string $secretArn, string $versionStage = 'AWSCURRENT'): array
    {
        // Return cached credentials if available (for performance in Lambda)
        $cacheKey = $secretArn . '_' . $versionStage;
        if (isset(self::$credentialsCache[$cacheKey])) {
            return self::$credentialsCache[$cacheKey];
        }

        // Try AWS Parameters and Secrets Extension first (faster, local cache)
        $credentials = $this->tryExtensionRetrieval($secretArn, $versionStage);
        
        if ($credentials === null) {
            // Fallback to direct AWS SDK call
            $credentials = $this->retrieveViaAwsSdk($secretArn, $versionStage);
        }

        // Cache credentials for the duration of the Lambda execution
        self::$credentialsCache[$cacheKey] = $credentials;
        return $credentials;
    }

    /**
     * Try to retrieve credentials using AWS Parameters and Secrets Extension
     * 
     * @param string $secretArn The ARN of the secret
     * @param string $versionStage The version stage
     * @return array|null Credentials array or null if extension not available
     */
    private function tryExtensionRetrieval(string $secretArn, string $versionStage): ?array
    {
        // Check if extension is available
        $port = $_ENV['PARAMETERS_SECRETS_EXTENSION_HTTP_PORT'] ?? '2773';
        $extensionUrl = "http://localhost:{$port}/secretsmanager/get?secretId=" . urlencode($secretArn);
        
        if ($versionStage !== 'AWSCURRENT') {
            $extensionUrl .= "&versionStage=" . urlencode($versionStage);
        }

        // Use curl with short timeout since this should be very fast
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $extensionUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_HTTPHEADER => [
                'X-Aws-Parameters-Secrets-Token: ' . ($_ENV['AWS_SESSION_TOKEN'] ?? ''),
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            // Extension not available or failed, will fallback to SDK
            return null;
        }

        $secretData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Failed to parse extension response JSON: " . json_last_error_msg());
            return null;
        }

        // Extract SecretString from the extension response
        if (!isset($secretData['SecretString'])) {
            error_log("SecretString not found in extension response");
            return null;
        }

        return $this->parseSecretString($secretData['SecretString']);
    }

    /**
     * Retrieve credentials using direct AWS SDK call
     * 
     * @param string $secretArn The ARN of the secret
     * @param string $versionStage The version stage
     * @return array Database credentials
     * @throws \Exception If unable to retrieve or parse credentials
     */
    private function retrieveViaAwsSdk(string $secretArn, string $versionStage): array
    {
        try {
            $result = $this->secretsClient->getSecretValue([
                'SecretId' => $secretArn,
                'VersionStage' => $versionStage
            ]);
            
            if (!isset($result['SecretString'])) {
                throw new \Exception("Secret string not found in secret: {$secretArn}");
            }

            return $this->parseSecretString($result['SecretString']);

        } catch (AwsException $e) {
            error_log("AWS Secrets Manager Error: " . $e->getMessage());
            throw new \Exception("Failed to retrieve database credentials: " . $e->getMessage());
        }
    }

    /**
     * Parse secret string JSON into credentials array
     * 
     * @param string $secretString JSON string containing credentials
     * @return array Parsed credentials
     * @throws \Exception If unable to parse
     */
    private function parseSecretString(string $secretString): array
    {
        $secretData = json_decode($secretString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Failed to parse secret JSON: " . json_last_error_msg());
        }

        if (!isset($secretData['username']) || !isset($secretData['password'])) {
            throw new \Exception("Secret must contain 'username' and 'password' fields");
        }

        return [
            'username' => $secretData['username'],
            'password' => $secretData['password'],
            'host' => $secretData['host'] ?? null,
            'port' => $secretData['port'] ?? 3306,
            'dbname' => $secretData['dbname'] ?? null,
            'engine' => $secretData['engine'] ?? 'mysql'
        ];
    }

    /**
     * Get database credentials with automatic fallback for rotation scenarios
     * Simplified for AWS managed rotation which is more reliable
     * 
     * @param string $secretArn The ARN of the secret
     * @return array Database credentials
     */
    public function getDatabaseCredentialsWithRotationFallback(string $secretArn): array
    {
        try {
            // Try AWSCURRENT first (99% of the time this works)
            return $this->getDatabaseCredentials($secretArn, 'AWSCURRENT');
        } catch (\Exception $e) {
            // During rotation window, try AWSPENDING as fallback
            error_log("AWSCURRENT failed during rotation, trying AWSPENDING: " . $e->getMessage());
            try {
                return $this->getDatabaseCredentials($secretArn, 'AWSPENDING');
            } catch (\Exception $fallbackException) {
                error_log("Both AWSCURRENT and AWSPENDING failed: " . $fallbackException->getMessage());
                throw new \Exception("Unable to retrieve credentials from any version: " . $e->getMessage());
            }
        }
    }

    /**
     * Get a single credential value from the secret
     * 
     * @param string $secretArn The ARN of the secret
     * @param string $key The key to retrieve ('username' or 'password')
     * @return string The credential value
     * @throws \Exception If unable to retrieve credential
     */
    public function getCredentialValue(string $secretArn, string $key): string
    {
        $credentials = $this->getDatabaseCredentials($secretArn);
        
        if (!isset($credentials[$key])) {
            throw new \Exception("Credential key '{$key}' not found in secret");
        }

        return $credentials[$key];
    }

    /**
     * Create a static instance for easy access
     * 
     * @param string $region AWS region
     * @return SecretsManagerService
     */
    public static function create(string|null $region = null): SecretsManagerService
    {
        $region = $region ?? $_ENV['AWS_REGION'] ?? 'us-east-2';
        return new self($region);
    }

    /**
     * Check if running in AWS Lambda environment
     * 
     * @return bool True if running in Lambda, false otherwise
     */
    public static function isLambdaEnvironment(): bool
    {
        return isset($_ENV['AWS_LAMBDA_FUNCTION_NAME']) || isset($_ENV['DB_SECRET_ARN']);
    }

    /**
     * Get database credentials with fallback for local development
     * 
     * @param string|null $secretArn Secret ARN (if null, will try to get from environment)
     * @param array $fallbackCredentials Fallback credentials for local development
     * @return array Database credentials
     */
    public static function getDatabaseCredentialsWithFallback(
        ?string $secretArn = null, 
        array $fallbackCredentials = []
    ): array {
        // If not in Lambda environment, use fallback credentials
        if (!self::isLambdaEnvironment()) {
            return $fallbackCredentials;
        }
        // Get secret ARN from environment if not provided
        $secretArn = $secretArn ?? $_ENV['DB_SECRET_ARN'] ?? null;
        if (!$secretArn) {
            throw new \Exception("DB_SECRET_ARN environment variable not set");
        }
        $service = self::create();

        return $service->getDatabaseCredentialsWithRotationFallback($secretArn);
    }

    /**
     * Clear credentials cache (useful for testing)
     */
    public static function clearCache(): void
    {
        self::$credentialsCache = [];
    }
}
