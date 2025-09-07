<?php

require_once __DIR__ . '/BaseController.php';

/**
 * Static Asset Controller
 * 
 * Serves static assets through the API Gateway for unified domain access.
 * Provides proper caching headers and MIME types for optimal performance.
 */
class AssetController extends BaseController
{
    private const CACHE_DURATION = [
        // Long cache for assets that rarely change
        'css' => 31536000,  // 1 year
        'js' => 31536000,   // 1 year
        'png' => 31536000,  // 1 year
        'jpg' => 31536000,  // 1 year
        'jpeg' => 31536000, // 1 year
        'avif' => 31536000, // 1 year
        'webp' => 31536000, // 1 year
        'ico' => 31536000,  // 1 year
        // Shorter cache for other files
        'default' => 3600   // 1 hour
    ];

    private const MIME_TYPES = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'avif' => 'image/avif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'svg' => 'image/svg+xml'
    ];

    /**
     * Serve static asset file
     * 
     * @param string $assetPath Path to the asset relative to assets directory
     */
    public function serveAsset(string $assetPath): void
    {
        try {
            // Security: Prevent directory traversal
            $assetPath = $this->sanitizeAssetPath($assetPath);
            
            // Get the local file path
            $localPath = $this->getLocalAssetPath($assetPath);
            
            if (!file_exists($localPath)) {
                $this->sendNotFound();
                return;
            }

            // Get file info
            $extension = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));
            $mimeType = self::MIME_TYPES[$extension] ?? 'application/octet-stream';
            $cacheTime = self::CACHE_DURATION[$extension] ?? self::CACHE_DURATION['default'];

            // Set caching headers (pass the local path for ETag calculation)
            $this->setCacheHeaders($cacheTime, $localPath);
            
            // Set content type and length
            header('Content-Type: ' . $mimeType);
            
            // Check if this is binary content that needs special handling
            if ($this->isBinaryContentType($mimeType)) {
                // For binary content in Lambda, we need to handle the response properly
                $content = file_get_contents($localPath);
                
                // Set headers for binary content
                header('Content-Length: ' . strlen($content));
                
                // Output the binary content directly
                // Bref will handle the base64 encoding automatically for binary responses
                echo $content;
            } else {
                // For text content, serve normally
                header('Content-Length: ' . filesize($localPath));
                readfile($localPath);
            }
            
        } catch (\Exception $e) {
            error_log("Asset serving error for '$assetPath': " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            $this->sendServerError();
        }
    }

    /**
     * Check if content type is binary
     * 
     * @param string $mimeType MIME type to check
     * @return bool True if binary content
     */
    private function isBinaryContentType(string $mimeType): bool
    {
        $binaryPrefixes = ['image/', 'font/', 'audio/', 'video/'];
        $binaryTypes = ['application/octet-stream'];
        
        foreach ($binaryPrefixes as $prefix) {
            if (strpos($mimeType, $prefix) === 0) {
                return true;
            }
        }
        
        return in_array($mimeType, $binaryTypes);
    }

    /**
     * Sanitize asset path to prevent directory traversal
     * 
     * @param string $path Raw asset path
     * @return string Sanitized path
     * @throws \InvalidArgumentException If path is invalid
     */
    private function sanitizeAssetPath(string $path): string
    {
        // Remove leading slashes and normalize
        $path = ltrim($path, '/');
        
        // Prevent directory traversal
        if (strpos($path, '..') !== false || strpos($path, '\\') !== false) {
            throw new \InvalidArgumentException('Invalid asset path');
        }

        // Only allow specific directories
        $allowedPaths = ['css/', 'js/', 'img/'];
        $isAllowed = false;
        
        foreach ($allowedPaths as $allowedPath) {
            if (strpos($path, $allowedPath) === 0) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            throw new \InvalidArgumentException('Asset path not allowed');
        }

        return $path;
    }

    /**
     * Get local file system path for asset
     * 
     * @param string $assetPath Asset path
     * @return string Full local path
     */
    private function getLocalAssetPath(string $assetPath): string
    {
        return PROJECT_ROOT . '/public/assets/' . $assetPath;
    }

    /**
     * Set cache headers for optimal performance
     * 
     * @param int $cacheTime Cache duration in seconds
     * @param string $localPath Full path to the file for ETag calculation
     */
    private function setCacheHeaders(int $cacheTime, string $localPath): void
    {
        // Set cache control headers
        header('Cache-Control: public, max-age=' . $cacheTime);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cacheTime) . ' GMT');
        
        // Set ETag for efficient caching (only if file exists)
        if (file_exists($localPath)) {
            $etag = md5_file($localPath);
            header('ETag: "' . $etag . '"');
            
            // Check if client has cached version
            if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === '"' . $etag . '"') {
                http_response_code(304);
                exit;
            }
        }
    }

    /**
     * Send 404 Not Found response
     */
    private function sendNotFound(): void
    {
        http_response_code(404);
        header('Content-Type: text/plain');
        echo 'Asset not found';
    }

    /**
     * Send 500 Server Error response
     */
    private function sendServerError(): void
    {
        http_response_code(500);
        header('Content-Type: text/plain');
        echo 'Server error';
    }

    /**
     * Get asset manifest for preloading critical assets
     * 
     * @return array Asset manifest with URLs and types
     */
    public function getAssetManifest(): array
    {
        // Use relative paths for stage independence
        $baseUrl = '/assets';
        
        return [
            'css' => [
                'style' => $baseUrl . '/css/style.css',
                'error' => $baseUrl . '/css/error.css'
            ],
            'js' => [
                'main' => $baseUrl . '/js/main.js',
                'csrf' => $baseUrl . '/js/csrf.js'
            ],
            'images' => [
                'favicon' => $baseUrl . '/img/favicon.png',
                'background' => $baseUrl . '/img/backgroundMain.avif',
                'characters' => [
                    'human' => $baseUrl . '/img/human.avif',
                    'cyborg' => $baseUrl . '/img/cyborg.avif',
                    'mutant' => $baseUrl . '/img/mutant.avif',
                    'guard' => $baseUrl . '/img/guard.avif',
                    'sentry' => $baseUrl . '/img/sentry.avif',
                    'shade' => $baseUrl . '/img/shade.avif',
                    'soldier' => $baseUrl . '/img/soldier.avif',
                    'spy' => $baseUrl . '/img/spy.avif',
                    'worker' => $baseUrl . '/img/worker.avif'
                ]
            ]
        ];
    }
}
