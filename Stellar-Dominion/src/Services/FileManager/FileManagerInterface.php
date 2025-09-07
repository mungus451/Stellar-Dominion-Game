<?php

namespace StellarDominion\Services\FileManager;

/**
 * File Manager Interface
 * 
 * Defines the contract for file storage operations across different storage drivers.
 * Supports both local filesystem and cloud storage (S3) implementations.
 */
interface FileManagerInterface
{
	/**
	 * Upload a file to the storage system
	 * 
	 * @param string $sourceFile The source file path (usually tmp_name from $_FILES)
	 * @param string $destinationPath The destination path where the file should be stored
	 * @param array $options Additional options for upload (metadata, ACL, etc.)
	 * @return bool True if upload successful, false otherwise
	 * @throws \Exception If upload fails with specific error details
	 */
	public function upload(string $sourceFile, string $destinationPath, array $options = []): bool;

	/**
	 * Delete a file from the storage system
	 * 
	 * @param string $filePath The path of the file to delete
	 * @return bool True if deletion successful, false otherwise
	 * @throws \Exception If deletion fails with specific error details
	 */
	public function delete(string $filePath): bool;

	/**
	 * Get the public URL for a file
	 * 
	 * @param string $filePath The path of the file
	 * @return string The public URL to access the file
	 */
	public function getUrl(string $filePath): string;

	/**
	 * Check if a file exists in the storage system
	 * 
	 * @param string $filePath The path of the file to check
	 * @return bool True if file exists, false otherwise
	 */
	public function exists(string $filePath): bool;

	/**
	 * Get file information (size, last modified, etc.)
	 * 
	 * @param string $filePath The path of the file
	 * @return array|null File information array or null if file doesn't exist
	 */
	public function getFileInfo(string $filePath): ?array;

	/**
	 * Move a file from one location to another within the storage system
	 * 
	 * @param string $sourcePath The current file path
	 * @param string $destinationPath The new file path
	 * @return bool True if move successful, false otherwise
	 * @throws \Exception If move operation fails
	 */
	public function move(string $sourcePath, string $destinationPath): bool;

	/**
	 * Copy a file to another location within the storage system
	 * 
	 * @param string $sourcePath The source file path
	 * @param string $destinationPath The destination file path
	 * @return bool True if copy successful, false otherwise
	 * @throws \Exception If copy operation fails
	 */
	public function copy(string $sourcePath, string $destinationPath): bool;
}
