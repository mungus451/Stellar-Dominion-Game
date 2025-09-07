<?php

namespace StellarDominion\Services\FileManager;

use StellarDominion\Services\FileManager\Config\FileManagerConfigInterface;
use StellarDominion\Services\FileManager\Config\LocalFileManagerConfig;
use StellarDominion\Services\FileManager\Config\S3FileManagerConfig;

/**
 * File Manager Factory
 * 
 * Factory class to instantiate the appropriate file manager driver
 * based on configuration objects or environment variables.
 */
class FileManagerFactory
{
	/**
	 * Create a file manager instance based on configuration object
	 * 
	 * @param FileManagerConfigInterface $config Configuration object
	 * @return FileManagerInterface The file manager instance
	 * @throws \Exception If configuration is invalid or driver not supported
	 */
	public static function createFromConfig(FileManagerConfigInterface $config): FileManagerInterface
	{
		// Validate configuration
		$config->validate();

		// Use the DriverType object for better type safety
		$driverType = $config->getDriverType();
		$driver = $driverType->getValue();

		switch ($driver) {
			case FileDriverType::LOCAL:
				return self::createLocalFileManagerFromConfig($config);
				
			case FileDriverType::S3:
				return self::createS3FileManagerFromConfig($config);
				
			default:
				throw new \Exception("Unsupported file storage driver: {$driverType}");
		}
	}

	/**
	 * Create a file manager instance based on configuration array (legacy support)
	 * 
	 * @param array $config Configuration array
	 * @return FileManagerInterface The file manager instance
	 * @throws \Exception If configuration is invalid or driver not supported
	 */
	public static function create(array $config): FileManagerInterface
	{
		$driver = FileDriverType::normalize($config['driver'] ?? FileDriverType::LOCAL);

		switch ($driver) {
			case FileDriverType::LOCAL:
				return self::createLocalFileManager($config);
				
			case FileDriverType::S3:
				return self::createS3FileManager($config);
				
			default:
				throw new \Exception("Unsupported file storage driver: {$driver}");
		}
	}

	/**
	 * Create file manager from environment variables
	 * 
	 * @return FileManagerInterface The file manager instance
	 * @throws \Exception If environment configuration is invalid
	 */
	public static function createFromEnvironment(): FileManagerInterface
	{
		$driver = FileDriverType::normalize($_ENV['FILE_STORAGE_DRIVER'] ?? FileDriverType::LOCAL);

		switch ($driver) {
			case FileDriverType::LOCAL:
				$config = LocalFileManagerConfig::fromEnvironment();
				return self::createFromConfig($config);

			case FileDriverType::S3:
				$config = S3FileManagerConfig::fromEnvironment();
				return self::createFromConfig($config);

			default:
				throw new \Exception("Unsupported file storage driver: {$driver}");
		}
	}

	/**
	 * Create a local file manager instance from configuration object
	 * 
	 * @param FileManagerConfigInterface $config Configuration object
	 * @return LocalFileManager
	 * @throws \Exception If configuration is invalid
	 */
	private static function createLocalFileManagerFromConfig(FileManagerConfigInterface $config): LocalFileManager
	{
		if (!$config instanceof LocalFileManagerConfig) {
			throw new \Exception("Invalid configuration type for local file manager");
		}

		return new LocalFileManager($config->getBaseDirectory(), $config->getBaseUrl());
	}

	/**
	 * Create an S3 file manager instance from configuration object
	 * 
	 * @param FileManagerConfigInterface $config Configuration object
	 * @return S3FileManager
	 * @throws \Exception If configuration is invalid
	 */
	private static function createS3FileManagerFromConfig(FileManagerConfigInterface $config): S3FileManager
	{
		if (!$config instanceof S3FileManagerConfig) {
			throw new \Exception("Invalid configuration type for S3 file manager");
		}

		return new S3FileManager(
			$config->getBucket(),
			$config->getRegion(),
			$config->getAccessKeyId(),
			$config->getSecretAccessKey(),
			$config->getBaseUrl()
		);
	}

	/**
	 * Create a local file manager instance
	 * 
	 * @param array $config Configuration array
	 * @return LocalFileManager
	 * @throws \Exception If configuration is invalid
	 */
	private static function createLocalFileManager(array $config): LocalFileManager
	{
		$baseDirectory = $config['base_directory'] ?? (PROJECT_ROOT . '/public/uploads');
		$baseUrl = $config['base_url'] ?? '/uploads';

		if (empty($baseDirectory)) {
			throw new \Exception("Local file storage requires 'base_directory' configuration");
		}

		return new LocalFileManager($baseDirectory, $baseUrl);
	}

	/**
	 * Create an S3 file manager instance
	 * 
	 * @param array $config Configuration array
	 * @return S3FileManager
	 * @throws \Exception If configuration is invalid
	 */
	private static function createS3FileManager(array $config): S3FileManager
	{
		$bucket = $config['bucket'] ?? '';
		$region = $config['region'] ?? 'us-east-1';
		$accessKeyId = $config['access_key_id'] ?? null;
		$secretAccessKey = $config['secret_access_key'] ?? null;
		$baseUrl = $config['base_url'] ?? null;

		if (empty($bucket)) {
			throw new \Exception("S3 file storage requires 'bucket' configuration");
		}

		return new S3FileManager(
			$bucket,
			$region,
			$accessKeyId,
			$secretAccessKey,
			$baseUrl
		);
	}

	/**
	 * Get default configuration for a driver
	 * 
	 * @param string $driver The driver name (use FileDriverType constants)
	 * @return FileManagerConfigInterface Default configuration object
	 * @throws \Exception If driver is not supported
	 */
	public static function getDefaultConfig(string $driver): FileManagerConfigInterface
	{
		$normalizedDriver = FileDriverType::normalize($driver);

		switch ($normalizedDriver) {
			case FileDriverType::LOCAL:
				return LocalFileManagerConfig::createDefault();

			case FileDriverType::S3:
				throw new \Exception("S3 configuration requires a bucket name. Use S3FileManagerConfig::createDevelopment() or S3FileManagerConfig::createProduction()");

			default:
				throw new \Exception("Unsupported file storage driver: {$driver}");
		}
	}

	/**
	 * Get default configuration for a driver (legacy array format)
	 * 
	 * @param string $driver The driver name (use FileDriverType constants)
	 * @return array Default configuration
	 * @deprecated Use getDefaultConfig() instead
	 */
	public static function getDefaultConfigArray(string $driver): array
	{
		$normalizedDriver = FileDriverType::normalize($driver);

		switch ($normalizedDriver) {
			case FileDriverType::LOCAL:
				return [
					'driver' => FileDriverType::LOCAL,
					'base_directory' => PROJECT_ROOT . '/public/uploads',
					'base_url' => '/uploads',
				];

			case FileDriverType::S3:
				return [
					'driver' => FileDriverType::S3,
					'bucket' => '',
					'region' => 'us-east-1',
					'access_key_id' => null,
					'secret_access_key' => null,
					'base_url' => null,
				];

			default:
				throw new \Exception("Unsupported file storage driver: {$driver}");
		}
	}

	/**
	 * Validate configuration for a driver
	 * 
	 * @param FileManagerConfigInterface $config Configuration to validate
	 * @return bool True if valid
	 * @throws \Exception If configuration is invalid
	 */
	public static function validateConfig(FileManagerConfigInterface $config): bool
	{
		$config->validate();
		return true;
	}

	/**
	 * Validate configuration for a driver (legacy array format)
	 * 
	 * @param array $config Configuration to validate
	 * @return bool True if valid
	 * @throws \Exception If configuration is invalid
	 * @deprecated Use validateConfig() with configuration objects instead
	 */
	public static function validateConfigArray(array $config): bool
	{
		$driver = $config['driver'] ?? '';

		if (empty($driver)) {
			throw new \Exception("File storage driver not specified");
		}

		$normalizedDriver = FileDriverType::normalize($driver);

		switch ($normalizedDriver) {
			case FileDriverType::LOCAL:
				if (empty($config['base_directory'])) {
					throw new \Exception("Local file storage requires 'base_directory' configuration");
				}
				break;

			case FileDriverType::S3:
				if (empty($config['bucket'])) {
					throw new \Exception("S3 file storage requires 'bucket' configuration");
				}
				if (empty($config['region'])) {
					throw new \Exception("S3 file storage requires 'region' configuration");
				}
				break;

			default:
				throw new \Exception("Unsupported file storage driver: {$driver}");
		}

		return true;
	}
}
