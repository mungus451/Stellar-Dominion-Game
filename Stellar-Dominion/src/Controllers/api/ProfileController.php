<?php
/**
 * src/Controllers/api/ProfileController.php
 *
 * API Controller for Profile management that combines the logic from:
 * - template/pages/profile.php (data fetching and display logic)
 * - src/Controllers/ProfileController.php (form submission logic)
 *
 * This controller handles both GET and POST requests and returns JSON responses
 * suitable for AJAX/SPA applications.
 */

namespace StellarDominion\Controllers\Api;

use StellarDominion\Core\BaseController;
use StellarDominion\Security\RequiresCSRF;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Exception;
use DateTime;
use DateTimeZone;

class ProfileController extends BaseController
{
    /**
     * Handle the incoming request based on HTTP method
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $method = $request->getMethod();
        
        switch ($method) {
            case 'GET':
                return $this->getProfile($request);
            case 'POST':
                return $this->updateProfile($request);
            default:
                return $this->createErrorResponse(
                    'Method not allowed',
                    null,
                    405
                );
        }
    }
    
    /**
     * GET /api/profile - Fetch user profile data
     */
    private function getProfile(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $userId = $this->getCurrentUserId();
            
            if (!$userId) {
                return $this->createErrorResponse(
                    'User not authenticated',
                    null,
                    401
                );
            }
            
            // Fetch user profile data (combining logic from template/pages/profile.php)
            $profileData = $this->fetchUserProfileData($userId);
            
            if (!$profileData) {
                return $this->createErrorResponse(
                    'Profile not found',
                    null,
                    404
                );
            }
            
            // Calculate timer data for next turn
            $timerData = $this->calculateTimerData($profileData['last_updated']);
            
            // Combine profile and timer data
            $responseData = array_merge($profileData, [
                'timer' => $timerData,
                'avatar_url' => $profileData['avatar_path'] ?? '/assets/img/default_alliance.avif'
            ]);
            
            return $this->createJsonResponse($responseData);
            
        } catch (Exception $e) {
            $this->logger->error('Profile fetch error: ' . $e->getMessage());
            return $this->createErrorResponse(
                'Failed to fetch profile data',
                null,
                500
            );
        }
    }
    
    /**
     * POST /api/profile - Update user profile
     */
    #[RequiresCSRF(methods: ['POST', 'PUT'], message: 'Security validation failed. Please refresh the page and try again.')]
    private function updateProfile(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $userId = $this->getCurrentUserId();
            
            if (!$userId) {
                return $this->createErrorResponse(
                    'User not authenticated',
                    null,
                    401
                );
            }
            
            // Get form data
            $parsedBody = $request->getParsedBody();
            $uploadedFiles = $request->getUploadedFiles();
            
            $biography = trim($parsedBody['biography'] ?? '');
            $avatarPath = null;
            
            // Handle avatar upload if present
            if (isset($uploadedFiles['avatar']) && $uploadedFiles['avatar']->getError() === UPLOAD_ERR_OK) {
                $uploadResult = $this->handleAvatarUpload($uploadedFiles['avatar'], $userId);
                
                if ($uploadResult['success']) {
                    $avatarPath = $uploadResult['path'];
                } else {
                    return $this->createErrorResponse(
                        $uploadResult['error'],
                        null,
                        400
                    );
                }
            }
            
            // Update database
            $this->updateUserProfile($userId, $biography, $avatarPath);
            
            // Fetch updated profile data to return
            $updatedProfile = $this->fetchUserProfileData($userId);
            $timerData = $this->calculateTimerData($updatedProfile['last_updated']);
            
            $responseData = array_merge($updatedProfile, [
                'timer' => $timerData,
                'avatar_url' => $updatedProfile['avatar_path'] ?? '/assets/img/default_alliance.avif',
                'message' => 'Profile updated successfully!'
            ]);
            
            return $this->createJsonResponse($responseData);
            
        } catch (Exception $e) {
            $this->logger->error('Profile update error: ' . $e->getMessage());
            return $this->createErrorResponse(
                'Failed to update profile: ' . $e->getMessage(),
                null,
                500
            );
        }
    }
    
    /**
     * Fetch user profile data from database
     */
    private function fetchUserProfileData(int $userId): ?array
    {
        // Using ORM approach with fallback to legacy database
        return $this->withORMFallback(
            function() use ($userId) {
                // ORM approach (when available)
                if ($this->isORMAvailable()) {
                    $user = $this->findEntityById('User', $userId);
                    if ($user) {
                        return [
                            'character_name' => $user->getCharacterName(),
                            'email' => $user->getEmail(),
                            'biography' => $user->getBiography(),
                            'avatar_path' => $user->getAvatarPath(),
                            'credits' => $user->getCredits(),
                            'untrained_citizens' => $user->getUntrainedCitizens(),
                            'level' => $user->getLevel(),
                            'experience' => $user->getExperience(),
                            'attack_turns' => $user->getAttackTurns(),
                            'last_updated' => $user->getLastUpdated()
                        ];
                    }
                }
                return null;
            },
            function() use ($userId) {
                // Legacy database approach
                $sql = "SELECT character_name, email, biography, avatar_path, credits, 
                              untrained_citizens, level, experience, attack_turns, last_updated 
                        FROM users WHERE id = ?";
                
                $stmt = mysqli_prepare($this->db, $sql);
                mysqli_stmt_bind_param($stmt, "i", $userId);
                mysqli_stmt_execute($stmt);
                
                $result = mysqli_stmt_get_result($stmt);
                $data = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);
                
                return $data ?: null;
            }
        );
    }
    
    /**
     * Calculate timer data for next turn
     */
    private function calculateTimerData(string $lastUpdated): array
    {
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $turnIntervalMinutes = 10;
        $lastUpdatedTime = new DateTime($lastUpdated, new DateTimeZone('UTC'));
        
        $secondsUntilNextTurn = ($turnIntervalMinutes * 60) - 
                               (($now->getTimestamp() - $lastUpdatedTime->getTimestamp()) % ($turnIntervalMinutes * 60));
        
        $minutesUntilNextTurn = floor($secondsUntilNextTurn / 60);
        $secondsRemainder = $secondsUntilNextTurn % 60;
        
        return [
            'minutes_until_next_turn' => $minutesUntilNextTurn,
            'seconds_remainder' => $secondsRemainder,
            'total_seconds_until_next_turn' => $secondsUntilNextTurn
        ];
    }
    
    /**
     * Handle avatar file upload
     */
    private function handleAvatarUpload($uploadedFile, int $userId): array
    {
        try {
            // Validate file size (10MB limit)
            if ($uploadedFile->getSize() > 10000000) {
                return ['success' => false, 'error' => 'File is too large. Maximum size is 10MB.'];
            }
            
            // Get file info
            $clientFilename = $uploadedFile->getClientFilename();
            $fileExtension = strtolower(pathinfo($clientFilename, PATHINFO_EXTENSION));
            
            // Validate file type
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($fileExtension, $allowedExtensions)) {
                return ['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.'];
            }
            
            // Create upload directory
            $uploadDir = __DIR__ . '/../../../public/uploads/avatars/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    return ['success' => false, 'error' => 'Could not create avatar directory.'];
                }
            }
            
            // Check if directory is writable
            if (!is_writable($uploadDir)) {
                return ['success' => false, 'error' => 'Avatar directory is not writable.'];
            }
            
            // Generate unique filename
            $newFilename = 'user_avatar_' . $userId . '_' . time() . '.' . $fileExtension;
            $destination = $uploadDir . $newFilename;
            
            // Move uploaded file
            $uploadedFile->moveTo($destination);
            
            // Return web-accessible path
            $webPath = '/uploads/avatars/' . $newFilename;
            return ['success' => true, 'path' => $webPath];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Upload failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Update user profile in database
     */
    private function updateUserProfile(int $userId, string $biography, ?string $avatarPath): void
    {
        // Use transaction with ORM fallback
        $this->executeTransaction(function() use ($userId, $biography, $avatarPath) {
            
            // Try ORM approach first
            if ($this->isORMAvailable()) {
                $user = $this->findEntityById('User', $userId);
                if ($user) {
                    $user->setBiography($biography);
                    if ($avatarPath !== null) {
                        $user->setAvatarPath($avatarPath);
                    }
                    $this->persistEntity($user);
                    return;
                }
            }
            
            // Fallback to legacy database
            if ($avatarPath !== null) {
                // Update both biography and avatar
                $sql = "UPDATE users SET biography = ?, avatar_path = ? WHERE id = ?";
                $stmt = mysqli_prepare($this->db, $sql);
                mysqli_stmt_bind_param($stmt, "ssi", $biography, $avatarPath, $userId);
            } else {
                // Update only biography
                $sql = "UPDATE users SET biography = ? WHERE id = ?";
                $stmt = mysqli_prepare($this->db, $sql);
                mysqli_stmt_bind_param($stmt, "si", $biography, $userId);
            }
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to update profile in database');
            }
            
            mysqli_stmt_close($stmt);
        });
    }
}
