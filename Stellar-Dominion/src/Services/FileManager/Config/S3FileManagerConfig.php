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
	private ?string $baseUrl;

	/**
	 * Constructor
	 * 
	 * @param string $bucket S3 bucket name
	 * @param string $region AWS region
	 * @throws \InvalidArgumentException If configuration is invalid
	 */
	public function __construct(
		string $bucket,
		string $region = 'us-east-2'
	) {
		$this->bucket = trim($bucket);
		$this->region = trim($region);

		$this->validate();
	}

	/**
	 * Create from environment variables (uses AWS SDK credential chain)
	 * 
	 * @return self
	 * @throws \InvalidArgumentException If required environment variables are missing
	 */
	public static function fromEnvironment(): self
	{
		$bucket = $_ENV['FILE_STORAGE_S3_BUCKET'] ?? '';
		$region = $_ENV['FILE_STORAGE_S3_REGION'] ?? 'us-east-2';

		
		return new self($bucket, $region);
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
		return new self($bucket, 'us-east-2');
	}
}
