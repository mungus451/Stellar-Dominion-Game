<?php

namespace StellarDominion\Services\FileManager;

/**
 * Local File Manager
 * 
 * Implements file operations for local filesystem storage.
 * Handles file uploads, deletions, and management on the local server.
 */
class LocalFileManager implements FileManagerInterface
{
	private string $baseDirectory;
	private string $baseUrl;

	/**
	 * Constructor
	 * 
	 * @param string $baseDirectory The base directory for file storage (absolute path)
	 * @param string $baseUrl The base URL for accessing files via web
	 */
	public function __construct(string $baseDirectory, string $baseUrl = '')
	{
		$this->baseDirectory = rtrim($baseDirectory, '/');
		$this->baseUrl = rtrim($baseUrl, '/');
		
		// Ensure base directory exists
		if (!is_dir($this->baseDirectory)) {
			if (!mkdir($this->baseDirectory, 0755, true)) {
				throw new \Exception("Failed to create base directory: {$this->baseDirectory}");
			}
		}

		// Check if directory is writable
		if (!is_writable($this->baseDirectory)) {
			throw new \Exception("Base directory is not writable: {$this->baseDirectory}");
		}
	}

	/**
	 * Upload a file to the local filesystem
	 * 
	 * @param string $sourceFile The source file path (usually tmp_name from $_FILES)
	 * @param string $destinationPath The destination path relative to base directory
	 * @param array $options Additional options (not used in local implementation)
	 * @return bool True if upload successful, false otherwise
	 * @throws \Exception If upload fails with specific error details
	 */
	public function upload(string $sourceFile, string $destinationPath, array $options = []): bool
	{
		$fullDestination = $this->getFullPath($destinationPath);
		
		// Create directory if it doesn't exist
		$directory = dirname($fullDestination);
		if (!is_dir($directory)) {
			if (!mkdir($directory, 0755, true)) {
				throw new \Exception("Failed to create directory: {$directory}");
			}
		}

		// Check if source file exists and is readable
		if (!is_file($sourceFile) || !is_readable($sourceFile)) {
			throw new \Exception("Source file does not exist or is not readable: {$sourceFile}");
		}

		// Move the uploaded file
		if (is_uploaded_file($sourceFile)) {
			// For actual uploaded files
			if (move_uploaded_file($sourceFile, $fullDestination)) {
				// Set appropriate permissions
				chmod($fullDestination, 0644);
				return true;
			}
		} else {
			// For testing or other file moves (not uploaded files)
			if (copy($sourceFile, $fullDestination)) {
				// Set appropriate permissions
				chmod($fullDestination, 0644);
				return true;
			}
		}

		throw new \Exception("Failed to move file from {$sourceFile} to {$fullDestination}");
	}

	/**
	 * Delete a file from the local filesystem
	 * 
	 * @param string $filePath The path of the file to delete (relative to base directory)
	 * @return bool True if deletion successful, false otherwise
	 * @throws \Exception If deletion fails with specific error details
	 */
	public function delete(string $filePath): bool
	{
		$fullPath = $this->getFullPath($filePath);
		
		if (!file_exists($fullPath)) {
			return true; // File doesn't exist, consider it deleted
		}

		if (!is_file($fullPath)) {
			throw new \Exception("Path is not a file: {$fullPath}");
		}

		if (!unlink($fullPath)) {
			throw new \Exception("Failed to delete file: {$fullPath}");
		}

		return true;
	}

	/**
	 * Get the public URL for a file
	 * 
	 * @param string $filePath The path of the file (relative to base directory)
	 * @return string The public URL to access the file
	 */
	public function getUrl(string $filePath): string
	{
		// Normalize the file path (remove leading slash)
		$normalizedPath = ltrim($filePath, '/');
		return $this->baseUrl . '/' . $normalizedPath;
	}

	/**
	 * Check if a file exists in the local filesystem
	 * 
	 * @param string $filePath The path of the file to check (relative to base directory)
	 * @return bool True if file exists, false otherwise
	 */
	public function exists(string $filePath): bool
	{
		$fullPath = $this->getFullPath($filePath);
		return file_exists($fullPath) && is_file($fullPath);
	}

	/**
	 * Get file information (size, last modified, etc.)
	 * 
	 * @param string $filePath The path of the file (relative to base directory)
	 * @return array|null File information array or null if file doesn't exist
	 */
	public function getFileInfo(string $filePath): ?array
	{
		$fullPath = $this->getFullPath($filePath);
		
		if (!$this->exists($filePath)) {
			return null;
		}

		return [
			'size' => filesize($fullPath),
			'modified' => filemtime($fullPath),
			'type' => mime_content_type($fullPath),
			'path' => $filePath,
			'full_path' => $fullPath
		];
	}

	/**
	 * Move a file from one location to another within the local filesystem
	 * 
	 * @param string $sourcePath The current file path (relative to base directory)
	 * @param string $destinationPath The new file path (relative to base directory)
	 * @return bool True if move successful, false otherwise
	 * @throws \Exception If move operation fails
	 */
	public function move(string $sourcePath, string $destinationPath): bool
	{
		$fullSourcePath = $this->getFullPath($sourcePath);
		$fullDestinationPath = $this->getFullPath($destinationPath);

		if (!$this->exists($sourcePath)) {
			throw new \Exception("Source file does not exist: {$sourcePath}");
		}

		// Create destination directory if it doesn't exist
		$directory = dirname($fullDestinationPath);
		if (!is_dir($directory)) {
			if (!mkdir($directory, 0755, true)) {
				throw new \Exception("Failed to create destination directory: {$directory}");
			}
		}

		if (!rename($fullSourcePath, $fullDestinationPath)) {
			throw new \Exception("Failed to move file from {$sourcePath} to {$destinationPath}");
		}

		return true;
	}

	/**
	 * Copy a file to another location within the local filesystem
	 * 
	 * @param string $sourcePath The source file path (relative to base directory)
	 * @param string $destinationPath The destination file path (relative to base directory)
	 * @return bool True if copy successful, false otherwise
	 * @throws \Exception If copy operation fails
	 */
	public function copy(string $sourcePath, string $destinationPath): bool
	{
		$fullSourcePath = $this->getFullPath($sourcePath);
		$fullDestinationPath = $this->getFullPath($destinationPath);

		if (!$this->exists($sourcePath)) {
			throw new \Exception("Source file does not exist: {$sourcePath}");
		}

		// Create destination directory if it doesn't exist
		$directory = dirname($fullDestinationPath);
		if (!is_dir($directory)) {
			if (!mkdir($directory, 0755, true)) {
				throw new \Exception("Failed to create destination directory: {$directory}");
			}
		}

		if (!copy($fullSourcePath, $fullDestinationPath)) {
			throw new \Exception("Failed to copy file from {$sourcePath} to {$destinationPath}");
		}

		// Set appropriate permissions
		chmod($fullDestinationPath, 0644);

		return true;
	}

	/**
	 * Get the full filesystem path for a relative path
	 * 
	 * @param string $relativePath The relative path
	 * @return string The full filesystem path
	 */
	private function getFullPath(string $relativePath): string
	{
		// Remove leading slash and normalize path
		$normalizedPath = ltrim($relativePath, '/');
		return $this->baseDirectory . '/' . $normalizedPath;
	}
}
