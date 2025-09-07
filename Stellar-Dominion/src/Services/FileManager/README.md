# File Manager Service

The File Manager Service provides a unified interface for file operations across different storage backends (local filesystem and Amazon S3) with optional CDN integration for cost optimization and performance.

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                    FileManagerFactory                          │
│  Creates appropriate manager based on configuration/environment │
└─────────────────────┬───────────────────────────────────────────┘
                      │
        ┌─────────────┴──────────────┐
        │                            │
┌───────▼──────┐              ┌──────▼─────┐
│LocalFileManager│            │S3FileManager│
│                │            │             │
│ - Local storage│            │ - AWS S3    │
│ - File system  │            │ - CDN ready │
│ - Development  │            │ - Production│
└────────────────┘            └─────────────┘
                                      │
                              ┌───────▼──────┐
                              │  CDNManager  │
                              │              │
                              │ - CloudFront │
                              │ - Cache opts │
                              │ - Cost mgmt  │
                              └──────────────┘
```

## Core Components

### FileManagerInterface
The main interface that all file managers implement:
- `upload(string $path, $data, array $options = []): array`
- `delete(string $path): bool` 
- `exists(string $path): bool`
- `getUrl(string $path): string`
- `listFiles(string $directory = ''): array`

### FileManagerFactory
Factory class that creates the appropriate file manager based on:
1. **Configuration objects** (preferred, type-safe)
2. **Environment variables** (automatic detection)
3. **Configuration arrays** (legacy support)

### Storage Drivers

#### LocalFileManager
- **Use Case**: Development, local testing, small deployments
- **Storage**: Local filesystem
- **URLs**: Direct file paths (`/uploads/file.jpg`)
- **Configuration**: Base directory and URL

#### S3FileManager  
- **Use Case**: Production, cloud deployments, scalable storage
- **Storage**: Amazon S3 buckets
- **URLs**: S3 URLs or CDN URLs (via CDNManager)
- **Configuration**: Bucket, region, credentials (optional with IAM)

### CDN Integration

#### CDNManager
Handles intelligent URL generation with environment-aware CDN usage:
- **Production**: Uses CloudFront CDN for cost optimization
- **Development**: Direct S3 URLs for simplicity
- **Caching**: File-type specific cache strategies

#### CloudFrontConfig
Provides pre-configured CloudFront settings optimized for:
- **Cost reduction**: Aggressive caching, proper TTLs
- **Security**: Origin Access Control (OAC)
- **Performance**: Edge locations, compression

## Usage Examples

### Basic Usage (Auto-Detection)

```php
use StellarDominion\Services\FileManager\FileManagerFactory;

// Automatically detects environment and creates appropriate manager
$fileManager = FileManagerFactory::create();

// Upload a file
$result = $fileManager->upload('avatars/user_123.jpg', $imageData);

// Get URL (CDN-aware in production)
$url = $fileManager->getUrl('avatars/user_123.jpg');
// Production: https://cdn.stellar-dominion.com/avatars/user_123.jpg
// Development: /uploads/avatars/user_123.jpg
```

### Configuration-Based Usage (Recommended)

```php
use StellarDominion\Services\FileManager\Config\S3FileManagerConfig;
use StellarDominion\Services\FileManager\FileManagerFactory;

// Create S3 configuration
$config = new S3FileManagerConfig(
    bucket: 'stellar-dominion-files',
    region: 'us-east-2',
    accessKeyId: null, // Uses IAM role in Lambda
    secretAccessKey: null, // Uses IAM role in Lambda
    baseUrl: 'https://cdn.stellar-dominion.com' // CDN domain
);

// Create manager from configuration
$fileManager = FileManagerFactory::createFromConfig($config);
```

### Environment Variables

The factory automatically detects these environment variables:

```bash
# S3 Configuration (for production/Lambda)
FILE_STORAGE_S3_BUCKET=stellar-dominion-files
AWS_REGION=us-east-2
CLOUDFRONT_DOMAIN=https://cdn.stellar-dominion.com

# Local Configuration (for development)
FILE_STORAGE_LOCAL_DIR=/var/www/uploads
FILE_STORAGE_LOCAL_URL=/uploads
```

## File Type Optimization

The CDN manager applies different caching strategies based on file types:

### Images (`avatars/`, `images/`)
- **Browser Cache**: 1 year (aggressive caching)
- **CDN Cache**: 2 years
- **Extensions**: jpg, jpeg, png, gif, webp, avif, svg
- **Rationale**: Images rarely change, maximize cache hits

### Documents (`documents/`, `files/`)
- **Browser Cache**: 1 day (moderate caching)
- **CDN Cache**: 2 days  
- **Extensions**: pdf, doc, docx, txt
- **Rationale**: Documents may update, balance freshness vs performance

### Other Files
- **Browser Cache**: 1 hour (conservative)
- **CDN Cache**: 1 hour
- **Rationale**: Unknown file types, prioritize freshness

## Environment Detection

The system automatically detects the runtime environment:

```php
// Production detection (Lambda)
if (isset($_ENV['AWS_LAMBDA_FUNCTION_NAME'])) {
    // Use S3FileManager with CDN
}

// Development detection
else {
    // Use LocalFileManager
}
```

## Error Handling

All file operations include comprehensive error handling:

```php
try {
    $result = $fileManager->upload('path/file.jpg', $data);
    if (!$result['success']) {
        throw new Exception($result['error']);
    }
} catch (Exception $e) {
    error_log("File upload failed: " . $e->getMessage());
    // Handle gracefully
}
```

## Security Considerations

### S3 Security
- **IAM Roles**: Production uses IAM roles instead of access keys
- **Bucket Policies**: Restrict access to CloudFront only
- **CORS**: Configured for specific origins only
- **Versioning**: Enabled for data protection

### CDN Security
- **Origin Access Control**: Prevents direct S3 access
- **Cache Poisoning**: Proper cache headers prevent attacks
- **HTTPS Only**: All CDN traffic uses SSL/TLS

### File Validation
```php
use StellarDominion\Services\FileManager\FileValidator;

$validator = new FileValidator([
    'max_size' => 5 * 1024 * 1024, // 5MB
    'allowed_types' => ['image/jpeg', 'image/png'],
    'allowed_extensions' => ['jpg', 'jpeg', 'png']
]);

if (!$validator->validate($file)) {
    throw new Exception('Invalid file: ' . $validator->getError());
}
```

## Cost Optimization

### CDN Benefits
1. **Request Reduction**: 90%+ reduction in S3 requests
2. **Bandwidth Savings**: Edge locations reduce transfer costs
3. **Attack Protection**: CloudFront shields S3 from abuse
4. **Global Performance**: Faster loading worldwide

### Monitoring Costs
```php
$cdnManager = new CDNManager($s3Url, $cdnDomain);
$info = $cdnManager->getCostOptimizationInfo();

foreach ($info['recommendations'] as $tip) {
    echo "💡 " . $tip . "\n";
}
```

## Configuration Files

### Local Development (.env)
```env
FILE_STORAGE_DRIVER=local
FILE_STORAGE_LOCAL_DIR=/var/www/uploads
FILE_STORAGE_LOCAL_URL=/uploads
```

### Production Lambda (serverless.yml)
```yaml
environment:
  FILE_STORAGE_S3_BUCKET: !Ref FileStorageBucket
  # CLOUDFRONT_DOMAIN: https://d123.cloudfront.net (manual setup)
```

## Testing

### Unit Tests
```php
public function testFileUpload()
{
    $manager = FileManagerFactory::create();
    $result = $manager->upload('test/file.txt', 'test content');
    
    $this->assertTrue($result['success']);
    $this->assertTrue($manager->exists('test/file.txt'));
}
```

### Integration Tests
```php
public function testCdnIntegration()
{
    $manager = FileManagerFactory::create();
    $url = $manager->getUrl('test/file.jpg');
    
    if (CDNManager::isProductionEnvironment()) {
        $this->assertStringContains('cloudfront', $url);
    } else {
        $this->assertStringStartsWith('/uploads', $url);
    }
}
```

## Troubleshooting

### Common Issues

1. **"S3 bucket not found"**
   - Check `FILE_STORAGE_S3_BUCKET` environment variable
   - Verify bucket exists and IAM permissions

2. **"CDN not working"**
   - Ensure `CLOUDFRONT_DOMAIN` is set in production
   - Check CloudFront distribution configuration

3. **"File upload fails"**
   - Check file size limits and permissions
   - Verify storage backend configuration

4. **"High S3 costs"**
   - Implement CloudFront CDN
   - Review cache headers and TTL settings

### Debug Information
```php
// Get current configuration
$info = FileManagerFactory::getEnvironmentInfo();
print_r($info);

// Check CDN status
$cdnManager = new CDNManager($s3Url, $cdnDomain);
echo "CDN Enabled: " . ($cdnManager->isCdnEnabled() ? 'Yes' : 'No');
```

## Best Practices

1. **Use Configuration Objects**: Type-safe, validation included
2. **Implement CDN**: Essential for production cost control
3. **Monitor Costs**: Regular CloudWatch review
4. **Test Both Environments**: Local and Lambda compatibility
5. **Validate Files**: Always validate uploads before processing
6. **Handle Errors**: Graceful degradation for file operations
7. **Cache Wisely**: Different strategies for different file types

## API Reference

See individual class files for detailed API documentation:
- `FileManagerInterface.php` - Main interface
- `FileManagerFactory.php` - Factory methods
- `S3FileManager.php` - S3 implementation
- `LocalFileManager.php` - Local implementation
- `CDNManager.php` - CDN optimization
- `CloudFrontConfig.php` - CDN configuration
