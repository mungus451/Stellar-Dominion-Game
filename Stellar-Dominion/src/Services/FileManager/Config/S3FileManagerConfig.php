<?php

namespace StellarDominion\Services\FileManager\Config;

use StellarDominion\Services\FileManager\FileDriverType;
use StellarDominion\Services\FileManager\DriverType;

/**
 * S3 File Manager Configuration
 * 
 * Configuration object for Amazon S3 storage.
 */
class S3FileManagerConfig implements FileManagerConfigInterface
{
	private string $bucket;
	private string $region;
	private ?string $accessKeyId;
	private ?string $secretAccessKey;
	private ?string $baseUrl;

	/**
	 * Constructor
	 * 
	 * @param string $bucket S3 bucket name
	 * @param string $region AWS region
	 * @param string|null $accessKeyId AWS access key ID (optional if using IAM roles)
	 * @param string|null $secretAccessKey AWS secret access key (optional if using IAM roles)
	 * @param string|null $baseUrl Custom base URL (optional, for CDN)
	 * @throws \InvalidArgumentException If configuration is invalid
	 */
	public function __construct(
		string $bucket,
		string $region = 'us-east-1',
		?string $accessKeyId = null,
		?string $secretAccessKey = null,
		?string $baseUrl = null
	) {
		$this->bucket = trim($bucket);
		$this->region = trim($region);
		$this->accessKeyId = $accessKeyId ? trim($accessKeyId) : null;
		$this->secretAccessKey = $secretAccessKey ? trim($secretAccessKey) : null;
		$this->baseUrl = $baseUrl ? rtrim($baseUrl, '/') : null;

		$this->validate();
	}

	/**
	 * Create from environment variables
	 * 
	 * @return self
	 * @throws \InvalidArgumentException If required environment variables are missing
	 */
	public static function fromEnvironment(): self
	{
		$bucket = $_ENV['FILE_STORAGE_S3_BUCKET'] ?? '';
		$region = $_ENV['FILE_STORAGE_S3_REGION'] ?? 'us-east-1';
		$accessKeyId = $_ENV['AWS_ACCESS_KEY_ID'] ?? null;
		$secretAccessKey = $_ENV['AWS_SECRET_ACCESS_KEY'] ?? null;
		
		// Support multiple CDN URL environment variables
		$baseUrl = $_ENV['CLOUDFRONT_DOMAIN'] ?? $_ENV['FILE_STORAGE_CDN_URL'] ?? $_ENV['FILE_STORAGE_S3_URL'] ?? null;

		return new self($bucket, $region, $accessKeyId, $secretAccessKey, $baseUrl);
	}

	/**
	 * Get the driver type as DriverType object
	 * 
	 * @return DriverType
	 */
	public function getDriverType(): DriverType
	{
		return DriverType::s3();
	}

	/**
	 * Get the S3 bucket name
	 * 
	 * @return string
	 */
	public function getBucket(): string
	{
		return $this->bucket;
	}

	/**
	 * Get the AWS region
	 * 
	 * @return string
	 */
	public function getRegion(): string
	{
		return $this->region;
	}

	/**
	 * Get the AWS access key ID
	 * 
	 * @return string|null
	 */
	public function getAccessKeyId(): ?string
	{
		return $this->accessKeyId;
	}

	/**
	 * Get the AWS secret access key
	 * 
	 * @return string|null
	 */
	public function getSecretAccessKey(): ?string
	{
		return $this->secretAccessKey;
	}

	/**
	 * Get the custom base URL
	 * 
	 * @return string|null
	 */
	public function getBaseUrl(): ?string
	{
		return $this->baseUrl;
	}

	/**
	 * Check if AWS credentials are provided
	 * 
	 * @return bool
	 */
	public function hasCredentials(): bool
	{
		return $this->accessKeyId !== null && $this->secretAccessKey !== null;
	}

	/**
	 * Validate the configuration
	 * 
	 * @throws \InvalidArgumentException If configuration is invalid
	 */
	public function validate(): void
	{
		if (empty($this->bucket)) {
			throw new \InvalidArgumentException("S3 bucket name cannot be empty");
		}

		if (empty($this->region)) {
			throw new \InvalidArgumentException("AWS region cannot be empty");
		}

		// Validate bucket name format (basic validation)
		if (!preg_match('/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/', $this->bucket)) {
			throw new \InvalidArgumentException("Invalid S3 bucket name format");
		}

		// If one credential is provided, both should be provided
		if (($this->accessKeyId === null) !== ($this->secretAccessKey === null)) {
			throw new \InvalidArgumentException("Both access key ID and secret access key must be provided together, or both omitted for IAM role usage");
		}
	}

	/**
	 * Convert configuration to array format
	 * 
	 * @return array
	 */
	public function toArray(): array
	{
		return [
			'driver' => $this->getDriverType(),
			'bucket' => $this->bucket,
			'region' => $this->region,
			'access_key_id' => $this->accessKeyId,
			'secret_access_key' => $this->secretAccessKey,
			'base_url' => $this->baseUrl,
		];
	}

	/**
	 * Create configuration for development (requires bucket name)
	 * 
	 * @param string $bucket S3 bucket name
	 * @return self
	 */
	public static function createDevelopment(string $bucket): self
	{
		return new self($bucket, 'us-east-1');
	}

	/**
	 * Create configuration for production with IAM roles
	 * 
	 * @param string $bucket S3 bucket name
	 * @param string $region AWS region
	 * @param string|null $cdnUrl Optional CDN URL
	 * @return self
	 */
	public static function createProduction(string $bucket, string $region = 'us-east-1', ?string $cdnUrl = null): self
	{
		return new self($bucket, $region, null, null, $cdnUrl);
	}

	/**
	 * Create configuration with CloudFront CDN
	 * 
	 * @param string $bucket S3 bucket name
	 * @param string $cloudFrontDomain CloudFront distribution domain
	 * @param string $region AWS region
	 * @return self
	 */
	public static function createWithCloudFront(string $bucket, string $cloudFrontDomain, string $region = 'us-east-1'): self
	{
		return new self($bucket, $region, null, null, $cloudFrontDomain);
	}

	/**
	 * Check if CDN is configured
	 * 
	 * @return bool True if CDN URL is configured
	 */
	public function hasCdn(): bool
	{
		return $this->baseUrl !== null;
	}

	/**
	 * Get CDN domain if configured
	 * 
	 * @return string|null CDN domain or null if not configured
	 */
	public function getCdnDomain(): ?string
	{
		return $this->baseUrl;
	}
}
