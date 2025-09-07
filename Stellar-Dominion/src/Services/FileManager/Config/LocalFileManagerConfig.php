<?php

namespace StellarDominion\Services\FileManager\Config;

use StellarDominion\Services\FileManager\FileDriverType;
use StellarDominion\Services\FileManager\DriverType;

/**
 * Local File Manager Configuration
 * 
 * Configuration object for local filesystem storage.
 */
class LocalFileManagerConfig implements FileManagerConfigInterface
{
	private string $baseDirectory;
	private string $baseUrl;

	/**
	 * Constructor
	 * 
	 * @param string $baseDirectory Absolute path to the base storage directory
	 * @param string $baseUrl Base URL for accessing files
	 * @throws \InvalidArgumentException If configuration is invalid
	 */
	public function __construct(string $baseDirectory, string $baseUrl = '/uploads')
	{
		$this->baseDirectory = rtrim($baseDirectory, '/');
		$this->baseUrl = rtrim($baseUrl, '/');
		
		$this->validate();
	}

	/**
	 * Create from environment variables
	 * 
	 * @return self
	 */
	public static function fromEnvironment(): self
	{
		$baseDirectory = $_ENV['FILE_STORAGE_LOCAL_PATH'] ?? (PROJECT_ROOT . '/public/uploads');
		$baseUrl = $_ENV['FILE_STORAGE_LOCAL_URL'] ?? '/uploads';

		return new self($baseDirectory, $baseUrl);
	}

	/**
	 * Get the driver type as DriverType object
	 * 
	 * @return DriverType
	/**
	 * Get the driver type as DriverType object
	 * 
	 * @return DriverType
	 */
	public function getDriverType(): DriverType
	{
		return DriverType::local();
	}

	/**
	 * Get the base directory
	 * 
	 * @return string
	 */
	public function getBaseDirectory(): string
	{
		return $this->baseDirectory;
	}

	/**
	 * Get the base URL
	 * 
	 * @return string
	 */
	public function getBaseUrl(): string
	{
		return $this->baseUrl;
	}

	/**
	 * Validate the configuration
	 * 
	 * @throws \InvalidArgumentException If configuration is invalid
	 */
	public function validate(): void
	{
		if (empty($this->baseDirectory)) {
			throw new \InvalidArgumentException("Base directory cannot be empty");
		}

		if (empty($this->baseUrl)) {
			throw new \InvalidArgumentException("Base URL cannot be empty");
		}

		// Additional validation can be added here
		// For example, checking if directory is writable (if it exists)
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
			'base_directory' => $this->baseDirectory,
			'base_url' => $this->baseUrl,
		];
	}

	/**
	 * Create default configuration
	 * 
	 * @return self
	 */
	public static function createDefault(): self
	{
		return new self(
			PROJECT_ROOT . '/public/uploads',
			'/uploads'
		);
	}
}
