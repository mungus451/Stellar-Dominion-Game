<?php

namespace StellarDominion\Services\FileManager;

/**
 * Driver Type Value Object
 * 
 * Represents a validated file storage driver type.
 * This ensures type safety at compile time and prevents invalid driver types.
 */
final class DriverType
{
	private string $value;

	private function __construct(string $value)
	{
		$this->value = $value;
	}

	/**
	 * Create LOCAL driver type
	 */
	public static function local(): self
	{
		return new self(FileDriverType::LOCAL);
	}

	/**
	 * Create S3 driver type
	 */
	public static function s3(): self
	{
		return new self(FileDriverType::S3);
	}

	/**
	 * Create from string value with validation
	 * 
	 * @param string $value The driver type string
	 * @return self
	 * @throws \InvalidArgumentException If driver type is invalid
	 */
	public static function fromString(string $value): self
	{
		FileDriverType::validate($value);
		return new self(FileDriverType::normalize($value));
	}

	/**
	 * Get the string value
	 */
	public function getValue(): string
	{
		return $this->value;
	}

	/**
	 * Check if this is a local driver
	 */
	public function isLocal(): bool
	{
		return $this->value === FileDriverType::LOCAL;
	}

	/**
	 * Check if this is an S3 driver
	 */
	public function isS3(): bool
	{
		return $this->value === FileDriverType::S3;
	}

	/**
	 * Convert to string
	 */
	public function __toString(): string
	{
		return $this->value;
	}

	/**
	 * Check equality
	 */
	public function equals(self $other): bool
	{
		return $this->value === $other->value;
	}
}
