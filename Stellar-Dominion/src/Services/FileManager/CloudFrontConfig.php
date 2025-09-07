<?php

namespace StellarDominion\Services\FileManager;

/**
 * CloudFront Configuration Helper
 * 
 * Provides CloudFront distribution configuration for cost optimization
 * and denial of wallet attack prevention.
 */
class CloudFrontConfig
{
	/**
	 * Get recommended CloudFront configuration for Stellar Dominion
	 * 
	 * @param string $s3BucketName S3 bucket name
	 * @param string $domainName Optional custom domain name
	 * @return array CloudFront configuration array
	 */
	public static function getRecommendedConfig(string $s3BucketName, ?string $domainName = null): array
	{
		return [
			'CallerReference' => 'stellar-dominion-' . time(),
			'Comment' => 'Stellar Dominion CDN for cost optimization and DoW prevention',
			'DefaultRootObject' => '',
			'Origins' => [
				'Quantity' => 1,
				'Items' => [
					[
						'Id' => 's3-' . $s3BucketName,
						'DomainName' => $s3BucketName . '.s3.amazonaws.com',
						'OriginPath' => '',
						'CustomOriginConfig' => null,
						'S3OriginConfig' => [
							'OriginAccessIdentity' => '' // Use Origin Access Control instead
						],
						'OriginAccessControlId' => '' // Set this to your OAC ID
					]
				]
			],
			'DefaultCacheBehavior' => self::getDefaultCacheBehavior(),
			'CacheBehaviors' => [
				'Quantity' => 2,
				'Items' => [
					self::getImageCacheBehavior(),
					self::getDocumentCacheBehavior()
				]
			],
			'CustomErrorPages' => [
				'Quantity' => 2,
				'Items' => [
					[
						'ErrorCode' => 403,
						'ResponsePagePath' => '/403.html',
						'ResponseCode' => '403',
						'ErrorCachingMinTTL' => 300
					],
					[
						'ErrorCode' => 404,
						'ResponsePagePath' => '/404.html',
						'ResponseCode' => '404',
						'ErrorCachingMinTTL' => 300
					]
				]
			],
			'Enabled' => true,
			'PriceClass' => 'PriceClass_100', // Use only North America and Europe for cost optimization
			'ViewerCertificate' => self::getViewerCertificate($domainName),
			'WebACLId' => '', // Optional: Add AWS WAF for additional protection
			'HttpVersion' => 'http2and3',
			'IsIPV6Enabled' => true,
			'Staging' => false
		];
	}

	/**
	 * Get default cache behavior
	 * 
	 * @return array Default cache behavior configuration
	 */
	private static function getDefaultCacheBehavior(): array
	{
		return [
			'TargetOriginId' => 's3-origin',
			'ViewerProtocolPolicy' => 'redirect-to-https',
			'TrustedSigners' => [
				'Enabled' => false,
				'Quantity' => 0,
				'Items' => []
			],
			'TrustedKeyGroups' => [
				'Enabled' => false,
				'Quantity' => 0,
				'Items' => []
			],
			'ForwardedValues' => [
				'QueryString' => false,
				'Cookies' => [
					'Forward' => 'none'
				],
				'Headers' => [
					'Quantity' => 0,
					'Items' => []
				]
			],
			'MinTTL' => 0,
			'DefaultTTL' => 3600, // 1 hour
			'MaxTTL' => 31536000, // 1 year
			'SmoothStreaming' => false,
			'Compress' => true,
			'AllowedMethods' => [
				'Quantity' => 2,
				'Items' => ['GET', 'HEAD'],
				'CachedMethods' => [
					'Quantity' => 2,
					'Items' => ['GET', 'HEAD']
				]
			]
		];
	}

	/**
	 * Get cache behavior for images (long cache)
	 * 
	 * @return array Image cache behavior
	 */
	private static function getImageCacheBehavior(): array
	{
		return [
			'PathPattern' => '*.{jpg,jpeg,png,gif,webp,avif,svg}',
			'TargetOriginId' => 's3-origin',
			'ViewerProtocolPolicy' => 'redirect-to-https',
			'MinTTL' => 86400, // 1 day minimum
			'DefaultTTL' => 31536000, // 1 year default
			'MaxTTL' => 31536000, // 1 year maximum
			'Compress' => true,
			'ForwardedValues' => [
				'QueryString' => false,
				'Cookies' => ['Forward' => 'none']
			],
			'AllowedMethods' => [
				'Quantity' => 2,
				'Items' => ['GET', 'HEAD'],
				'CachedMethods' => [
					'Quantity' => 2,
					'Items' => ['GET', 'HEAD']
				]
			]
		];
	}

	/**
	 * Get cache behavior for documents (medium cache)
	 * 
	 * @return array Document cache behavior
	 */
	private static function getDocumentCacheBehavior(): array
	{
		return [
			'PathPattern' => '*.{pdf,doc,docx,txt}',
			'TargetOriginId' => 's3-origin',
			'ViewerProtocolPolicy' => 'redirect-to-https',
			'MinTTL' => 3600, // 1 hour minimum
			'DefaultTTL' => 86400, // 1 day default
			'MaxTTL' => 604800, // 1 week maximum
			'Compress' => true,
			'ForwardedValues' => [
				'QueryString' => false,
				'Cookies' => ['Forward' => 'none']
			],
			'AllowedMethods' => [
				'Quantity' => 2,
				'Items' => ['GET', 'HEAD'],
				'CachedMethods' => [
					'Quantity' => 2,
					'Items' => ['GET', 'HEAD']
				]
			]
		];
	}

	/**
	 * Get viewer certificate configuration
	 * 
	 * @param string|null $domainName Custom domain name
	 * @return array Viewer certificate configuration
	 */
	private static function getViewerCertificate(?string $domainName): array
	{
		if ($domainName) {
			return [
				'ACMCertificateArn' => '', // Set this to your ACM certificate ARN
				'SSLSupportMethod' => 'sni-only',
				'MinimumProtocolVersion' => 'TLSv1.2_2021',
				'Certificate' => '',
				'CertificateSource' => 'acm'
			];
		}

		return [
			'CloudFrontDefaultCertificate' => true,
			'MinimumProtocolVersion' => 'TLSv1.2_2021'
		];
	}

	/**
	 * Get cost optimization recommendations
	 * 
	 * @return array Cost optimization tips
	 */
	public static function getCostOptimizationTips(): array
	{
		return [
			'cache_optimization' => [
				'title' => 'Cache Optimization',
				'tips' => [
					'Images cached for 1 year - minimal S3 requests for repeat access',
					'Documents cached for 1 day - balanced between freshness and cost',
					'Compression enabled to reduce bandwidth costs',
					'Query string forwarding disabled to improve cache hit ratio'
				]
			],
			'price_class' => [
				'title' => 'Price Class Optimization',
				'tips' => [
					'Use PriceClass_100 for North America and Europe only',
					'Consider PriceClass_200 if you have global users',
					'Monitor CloudWatch metrics to optimize price class'
				]
			],
			'origin_access' => [
				'title' => 'Origin Access Control',
				'tips' => [
					'Block direct S3 access to prevent bypass charges',
					'Use Origin Access Control (OAC) instead of Origin Access Identity',
					'Configure S3 bucket policy to only allow CloudFront access'
				]
			],
			'monitoring' => [
				'title' => 'Cost Monitoring',
				'tips' => [
					'Monitor cache hit ratio in CloudWatch',
					'Set up billing alerts for unexpected traffic spikes',
					'Use AWS Cost Explorer to track CloudFront vs S3 costs',
					'Consider Reserved Capacity for predictable workloads'
				]
			]
		];
	}

	/**
	 * Get sample S3 bucket policy for CloudFront-only access
	 * 
	 * @param string $bucketName S3 bucket name
	 * @param string $distributionId CloudFront distribution ID
	 * @return array S3 bucket policy
	 */
	public static function getS3BucketPolicy(string $bucketName, string $distributionId): array
	{
		return [
			'Version' => '2012-10-17',
			'Statement' => [
				[
					'Sid' => 'AllowCloudFrontServicePrincipal',
					'Effect' => 'Allow',
					'Principal' => [
						'Service' => 'cloudfront.amazonaws.com'
					],
					'Action' => 's3:GetObject',
					'Resource' => "arn:aws:s3:::{$bucketName}/*",
					'Condition' => [
						'StringEquals' => [
							'AWS:SourceArn' => "arn:aws:cloudfront::*:distribution/{$distributionId}"
						]
					]
				]
			]
		];
	}
}
