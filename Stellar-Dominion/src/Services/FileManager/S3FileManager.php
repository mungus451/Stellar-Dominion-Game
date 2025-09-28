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

	/**
	 * Constructor - Uses AWS SDK credential chain (IAM roles, environment variables, etc.)
	 * 
	 * @param string $bucket The S3 bucket name
	 * @param string $region The AWS region
	 * @param string|null $baseUrl Custom base URL for accessing files (optional)
	 */
	public function __construct(
		string $bucket,
		string $region,
		?string $baseUrl = null
	) {
		$this->bucket = $bucket;
		$this->region = $region;

		// Build S3 client configuration using AWS SDK credential chain
		$config = [
			'version' => 'latest',
			'region' => $region,
			// AWS SDK will automatically use credential chain:
			// 1. Environment variables (AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY)
			// 2. IAM instance profile (Lambda execution role)
			// 3. AWS credentials file
			// 4. IAM roles for Amazon EC2
		];

		// For VPC environments, ensure we use the VPC endpoint
		// The AWS SDK will automatically use VPC endpoints when available in the route table
		if (isset($_ENV['AWS_LAMBDA_FUNCTION_NAME'])) {
			// Running in Lambda - VPC endpoint should be used automatically
			$config['signature_version'] = 'v4';
			// Add timeout settings for Lambda environment - keep 5s connect timeout
			$config['http'] = [
				'timeout' => 25, // 25 second timeout for uploads
				'connect_timeout' => 5, // 5 second connection timeout for VPC
			];
			// Force path-style addressing for VPC Gateway endpoint compatibility
			$config['use_path_style_endpoint'] = true;
		}

		$this->s3Client = new S3Client($config);
	}
	
	/**
	 * Upload a file to S3 (bucket policy controls access)
	 * 
	 * @param string $sourceFile The source file path (usually tmp_name from $_FILES)
	 * @param string $destinationPath The destination path (S3 key)
	 * @param array $options Additional options (metadata, content_type, etc.)
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

			// Default upload parameters (let bucket control ACL)
			$uploadParams = [
				'Bucket' => $this->bucket,
				'Key' => $key,
				'SourceFile' => $sourceFile,
				// No ACL parameter - bucket policy controls access
			];

			// Add content type if provided
			if (isset($options['content_type'])) {
				$uploadParams['ContentType'] = $options['content_type'];
			} else {
				// Try to detect content type
				$finfo = new \finfo(FILEINFO_MIME_TYPE);
				$uploadParams['ContentType'] = $finfo->file($sourceFile);
			}

			// Add cache control for images
			$extension = strtolower(pathinfo($destinationPath, PATHINFO_EXTENSION));
			if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'])) {
				$uploadParams['CacheControl'] = 'max-age=31536000'; // 1 year for images
			}

			// Add metadata if provided
			if (isset($options['metadata']) && is_array($options['metadata'])) {
				$uploadParams['Metadata'] = $options['metadata'];
			}

			// Upload the file
			$uploadStartTime = microtime(true);
			$result = $this->s3Client->putObject($uploadParams);
			$uploadEndTime = microtime(true);
			$uploadDuration = round(($uploadEndTime - $uploadStartTime) * 1000, 2);
			
			// Log successful upload timing for VPC endpoint performance monitoring
			error_log("S3 Upload Success - Duration: {$uploadDuration}ms, Key: {$key}, Size: " . filesize($sourceFile) . " bytes");

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
	 * Get the public URL for a file (domain-relative)
	 * 
	 * @param string $filePath The path of the file (S3 key)
	 * @return string The public URL to access the file through your domain
	 */
	public function getUrl(string $filePath): string
	{
		$key = ltrim($filePath, '/');
		// Return domain-relative URL that will be served directly by CloudFront CDN
		// CDN is configured to serve /* directly from S3
		return "/{$key}";
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
				// No ACL parameter - bucket policy controls access
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
}
