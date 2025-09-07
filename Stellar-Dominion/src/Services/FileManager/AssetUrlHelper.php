<?php

namespace StellarDominion\Services\FileManager;

/**
 * Asset URL Helper for CloudFront Integration
 * 
 * Provides easy access to static assets served through CloudFront.
 * Assets are automatically uploaded to S3 and served via CloudFront CDN.
 */
class AssetUrlHelper
{
    private const ASSET_BASE_URL = '/assets';

    /**
     * Get the URL for a static asset
     * 
     * @param string $assetPath Path relative to assets directory (e.g., 'css/style.css')
     * @return string Full URL to the asset
     */
    public static function getAssetUrl(string $assetPath): string
    {
        $path = ltrim($assetPath, '/');
        return self::ASSET_BASE_URL . '/' . $path;
    }

    /**
     * Get CSS file URL
     * 
     * @param string $filename CSS filename (e.g., 'style.css')
     * @return string Full URL to the CSS file
     */
    public static function getCssUrl(string $filename): string
    {
        return self::getAssetUrl('css/' . ltrim($filename, '/'));
    }

    /**
     * Get JavaScript file URL
     * 
     * @param string $filename JS filename (e.g., 'main.js')
     * @return string Full URL to the JS file
     */
    public static function getJsUrl(string $filename): string
    {
        return self::getAssetUrl('js/' . ltrim($filename, '/'));
    }

    /**
     * Get image file URL
     * 
     * @param string $filename Image filename (e.g., 'cyborg.png')
     * @return string Full URL to the image file
     */
    public static function getImageUrl(string $filename): string
    {
        return self::getAssetUrl('img/' . ltrim($filename, '/'));
    }

    /**
     * Check if CDN is enabled
     * 
     * @return bool Always false since we use relative paths for stage independence
     */
    public static function isCdnEnabled(): bool
    {
        return false;
    }

    /**
     * Get the base CDN URL
     * 
     * @return string Base URL for assets (always relative)
     */
    public static function getBaseUrl(): string
    {
        return self::ASSET_BASE_URL;
    }

    /**
     * Generate preload tags for critical assets
     * 
     * @param array $assets Array of asset paths
     * @return string HTML preload tags
     */
    public static function generatePreloadTags(array $assets): string
    {
        $tags = [];
        
        foreach ($assets as $asset) {
            $url = self::getAssetUrl($asset);
            $extension = strtolower(pathinfo($asset, PATHINFO_EXTENSION));
            
            switch ($extension) {
                case 'css':
                    $tags[] = "<link rel=\"preload\" href=\"{$url}\" as=\"style\">";
                    break;
                case 'js':
                    $tags[] = "<link rel=\"preload\" href=\"{$url}\" as=\"script\">";
                    break;
                case 'png':
                case 'jpg':
                case 'jpeg':
                case 'avif':
                case 'webp':
                    $tags[] = "<link rel=\"preload\" href=\"{$url}\" as=\"image\">";
                    break;
            }
        }
        
        return implode("\n", $tags);
    }
}
