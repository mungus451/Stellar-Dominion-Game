<?php

namespace StellarDominion\Services\FileManager;

/**
 * CDN Manager
 * 
 * Handles CDN URL generation and cache optimization for different environments.
 * Provides cost-effective file delivery with CloudFront integration.
 */
class CDNManager
{
	private bool $isProduction;
	private ?string $cdnDomain;
	private string $s3BucketUrl;
	private array $cacheSettings;

	/**
	 * Constructor
	 * 
	 * @param string $s3BucketUrl Direct S3 bucket URL
	 * @param string|null $cdnDomain CloudFront distribution domain
	 * @param bool|null $isProduction Override environment detection
	 */
	public function __construct(string $s3BucketUrl, ?string $cdnDomain = null, ?bool $isProduction = null)
	{
		$this->s3BucketUrl = rtrim($s3BucketUrl, '/');
		$this->cdnDomain = $cdnDomain ? rtrim($cdnDomain, '/') : null;
		$this->isProduction = $isProduction ?? $this->detectProductionEnvironment();
		
		$this->cacheSettings = [
			'images' => [
				'max_age' => 31536000, // 1 year
				'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg']
			],
			'documents' => [
				'max_age' => 86400, // 1 day
				'extensions' => ['pdf', 'doc', 'docx', 'txt']
			],
			'default' => [
				'max_age' => 3600, // 1 hour
				'extensions' => []
			]
		];
	}

	/**
	 * Get optimized URL for a file
	 * 
	 * @param string $filePath File path relative to bucket
	 * @return string Optimized URL (CDN in production, S3 in development)
	 */
	public function getUrl(string $filePath): string
	{
		$normalizedPath = ltrim($filePath, '/');
		
		// Use CDN in production if available
		if ($this->isProduction && $this->cdnDomain) {
			return $this->cdnDomain . '/' . $normalizedPath;
		}
		
		// Fallback to S3 direct URL
		return $this->s3BucketUrl . '/' . $normalizedPath;
	}

	/**
	 * Get appropriate cache control header for a file
	 * 
	 * @param string $filePath File path to analyze
	 * @return string Cache-Control header value
	 */
	public function getCacheControl(string $filePath): string
	{
		$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
		
		foreach ($this->cacheSettings as $type => $settings) {
			if (in_array($extension, $settings['extensions'])) {
				$maxAge = $settings['max_age'];
				
				// Different strategies for different environments
				if ($this->isProduction) {
					return "public, max-age={$maxAge}, s-maxage=" . ($maxAge * 2);
				} else {
					// Shorter cache in development
					return "public, max-age=300"; // 5 minutes
				}
			}
		}
		
		// Default cache settings
		$defaultMaxAge = $this->cacheSettings['default']['max_age'];
		if ($this->isProduction) {
			return "public, max-age={$defaultMaxAge}";
		} else {
			return "public, max-age=300";
		}
	}

	/**
	 * Check if CDN is available and should be used
	 * 
	 * @return bool True if CDN should be used
	 */
	public function isCdnEnabled(): bool
	{
		return $this->isProduction && $this->cdnDomain !== null;
	}

	/**
	 * Get upload options optimized for CDN
	 * 
	 * @param string $filePath File path to upload
	 * @param array $baseOptions Base upload options
	 * @return array Enhanced upload options with cache headers
	 */
	public function getOptimizedUploadOptions(string $filePath, array $baseOptions = []): array
	{
		$options = array_merge($baseOptions, [
			'cache_control' => $this->getCacheControl($filePath),
		]);

		// Add CloudFront-specific headers for production
		if ($this->isProduction) {
			$options['metadata'] = array_merge(
				$options['metadata'] ?? [],
				[
					'cdn_enabled' => 'true',
					'cache_strategy' => $this->getCacheStrategy($filePath),
					'environment' => 'production'
				]
			);
		} else {
			$options['metadata'] = array_merge(
				$options['metadata'] ?? [],
				['environment' => 'development']
			);
		}

		return $options;
	}

	/**
	 * Get cache invalidation paths for CloudFront
	 * 
	 * @param array $filePaths Array of file paths to invalidate
	 * @return array CloudFront invalidation paths
	 */
	public function getInvalidationPaths(array $filePaths): array
	{
		if (!$this->isCdnEnabled()) {
			return []; // No invalidation needed without CDN
		}

		$paths = [];
		foreach ($filePaths as $filePath) {
			$normalizedPath = '/' . ltrim($filePath, '/');
			$paths[] = $normalizedPath;
		}

		return array_unique($paths);
	}

	/**
	 * Get cost optimization recommendations
	 * 
	 * @return array Cost optimization tips
	 */
	public function getCostOptimizationInfo(): array
	{
		$info = [
			'cdn_enabled' => $this->isCdnEnabled(),
			'environment' => $this->isProduction ? 'production' : 'development',
			'recommendations' => []
		];

		if ($this->isCdnEnabled()) {
			$info['recommendations'][] = 'CDN is active - requests are cached at edge locations';
			$info['recommendations'][] = 'Images cached for 1 year - minimal S3 requests for repeat access';
			$info['recommendations'][] = 'Use CloudFront Origin Access Control to block direct S3 access';
		} else {
			$info['recommendations'][] = 'Configure CloudFront CDN for production to reduce S3 costs';
			$info['recommendations'][] = 'Direct S3 access - consider adding CDN for cost optimization';
		}

		$info['recommendations'][] = 'Monitor CloudWatch metrics for cache hit ratio';
		$info['recommendations'][] = 'Consider S3 Intelligent Tiering for infrequently accessed files';

		return $info;
	}

	/**
	 * Create CDN manager from environment
	 * 
	 * @param string $bucket S3 bucket name
	 * @param string $region AWS region
	 * @return self
	 */
	public static function createFromEnvironment(string $bucket, string $region): self
	{
		$s3BucketUrl = "https://{$bucket}.s3.{$region}.amazonaws.com";
		$cdnDomain = $_ENV['CLOUDFRONT_DOMAIN'] ?? $_ENV['FILE_STORAGE_CDN_URL'] ?? null;
		
		return new self($s3BucketUrl, $cdnDomain);
	}

	/**
	 * Detect if running in production environment
	 * 
	 * @return bool True if production environment
	 */
	private function detectProductionEnvironment(): bool
	{
		// Check for Lambda environment
		if (isset($_ENV['AWS_LAMBDA_FUNCTION_NAME'])) {
			return true;
		}

		// Check for explicit environment setting
		$appEnv = $_ENV['APP_ENV'] ?? $_ENV['ENVIRONMENT'] ?? 'development';
		return in_array(strtolower($appEnv), ['production', 'prod', 'live']);
	}

	/**
	 * Get cache strategy name for a file
	 * 
	 * @param string $filePath File path
	 * @return string Cache strategy name
	 */
	private function getCacheStrategy(string $filePath): string
	{
		$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
		
		foreach ($this->cacheSettings as $type => $settings) {
			if (in_array($extension, $settings['extensions'])) {
				return $type;
			}
		}
		
		return 'default';
	}

	/**
	 * Get current configuration summary
	 * 
	 * @return array Configuration summary
	 */
	public function getConfigSummary(): array
	{
		return [
			'is_production' => $this->isProduction,
			'cdn_domain' => $this->cdnDomain,
			's3_bucket_url' => $this->s3BucketUrl,
			'cdn_enabled' => $this->isCdnEnabled(),
			'cache_settings' => $this->cacheSettings
		];
	}
}
