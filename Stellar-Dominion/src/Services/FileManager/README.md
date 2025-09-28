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
        │ - DriverType               │
        │ - Config Objects           │
        └────────────────────────────┘
```

## File Storage System

Stellar Dominion uses a driver-based file storage system that supports both local filesystem and Amazon S3 storage. This allows for flexibility in deployment environments.

### Configuration

The file storage system is configured via environment variables:

```bash
# Choose storage driver: 'local' or 's3'
FILE_STORAGE_DRIVER=local

# Local storage settings (when using local driver)
FILE_STORAGE_LOCAL_PATH=/path/to/uploads
FILE_STORAGE_LOCAL_URL=/uploads

# S3 storage settings (when using s3 driver)
FILE_STORAGE_S3_BUCKET=your-bucket-name
FILE_STORAGE_S3_REGION=us-east-2
```

### Supported Features

- **Avatar Uploads**: User and alliance avatar management
- **File Validation**: Automatic validation of file types, sizes, and security
- **Multiple Drivers**: Seamless switching between local and S3 storage
- **Security**: Built-in protection against malicious file uploads
- **Metadata**: File metadata storage for tracking and management

### Usage

The system automatically handles file operations based on the configured driver. You can use either configuration objects (recommended) or arrays (legacy support):

#### Using Configuration Objects (Recommended)

```php
use StellarDominion\Services\FileManager\FileManagerFactory;
use StellarDominion\Services\FileManager\Config\LocalFileManagerConfig;
use StellarDominion\Services\FileManager\Config\S3FileManagerConfig;

// Create from environment variables
$fileManager = FileManagerFactory::createFromEnvironment();

// Or create with specific configuration objects
$localConfig = new LocalFileManagerConfig('/path/to/uploads', '/uploads');
$fileManager = FileManagerFactory::createFromConfig($localConfig);

// S3 for development
$s3Config = S3FileManagerConfig::createDevelopment('my-dev-bucket');
$fileManager = FileManagerFactory::createFromConfig($s3Config);
```

#### Using Arrays (Legacy Support)

```php
// Get the configured file manager (legacy method)
$fileManager = FileManagerFactory::createFromEnvironment();

// Upload a file
$fileManager->upload($sourceFile, 'avatars/user_123.jpg');

// Get file URL
$url = $fileManager->getUrl('avatars/user_123.jpg');

// Delete a file
$fileManager->delete('avatars/user_123.jpg');
```

#### Configuration Benefits

Configuration objects provide several advantages:
- **Type Safety**: Compile-time validation of configuration parameters
- **IDE Support**: Full autocompletion and documentation
- **Validation**: Built-in validation with clear error messages
- **Immutability**: Configuration cannot be accidentally modified
- **Defaults**: Easy creation of standard configurations

### Deployment Notes

- **Local Development**: Use `local` driver with `FILE_STORAGE_LOCAL_PATH` pointing to a writable directory
- **Production with S3**: Use `s3` driver with appropriate AWS credentials and bucket configuration
- **CloudFront**: Configure your CDN/CloudFront to point to the S3 bucket. No special env var is required by the codebase.

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

