<?php

namespace StellarDominion\Services\FileManager;

/**
 * File Validator
 * 
 * Centralizes file validation logic including type, size, and security checks.
 * Used by controllers before file upload operations.
 */
class FileValidator
{
	// Default configuration
	private array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'avif'];
	private array $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/avif'];
	private int $maxFileSize = 10485760; // 10MB in bytes
	private int $minFileSize = 1024; // 1KB minimum

	/**
	 * Constructor
	 * 
	 * @param array $options Validation options
	 */
	public function __construct(array $options = [])
	{
		if (isset($options['allowed_extensions'])) {
			$this->allowedExtensions = $options['allowed_extensions'];
		}

		if (isset($options['allowed_mime_types'])) {
			$this->allowedMimeTypes = $options['allowed_mime_types'];
		}

		if (isset($options['max_file_size'])) {
			$this->maxFileSize = $options['max_file_size'];
		}

		if (isset($options['min_file_size'])) {
			$this->minFileSize = $options['min_file_size'];
		}
	}

	/**
	 * Validate uploaded file from $_FILES array
	 * 
	 * @param array $fileData File data from $_FILES
	 * @return array Validation result with 'valid' boolean and 'error' message
	 */
	public function validateUploadedFile(array $fileData): array
	{
		// Check for upload errors
		if (isset($fileData['error']) && $fileData['error'] !== UPLOAD_ERR_OK) {
			return [
				'valid' => false,
				'error' => $this->getUploadErrorMessage($fileData['error'])
			];
		}

		// Check if file was actually uploaded
		if (empty($fileData['tmp_name']) || !is_uploaded_file($fileData['tmp_name'])) {
			return [
				'valid' => false,
				'error' => 'No file was uploaded or file is not valid.'
			];
		}

		// Validate file size
		$sizeValidation = $this->validateFileSize($fileData['size']);
		if (!$sizeValidation['valid']) {
			return $sizeValidation;
		}

		// Validate file extension
		$extensionValidation = $this->validateFileExtension($fileData['name']);
		if (!$extensionValidation['valid']) {
			return $extensionValidation;
		}

		// Validate MIME type
		$mimeValidation = $this->validateMimeType($fileData['tmp_name']);
		if (!$mimeValidation['valid']) {
			return $mimeValidation;
		}

		// Additional security checks
		$securityValidation = $this->performSecurityChecks($fileData['tmp_name'], $fileData['name']);
		if (!$securityValidation['valid']) {
			return $securityValidation;
		}

		return ['valid' => true, 'error' => null];
	}

	/**
	 * Validate file size
	 * 
	 * @param int $fileSize File size in bytes
	 * @return array Validation result
	 */
	public function validateFileSize(int $fileSize): array
	{
		if ($fileSize < $this->minFileSize) {
			return [
				'valid' => false,
				'error' => "File is too small. Minimum size is " . $this->formatFileSize($this->minFileSize) . "."
			];
		}

		if ($fileSize > $this->maxFileSize) {
			return [
				'valid' => false,
				'error' => "File is too large. Maximum size is " . $this->formatFileSize($this->maxFileSize) . "."
			];
		}

		return ['valid' => true, 'error' => null];
	}

	/**
	 * Validate file extension
	 * 
	 * @param string $filename Original filename
	 * @return array Validation result
	 */
	public function validateFileExtension(string $filename): array
	{
		$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

		if (!in_array($extension, $this->allowedExtensions)) {
			return [
				'valid' => false,
				'error' => "Invalid file type. Only " . implode(', ', array_map('strtoupper', $this->allowedExtensions)) . " files are allowed."
			];
		}

		return ['valid' => true, 'error' => null];
	}

	/**
	 * Validate MIME type
	 * 
	 * @param string $filePath Path to the uploaded file
	 * @return array Validation result
	 */
	public function validateMimeType(string $filePath): array
	{
		if (!file_exists($filePath)) {
			return [
				'valid' => false,
				'error' => 'File does not exist for MIME type validation.'
			];
		}

		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		$mimeType = $finfo->file($filePath);

		if (!in_array($mimeType, $this->allowedMimeTypes)) {
			return [
				'valid' => false,
				'error' => "Invalid file format. Only " . implode(', ', $this->allowedMimeTypes) . " are allowed."
			];
		}

		return ['valid' => true, 'error' => null];
	}

	/**
	 * Perform additional security checks
	 * 
	 * @param string $filePath Path to the uploaded file
	 * @param string $originalName Original filename
	 * @return array Validation result
	 */
	public function performSecurityChecks(string $filePath, string $originalName): array
	{
		// Check for suspicious file names
		if ($this->hasSuspiciousFilename($originalName)) {
			return [
				'valid' => false,
				'error' => 'Filename contains suspicious characters.'
			];
		}

		// Check for executable content in image files
		if ($this->hasExecutableContent($filePath)) {
			return [
				'valid' => false,
				'error' => 'File contains potentially malicious content.'
			];
		}

		return ['valid' => true, 'error' => null];
	}

	/**
	 * Generate a safe filename for storage
	 * 
	 * @param string $originalName Original filename
	 * @param string $prefix Optional prefix
	 * @param int|null $userId Optional user ID for unique naming
	 * @return string Safe filename
	 */
	public function generateSafeFilename(string $originalName, string $prefix = '', ?int $userId = null): string
	{
		$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
		$timestamp = time();
		$randomString = bin2hex(random_bytes(8));

		$parts = [];
		if (!empty($prefix)) {
			$parts[] = $prefix;
		}
		if ($userId !== null) {
			$parts[] = $userId;
		}
		$parts[] = $timestamp;
		$parts[] = $randomString;

		return implode('_', $parts) . '.' . $extension;
	}

	/**
	 * Get upload error message from error code
	 * 
	 * @param int $errorCode PHP upload error code
	 * @return string Error message
	 */
	private function getUploadErrorMessage(int $errorCode): string
	{
		switch ($errorCode) {
			case UPLOAD_ERR_INI_SIZE:
				return "File exceeds the maximum allowed size configured on the server.";
			case UPLOAD_ERR_FORM_SIZE:
				return "File exceeds the maximum size specified in the form.";
			case UPLOAD_ERR_PARTIAL:
				return "File was only partially uploaded. Please try again.";
			case UPLOAD_ERR_NO_FILE:
				return "No file was selected for upload.";
			case UPLOAD_ERR_NO_TMP_DIR:
				return "Server error: Missing temporary upload directory.";
			case UPLOAD_ERR_CANT_WRITE:
				return "Server error: Cannot write file to disk.";
			case UPLOAD_ERR_EXTENSION:
				return "Upload stopped by a server extension.";
			default:
				return "An unknown upload error occurred.";
		}
	}

	/**
	 * Check if filename contains suspicious characters
	 * 
	 * @param string $filename Filename to check
	 * @return bool True if suspicious
	 */
	private function hasSuspiciousFilename(string $filename): bool
	{
		// Check for null bytes
		if (strpos($filename, "\0") !== false) {
			return true;
		}

		// Check for directory traversal attempts
		if (strpos($filename, '..') !== false) {
			return true;
		}

		// Check for executable extensions (double extension attack)
		$suspiciousExtensions = ['php', 'asp', 'aspx', 'jsp', 'js', 'exe', 'bat', 'cmd', 'com', 'scr'];
		foreach ($suspiciousExtensions as $ext) {
			if (strpos(strtolower($filename), '.' . $ext) !== false) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check for executable content in files
	 * 
	 * @param string $filePath Path to the file
	 * @return bool True if executable content found
	 */
	private function hasExecutableContent(string $filePath): bool
	{
		// Read first 1KB of file to check for suspicious content
		$handle = fopen($filePath, 'rb');
		if (!$handle) {
			return true; // Fail safe
		}

		$content = fread($handle, 1024);
		fclose($handle);

		// Check for common script tags and PHP opening tags
		$suspiciousPatterns = [
			'<?php',
			'<%',
			'<script',
			'javascript:',
			'vbscript:',
			'onload=',
			'onerror=',
		];

		foreach ($suspiciousPatterns as $pattern) {
			if (stripos($content, $pattern) !== false) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Format file size for human readability
	 * 
	 * @param int $bytes File size in bytes
	 * @return string Formatted file size
	 */
	private function formatFileSize(int $bytes): string
	{
		$units = ['B', 'KB', 'MB', 'GB'];
		$unitIndex = 0;

		while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
			$bytes /= 1024;
			$unitIndex++;
		}

		return round($bytes, 2) . ' ' . $units[$unitIndex];
	}

	/**
	 * Get current validation configuration
	 * 
	 * @return array Current configuration
	 */
	public function getConfig(): array
	{
		return [
			'allowed_extensions' => $this->allowedExtensions,
			'allowed_mime_types' => $this->allowedMimeTypes,
			'max_file_size' => $this->maxFileSize,
			'min_file_size' => $this->minFileSize,
		];
	}
}
