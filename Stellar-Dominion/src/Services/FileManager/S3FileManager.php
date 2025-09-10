<?php

namespace StellarDominion\Services\FileManager;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

/**
 * S3 File Manager
 * 
 * Implements file operations for Amazon S3 storage with CDN integration.
 * Handles file uploads, deletions, and management on S3 with CloudFront support.
 */
class S3FileManager implements FileManagerInterface
{
	private S3Client $s3Client;
	private string $bucket;
	private string $region;
	private CDNManager $cdnManager;

	/**
	 * Constructor
	 * 
	 * @param string $bucket The S3 bucket name
	 * @param string $region The AWS region
	 * @param string $accessKeyId AWS access key ID (optional if using IAM roles)
	 * @param string $secretAccessKey AWS secret access key (optional if using IAM roles)
	 * @param string $baseUrl Custom base URL for accessing files (CDN domain)
	 */
	public function __construct(
		string $bucket,
		string $region,
		?string $accessKeyId = null,
		?string $secretAccessKey = null,
		?string $baseUrl = null
	) {
		$this->bucket = $bucket;
		$this->region = $region;

		// Build S3 client configuration
		$config = [
			'version' => 'latest',
			'region' => $region,
			// Force use of VPC endpoints when running in Lambda/VPC environment
			'use_path_style_endpoint' => false,
			// Disable dual-stack to ensure VPC endpoint usage
			'use_dual_stack_endpoint' => false,
		];

		// Add credentials if provided (for local development)
		if ($accessKeyId && $secretAccessKey) {
			$config['credentials'] = [
				'key' => $accessKeyId,
				'secret' => $secretAccessKey,
			];
		}

		// For VPC environments, ensure we use the VPC endpoint
		// The AWS SDK will automatically use VPC endpoints when available in the route table
		if (isset($_ENV['AWS_LAMBDA_FUNCTION_NAME'])) {
			// Running in Lambda - VPC endpoint should be used automatically
			$config['signature_version'] = 'v4';
			// Add timeout settings for Lambda environment
			$config['http'] = [
				'timeout' => 20, // 20 second timeout for uploads
				'connect_timeout' => 5, // 5 second connection timeout
			];
		}

		$this->s3Client = new S3Client($config);
		
		// Initialize CDN manager
		$s3BucketUrl = "https://{$bucket}.s3.{$region}.amazonaws.com";
		$this->cdnManager = new CDNManager($s3BucketUrl, $baseUrl);
	}

	/**
	 * Upload a file to S3 with CDN optimization
	 * 
	 * @param string $sourceFile The source file path (usually tmp_name from $_FILES)
	 * @param string $destinationPath The destination path (S3 key)
	 * @param array $options Additional options (ACL, metadata, etc.)
	 * @return bool True if upload successful, false otherwise
	 * @throws \Exception If upload fails with specific error details
	 */
	public function upload(string $sourceFile, string $destinationPath, array $options = []): bool
	{
		try {
			// Normalize the destination path (remove leading slash)
			$key = ltrim($destinationPath, '/');

			// Check if source file exists and is readable
			if (!is_file($sourceFile) || !is_readable($sourceFile)) {
				throw new \Exception("Source file does not exist or is not readable: {$sourceFile}");
			}

			// Get CDN-optimized upload options
			$cdnOptions = $this->cdnManager->getOptimizedUploadOptions($destinationPath, $options);

			// Default upload parameters
			$uploadParams = [
				'Bucket' => $this->bucket,
				'Key' => $key,
				'SourceFile' => $sourceFile,
				'ACL' => $cdnOptions['acl'] ?? 'public-read', // Default to public read
			];

			// Add content type if provided
			if (isset($cdnOptions['content_type'])) {
				$uploadParams['ContentType'] = $cdnOptions['content_type'];
			} else {
				// Try to detect content type
				$finfo = new \finfo(FILEINFO_MIME_TYPE);
				$uploadParams['ContentType'] = $finfo->file($sourceFile);
			}

			// Add CDN-optimized cache control
			if (isset($cdnOptions['cache_control'])) {
				$uploadParams['CacheControl'] = $cdnOptions['cache_control'];
			}

			// Add metadata if provided
			if (isset($cdnOptions['metadata']) && is_array($cdnOptions['metadata'])) {
				$uploadParams['Metadata'] = $cdnOptions['metadata'];
			}

			// Upload the file
			$result = $this->s3Client->putObject($uploadParams);

			// Check if upload was successful
			return !empty($result['ObjectURL']);

		} catch (AwsException $e) {
			// Enhanced error logging for VPC endpoint debugging
			$errorCode = $e->getAwsErrorCode();
			$errorMessage = $e->getMessage();
			$errorType = $e->getAwsErrorType();
			
			// Log detailed error information for troubleshooting
			error_log("S3 Upload Error - Code: {$errorCode}, Type: {$errorType}, Message: {$errorMessage}");
			
			// Check for common VPC endpoint issues
			if (strpos($errorMessage, 'timeout') !== false || 
				strpos($errorMessage, 'connection') !== false ||
				$errorCode === 'RequestTimeout') {
				throw new \Exception("S3 upload timeout - check VPC endpoint configuration. Error: " . $errorMessage);
			}
			
			throw new \Exception("S3 upload failed: " . $errorMessage);
		} catch (\Exception $e) {
			error_log("S3 Upload Exception: " . $e->getMessage());
			throw new \Exception("Upload error: " . $e->getMessage());
		}
	}

	/**
	 * Delete a file from S3
	 * 
	 * @param string $filePath The path of the file to delete (S3 key)
	 * @return bool True if deletion successful, false otherwise
	 * @throws \Exception If deletion fails with specific error details
	 */
	public function delete(string $filePath): bool
	{
		try {
			// Normalize the file path (remove leading slash)
			$key = ltrim($filePath, '/');

			$this->s3Client->deleteObject([
				'Bucket' => $this->bucket,
				'Key' => $key,
			]);

			return true;

		} catch (AwsException $e) {
			throw new \Exception("S3 delete failed: " . $e->getMessage());
		}
	}

	/**
	 * Get the public URL for a file (CDN-aware)
	 * 
	 * @param string $filePath The path of the file (S3 key)
	 * @return string The public URL to access the file (CDN URL in production, S3 URL in development)
	 */
	public function getUrl(string $filePath): string
	{
		return $this->cdnManager->getUrl($filePath);
	}

	/**
	 * Check if a file exists in S3
	 * 
	 * @param string $filePath The path of the file to check (S3 key)
	 * @return bool True if file exists, false otherwise
	 */
	public function exists(string $filePath): bool
	{
		try {
			// Normalize the file path (remove leading slash)
			$key = ltrim($filePath, '/');

			$this->s3Client->headObject([
				'Bucket' => $this->bucket,
				'Key' => $key,
			]);

			return true;

		} catch (AwsException $e) {
			// If the error is 404, the file doesn't exist
			if ($e->getStatusCode() === 404) {
				return false;
			}
			
			// For other errors, re-throw
			throw new \Exception("S3 exists check failed: " . $e->getMessage());
		}
	}

	/**
	 * Get file information (size, last modified, etc.)
	 * 
	 * @param string $filePath The path of the file (S3 key)
	 * @return array|null File information array or null if file doesn't exist
	 */
	public function getFileInfo(string $filePath): ?array
	{
		try {
			// Normalize the file path (remove leading slash)
			$key = ltrim($filePath, '/');

			$result = $this->s3Client->headObject([
				'Bucket' => $this->bucket,
				'Key' => $key,
			]);

			return [
				'size' => $result['ContentLength'],
				'modified' => $result['LastModified']->getTimestamp(),
				'type' => $result['ContentType'],
				'etag' => trim($result['ETag'], '"'),
				'path' => $filePath,
				'metadata' => $result['Metadata'] ?? [],
			];

		} catch (AwsException $e) {
			// If the error is 404, the file doesn't exist
			if ($e->getStatusCode() === 404) {
				return null;
			}
			
			// For other errors, re-throw
			throw new \Exception("S3 file info failed: " . $e->getMessage());
		}
	}

	/**
	 * Move a file from one location to another within S3
	 * 
	 * @param string $sourcePath The current file path (S3 key)
	 * @param string $destinationPath The new file path (S3 key)
	 * @return bool True if move successful, false otherwise
	 * @throws \Exception If move operation fails
	 */
	public function move(string $sourcePath, string $destinationPath): bool
	{
		// S3 doesn't have a native "move" operation, so we copy then delete
		if ($this->copy($sourcePath, $destinationPath)) {
			return $this->delete($sourcePath);
		}

		return false;
	}

	/**
	 * Copy a file to another location within S3
	 * 
	 * @param string $sourcePath The source file path (S3 key)
	 * @param string $destinationPath The destination file path (S3 key)
	 * @return bool True if copy successful, false otherwise
	 * @throws \Exception If copy operation fails
	 */
	public function copy(string $sourcePath, string $destinationPath): bool
	{
		try {
			// Normalize the file paths (remove leading slashes)
			$sourceKey = ltrim($sourcePath, '/');
			$destinationKey = ltrim($destinationPath, '/');

			// Check if source file exists
			if (!$this->exists($sourcePath)) {
				throw new \Exception("Source file does not exist: {$sourcePath}");
			}

			$this->s3Client->copyObject([
				'Bucket' => $this->bucket,
				'Key' => $destinationKey,
				'CopySource' => $this->bucket . '/' . $sourceKey,
				'ACL' => 'public-read', // Maintain public read access
			]);

			return true;

		} catch (AwsException $e) {
			throw new \Exception("S3 copy failed: " . $e->getMessage());
		}
	}

	/**
	 * Generate a presigned URL for temporary access to a private file
	 * 
	 * @param string $filePath The path of the file (S3 key)
	 * @param int $expirationMinutes Expiration time in minutes (default: 60)
	 * @return string The presigned URL
	 */
	public function getPresignedUrl(string $filePath, int $expirationMinutes = 60): string
	{
		// Normalize the file path (remove leading slash)
		$key = ltrim($filePath, '/');

		$command = $this->s3Client->getCommand('GetObject', [
			'Bucket' => $this->bucket,
			'Key' => $key,
		]);

		$presignedUrl = $this->s3Client->createPresignedRequest(
			$command,
			'+' . $expirationMinutes . ' minutes'
		);

		return (string) $presignedUrl->getUri();
	}

	/**
	 * Get CDN manager instance
	 * 
	 * @return CDNManager
	 */
	public function getCdnManager(): CDNManager
	{
		return $this->cdnManager;
	}

	/**
	 * Check if CDN is enabled
	 * 
	 * @return bool
	 */
	public function isCdnEnabled(): bool
	{
		return $this->cdnManager->isCdnEnabled();
	}

	/**
	 * Get cost optimization information
	 * 
	 * @return array
	 */
	public function getCostOptimizationInfo(): array
	{
		return $this->cdnManager->getCostOptimizationInfo();
	}

	/**
	 * Get direct S3 URL (bypass CDN)
	 * Used for administrative purposes or when CDN bypass is needed
	 * 
	 * @param string $filePath The path of the file (S3 key)
	 * @return string Direct S3 URL
	 */
	public function getDirectS3Url(string $filePath): string
	{
		$key = ltrim($filePath, '/');
		return "https://{$this->bucket}.s3.{$this->region}.amazonaws.com/{$key}";
	}
}
