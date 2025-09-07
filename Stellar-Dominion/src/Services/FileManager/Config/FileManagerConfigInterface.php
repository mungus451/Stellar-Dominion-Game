<?php

namespace StellarDominion\Services\FileManager\Config;

use StellarDominion\Services\FileManager\DriverType;

/**
 * Base Configuration Interface
 * 
 * Defines the contract for file manager configuration objects.
 */
interface FileManagerConfigInterface
{
	/**
	 * Get the driver type
	 * 
	 * @return DriverType The validated driver type
	 */
	public function getDriverType(): DriverType;

	/**
	 * Validate the configuration
	 * 
	 * @throws \InvalidArgumentException If configuration is invalid
	 */
	public function validate(): void;

	/**
	 * Convert configuration to array format
	 * 
	 * @return array Configuration as array
	 */
	public function toArray(): array;
}
