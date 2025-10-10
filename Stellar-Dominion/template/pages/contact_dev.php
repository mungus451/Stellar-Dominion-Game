<?php
// template/pages/contact_dev.php

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token(): string {
        if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
        return $_SESSION['csrf_token'];
    }
}
$_SESSION['csrf_token'] = generate_csrf_token();

$page_title  = 'Contact the Developer';
$active_page = 'contact_dev.php';
include_once __DIR__ . '/../includes/public_header.php';

// The $returnTo variable is still useful for the hidden form input.
$returnTo = $_SERVER['REQUEST_URI'] ?? '/contact_dev.php';
?>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<main class="container mx-auto px-6 pt-24">
  <section id="contact" class="min-h-[calc(100vh-6rem)] flex items-start justify-center">
    <div class="w-full max-w-3xl">
      <div 
        x-data="{
          status: 'idle',
          responseMessage: '',
          submitForm(event) {
            this.status = 'submitting';
            this.responseMessage = '';

            const form = event.target;
            const formData = new FormData(form);

            fetch(form.action, {
              method: form.method,
              body: formData,
              headers: {
                'Accept': 'application/json',
              }
            })
            .then(response => {
              if (!response.ok) {
                // Try to get error from JSON response, otherwise use status text
                return response.json().then(err => { throw new Error(err.message || response.statusText); });
              }
              return response.json();
            })
            .then(data => {
              this.responseMessage = data.message;
              if (data.status === 'success') {
                this.status = 'success';
                form.reset(); // Clear the form fields on success
              } else {
                this.status = 'error';
              }
            })
            .catch(error => {
              this.status = 'error';
              this.responseMessage = error.message || 'An unexpected error occurred. Please try again.';
            });
          }
        }"
        class="content-box rounded-lg p-6 shadow-2xl border border-cyan-400/20 bg-dark-translucent backdrop-blur"
      >
        <h1 class="text-3xl md:text-4xl font-title font-bold tracking-wider text-shadow-glow text-white mb-2">
          Contact the Developer
        </h1>
        <p class="text-white/80 mb-6">Have a question, bug report, or feature idea? Reach us here.</p>

        <template x-if="status !== 'idle'">
          <div 
            x-show="status === 'success'"
            x-transition
            class="bg-green-900/40 border border-green-500/50 text-green-300 p-3 rounded-md mb-4"
            x-text="responseMessage"
          ></div>
        </template>
        <template x-if="status === 'error'">
          <div 
            x-show="status === 'error'"
            x-transition
            class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md mb-4"
            x-text="responseMessage"
          ></div>
        </template>

        <div class="mb-6 text-sm">
          <ul class="list-disc ml-6 space-y-1">
            <li>Email: <a class="text-cyan-400 underline" href="mailto:starlightdominiongame@gmail.com">starlightdominiongame@gmail.com</a></li>
            <li>Discord: <a class="text-cyan-400 underline" href="https://discord.gg/sCKvuxHAqt" rel="noopener" target="_blank">Join our server</a></li>
          </ul>
        </div>

        <form action="/api/contact_dev.php" method="post" class="space-y-5" @submit.prevent="submitForm">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($returnTo); ?>">

          <div class="hidden">
            <label for="website">Website</label>
            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
          </div>

          <div>
            <label for="name" class="block text-sm font-medium mb-1 text-gray-200">Your Name (optional)</label>
            <input id="name" name="name" type="text" maxlength="80"
                   class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-shadow"
                   placeholder="Captain Nova">
          </div>

          <div>
            <label for="email" class="block text-sm font-medium mb-1 text-gray-200">Your Email (for reply) — optional</label>
            <input id="email" name="email" type="email" maxlength="191"
                   class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-shadow"
                   placeholder="you@example.com" autocomplete="off" autocapitalize="none" spellcheck="false">
          </div>

          <div>
            <label for="subject" class="block text-sm font-medium mb-1 text-gray-200">Subject</label>
            <input id="subject" name="subject" type="text" maxlength="120" required
                   class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-shadow"
                   placeholder="Short summary">
          </div>

          <div>
            <label for="message" class="block text-sm font-medium mb-1 text-gray-200">Message</label>
            <textarea id="message" name="message" rows="8" minlength="10" maxlength="8000" required
                      class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-shadow"
                      placeholder="Describe the issue, steps to reproduce, what you expected, and what happened."></textarea>
            <p class="mt-1 text-xs text-white/70">Include relevant IDs, timestamps if applicable.</p>
          </div>

          <div class="pt-2">
            <button type="submit"
                    :disabled="status === 'submitting'"
                    class="w-full md:w-auto bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 px-6 rounded-lg transition-colors disabled:bg-cyan-800 disabled:cursor-not-allowed flex items-center justify-center">
              <span x-show="status !== 'submitting'">Send Message</span>
              <span x-show="status === 'submitting'">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Sending...
              </span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </section>
</main>

<?php include_once __DIR__ . '/../includes/public_footer.php'; ?>