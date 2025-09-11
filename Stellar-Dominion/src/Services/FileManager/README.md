# File Manager Service

The File Manager Service provides a unified interface for file operations across different storage backends (local filesystem and Amazon S3) with comprehensive validation and security for the Stellar Dominion game.

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                    FileManagerFactory                           │
│  Creates appropriate manager based on configuration/environment │
└─────────────────────┬───────────────────────────────────────────┘
                      │
        ┌─────────────┴──────────────┐
        │                            │
┌───────▼────────┐            ┌──────▼──────┐
│LocalFileManager│            │S3FileManager│
│                │            │             │
│ - Local storage│            │ - AWS S3    │
│ - File system  │            │ - Production│
│ - Development  │            │ - Cloud     │
└────────────────┘            └─────────────┘
        │                            │
        └─────────────┬──────────────┘
                      │
        ┌─────────────▼──────────────┐
        │      Supporting Classes    │
        │ - FileValidator            │
        │ - AssetUrlHelper           │
        │ - DriverType               │
        │ - Config Objects           │
        └────────────────────────────┘
```

## Core Components

### FileManagerInterface
The main interface that all file managers implement:
- `upload(string $sourceFile, string $destinationPath, array $options = []): bool`
- `delete(string $filePath): bool` 
- `exists(string $filePath): bool`
- `getUrl(string $filePath): string`
- `getFileInfo(string $filePath): ?array`
- `move(string $sourcePath, string $destinationPath): bool`
- `copy(string $sourcePath, string $destinationPath): bool`

### FileManagerFactory
Factory class that creates the appropriate file manager using multiple initialization methods:

1. **Configuration Objects** (Type-safe, preferred)
   ```php
   $config = new S3FileManagerConfig($bucket, $region);
   $manager = FileManagerFactory::createFromConfig($config);
   ```

2. **Environment Variables** (Automatic detection)
   ```php
   $manager = FileManagerFactory::createFromEnvironment();
   ```

3. **Configuration Arrays** (Legacy support)
   ```php
   $manager = FileManagerFactory::create(['driver' => 's3', 'bucket' => 'my-bucket']);
   ```

## Storage Drivers

### LocalFileManager
**Purpose**: Development, testing, and small-scale deployments

**Features**:
- Local filesystem storage with directory management
- Automatic directory creation with proper permissions (0755)
- Support for both uploaded files and regular file operations
- File existence and security validation
- Writable directory verification

**Configuration**:
```php
$manager = new LocalFileManager('/var/www/uploads', '/uploads');
```

**Use Cases**:
- Development environments
- Local testing
- Small deployments without cloud requirements

### S3FileManager
**Purpose**: Production cloud deployments with scalable storage

**Features**:
- AWS S3 storage with automatic credential chain
- VPC endpoint optimization for Lambda environments
- Automatic cache headers for browser optimization
- Automatic content type detection
- Enhanced error handling for VPC environments
- Path-style addressing for VPC Gateway endpoints

**AWS Credential Chain**:
1. Environment variables (AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY)
2. IAM instance profile (Lambda execution role)
3. AWS credentials file
4. IAM roles for Amazon EC2

**Configuration**:
```php
$manager = new S3FileManager('my-bucket', 'us-east-1');
```

**Lambda Optimizations**:
- VPC endpoint automatic detection
- Path-style addressing for gateway endpoints
- Optimized timeouts (25s upload, 5s connect)
- Enhanced error logging for troubleshooting
## Supporting Classes

### FileValidator
Comprehensive file validation with security checks:

**Features**:
- File size validation (min/max limits)
- Extension validation (whitelist approach)
- MIME type validation (real file inspection)
- Security checks (malicious file detection)
- Upload error handling

**Configuration**:
```php
$validator = new FileValidator([
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'avif'],
    'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/avif'],
    'max_file_size' => 10485760, // 10MB
    'min_file_size' => 1024      // 1KB
]);

$result = $validator->validateUploadedFile($_FILES['avatar']);
if (!$result['valid']) {
    throw new Exception($result['error']);
}
```

### AssetUrlHelper
Static asset URL management with relative paths:

**Features**:
- Stage-independent relative URLs
- Type-specific URL generation (CSS, JS, images)
- Preload tag generation for critical assets
- Simple relative path management

**Usage**:
```php
// Get asset URLs
$cssUrl = AssetUrlHelper::getCssUrl('style.css');        // /assets/css/style.css
$jsUrl = AssetUrlHelper::getJsUrl('main.js');            // /assets/js/main.js
$imageUrl = AssetUrlHelper::getImageUrl('logo.png');     // /assets/img/logo.png

// Generate preload tags
$preloadTags = AssetUrlHelper::generatePreloadTags([
    'css/critical.css',
    'js/app.js'
]);
```

### DriverType & FileDriverType
Type-safe driver selection with validation:

**Features**:
- Compile-time type safety
- String validation and normalization
- Support for LOCAL and S3 drivers
- Invalid driver prevention

**Usage**:
```php
// Type-safe creation
$driverType = DriverType::s3();
$driverType = DriverType::fromString('local');

// Validation
FileDriverType::validate('s3'); // throws exception if invalid
$normalized = FileDriverType::normalize('S3'); // returns 's3'
```

## Configuration System

### Interface-Based Configuration
All configuration objects implement `FileManagerConfigInterface`:

```php
interface FileManagerConfigInterface
{
    public function getDriverType(): DriverType;
    public function validate(): void;
    public function toArray(): array;
}
```

### LocalFileManagerConfig
Configuration for local filesystem storage:

```php
$config = new LocalFileManagerConfig(
    baseDirectory: '/var/www/uploads',
    baseUrl: '/uploads'
);
```

### S3FileManagerConfig  
Configuration for S3 storage:

```php
$config = new S3FileManagerConfig(
    bucket: 'stellar-dominion-files',
    region: 'us-east-2'
);
```

## Usage Examples

### Basic File Upload
```php
use StellarDominion\Services\FileManager\FileManagerFactory;
use StellarDominion\Services\FileManager\FileValidator;

// Create manager (auto-detects environment)
$fileManager = FileManagerFactory::createFromEnvironment();

// Validate uploaded file
$validator = new FileValidator();
$validation = $validator->validateUploadedFile($_FILES['avatar']);

if (!$validation['valid']) {
    throw new Exception('Invalid file: ' . $validation['error']);
}

// Upload file
$success = $fileManager->upload(
    $_FILES['avatar']['tmp_name'],
    'avatars/user_' . $userId . '.jpg'
);

if ($success) {
    $url = $fileManager->getUrl('avatars/user_' . $userId . '.jpg');
    echo "File uploaded successfully: " . $url;
}
```

### Environment-Based Configuration
```php
// Set environment variables
$_ENV['FILE_STORAGE_DRIVER'] = 's3';
$_ENV['FILE_STORAGE_S3_BUCKET'] = 'my-bucket';
$_ENV['AWS_REGION'] = 'us-east-1';

// Factory automatically detects S3 configuration
$fileManager = FileManagerFactory::createFromEnvironment();
```

### Configuration Object Usage (Recommended)
```php
use StellarDominion\Services\FileManager\Config\S3FileManagerConfig;

// Type-safe configuration
$config = new S3FileManagerConfig('my-bucket', 'us-east-1');
$fileManager = FileManagerFactory::createFromConfig($config);

// Automatic validation
$config->validate(); // Throws exception if invalid
```

## File Operations

### Standard Operations
```php
// Upload file
$success = $fileManager->upload($tempFile, 'uploads/document.pdf');

// Check existence
if ($fileManager->exists('uploads/document.pdf')) {
    // File exists
}

// Get file info
$info = $fileManager->getFileInfo('uploads/document.pdf');
// Returns: ['size' => 1024, 'last_modified' => '2025-01-01', ...]

// Move file
$fileManager->move('temp/file.pdf', 'permanent/file.pdf');

// Copy file
$fileManager->copy('original/file.pdf', 'backup/file.pdf');

// Delete file
$fileManager->delete('uploads/old_file.pdf');

// Get public URL
$url = $fileManager->getUrl('uploads/document.pdf');
```

## Environment Detection

The system automatically adapts to different environments:

### Lambda Environment
- Detects `$_ENV['AWS_LAMBDA_FUNCTION_NAME']`
- Uses S3FileManager with VPC optimizations
- Enables path-style addressing for VPC endpoints
- Sets appropriate timeouts for serverless

### Local Development
- Uses LocalFileManager by default
- Creates directories automatically
- Validates file permissions

## Security Features

### File Validation Security
- **Real MIME type checking**: Inspects file content, not just extension
- **Extension whitelist**: Only allows explicitly permitted file types
- **Size limits**: Prevents large file attacks
- **Upload error handling**: Proper PHP upload error detection

### S3 Security
- **IAM role integration**: No hardcoded credentials in Lambda
- **VPC endpoint support**: Secure internal AWS communication
- **Bucket policies**: Access control at storage level
- **Content type enforcement**: Automatic content type detection

### Local Storage Security
- **Directory permissions**: Automatic 0755 for directories, 0644 for files
- **Path validation**: Prevents directory traversal attacks
- **Writable checks**: Validates directory access before operations

## Error Handling

### Comprehensive Error Coverage
```php
try {
    $fileManager->upload($source, $destination);
} catch (\Exception $e) {
    // Specific error types:
    // - File permission errors
    // - S3 connection timeouts
    // - Invalid file types
    // - Disk space issues
    error_log("Upload failed: " . $e->getMessage());
}
```

### S3-Specific Error Handling
- VPC endpoint timeout detection
- Connection failure diagnostics
- AWS credential validation
- Enhanced logging for troubleshooting

## Performance Optimizations

### S3 Optimizations
- **Content type detection**: Automatic MIME type setting
- **Cache headers**: 1-year cache for images  
- **VPC endpoints**: Reduced latency in Lambda
- **Timeouts**: Optimized for serverless environment

### Local Optimizations
- **Directory caching**: Minimizes filesystem calls
- **Permission checks**: Validates access upfront
- **Batch operations**: Efficient for multiple files

## Development vs Production

### Development (Local)
```env
FILE_STORAGE_DRIVER=local
FILE_STORAGE_LOCAL_DIR=/var/www/html/public/uploads
FILE_STORAGE_LOCAL_URL=/uploads
```

### Production (Lambda + S3)
```env
FILE_STORAGE_DRIVER=s3
FILE_STORAGE_S3_BUCKET=stellar-dominion-files
AWS_REGION=us-east-1
```

## Integration with Stellar Dominion

The File Manager Service integrates with:
- **User avatars**: Profile image management
- **Game assets**: Static resource serving
- **Document uploads**: File attachment system
- **Asset delivery**: Optimized static file serving

### Typical Usage Patterns
1. **Avatar uploads**: User profile images via LocalFileManager (dev) or S3FileManager (prod)
2. **Static assets**: CSS/JS/images via AssetUrlHelper with relative paths
3. **File validation**: All uploads validated via FileValidator
4. **Environment adaptation**: Automatic driver selection via Factory

## Usage Examples

### Basic File Upload
```php
use StellarDominion\Services\FileManager\FileManagerFactory;
use StellarDominion\Services\FileManager\FileValidator;

// Create manager (auto-detects environment)
$fileManager = FileManagerFactory::createFromEnvironment();

// Validate uploaded file
$validator = new FileValidator();
$validation = $validator->validateUploadedFile($_FILES['avatar']);

if (!$validation['valid']) {
    throw new Exception('Invalid file: ' . $validation['error']);
}

// Upload file
$success = $fileManager->upload(
    $_FILES['avatar']['tmp_name'],
    'avatars/user_' . $userId . '.jpg'
);

if ($success) {
    $url = $fileManager->getUrl('avatars/user_' . $userId . '.jpg');
    echo "File uploaded successfully: " . $url;
}
```

### Environment-Based Configuration
```php
// Set environment variables
$_ENV['FILE_STORAGE_DRIVER'] = 's3';
$_ENV['FILE_STORAGE_S3_BUCKET'] = 'my-bucket';
$_ENV['AWS_REGION'] = 'us-east-1';

// Factory automatically detects S3 configuration
$fileManager = FileManagerFactory::createFromEnvironment();
$url = $fileManager->getUrl('avatars/user_123.jpg');
// Production: https://stellar-dominion.com/avatars/user_123.jpg
// Development: /uploads/avatars/user_123.jpg
```

### Configuration-Based Usage (Recommended)

```php
use StellarDominion\Services\FileManager\Config\S3FileManagerConfig;
use StellarDominion\Services\FileManager\FileManagerFactory;

// Create S3 configuration
$config = new S3FileManagerConfig(
    bucket: 'stellar-dominion-files',
    region: 'us-east-2'
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

# Local Configuration (for development)
FILE_STORAGE_LOCAL_DIR=/var/www/uploads
FILE_STORAGE_LOCAL_URL=/uploads
```

## Environment Detection

The system automatically adapts to different environments:

### Lambda Environment
- Detects `$_ENV['AWS_LAMBDA_FUNCTION_NAME']`
- Uses S3FileManager with VPC optimizations
- Enables path-style addressing for VPC endpoints
- Sets appropriate timeouts for serverless

### Local Development
- Uses LocalFileManager by default
- Creates directories automatically
- Validates file permissions

## File Operations

### Standard Operations
```php
// Upload file
$success = $fileManager->upload($tempFile, 'uploads/document.pdf');

// Check existence
if ($fileManager->exists('uploads/document.pdf')) {
    // File exists
}

// Get file info
$info = $fileManager->getFileInfo('uploads/document.pdf');
// Returns: ['size' => 1024, 'last_modified' => '2025-01-01', ...]

// Move file
$fileManager->move('temp/file.pdf', 'permanent/file.pdf');

// Copy file
$fileManager->copy('original/file.pdf', 'backup/file.pdf');

// Delete file
$fileManager->delete('uploads/old_file.pdf');

// Get public URL
$url = $fileManager->getUrl('uploads/document.pdf');
```

## Security Features

### File Validation Security
- **Real MIME type checking**: Inspects file content, not just extension
- **Extension whitelist**: Only allows explicitly permitted file types
- **Size limits**: Prevents large file attacks
- **Upload error handling**: Proper PHP upload error detection

### S3 Security
- **IAM role integration**: No hardcoded credentials in Lambda
- **VPC endpoint support**: Secure internal AWS communication
- **Bucket policies**: Access control at storage level
- **Content type enforcement**: Automatic content type detection

### Local Storage Security
- **Directory permissions**: Automatic 0755 for directories, 0644 for files
- **Path validation**: Prevents directory traversal attacks
- **Writable checks**: Validates directory access before operations

## Error Handling

### Comprehensive Error Coverage
```php
try {
    $fileManager->upload($source, $destination);
} catch (\Exception $e) {
    // Specific error types:
    // - File permission errors
    // - S3 connection timeouts
    // - Invalid file types
    // - Disk space issues
    error_log("Upload failed: " . $e->getMessage());
}
```

### S3-Specific Error Handling
- VPC endpoint timeout detection
- Connection failure diagnostics
- AWS credential validation
- Enhanced logging for troubleshooting

## Performance Optimizations

### S3 Optimizations
- **Content type detection**: Automatic MIME type setting
- **Cache headers**: 1-year cache for images  
- **VPC endpoints**: Reduced latency in Lambda
- **Timeouts**: Optimized for serverless environment

### Local Optimizations
- **Directory caching**: Minimizes filesystem calls
- **Permission checks**: Validates access upfront
- **Batch operations**: Efficient for multiple files

## Development vs Production

### Development (Local)
```env
FILE_STORAGE_DRIVER=local
FILE_STORAGE_LOCAL_DIR=/var/www/html/public/uploads
FILE_STORAGE_LOCAL_URL=/uploads
```

### Production (Lambda + S3)
```env
FILE_STORAGE_DRIVER=s3
FILE_STORAGE_S3_BUCKET=stellar-dominion-files
AWS_REGION=us-east-1
```

## Integration with Stellar Dominion

The File Manager Service integrates with:
- **User avatars**: Profile image management
- **Game assets**: Static resource serving
- **Document uploads**: File attachment system
- **Asset delivery**: Optimized static file serving

### Typical Usage Patterns
1. **Avatar uploads**: User profile images via LocalFileManager (dev) or S3FileManager (prod)
2. **Static assets**: CSS/JS/images via AssetUrlHelper with relative paths
3. **File validation**: All uploads validated via FileValidator
4. **Environment adaptation**: Automatic driver selection via Factory


## Troubleshooting

### Common Issues

1. **"S3 bucket not found"**
   - Check `FILE_STORAGE_S3_BUCKET` environment variable
   - Verify bucket exists and IAM permissions

2. **"File upload fails"**
   - Check file size limits and permissions
   - Verify storage backend configuration

3. **"High S3 costs"**
   - Implement CloudFront CDN
   - Review cache headers and TTL settings

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
