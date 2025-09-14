<?php
namespace StellarDominion\Services\Upload;

use StellarDominion\Services\FileManager\FileManagerFactory;
use StellarDominion\Services\FileManager\FileValidator;

/**
 * AvatarUploader
 *
 * Encapsulates avatar upload logic so controllers can delegate file validation
 * and upload handling to a single reusable service.
 */
class AvatarUploader
{
    protected FileValidator $validator;

    public function __construct(array $validatorOptions = [])
    {
        // default options
        $defaults = [
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'avif'],
            'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/avif'],
            'max_file_size' => 10485760, // 10MB
            'min_file_size' => 1024, // 1KB
        ];

        $this->validator = new FileValidator(array_merge($defaults, $validatorOptions));
    }

    /**
     * Handle an uploaded avatar file and return the public URL for storage.
     *
     * @param array $file The $_FILES['...'] entry
     * @param string $type Short type label used in filenames/metadata (e.g. 'avatar' or 'alliance_avatar')
     * @param int $ownerId Numeric id of the owner (user or alliance)
     * @return string|null Public URL on success, null if no file provided
     * @throws \Exception on validation or upload failure
     */
    public function uploadAvatarFromRequest(array $file, string $type, int $ownerId): ?string
    {
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $validation = $this->validator->validateUploadedFile($file);
        if (!$validation['valid']) {
            throw new \Exception($validation['error'] ?? 'Invalid file');
        }

        $fileManager = FileManagerFactory::createFromEnvironment();

        $safeFilename = $this->validator->generateSafeFilename($file['name'], $type, $ownerId);
        $destinationPath = 'avatars/' . $safeFilename;

        $uploadOptions = [
            'content_type' => $file['type'] ?? null,
            'metadata' => [
                'owner_type' => $type,
                'owner_id' => (string)$ownerId,
                'original_name' => $file['name'] ?? '',
                'upload_time' => date('Y-m-d H:i:s'),
            ],
        ];

        $tmpPath = $file['tmp_name'] ?? null;
        if (!$tmpPath || !is_readable($tmpPath)) {
            throw new \Exception('Uploaded file is not readable.');
        }

        if (!$fileManager->upload($tmpPath, $destinationPath, $uploadOptions)) {
            throw new \Exception('Failed to upload file.');
        }

        return $fileManager->getUrl($destinationPath);
    }
}
