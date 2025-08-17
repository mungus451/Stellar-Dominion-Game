<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Starlight Dominion - Commander Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">

            <!-- Navigation will be loaded via JavaScript or included statically -->
            <div id="navigation-container">
                <?php 
                // active page mannually carried over for now.
                $active_page = 'profile.php';
                include_once __DIR__ . '/../includes/navigation.php'; ?>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 p-4">
                <aside class="lg:col-span-1 space-y-4">
                    <div id="advisor-container">
                        <!-- Advisor will be loaded dynamically -->
                        <div class="content-box rounded-lg p-6">
                            <h3 class="font-title text-lg text-cyan-400 mb-4">Advisor</h3>
                            <div id="advisor-content">
                                <p class="text-gray-400">Loading...</p>
                            </div>
                        </div>
                    </div>
                </aside>
                
                <main class="lg:col-span-3 space-y-4">
                    <!-- Message containers for success/error feedback -->
                    <div id="message-container" class="hidden">
                        <div id="success-message" class="hidden bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
                        </div>
                        <div id="error-message" class="hidden bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
                        </div>
                    </div>

                    <!-- Loading indicator -->
                    <div id="loading-indicator" class="content-box rounded-lg p-6 text-center">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-cyan-400 mx-auto"></div>
                        <p class="mt-2 text-gray-400">Loading profile...</p>
                    </div>

                    <!-- Profile form container -->
                    <div id="profile-container" class="hidden content-box rounded-lg p-6">
                        <h1 class="font-title text-2xl text-cyan-400 mb-4 border-b border-gray-600 pb-2">My Profile</h1>
                        
                        <form id="profile-form" enctype="multipart/form-data">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Left Column: Avatar -->
                                <div class="space-y-4">
                                    <div>
                                        <h3 class="font-title text-lg text-white">Current Avatar</h3>
                                        <div class="mt-2 flex justify-center">
                                            <img id="current-avatar" src="" alt="Current Avatar" class="w-32 h-32 rounded-full object-cover border-2 border-gray-600">
                                        </div>
                                    </div>
                                    <div>
                                        <h3 class="font-title text-lg text-white">New Avatar</h3>
                                        <p class="text-xs text-gray-500 mb-2">Limits: 10MB, JPG/PNG</p>
                                        <input type="file" name="avatar" id="avatar" accept="image/*" class="w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gray-700 file:text-cyan-300 hover:file:bg-gray-600">
                                        <div id="avatar-preview" class="mt-2 hidden">
                                            <img id="preview-image" src="" alt="Preview" class="w-24 h-24 rounded-full object-cover border-2 border-gray-600">
                                        </div>
                                    </div>
                                </div>
                                <!-- Right Column: Biography -->
                                <div>
                                    <h3 class="font-title text-lg text-white">Profile Biography</h3>
                                    <textarea id="biography" name="biography" rows="8" class="mt-2 w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-1 focus:ring-cyan-500" placeholder="Tell other commanders about yourself..."></textarea>
                                </div>
                            </div>

                            <div class="mt-6 text-right">
                                <button type="submit" id="save-button" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span id="save-text">Save Profile</span>
                                    <span id="save-spinner" class="hidden">
                                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Saving...
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </main>
            </div>

        </div>
    </div>

    <!-- CSRF Token for API requests -->
    <script>
        // Get CSRF token from API
        let csrfToken = '';
        
        // Profile API client
        class ProfileAPI {
            constructor() {
                this.baseUrl = '/api/profile';
                this.init();
            }
            
            async init() {
                await this.fetchCSRFToken();
                await this.loadProfile();
                this.bindEvents();
            }
            
            async fetchCSRFToken() {
                try {
                    const response = await fetch('/api/csrf-token.php');
                    const data = await response.json();
                    csrfToken = data.token;
                } catch (error) {
                    console.error('Failed to fetch CSRF token:', error);
                }
            }
            
            async loadProfile() {
                try {
                    const response = await fetch(this.baseUrl, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-Token': csrfToken
                        }
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    const data = await response.json();
                    this.populateProfile(data);
                    
                } catch (error) {
                    console.error('Failed to load profile:', error);
                    this.showError('Failed to load profile data. Please refresh the page.');
                } finally {
                    this.hideLoading();
                }
            }
            
            populateProfile(data) {
                // Populate form fields
                document.getElementById('biography').value = data.biography || '';
                document.getElementById('current-avatar').src = data.avatar_url;
                
                // Update advisor with user stats
                this.updateAdvisor(data);
                
                // Show the profile container
                document.getElementById('profile-container').classList.remove('hidden');
            }
            
            updateAdvisor(data) {
                const advisorContent = document.getElementById('advisor-content');
                advisorContent.innerHTML = `
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span>Character:</span>
                            <span class="text-cyan-400">${data.character_name}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Level:</span>
                            <span class="text-cyan-400">${data.level}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Experience:</span>
                            <span class="text-cyan-400">${data.experience}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Credits:</span>
                            <span class="text-cyan-400">${data.credits.toLocaleString()}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Citizens:</span>
                            <span class="text-cyan-400">${data.untrained_citizens}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Attack Turns:</span>
                            <span class="text-cyan-400">${data.attack_turns}</span>
                        </div>
                        <div class="mt-4 pt-2 border-t border-gray-600">
                            <div class="text-sm">
                                <span>Next Turn:</span>
                                <span class="text-cyan-400">${data.timer.minutes_until_next_turn}m ${data.timer.seconds_remainder}s</span>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            bindEvents() {
                // Avatar preview
                document.getElementById('avatar').addEventListener('change', this.handleAvatarPreview.bind(this));
                
                // Form submission
                document.getElementById('profile-form').addEventListener('submit', this.handleSubmit.bind(this));
            }
            
            handleAvatarPreview(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        document.getElementById('preview-image').src = e.target.result;
                        document.getElementById('avatar-preview').classList.remove('hidden');
                    };
                    reader.readAsDataURL(file);
                } else {
                    document.getElementById('avatar-preview').classList.add('hidden');
                }
            }
            
            async handleSubmit(event) {
                event.preventDefault();
                
                const saveButton = document.getElementById('save-button');
                const saveText = document.getElementById('save-text');
                const saveSpinner = document.getElementById('save-spinner');
                
                // Show loading state
                saveButton.disabled = true;
                saveText.classList.add('hidden');
                saveSpinner.classList.remove('hidden');
                
                try {
                    const formData = new FormData();
                    formData.append('biography', document.getElementById('biography').value);
                    
                    const avatarFile = document.getElementById('avatar').files[0];
                    if (avatarFile) {
                        formData.append('avatar', avatarFile);
                    }
                    
                    const response = await fetch(this.baseUrl, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-CSRF-Token': csrfToken
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (response.ok) {
                        this.showSuccess(data.message || 'Profile updated successfully!');
                        
                        // Update the current avatar if a new one was uploaded
                        if (data.avatar_url) {
                            document.getElementById('current-avatar').src = data.avatar_url;
                        }
                        
                        // Update advisor with new data
                        this.updateAdvisor(data);
                        
                        // Clear the file input and preview
                        document.getElementById('avatar').value = '';
                        document.getElementById('avatar-preview').classList.add('hidden');
                        
                    } else {
                        this.showError(data.error || 'Failed to update profile');
                    }
                    
                } catch (error) {
                    console.error('Profile update error:', error);
                    this.showError('Failed to update profile. Please try again.');
                } finally {
                    // Reset button state
                    saveButton.disabled = false;
                    saveText.classList.remove('hidden');
                    saveSpinner.classList.add('hidden');
                }
            }
            
            showSuccess(message) {
                const container = document.getElementById('message-container');
                const successMsg = document.getElementById('success-message');
                const errorMsg = document.getElementById('error-message');
                
                errorMsg.classList.add('hidden');
                successMsg.textContent = message;
                successMsg.classList.remove('hidden');
                container.classList.remove('hidden');
                
                // Auto-hide after 5 seconds
                setTimeout(() => {
                    container.classList.add('hidden');
                }, 5000);
            }
            
            showError(message) {
                const container = document.getElementById('message-container');
                const successMsg = document.getElementById('success-message');
                const errorMsg = document.getElementById('error-message');
                
                successMsg.classList.add('hidden');
                errorMsg.textContent = message;
                errorMsg.classList.remove('hidden');
                container.classList.remove('hidden');
            }
            
            hideLoading() {
                document.getElementById('loading-indicator').classList.add('hidden');
            }
        }
        
        // Initialize the profile API when the page loads
        document.addEventListener('DOMContentLoaded', () => {
            new ProfileAPI();
        });
    </script>
</body>
</html>
