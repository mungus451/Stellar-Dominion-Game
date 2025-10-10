<?php
// template/pages/terms.php
// Public Terms & Conditions page using the same styling/layout as landing.php.

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$page_title  = 'Terms and Conditions';
$active_page = 'terms.php';
include_once __DIR__ . '/../includes/public_header.php';
?>
<main class="container mx-auto px-6 pt-24 pb-12">
  <section class="flex items-start justify-center">
    <div class="w-full max-w-4xl">
      <div class="content-box rounded-lg p-6 md:p-8 shadow-2xl border border-cyan-400/20 bg-dark-translucent backdrop-blur">
        <h1 class="text-3xl font-title font-bold tracking-wider text-shadow-glow text-white mb-2">Terms and Conditions</h1>
        <!-- Updated Date -->
        <p class="opacity-80 mb-6 text-sm">Last Updated: October 10, 2025</p>

        <div class="prose prose-invert prose-p:leading-relaxed prose-li:leading-relaxed max-w-none">
            <p>
              These are the Terms and Conditions for
              <a href="https://www.starlightdominion.com" class="text-cyan-400 underline">https://www.starlightdominion.com</a>
            </p>

            <h2 class="text-xl font-semibold mt-6 mb-2">1. Agreement to Terms</h2>
            <p>
              Welcome to
              <a href="https://www.starlightdominion.com" class="text-cyan-400 underline">https://www.starlightdominion.com</a>
              (the "Site"). These Terms and Conditions constitute a legally binding agreement made between you, whether personally or on behalf of an entity (“you”) and Starlight Dominion ("we," "us," or "our"), concerning your access to and use of the Site.
            </p>
            <p>
              By accessing the Site, you agree to be bound by these Terms and Conditions. If you do not agree with all of these terms, you are expressly prohibited from using the Site and must discontinue use immediately.
            </p>
            <p>
              We reserve the right, in our sole discretion, to make changes or modifications to these Terms and Conditions at any time and for any reason. We will alert you about any changes by updating the “Last Updated” date of these Terms and Conditions. It is your responsibility to periodically review these Terms and Conditions to stay informed of updates.
            </p>

            <h2 class="text-xl font-semibold mt-6 mb-2">2. Intellectual Property Rights</h2>
            <p>
              Unless otherwise indicated, the Site is our proprietary property. All source code, databases, functionality, software, website designs, audio, video, text, photographs, and graphics on the Site (collectively, the “Content”) and the trademarks, service marks, and logos contained therein (the “Marks”) are owned or controlled by us or licensed to us, and are protected by copyright and trademark laws.
              The Content and the Marks are provided on the Site “AS IS” for your information and personal use only. Except as expressly provided in these Terms and Conditions, no part of the Site and no Content or Marks may be copied, reproduced, aggregated, republished, uploaded, posted, publicly displayed, encoded, translated, transmitted, distributed, sold, licensed, or otherwise exploited for any commercial purpose whatsoever, without our express prior written permission.
            </p>

            <h2 class="text-xl font-semibold mt-6 mb-2">3. User Representations</h2>
            <p>By using the Site, you represent and warrant that:</p>
            <ul class="list-disc ml-6 space-y-2">
              <li>(1) you have the legal capacity and you agree to comply with these Terms and Conditions;</li>
              <li>(2) you are not a minor in the jurisdiction in which you reside;</li>
              <li>(3) you will not access the Site through automated or non-human means, whether through a bot, script, or otherwise;</li>
              <li>(4) you will not use the Site for any illegal or unauthorized purpose; and</li>
              <li>(5) your use of the Site will not violate any applicable law or regulation.</li>
            </ul>

            <h2 class="text-xl font-semibold mt-6 mb-2">4. Prohibited Activities</h2>
            <p>
              You may not access or use the Site for any purpose other than that for which we make the Site available. The Site may not be used in connection with any commercial endeavors except those that are specifically endorsed or approved by us.
            </p>
            <p>As a user of the Site, you agree not to:</p>
            <ul class="list-disc ml-6 space-y-2">
              <li>Systematically retrieve data or other content from the Site to create or compile, directly or indirectly, a collection, compilation, database, or directory without written permission from us.</li>
              <li>Make any unauthorized use of the Site, including collecting usernames and/or email addresses of users by electronic or other means for the purpose of sending unsolicited email.</li>
              <li>Use the Site to advertise or offer to sell goods and services, including participating in or generating revenue through advertising programs like Google AdSense in a fraudulent manner.</li>
              <li>Circumvent, disable, or otherwise interfere with security-related features of the Site.</li>
              <li>Engage in unauthorized framing of or linking to the Site.</li>
              <li>Trick, defraud, or mislead us and other users, especially in any attempt to learn sensitive account information such as user passwords.</li>
              <li>Use any information obtained from the Site in order to harass, abuse, or harm another person.</li>
              <li>Use the Site as part of any effort to compete with us or otherwise use the Site and/or the Content for any revenue-generating endeavor or commercial enterprise.</li>
              <li>Upload or transmit (or attempt to upload or to transmit) viruses, Trojan horses, or other material that interferes with any party’s uninterrupted use and enjoyment of the Site.</li>
              <li>Harass, annoy, intimidate, or threaten any of our employees or agents engaged in providing any portion of the Site to you.</li>
            </ul>

            <h2 class="text-xl font-semibold mt-6 mb-2">5. Third-Party Websites and Content</h2>
            <p>
              The Site contains (or you may be sent via the Site) links to other websites ("Third-Party Websites") as well as articles, photographs, text, graphics, pictures, designs, music, sound, video, information, applications, software, and other content or items belonging to or originating from third parties ("Third-Party Content"). This includes advertisements served by third-party networks such as Google AdSense.
            </p>
            <p>
              Such Third-Party Websites and Third-Party Content are not investigated, monitored, or checked for accuracy, appropriateness, or completeness by us, and we are not responsible for any Third-Party Websites accessed through the Site or any Third-Party Content posted on, available through, or installed from the Site. Inclusion of, linking to, or permitting the use or installation of any Third-Party Websites or any Third-Party Content does not imply approval or endorsement thereof by us. If you decide to leave the Site and access the Third-Party Websites or to use or install any Third-Party Content, you do so at your own risk.
            </p>

            <h2 class="text-xl font-semibold mt-6 mb-2">6. Site Management</h2>
            <p>
              We reserve the right, but not the obligation, to: (1) monitor the Site for violations of these Terms and Conditions; (2) take appropriate legal action against anyone who, in our sole discretion, violates the law or these Terms and Conditions; (3) in our sole discretion and without limitation, refuse, restrict access to, limit the availability of, or disable any of your contributions or any portion thereof; (4) otherwise manage the Site in a manner designed to protect our rights and property and to facilitate the proper functioning of the Site.
            </p>

            <h2 class="text-xl font-semibold mt-6 mb-2">7. Privacy Policy</h2>
            <p>
              We care about data privacy and security. Please review our
              <a href="/privacy.php" class="text-cyan-400 underline">Privacy Policy</a>.
              By using the Site, you agree to be bound by our Privacy Policy, which is incorporated into these Terms and Conditions.
            </p>

            <h2 class="text-xl font-semibold mt-6 mb-2">8. Term and Termination</h2>
            <p>
              These Terms and Conditions shall remain in full force and effect while you use the Site. WITHOUT LIMITING ANY OTHER PROVISION OF THESE TERMS AND CONDITIONS, WE RESERVE THE RIGHT TO, IN OUR SOLE DISCRETION AND WITHOUT NOTICE OR LIABILITY, DENY ACCESS TO AND USE OF THE SITE (INCLUDING BLOCKING CERTAIN IP ADDRESSES), TO ANY PERSON FOR ANY REASON OR FOR NO REASON.
            </p>

            <h2 class="text-xl font-semibold mt-6 mb-2">9. Modifications and Interruptions</h2>
            <p>
              We reserve the right to change, modify, or remove the contents of the Site at any time or for any reason at our sole discretion without notice. We also reserve the right to modify or discontinue all or part of the Site without notice at any time. We will not be liable to you or any third party for any modification, price change, suspension, or discontinuance of the Site.
            </p>
            <p>
              We cannot guarantee the Site will be available at all times. We may experience hardware, software, or other problems or need to perform maintenance related to the Site, resulting in interruptions, delays, or errors.
            </p>

            <h2 class="text-xl font-semibold mt-6 mb-2">10. Governing Law</h2>
            <p>
              These Terms and Conditions and your use of the Site are governed by and construed in accordance with the laws of the State of Florida applicable to agreements made and to be entirely performed within the State of Florida, without regard to its conflict of law principles.
            </p>

            <h2 class="text-xl font-semibold mt-6 mb-2">11. Disclaimer</h2>
            <p>
              THE SITE IS PROVIDED ON AN AS-IS AND AS-AVAILABLE BASIS. YOU AGREE THAT YOUR USE OF THE SITE AND OUR SERVICES WILL BE AT YOUR SOLE RISK. TO THE FULLEST EXTENT PERMITTED BY LAW, WE DISCLAIM ALL WARRANTIES, EXPRESS OR IMPLIED, IN CONNECTION WITH THE SITE AND YOUR USE THEREOF, INCLUDING, WITHOUT LIMITATION, THE IMPLIED WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, AND NON-INFRINGEMENT. WE MAKE NO WARRANTIES OR REPRESENTATIONS ABOUT THE ACCURACY OR COMPLETENESS OF THE SITE’S CONTENT OR THE CONTENT OF ANY WEBSITES LINKED TO THE SITE AND WE WILL ASSUME NO LIABILITY OR RESPONSIBILITY FOR ANY (1) ERRORS, MISTAKES, OR INACCURACIES OF CONTENT AND MATERIALS, (2) PERSONAL INJURY OR PROPERTY DAMAGE, OF ANY NATURE WHATSOEVER, RESULTING FROM YOUR ACCESS TO AND USE OF THE SITE, (3) ANY UNAUTHORIZED ACCESS TO OR USE OF OUR SECURE SERVERS AND/OR ANY AND ALL PERSONAL INFORMATION AND/OR FINANCIAL INFORMATION STORED THEREIN, (4) ANY INTERRUPTION OR CESSATION OF TRANSMISSION TO OR FROM THE SITE, (5) ANY BUGS, VIRUSES, TROJAN HORSES, OR THE LIKE WHICH MAY BE TRANSMITTED TO OR THROUGH THE SITE BY ANY THIRD PARTY, AND/OR (6) ANY ERRORS OR OMISSIONS IN ANY CONTENT AND MATERIALS OR FOR ANY LOSS OR DAMAGE OF ANY KIND INCURRED AS A RESULT OF THE USE OF ANY CONTENT POSTED, TRANSMITTED, OR OTHERWISE MADE AVAILABLE VIA THE SITE.
            </p>

            <h2 class="text-xl font-semibold mt-6 mb-2">12. Limitation of Liability</h2>
            <p>
              IN NO EVENT WILL WE OR OUR DIRECTORS, EMPLOYEES, OR AGENTS BE LIABLE TO YOU OR ANY THIRD PARTY FOR ANY DIRECT, INDIRECT, CONSEQUENTIAL, EXEMPLARY, INCIDENTAL, SPECIAL, OR PUNITIVE DAMAGES, INCLUDING LOST PROFIT, LOST REVENUE, LOSS OF DATA, OR OTHER DAMAGES ARISING FROM YOUR USE OF THE SITE, EVEN IF WE HAVE BEEN ADVISED OF THE POSSIBILITY OF SUCH DAMAGES.
            </p>

            <h2 class="text-xl font-semibold mt-6 mb-2">13. Indemnification</h2>
            <p>
              You agree to defend, indemnify, and hold us harmless, including our subsidiaries, affiliates, and all of our respective officers, agents, partners, and employees, from and against any loss, damage, liability, claim, or demand, including reasonable attorneys’ fees and expenses, made by any third party due to or arising out of: (1) your use of the Site; (2) your breach of these Terms and Conditions; (3) any breach of your representations and warranties set forth in these Terms and Conditions; or (4) your violation of the rights of a third party, including but not limited to intellectual property rights.
            </p>

            <h2 class="text-xl font-semibold mt-6 mb-2">14. Contact Us</h2>
            <p>In order to resolve a complaint regarding the Site or to receive further information regarding use of the Site, please contact us at:</p>
            <ul class="list-disc ml-6 space-y-2">
              <li><span class="font-medium">Starlight Dominion</span></li>
              <li><span class="font-medium">Email:</span> <a href="mailto:starlightdominiongame@gmail.com" class="text-cyan-400 underline">starlightdominiongame@gmail.com</a></li>
            </ul>
        </div>
      </div>
    </div>
  </section>
</main>
<?php include_once __DIR__ . '/../includes/public_footer.php'; ?>
