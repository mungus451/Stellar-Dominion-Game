<?php
// template/pages/privacy.php
// Public Privacy Policy page using the same styling/layout as landing.php.

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$page_title  = 'Privacy Policy';
$active_page = 'privacy.php';
include_once __DIR__ . '/../includes/public_header.php';
?>
<main class="container mx-auto px-6 pt-24 pb-12">
  <section class="flex items-start justify-center">
    <div class="w-full max-w-4xl">
      <div class="content-box rounded-lg p-6 md:p-8 shadow-2xl border border-cyan-400/20 bg-dark-translucent backdrop-blur">
        <h1 class="text-3xl font-title font-bold tracking-wider text-shadow-glow text-white mb-2">Privacy Policy</h1>
        <p class="opacity-80 mb-6 text-sm">Last Updated: October 10, 2025</p>

        <div class="prose prose-invert prose-p:leading-relaxed prose-li:leading-relaxed max-w-none">
            <p>
              This is the Privacy Policy for
              <a href="https://www.starlightdominion.com" target="_blank" rel="noopener" class="text-cyan-400 underline">https://www.starlightdominion.com</a>
            </p>

            <h2 class="text-xl font-semibold mt-6 mb-2">1. Introduction</h2>
            <p>
              Welcome to Starlight Dominion ("we," "us," or "our"). We respect your privacy and are committed to protecting your personal data.
              This privacy policy will inform you as to how we look after your personal data when you visit our website,
              <span class="font-medium">www.starlightdominion.com</span> (regardless of where you visit it from), and tell you about your privacy rights and how the law protects you.
            </p>

            <h2 class="text-xl font-semibold mt-6 mb-2">2. Information We Collect</h2>
            <p>
              We may collect, use, store, and transfer different kinds of personal data about you which we have grouped together as follows:
            </p>
            <ul class="list-disc ml-6 space-y-2">
              <li><span class="font-medium">Identity Data:</span> Includes first name, last name, username, or similar identifier.</li>
              <li><span class="font-medium">Contact Data:</span> Includes billing address, email address, and telephone numbers.</li>
              <li><span class="font-medium">Technical Data:</span> Includes internet protocol (IP) address, your login data, browser type and version, time zone setting and location, browser plug-in types and versions, operating system and platform, and other technology on the devices you use to access this website.</li>
              <li><span class="font-medium">Usage Data:</span> Includes information about how you use our website, products, and services.</li>
            </ul>
            <p class="mt-3">
              We collect this data through direct interactions (e.g., when you fill out a form) and automated technologies like cookies.
            </p>

            <h2 class="text-xl font-semibold mt-6 mb-2">3. How We Use Your Information</h2>
            <p>We use the information we collect to:</p>
            <ul class="list-disc ml-6 space-y-2">
              <li>Operate and maintain our website.</li>
              <li>Improve, personalize, and expand our website.</li>
              <li>Understand and analyze how you use our website.</li>
              <li>Communicate with you, either directly or through one of our partners, including for customer service, to provide you with updates and other information relating to the website, and for marketing and promotional purposes.</li>
              <li>Send you emails.</li>
              <li>Find and prevent fraud.</li>
            </ul>

            <h2 class="text-xl font-semibold mt-6 mb-2">4. Cookies and Web Beacons</h2>
            <p>
              Like any other website,
              <a href="https://www.starlightdominion.com" target="_blank" rel="noopener" class="text-cyan-400 underline">https://www.starlightdominion.com</a>
              uses 'cookies'. These cookies are used to store information including visitors' preferences, and the pages on the website that the visitor accessed or visited.
              The information is used to optimize the users' experience by customizing our web page content based on visitors' browser type and/or other information.
            </p>

            <h2 class="text-xl font-semibold mt-6 mb-2">5. Google AdSense &amp; The DoubleClick DART Cookie</h2>
            <p>
              We use Google AdSense to serve advertising on our website. Google is a third-party vendor that uses cookies to serve ads on
              <a href="https://www.starlightdominion.com" target="_blank" rel="noopener" class="text-cyan-400 underline">https://www.starlightdominion.com</a>.
              Third-party vendors, including Google, use cookies to serve ads based on a user's prior visits to our website or other websites.
              Google's use of advertising cookies enables it and its partners to serve ads to our users based on their visit to our site and/or other sites on the Internet.
              The cookie used is the DoubleClick DART cookie. Google's use of the DART cookie enables it to serve ads to our users based on their visit to our site and other sites on the Internet.
            </p>
            <ul class="list-disc ml-6 space-y-2">
              <li>Users may opt out of personalized advertising by visiting <a href="https://adssettings.google.com/" target="_blank" rel="noopener" class="text-cyan-400 underline">Google's Ad Settings</a>.</li>
              <li>Alternatively, opt out of third-party vendors' personalized ads via <a href="http://www.aboutads.info/choices" target="_blank" rel="noopener" class="text-cyan-400 underline">aboutads.info/choices</a>.</li>
              <li>See Google's policy: <a href="https://policies.google.com/privacy" target="_blank" rel="noopener" class="text-cyan-400 underline">Google Privacy &amp; Terms</a>.</li>
            </ul>

            <h2 class="text-xl font-semibold mt-6 mb-2">6. Your Data Protection Rights (GDPR &amp; CCPA)</h2>
            <p>Depending on your location, you may have the following rights regarding your personal data:</p>
            <ul class="list-disc ml-6 space-y-2">
              <li><span class="font-medium">The right to access</span> – request copies of your personal data.</li>
              <li><span class="font-medium">The right to rectification</span> – request that we correct any information you believe is inaccurate.</li>
              <li><span class="font-medium">The right to erasure</span> – request that we erase your personal data, under certain conditions.</li>
              <li><span class="font-medium">The right to restrict processing</span> – request that we restrict the processing of your personal data, under certain conditions.</li>
              <li><span class="font-medium">The right to object to processing</span> – object to our processing of your personal data, under certain conditions.</li>
              <li><span class="font-medium">The right to data portability</span> – request that we transfer the data that we have collected to another organization, or directly to you, under certain conditions.</li>
            </ul>
            <p class="mt-3">
              If you make a request, we have one month to respond to you. If you would like to exercise any of these rights, please contact us.
            </p>

            <h2 class="text-xl font-semibold mt-6 mb-2">7. Children's Privacy</h2>
            <p>
              Our website is not intended for children under the age of 13, and we do not knowingly collect personally identifiable information from children under 13.
              If you believe that your child has provided this kind of information on our website, please contact us immediately and we will do our best efforts to promptly remove such information from our records.
            </p>

            <h2 class="text-xl font-semibold mt-6 mb-2">8. Changes to This Privacy Policy</h2>
            <p>
              We may update our Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page.
              You are advised to review this Privacy Policy periodically for any changes. Changes to this Privacy Policy are effective when they are posted on this page.
            </p>

            <h2 class="text-xl font-semibold mt-6 mb-2">9. Contact Us</h2>
            <p>
              If you have any questions or suggestions about our Privacy Policy, do not hesitate to contact us at:
            </p>
            <ul class="list-disc ml-6 space-y-2">
              <li><span class="font-medium">By Email:</span> <a href="mailto:starlightdominiongame@gmail.com" class="text-cyan-400 underline">starlightdominiongame@gmail.com</a></li>
              <li><span class="font-medium">By Visiting this Page on our Website:</span> <a href="/contact_dev.php" class="text-cyan-400 underline">Contact the Developer</a></li>
            </ul>
        </div>
      </div>
    </div>
  </section>
</main>
<?php include_once __DIR__ . '/../includes/public_footer.php'; ?>
