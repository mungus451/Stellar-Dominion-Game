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
     * Get database credentials from Secrets Manager
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

        try {
            $result = $this->secretsClient->getSecretValue([
                'SecretId' => $secretArn,
                'VersionStage' => $versionStage
            ]);
            

            if (!isset($result['SecretString'])) {
                throw new \Exception("Secret string not found in secret: {$secretArn}");
            }

            $secretData = json_decode($result['SecretString'], true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Failed to parse secret JSON: " . json_last_error_msg());
            }

            if (!isset($secretData['username']) || !isset($secretData['password'])) {
                throw new \Exception("Secret must contain 'username' and 'password' fields");
            }

            // Cache credentials for the duration of the Lambda execution
            self::$credentialsCache[$cacheKey] = [
                'username' => $secretData['username'],
                'password' => $secretData['password'],
                'host' => $secretData['host'] ?? null,
                'port' => $secretData['port'] ?? 3306,
                'dbname' => $secretData['dbname'] ?? null,
                'engine' => $secretData['engine'] ?? 'mysql'
            ];

            return self::$credentialsCache[$cacheKey];

        } catch (AwsException $e) {
            error_log("AWS Secrets Manager Error: " . $e->getMessage());
            throw new \Exception("Failed to retrieve database credentials: " . $e->getMessage());
        }
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
