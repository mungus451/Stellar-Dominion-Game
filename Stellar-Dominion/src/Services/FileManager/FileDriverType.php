<?php

namespace StellarDominion\Services\FileManager;

/**
 * File Driver Type Constants
 * 
 * Defines the available file storage driver types to prevent typos
 * and ensure consistency across the application.
 */
final class FileDriverType
{
	public const LOCAL = 'local';
	public const S3 = 's3';

	/**
	 * Get all available driver types
	 * 
	 * @return array List of all supported driver types
	 */
	public static function getAll(): array
	{
		return [
			self::LOCAL,
			self::S3,
		];
	}

	/**
	 * Check if a driver type is valid
	 * 
	 * @param string $driverType The driver type to validate
	 * @return bool True if valid, false otherwise
	 */
	public static function isValid(string $driverType): bool
	{
		return in_array(strtolower($driverType), self::getAll(), true);
	}

	/**
	 * Validate a driver type and throw exception if invalid
	 * 
	 * @param string $driverType The driver type to validate
	 * @throws \InvalidArgumentException If driver type is invalid
	 */
	public static function validate(string $driverType): void
	{
		if (!self::isValid($driverType)) {
			throw new \InvalidArgumentException(
				"Invalid driver type '{$driverType}'. Supported types: " . implode(', ', self::getAll())
			);
		}
	}

	/**
	 * Normalize driver type to lowercase
	 * 
	 * @param string $driverType The driver type to normalize
	 * @return string Normalized driver type
	 * @throws \InvalidArgumentException If driver type is invalid
	 */
	public static function normalize(string $driverType): string
	{
		$normalized = strtolower(trim($driverType));
		self::validate($normalized);
		return $normalized;
	}

	/**
	 * Prevent instantiation of this utility class
	 */
	private function __construct()
	{
		// This class should not be instantiated
	}
}
