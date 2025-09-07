# CDN Integration Guide

The Stellar Dominion file management system now includes comprehensive CDN integration to prevent denial of wallet attacks on S3 and reduce hosting costs.

## Features

- **Environment-Aware**: Automatically uses CDN in production (Lambda) and direct S3 in development
- **Cost Optimization**: Aggressive caching strategies to minimize S3 requests
- **Denial of Wallet Prevention**: CloudFront shields S3 from direct public access
- **Lambda Compatible**: Works seamlessly with serverless hosting
- **Local Development Friendly**: No CDN required for development environments

## Quick Start

### 1. Environment Configuration

Add these variables to your environment:

```bash
# For Production (Lambda)
FILE_STORAGE_S3_BUCKET=stellar-dominion-files
CLOUDFRONT_DOMAIN=https://cdn.stellar-dominion.com

# For Development (Local)
FILE_STORAGE_S3_BUCKET=stellar-dominion-dev
# No CLOUDFRONT_DOMAIN needed - will use direct S3
```

### 2. CloudFront Setup

Use the built-in configuration helper:

```php
use StellarDominion\Services\FileManager\CloudFrontConfig;

// Get recommended CloudFront configuration
$config = CloudFrontConfig::getRecommendedConfig('your-s3-bucket-name');

// Get cost optimization tips
$tips = CloudFrontConfig::getCostOptimizationTips();
```

### 3. Using the File Manager

The CDN integration is automatic:

```php
use StellarDominion\Services\FileManager\FileManagerFactory;

$fileManager = FileManagerFactory::create();

// Upload a file - automatically gets optimized cache headers
$result = $fileManager->upload('avatars/user_123.jpg', $fileData);

// Get URL - automatically uses CDN in production, S3 in development
$url = $fileManager->getUrl('avatars/user_123.jpg');
```

## Architecture

### Environment Detection

The system automatically detects the environment:

- **Production**: Detected by `AWS_LAMBDA_FUNCTION_NAME` environment variable
- **Development**: Any other environment (local, staging, etc.)

### Caching Strategy

#### Images (avatars/, images/)
- **Browser Cache**: 1 year (31,536,000 seconds)
- **CDN Cache**: 2 years (63,072,000 seconds)
- **Rationale**: Images rarely change, aggressive caching saves costs

#### Documents (documents/, files/)
- **Browser Cache**: 1 day (86,400 seconds)
- **CDN Cache**: 2 days (172,800 seconds)
- **Rationale**: Documents may update, shorter cache for freshness

#### Other Files
- **Browser Cache**: 5 minutes (300 seconds)
- **CDN Cache**: Same as browser
- **Rationale**: Conservative caching for unknown file types

### URL Generation

```php
// Production (Lambda environment)
$url = $fileManager->getUrl('avatars/user.jpg');
// Returns: https://cdn.stellar-dominion.com/avatars/user.jpg

// Development environment
$url = $fileManager->getUrl('avatars/user.jpg');
// Returns: https://bucket.s3.region.amazonaws.com/avatars/user.jpg
```

## Cost Optimization

### How CDN Prevents Denial of Wallet

1. **Request Shielding**: CloudFront caches responses, reducing S3 requests
2. **Origin Access Control**: Blocks direct S3 access, forcing traffic through CDN
3. **Cache Hit Optimization**: Long cache times mean files served from edge locations
4. **Regional Distribution**: Edge locations reduce data transfer costs

### Expected Cost Savings

- **S3 Requests**: 90%+ reduction for repeated file access
- **Data Transfer**: Regional edge locations reduce bandwidth costs
- **Attack Protection**: Cached responses prevent S3 request spikes

### Monitoring Costs

The system provides cost analysis methods:

```php
$cdn = new CDNManager($s3BaseUrl, $cdnDomain);
$info = $cdn->getCostOptimizationInfo();

echo "Environment: " . $info['environment'];
echo "CDN Enabled: " . ($info['cdn_enabled'] ? 'Yes' : 'No');
foreach ($info['recommendations'] as $tip) {
    echo $tip;
}
```

## Setup Instructions

### 1. Create CloudFront Distribution

```php
// Get the recommended configuration
$config = CloudFrontConfig::getRecommendedConfig('your-bucket-name');

// Use this configuration to create your CloudFront distribution
// through AWS Console, CLI, or CDK
```

Key settings from the recommended config:
- **Price Class**: `PriceClass_100` (US/Europe only - most cost effective)
- **Default TTL**: 1 hour
- **Origin Access Control**: Required to block direct S3 access
- **Compression**: Enabled for better performance

### 2. Configure S3 Bucket Policy

```php
// Get the recommended S3 bucket policy
$bucketPolicy = CloudFrontConfig::generateS3BucketPolicy(
    'your-bucket-name',
    'your-cloudfront-oac-id'
);

// Apply this policy to your S3 bucket
```

### 3. Update Environment Variables

```bash
# Production Lambda environment
CLOUDFRONT_DOMAIN=https://d1234567890123.cloudfront.net
FILE_STORAGE_CDN_URL=https://d1234567890123.cloudfront.net

# Development environment
# Leave CLOUDFRONT_DOMAIN empty to use direct S3
```

### 4. Test the Integration

Run the included test:

```bash
php test_cdn_integration.php
```

## Troubleshooting

### CDN Not Working in Production

1. **Check Environment**: Ensure `AWS_LAMBDA_FUNCTION_NAME` is set
2. **Verify Domain**: Confirm `CLOUDFRONT_DOMAIN` is correct
3. **Test Manually**: Use `CDNManager::isCdnEnabled()` to debug

### Files Not Caching

1. **Check Headers**: Verify cache headers are set on upload
2. **CloudFront Config**: Ensure distribution respects origin cache headers
3. **Cache Behaviors**: Verify path patterns match your file structure

### High S3 Costs

1. **Monitor Hit Ratio**: Check CloudFront cache hit ratio in CloudWatch
2. **Verify OAC**: Ensure Origin Access Control blocks direct S3 access
3. **Check TTL**: Verify long cache times for static assets

### Development Issues

1. **Local S3**: Ensure development uses different bucket or LocalFileManager
2. **No CDN Required**: Development should work without CloudFront
3. **Environment Variables**: Check that `CLOUDFRONT_DOMAIN` is not set locally

## Security Considerations

### Origin Access Control

- **Required**: Blocks direct public access to S3 bucket
- **Setup**: Use CloudFront OAC instead of legacy OAI
- **Benefit**: Forces all traffic through CDN, preventing direct S3 attacks

### Cache Poisoning Prevention

- **Origin Headers**: CDN respects cache headers from S3
- **Path-based Caching**: Different cache strategies for different file types
- **Invalidation**: Use CloudFront invalidations for immediate updates

### CORS Configuration

The S3 bucket should allow CloudFront origin:

```json
{
    "CORSRules": [
        {
            "AllowedHeaders": ["*"],
            "AllowedMethods": ["GET", "HEAD"],
            "AllowedOrigins": ["https://your-cdn-domain.com"],
            "MaxAgeSeconds": 3600
        }
    ]
}
```

## Performance Benefits

### Global Distribution

- **Edge Locations**: Files served from locations closest to users
- **Reduced Latency**: Faster loading times worldwide
- **Bandwidth Optimization**: Compression and caching at edge

### Caching Efficiency

- **Browser Caching**: Long-term client-side storage
- **CDN Caching**: Intermediate caching at edge locations
- **Origin Shielding**: Minimal requests to actual S3 bucket

## Maintenance

### Regular Tasks

1. **Monitor CloudWatch**: Check cache hit ratios and error rates
2. **Review Costs**: Monthly analysis of S3 and CloudFront costs
3. **Update Cache**: Invalidate CDN when critical files change
4. **Security Audit**: Verify OAC configuration and bucket policies

### Cache Invalidation

When you need to update cached files immediately:

```bash
# Use AWS CLI to invalidate specific files
aws cloudfront create-invalidation \
    --distribution-id E1234567890123 \
    --paths "/avatars/updated-avatar.jpg"
```

### Cost Monitoring

Set up CloudWatch alarms for:
- S3 request count spikes
- CloudFront data transfer costs
- Cache hit ratio below 90%

## Integration Examples

### Profile Avatar Upload

```php
// Upload avatar with optimized caching
$result = $fileManager->upload(
    'avatars/user_' . $userId . '.jpg',
    $imageData,
    ['content_type' => 'image/jpeg']
);

// Get CDN URL for display
$avatarUrl = $fileManager->getUrl('avatars/user_' . $userId . '.jpg');

// In production: https://cdn.stellar-dominion.com/avatars/user_123.jpg
// In development: https://bucket.s3.region.amazonaws.com/avatars/user_123.jpg
```

### Document Download

```php
// Upload document with shorter cache time
$result = $fileManager->upload(
    'documents/alliance_charter.pdf',
    $pdfData,
    ['content_type' => 'application/pdf']
);

// Get download URL
$downloadUrl = $fileManager->getUrl('documents/alliance_charter.pdf');
```

The CDN integration is now complete and ready for production use! 🚀
