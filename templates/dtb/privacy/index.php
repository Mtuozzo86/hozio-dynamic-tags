<?php
/**
 * Privacy Policy — default master skeleton
 *
 * Usage: [template name="privacy/index"]
 *
 * 15-section comprehensive US privacy policy. Single template that
 * adapts automatically:
 *   - Standard service-business clients render the universal sections
 *     (introduction, contact-form data, cookies, analytics, advertising,
 *     state privacy rights, etc.).
 *   - Ecommerce clients (with WooCommerce active) additionally render
 *     payment/order/account/checkout/coupon sections.
 *
 * Branching is via `class_exists( 'WooCommerce' )` — no hozio-config
 * flag, no client-typing key. Install WooCommerce on the site, the
 * ecommerce sections appear; uninstall it, they hide. The same
 * deployed template covers both client types.
 *
 * Company-specific values come from `hozio-config.php` via `[hozio]`
 * shortcodes (client-name, client-url, company-address, company-email,
 * company-phone-1) so a new client gets a correct policy as soon as
 * those keys are filled in.
 *
 * What clients usually need to verify or customize beyond company info:
 *   - Effective date + Last Updated string (top of body section)
 *   - State-specific compliance (15.2 NY SHIELD assumes NY presence —
 *     remove or replace if the client operates elsewhere)
 *   - Specific third-party processors named (Stripe, Mailchimp,
 *     Microsoft Clarity, etc.) — leave the ones the client uses,
 *     remove the ones they don't
 *   - International / GDPR sections if the client serves outside the US
 *
 * @package DTB
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Effective + Last Updated date placeholders. Update on material policy changes.
$dtb_privacy_effective    = 'January 1, 2026';
$dtb_privacy_last_updated = 'January 1, 2026';

// Convenience flag for ecommerce-only blocks.
$dtb_is_ecommerce = class_exists( 'WooCommerce' );
?>

<!-- ═══ Hero ═══════════════════════════════════════════════════════════════ -->
<section class="hz-section hz-section--dark hz-legal-hero">
  <div class="hz-container">
    <h1 class="hz-eyebrow">Privacy Policy</h1>
    <p class="hz-display hz-legal-hero__headline">
      How we collect, use, and protect your information
    </p>
    <p class="hz-subtitle hz-legal-hero__meta">
      <span class="hz-legal-hero__meta-item"><strong>Effective:</strong> <?php echo esc_html( $dtb_privacy_effective ); ?></span><span class="hz-legal-hero__meta-sep">&nbsp;|&nbsp;</span><span class="hz-legal-hero__meta-item"><strong>Last updated:</strong> <?php echo esc_html( $dtb_privacy_last_updated ); ?></span>
    </p>
  </div>
</section>

<!-- Policy body -->
<section class="hz-section">
  <div class="hz-container">
    <div class="hz-legal">

      <!-- Table of Contents -->
      <nav class="hz-legal-toc" aria-label="Table of Contents">
        <h2 class="hz-legal-toc__title">Table of Contents</h2>
        <ol class="hz-legal-toc__list">
          <li><a href="#section-1">Introduction</a></li>
          <li><a href="#section-2">Information We Collect</a></li>
          <li><a href="#section-3">How We Use Your Information</a></li>
          <li><a href="#section-4">Cookies and Tracking Technologies</a></li>
          <li><a href="#section-5">How We Share Your Information</a></li>
          <li><a href="#section-6">Do Not Sell or Share My Personal Information</a></li>
          <li><a href="#section-7">Data Retention</a></li>
          <li><a href="#section-8">Data Security</a></li>
          <li><a href="#section-9">Your Privacy Rights</a></li>
          <li><a href="#section-10">Opt-Out Mechanisms</a></li>
          <li><a href="#section-11">Children's Privacy</a></li>
          <li><a href="#section-12">International Visitors</a></li>
          <li><a href="#section-13">Changes to This Policy</a></li>
          <li><a href="#section-14">Contact Information</a></li>
          <li><a href="#section-15">Supplemental State Privacy Disclosures</a></li>
        </ol>
      </nav>

      <!-- ═══ 1. Introduction ═══════════════════════════════════════════ -->
      <div id="section-1">
        <h2>1. Introduction</h2>
        <p>
          [hozio tag="client-name"] ("[hozio tag='client-name']," "we," "us," or "our") operates the website located at <a href="[hozio tag='client-url']">[hozio tag="client-url"]</a> (the "Site"),
          <?php if ( $dtb_is_ecommerce ): ?>
          an e-commerce retailer
          <?php else: ?>
          a service business
          <?php endif; ?>
          headquartered at [hozio tag="company-address"].
        </p>
        <p>
          This Privacy Policy describes how we collect, use, disclose, and protect your personal information when you visit our Site<?php if ( $dtb_is_ecommerce ): ?>, create an account, make a purchase<?php endif; ?>, sign up for our newsletter, submit an inquiry or contact form, or otherwise interact with us. It also explains your rights regarding your personal information under applicable federal and state laws.
        </p>
        <p>
          We are committed to transparency and to handling your personal information responsibly. We encourage you to read this Privacy Policy in its entirety. By using our Site, you acknowledge that you have read and understand this Privacy Policy.
        </p>
        <p>
          This Site is intended for users located in the United States.
          <?php if ( $dtb_is_ecommerce ): ?>
          We ship to all 50 states but do not offer international shipping or knowingly market to individuals outside the United States.
          <?php else: ?>
          We do not specifically target or market to individuals outside the United States.
          <?php endif; ?>
        </p>
      </div>

      <!-- ═══ 2. Information We Collect ═════════════════════════════════ -->
      <div id="section-2">
        <h2>2. Information We Collect</h2>
        <p>We collect personal information through several channels. The categories below describe each type of information, where it comes from, and why we collect it.</p>

        <h3>2.1 Information You Provide Directly</h3>

        <?php if ( $dtb_is_ecommerce ): ?>
        <h4>Account Registration and Checkout</h4>
        <p>When you create an account or place an order, we collect:</p>
        <ul>
          <li>Full name (first and last)</li>
          <li>Billing address</li>
          <li>Shipping address</li>
          <li>Email address</li>
          <li>Phone number</li>
          <li>Payment information (credit or debit card number, expiration date, and security code) — this information is transmitted directly to our payment processor and is not stored on our servers</li>
          <li>Order details (products purchased, quantities, prices)</li>
        </ul>

        <h4>Customer Account</h4>
        <p>If you create a customer account, we also store:</p>
        <ul>
          <li>Your order history</li>
          <li>Saved shipping addresses</li>
          <li>Saved payment methods (these are tokenized by our payment processor — we never store your actual card numbers)</li>
          <li>Account preferences</li>
        </ul>
        <?php endif; ?>

        <h4>Newsletter Signup</h4>
        <p>When you subscribe to our newsletter, we collect your email address.</p>

        <?php if ( $dtb_is_ecommerce ): ?>
        <h4>Product Inquiry Form</h4>
        <p>When you submit a product inquiry through the form on our product pages, we collect:</p>
        <ul>
          <li>Your name</li>
          <li>Email address</li>
          <li>Your message</li>
          <li>The product you are inquiring about (collected automatically based on the page)</li>
        </ul>
        <?php endif; ?>

        <h4>Contact Page</h4>
        <p>When you submit our general contact form, we collect:</p>
        <ul>
          <li>Your name</li>
          <li>Email address</li>
          <li>Phone number</li>
          <li>Your message</li>
        </ul>

        <?php if ( $dtb_is_ecommerce ): ?>
        <h4>Coupon and Promotional Usage</h4>
        <p>We track coupon usage associated with your billing email address to enforce per-customer limits on promotional offers.</p>
        <?php endif; ?>

        <h3>2.2 Information Collected Automatically</h3>
        <p>When you visit our Site, certain information is collected automatically through cookies, pixels, and similar technologies. This includes:</p>
        <ul>
          <li><strong>Device information:</strong> Device type, operating system, browser type and version, screen resolution, and device identifiers</li>
          <li><strong>Usage data:</strong> Pages visited, time spent on pages, click patterns, scroll depth, mouse movements, <?php if ( $dtb_is_ecommerce ): ?>products viewed, <?php endif; ?>search queries, referring URL, and exit pages</li>
          <li><strong>Network information:</strong> IP address, approximate geographic location (derived from IP address), and internet service provider</li>
          <li><strong>Session recordings:</strong> We may use behavioral analytics tools (such as Microsoft Clarity) to record user sessions, including mouse movements, clicks, scrolls, and page interactions. These recordings help us understand how visitors use our Site and identify areas for improvement. Sensitive input fields are masked by default.</li>
          <li><strong>Analytics data:</strong> We use Google Analytics (GA4) to collect data about website traffic, user behavior, demographics, and conversion events</li>
          <li><strong>Advertising data:</strong> The Meta (Facebook) Pixel collects data about your browsing activity on our Site and transmits it to Meta Platforms, Inc. for advertising purposes, including conversion tracking, ad optimization, retargeting, and the creation of custom and lookalike audiences</li>
        </ul>

        <h3>2.3 Information from Third Parties</h3>
        <p>We may receive limited information from third-party services we use:</p>
        <ul>
          <?php if ( $dtb_is_ecommerce ): ?>
          <li><strong>Payment processors:</strong> Fraud detection signals and payment verification data associated with your transactions</li>
          <?php endif; ?>
          <li><strong>Meta Platforms:</strong> Aggregated advertising performance data and audience insights (not individual-level personal information returned to us)</li>
          <li><strong>Google:</strong> Aggregated analytics and search performance data</li>
        </ul>
      </div>

      <!-- ═══ 3. How We Use Your Information ════════════════════════════ -->
      <div id="section-3">
        <h2>3. How We Use Your Information</h2>
        <p>We use the personal information we collect for the following purposes:</p>

        <?php if ( $dtb_is_ecommerce ): ?>
        <h3>3.1 Order Fulfillment and Customer Service</h3>
        <ul>
          <li>Processing and fulfilling your orders, including shipping and delivery</li>
          <li>Sending order confirmation, shipping notifications, and delivery updates</li>
          <li>Processing returns, refunds, and exchanges</li>
          <li>Responding to your questions, product inquiries, and support requests</li>
          <li>Managing your customer account</li>
        </ul>
        <?php else: ?>
        <h3>3.1 Service Delivery and Customer Service</h3>
        <ul>
          <li>Responding to your inquiries, requests, and quotes</li>
          <li>Scheduling and delivering services</li>
          <li>Sending confirmation, scheduling, and follow-up communications</li>
          <li>Responding to your questions and support requests</li>
        </ul>
        <?php endif; ?>

        <h3>3.2 Communication</h3>
        <ul>
          <?php if ( $dtb_is_ecommerce ): ?>
          <li>Sending transactional emails related to your orders and account</li>
          <?php else: ?>
          <li>Sending transactional emails related to your inquiries and service appointments</li>
          <?php endif; ?>
          <li>Sending marketing emails and newsletters (only with your consent, and you can unsubscribe at any time)</li>
          <?php if ( $dtb_is_ecommerce ): ?>
          <li>Notifying you about product availability, price changes, or promotions you may be interested in</li>
          <?php else: ?>
          <li>Notifying you about service updates, seasonal offerings, or promotions you may be interested in</li>
          <?php endif; ?>
        </ul>

        <h3>3.3 Marketing and Advertising</h3>
        <ul>
          <li>Displaying targeted advertisements on third-party platforms (such as Facebook and Instagram) based on your browsing activity on our Site</li>
          <li>Measuring advertising effectiveness and conversion rates</li>
          <li>Creating custom and lookalike audiences for advertising campaigns</li>
          <li>Retargeting — showing you ads for <?php if ( $dtb_is_ecommerce ): ?>products<?php else: ?>services<?php endif; ?> you viewed on our Site while you browse other websites and social media platforms</li>
        </ul>

        <h3>3.4 Analytics and Site Improvement</h3>
        <ul>
          <li>Understanding how visitors use our Site through analytics and session recordings</li>
          <li>Identifying usability issues, errors, and areas for improvement</li>
          <li>Testing new features and design changes</li>
          <li>Generating heatmaps and click maps to optimize page layouts</li>
          <li>Analyzing traffic sources, user demographics, and conversion funnels</li>
        </ul>

        <h3>3.5 Fraud Prevention and Security</h3>
        <ul>
          <li>Detecting and preventing fraudulent <?php if ( $dtb_is_ecommerce ): ?>transactions and <?php endif; ?>unauthorized access</li>
          <?php if ( $dtb_is_ecommerce ): ?>
          <li>Verifying your identity in connection with payment processing</li>
          <?php endif; ?>
          <li>Protecting the security and integrity of our Site and systems</li>
        </ul>

        <h3>3.6 Legal Compliance</h3>
        <ul>
          <li>Complying with applicable laws, regulations, and legal processes</li>
          <li>Enforcing our Terms of Use and other agreements</li>
          <li>Responding to lawful requests from law enforcement or government authorities</li>
          <li>Maintaining records for tax, accounting, and regulatory purposes</li>
        </ul>
      </div>

      <!-- ═══ 4. Cookies and Tracking Technologies ══════════════════════ -->
      <div id="section-4">
        <h2>4. Cookies and Tracking Technologies</h2>
        <p>Our Site uses cookies and similar tracking technologies to provide functionality, analyze usage, and deliver targeted advertising. A cookie is a small text file placed on your device by a website you visit. Below is a comprehensive list of the cookies and tracking technologies used on our Site.</p>

        <h3>4.1 Strictly Necessary Cookies</h3>
        <p>These cookies are essential for the Site to function properly. They enable core features like <?php if ( $dtb_is_ecommerce ): ?>shopping cart functionality, user authentication, and secure checkout<?php else: ?>user authentication and session management<?php endif; ?>. You cannot opt out of these cookies because the Site cannot operate without them.</p>

        <div class="hz-legal-table-wrap">
          <table class="hz-legal-table">
            <thead>
              <tr><th>Cookie Name</th><th>Provider</th><th>Purpose</th><th>Duration</th></tr>
            </thead>
            <tbody>
              <?php if ( $dtb_is_ecommerce ): ?>
              <tr><td>woocommerce_cart_hash</td><td>WooCommerce</td><td>Stores a hash of your shopping cart contents to ensure cart data is up to date</td><td>Session</td></tr>
              <tr><td>woocommerce_items_in_cart</td><td>WooCommerce</td><td>Indicates whether your cart contains items, used to control cache behavior</td><td>Session</td></tr>
              <tr><td>wp_woocommerce_session_*</td><td>WooCommerce</td><td>Contains a unique session identifier so the server can associate your cart and checkout data with your browser session</td><td>2 days</td></tr>
              <?php endif; ?>
              <tr><td>wordpress_logged_in_*</td><td>WordPress</td><td>Indicates when you are logged in and identifies your user account</td><td>Session / 14 days (if "Remember Me" is checked)</td></tr>
              <tr><td>wordpress_sec_*</td><td>WordPress</td><td>Stores authentication details for the WordPress admin area (admin users only)</td><td>Session / 14 days</td></tr>
              <tr><td>wp-settings-*</td><td>WordPress</td><td>Stores your WordPress dashboard preferences (admin users only)</td><td>1 year</td></tr>
              <?php if ( $dtb_is_ecommerce ): ?>
              <tr><td>__stripe_mid</td><td>Stripe</td><td>Fraud prevention — used to identify your device and detect fraudulent payment behavior</td><td>1 year</td></tr>
              <tr><td>__stripe_sid</td><td>Stripe</td><td>Fraud prevention — used to identify your browsing session for payment security</td><td>30 minutes</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <h3>4.2 Performance and Hosting Cookies</h3>
        <p>These cookies are set by our hosting provider to optimize Site performance, manage caching, and ensure pages load quickly.</p>

        <div class="hz-legal-table-wrap">
          <table class="hz-legal-table">
            <thead><tr><th>Cookie Name</th><th>Provider</th><th>Purpose</th><th>Duration</th></tr></thead>
            <tbody>
              <tr><td>sg_*</td><td>SiteGround</td><td>Performance optimization, caching, and load balancing to ensure fast page delivery</td><td>Varies</td></tr>
            </tbody>
          </table>
        </div>

        <h3>4.3 Analytics Cookies</h3>
        <p>These cookies help us understand how visitors interact with our Site by collecting information about pages visited, time spent, traffic sources, and user behavior. This data is used in aggregate to improve the Site.</p>

        <div class="hz-legal-table-wrap">
          <table class="hz-legal-table">
            <thead><tr><th>Cookie Name</th><th>Provider</th><th>Purpose</th><th>Duration</th></tr></thead>
            <tbody>
              <tr><td>_ga</td><td>Google Analytics</td><td>Distinguishes unique users by assigning a randomly generated number as a client identifier. Included in each page request and used to calculate visitor, session, and campaign data for analytics reports.</td><td>2 years</td></tr>
              <tr><td>_ga_*</td><td>Google Analytics</td><td>Used by Google Analytics 4 to persist session state (e.g., session count, engagement time, page views within a session)</td><td>2 years</td></tr>
              <tr><td>_gid</td><td>Google Analytics</td><td>Distinguishes unique users for a 24-hour period. Used to throttle request rates and provide short-term session analytics.</td><td>24 hours</td></tr>
              <tr><td>_clck</td><td>Microsoft Clarity</td><td>Persists the Clarity user identifier and settings. Used to link multiple page views by the same user into a single session for heatmaps and session recordings.</td><td>1 year</td></tr>
              <tr><td>_clsk</td><td>Microsoft Clarity</td><td>Stores and combines page views by a user into a single session recording</td><td>1 day</td></tr>
              <tr><td>CLID</td><td>Microsoft Clarity</td><td>Identifies the first time Clarity saw a user on any site that uses Clarity</td><td>1 year</td></tr>
              <tr><td>MUID</td><td>Microsoft Clarity</td><td>Identifies unique web browsers visiting Microsoft sites. Used to provide advertising, site analytics, and other operational purposes.</td><td>1 year</td></tr>
            </tbody>
          </table>
        </div>

        <h3>4.4 Advertising and Targeting Cookies</h3>
        <p>These cookies are used to deliver advertisements that are relevant to you, measure the effectiveness of advertising campaigns, and build audience profiles for targeted advertising. Data collected through these cookies may be shared with third-party advertising platforms.</p>

        <div class="hz-legal-table-wrap">
          <table class="hz-legal-table">
            <thead><tr><th>Cookie Name</th><th>Provider</th><th>Purpose</th><th>Duration</th></tr></thead>
            <tbody>
              <tr><td>_fbp</td><td>Meta (Facebook)</td><td>Used by Meta to deliver, measure, and improve the relevancy of ads. Stores a unique browser identifier to track browsing activity across websites that use the Meta Pixel for cross-context behavioral advertising.</td><td>3 months</td></tr>
              <tr><td>_fbc</td><td>Meta (Facebook)</td><td>Stores the last click identifier from a Facebook ad. Used to attribute conversions <?php if ( $dtb_is_ecommerce ): ?>(purchases, page views, add-to-cart events) <?php endif; ?>back to specific Facebook ad clicks.</td><td>3 months</td></tr>
            </tbody>
          </table>
        </div>

        <h3>4.5 Tracking Pixels and Tags</h3>
        <p>In addition to cookies, we use the following tracking technologies:</p>
        <ul>
          <li><strong>Meta (Facebook) Pixel:</strong> A small piece of JavaScript code that loads on every page of our Site. It sends data to Meta Platforms, Inc. about your browsing behavior, including page views<?php if ( $dtb_is_ecommerce ): ?>, product views, add-to-cart actions, purchases, and search queries<?php endif; ?>. Meta uses this data for ad targeting, conversion tracking, retargeting, and audience creation. This constitutes "sharing" of personal information for cross-context behavioral advertising under the California Consumer Privacy Act (see Section 6 below).</li>
          <li><strong>Google Tag Manager (GTM):</strong> A tag management system that loads and manages other tracking scripts (such as Google Analytics and Meta Pixel) on our Site. GTM itself does not collect personal information, but it enables the deployment of tags that do.</li>
          <li><strong>Microsoft Clarity:</strong> A behavioral analytics tool that records user sessions, generates heatmaps, and tracks click patterns and scroll depth. Clarity captures how you interact with our Site but masks sensitive form inputs by default.</li>
        </ul>

        <h3>4.6 Google Fonts</h3>
        <p>Our Site uses Google Fonts, a font delivery service provided by Google LLC. When you load a page on our Site, your browser connects to Google's servers to download the font files. In doing so, your IP address and certain browser information (user agent, referring URL) are transmitted to Google. Google states that it does not use this data to create user profiles or for targeted advertising. For more information, see <a href="https://developers.google.com/fonts/faq/privacy" target="_blank" rel="noopener noreferrer">Google Fonts Privacy and Data Collection</a>.</p>

        <h3>4.7 Managing Cookies</h3>
        <p>Most web browsers allow you to control cookies through their settings. You can typically set your browser to refuse all cookies, accept only first-party cookies, or delete cookies when you close your browser. Please note that disabling cookies may affect the functionality of our Site<?php if ( $dtb_is_ecommerce ): ?> — for example, you may not be able to add items to your cart or complete checkout if session cookies are blocked<?php endif; ?>.</p>
        <p>For more information on managing cookies in your browser:</p>
        <ul>
          <li><a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener noreferrer">Google Chrome</a></li>
          <li><a href="https://support.mozilla.org/en-US/kb/cookies-information-websites-store-on-your-computer" target="_blank" rel="noopener noreferrer">Mozilla Firefox</a></li>
          <li><a href="https://support.apple.com/guide/safari/manage-cookies-sfri11471/mac" target="_blank" rel="noopener noreferrer">Apple Safari</a></li>
          <li><a href="https://support.microsoft.com/en-us/microsoft-edge/manage-cookies-in-microsoft-edge-168dab11-0753-043d-7c16-ede5947fc64d" target="_blank" rel="noopener noreferrer">Microsoft Edge</a></li>
        </ul>
      </div>

      <!-- ═══ 5. How We Share Your Information ══════════════════════════ -->
      <div id="section-5">
        <h2>5. How We Share Your Information</h2>
        <p>We do not sell your personal information for monetary consideration. However, as explained in Section 6, our use of certain advertising technologies may constitute "sharing" of personal information under the California Consumer Privacy Act.</p>
        <p>We share your personal information with the following categories of third parties, each for the specific purposes described below:</p>

        <?php if ( $dtb_is_ecommerce ): ?>
        <h3>5.1 Payment Processors</h3>
        <p><strong>Stripe, Inc.</strong> — We use Stripe to process all credit and debit card payments. When you make a purchase, your payment card information is transmitted directly to Stripe's PCI DSS-compliant servers. We do not store, process, or have access to your full card numbers. Stripe may also collect device information and browser metadata for fraud prevention purposes. <a href="https://stripe.com/privacy" target="_blank" rel="noopener noreferrer">Stripe's Privacy Policy</a></p>
        <?php endif; ?>

        <h3><?php echo $dtb_is_ecommerce ? '5.2' : '5.1'; ?> Analytics Providers</h3>
        <p><strong>Google Analytics (Google LLC)</strong> — We use Google Analytics 4 to analyze website traffic, user behavior, demographics, and conversion events. Google Analytics uses cookies to collect pseudonymous data about your interactions with our Site. Google may use this data in accordance with its own privacy policy. <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">Google's Privacy Policy</a></p>
        <p><strong>Microsoft Clarity (Microsoft Corporation)</strong> — We use Microsoft Clarity for session recordings, heatmaps, and click tracking. Clarity captures user interactions on our Site, including mouse movements, clicks, scrolls, and page rendering. Microsoft Clarity masks sensitive input fields by default but may capture information visible on the page during a session recording. <a href="https://privacy.microsoft.com/privacystatement" target="_blank" rel="noopener noreferrer">Microsoft's Privacy Statement</a></p>

        <h3><?php echo $dtb_is_ecommerce ? '5.3' : '5.2'; ?> Advertising Partners</h3>
        <p><strong>Meta Platforms, Inc. (Facebook/Instagram)</strong> — We use the Meta Pixel on our Site, which transmits data about your browsing behavior to Meta for cross-context behavioral advertising. This includes page views<?php if ( $dtb_is_ecommerce ): ?>, product views, add-to-cart events, purchases, and other conversion actions<?php endif; ?>. Meta uses this data to deliver targeted ads, build advertising audiences, measure ad performance, and provide retargeting. Because Meta uses this data for its own advertising purposes across its platform (Facebook, Instagram, Messenger, and the Meta Audience Network), this constitutes "sharing" of personal information under the CCPA/CPRA. See Section 6 for your opt-out rights. <a href="https://www.facebook.com/privacy/policy/" target="_blank" rel="noopener noreferrer">Meta's Privacy Policy</a></p>

        <h3><?php echo $dtb_is_ecommerce ? '5.4' : '5.3'; ?> Email Marketing</h3>
        <p>If you subscribe to our newsletter, your email address and engagement data (open rates, click rates) are stored and processed by our email marketing platform. We use this platform to send marketing emails, promotional offers, and company updates. You can unsubscribe at any time using the link in any marketing email.</p>

        <h3><?php echo $dtb_is_ecommerce ? '5.5' : '5.4'; ?> Tag Management</h3>
        <p><strong>Google Tag Manager (Google LLC)</strong> — We use Google Tag Manager to manage the deployment of tracking scripts on our Site. While GTM itself does not collect personal information, it facilitates the loading of other services (such as Google Analytics and Meta Pixel) that do.</p>

        <h3><?php echo $dtb_is_ecommerce ? '5.6' : '5.5'; ?> Search Performance</h3>
        <p><strong>Google Search Console (Google LLC)</strong> — We use Google Search Console to monitor our Site's search performance, indexing status, and search queries that lead users to our Site. This service processes aggregated search data and does not collect personal information directly from visitors.</p>

        <h3><?php echo $dtb_is_ecommerce ? '5.7' : '5.6'; ?> Web Hosting</h3>
        <p><strong>SiteGround (SiteGround Hosting Ltd.)</strong> — Our Site is hosted on SiteGround's servers. SiteGround automatically logs server access data, including your IP address, the pages you request, your browser type, and the date and time of your visit. This data is used for server management, security monitoring, and performance optimization. <a href="https://www.siteground.com/privacy.htm" target="_blank" rel="noopener noreferrer">SiteGround's Privacy Policy</a></p>

        <h3><?php echo $dtb_is_ecommerce ? '5.8' : '5.7'; ?> Font Services</h3>
        <p><strong>Google Fonts (Google LLC)</strong> — Our Site loads fonts from Google's content delivery network. When a page loads, your browser makes a request to Google's servers, transmitting your IP address. Google states that this data is not used for tracking or advertising purposes. See Section 4.6 above for more detail.</p>

        <h3><?php echo $dtb_is_ecommerce ? '5.9' : '5.8'; ?> Customer Reviews</h3>
        <p><strong>Trustindex (Trustindex Ltd.)</strong> — We may use Trustindex to display customer reviews on our Site. When Trustindex widgets load, your browser may connect to Trustindex's servers, transmitting your IP address and basic browser information. <a href="https://www.trustindex.io/privacy-policy/" target="_blank" rel="noopener noreferrer">Trustindex's Privacy Policy</a></p>

        <h3><?php echo $dtb_is_ecommerce ? '5.10' : '5.9'; ?> Other Disclosures</h3>
        <p>We may also disclose your personal information:</p>
        <ul>
          <li><strong>Legal obligations:</strong> When required by law, subpoena, court order, or government investigation</li>
          <li><strong>Protection of rights:</strong> To protect the rights, property, or safety of [hozio tag="client-name"], our customers, or others</li>
          <li><strong>Business transfers:</strong> In connection with a merger, acquisition, bankruptcy, or sale of all or a portion of our assets, in which case your personal information may be transferred to the acquiring entity</li>
          <li><strong>With your consent:</strong> When you direct us to share your information with a third party</li>
        </ul>
      </div>

      <!-- ═══ 6. Do Not Sell or Share ═══════════════════════════════════ -->
      <div id="section-6">
        <h2>6. Do Not Sell or Share My Personal Information</h2>

        <h3>6.1 Our Position on Selling and Sharing</h3>
        <p>[hozio tag="client-name"] does not sell your personal information for monetary compensation. We have never sold personal information and have no plans to do so.</p>
        <p>However, under the California Consumer Privacy Act as amended by the California Privacy Rights Act (CCPA/CPRA), the term "sharing" has a specific legal meaning. "Sharing" means disclosing a consumer's personal information to a third party for cross-context behavioral advertising, whether or not for monetary consideration. Cross-context behavioral advertising means the targeting of advertising to a consumer based on personal information obtained from the consumer's activity across websites, applications, or services other than the one with which the consumer intentionally interacts.</p>

        <h3>6.2 Meta Pixel and Cross-Context Behavioral Advertising</h3>
        <p>Our use of the Meta (Facebook) Pixel constitutes "sharing" of personal information under the CCPA/CPRA. When the Meta Pixel is active on our Site, it collects identifiers (including browser identifiers and cookies such as _fbp and _fbc) and data about your browsing behavior, and transmits this data to Meta Platforms, Inc. Meta uses this data across its family of platforms (Facebook, Instagram, Messenger, Meta Audience Network) for purposes including:</p>
        <ul>
          <li>Retargeting — showing you ads for <?php if ( $dtb_is_ecommerce ): ?>products<?php else: ?>services<?php endif; ?> you viewed on our Site</li>
          <li>Conversion tracking — measuring whether our ads led to <?php if ( $dtb_is_ecommerce ): ?>purchases<?php else: ?>inquiries<?php endif; ?></li>
          <li>Custom audience creation — building advertising audiences from our website visitors</li>
          <li>Lookalike audience creation — finding new potential customers who resemble our existing customers</li>
          <li>Ad optimization — improving the delivery and relevancy of ads shown to you</li>
        </ul>
        <p>Because Meta uses this data for its own advertising purposes beyond simply providing a service to us, this data transfer meets the CCPA/CPRA definition of "sharing" for cross-context behavioral advertising.</p>

        <h3>6.3 Categories of Personal Information Shared</h3>
        <p>The following categories of personal information may be shared with Meta through the Meta Pixel:</p>
        <ul>
          <li>Internet or other electronic network activity information (browsing history on our Site, pages viewed<?php if ( $dtb_is_ecommerce ): ?>, products viewed<?php endif; ?>, search queries, click behavior)</li>
          <li>Unique personal identifiers (browser cookie identifiers, device identifiers)</li>
          <?php if ( $dtb_is_ecommerce ): ?>
          <li>Commercial information (products viewed, add-to-cart events, purchase events)</li>
          <?php endif; ?>
          <li>Geolocation data (approximate location derived from IP address)</li>
        </ul>

        <h3>6.4 Your Right to Opt Out</h3>
        <p>You have the right to opt out of the sharing of your personal information for cross-context behavioral advertising. See Section 10 for detailed instructions on how to opt out of Meta Pixel tracking and other advertising technologies.</p>
        <p>We also honor Global Privacy Control (GPC) signals. If your browser or a browser extension sends a GPC signal, we will treat it as a valid request to opt out of the sharing of your personal information for cross-context behavioral advertising.</p>
      </div>

      <!-- ═══ 7. Data Retention ═════════════════════════════════════════ -->
      <div id="section-7">
        <h2>7. Data Retention</h2>
        <p>We retain your personal information only for as long as reasonably necessary to fulfill the purposes for which it was collected, comply with our legal obligations, resolve disputes, and enforce our agreements. The specific retention periods depend on the type of data:</p>

        <div class="hz-legal-table-wrap">
          <table class="hz-legal-table">
            <thead><tr><th>Data Type</th><th>Retention Period</th><th>Reason</th></tr></thead>
            <tbody>
              <?php if ( $dtb_is_ecommerce ): ?>
              <tr><td>Order records and transaction data</td><td>7 years after order date</td><td>Tax, accounting, and legal compliance (IRS record-keeping requirements)</td></tr>
              <tr><td>Customer account data</td><td>Duration of active account + 3 years after last activity</td><td>Customer service, order history, and dispute resolution</td></tr>
              <?php endif; ?>
              <tr><td>Contact form<?php if ( $dtb_is_ecommerce ): ?> and product inquiry<?php endif; ?> submissions</td><td>2 years after submission</td><td>Customer service follow-up and business records</td></tr>
              <tr><td>Newsletter subscriber email addresses</td><td>Until you unsubscribe or 2 years of inactivity</td><td>Marketing communications (consent-based)</td></tr>
              <?php if ( $dtb_is_ecommerce ): ?>
              <tr><td>Payment card information</td><td>Not stored on our servers</td><td>Processed and stored by our payment processor in tokenized form per their retention policies</td></tr>
              <?php endif; ?>
              <tr><td>Server logs (IP addresses, access logs)</td><td>90 days</td><td>Security monitoring and troubleshooting (managed by our hosting provider)</td></tr>
              <tr><td>Analytics cookies (_ga, _ga_*)</td><td>2 years (set by Google)</td><td>Website usage analysis</td></tr>
              <tr><td>Advertising cookies (_fbp, _fbc)</td><td>3 months (set by Meta)</td><td>Ad targeting and conversion attribution</td></tr>
              <tr><td>Session recording data (Microsoft Clarity)</td><td>30 days</td><td>Behavioral analytics and site optimization (managed by Microsoft)</td></tr>
              <?php if ( $dtb_is_ecommerce ): ?>
              <tr><td>Coupon usage records</td><td>Duration of the promotional program</td><td>Enforcement of per-customer coupon limits</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <p>When your personal information is no longer needed for the purposes described above, we will delete or anonymize it. If deletion is not immediately possible (for example, because the information is stored in backup archives), we will securely store and isolate the information until deletion is feasible.</p>
      </div>

      <!-- ═══ 8. Data Security ══════════════════════════════════════════ -->
      <div id="section-8">
        <h2>8. Data Security</h2>
        <p>We take the security of your personal information seriously and implement reasonable administrative, technical, and physical safeguards to protect it from unauthorized access, use, alteration, and destruction. These measures include:</p>

        <h3>8.1 Technical Safeguards</h3>
        <ul>
          <li><strong>SSL/TLS encryption:</strong> All data transmitted between your browser and our Site is encrypted using Secure Sockets Layer (SSL) / Transport Layer Security (TLS) technology. Our Site uses HTTPS on every page.</li>
          <?php if ( $dtb_is_ecommerce ): ?>
          <li><strong>PCI DSS compliance:</strong> We do not store, process, or transmit credit card data on our servers. All payment processing is handled by our payment processor, which is certified as a PCI Level 1 Service Provider — the highest level of certification in the payment card industry.</li>
          <li><strong>Tokenized payment storage:</strong> Saved payment methods in customer accounts are stored as secure tokens by our payment processor. We never have access to your full card number, expiration date, or security code.</li>
          <?php endif; ?>
          <li><strong>Server security:</strong> Our hosting provider implements server-level security measures including firewall protection, malware scanning, intrusion detection, automatic software updates, and distributed denial-of-service (DDoS) protection.</li>
          <li><strong>Access controls:</strong> Administrative access to our Site and customer data is restricted to authorized personnel and protected by strong authentication.</li>
        </ul>

        <h3>8.2 Administrative Safeguards</h3>
        <ul>
          <li>Regular review of data handling practices and security procedures</li>
          <li>Limiting employee and contractor access to personal information on a need-to-know basis</li>
          <li>Vendor assessment to ensure third-party service providers maintain appropriate security standards</li>
        </ul>

        <h3>8.3 NY SHIELD Act Compliance</h3>
        <p>As a business that collects personal information of New York residents, we comply with the New York Stop Hacks and Improve Electronic Data Security (SHIELD) Act. This law requires us to implement and maintain reasonable safeguards to protect the security, confidentiality, and integrity of private information, including Social Security numbers, driver's license numbers, financial account numbers, and — as of March 2025 — medical and health insurance information. Our data security program includes the administrative, technical, and physical safeguards described above, consistent with the SHIELD Act's requirements.</p>
        <p>In the event of a data breach involving your private information, we will notify affected New York residents within 30 days as required by the SHIELD Act's amended breach notification provisions, and we will report the breach to the New York Attorney General, the Department of State, the State Police, and the Department of Financial Services as required by law.</p>

        <h3>8.4 Limitations</h3>
        <p>While we strive to protect your personal information, no method of transmission over the internet or method of electronic storage is 100% secure. We cannot guarantee absolute security. If you have reason to believe that your interaction with us is no longer secure, please contact us immediately using the information in Section 14.</p>
      </div>

      <!-- ═══ 9. Your Privacy Rights ════════════════════════════════════ -->
      <div id="section-9">
        <h2>9. Your Privacy Rights</h2>
        <p>Your privacy rights depend on where you live. Below we describe the rights available to all U.S. residents, followed by additional rights for residents of specific states. We respond to all verifiable privacy requests free of charge.</p>

        <h3>9.1 Rights for All U.S. Residents</h3>
        <p>Regardless of your state of residence, you have the right to:</p>
        <ul>
          <li><strong>Opt out of marketing emails:</strong> Click the "Unsubscribe" link at the bottom of any marketing email. We will process your request within 10 business days as required by the CAN-SPAM Act. Note that you will continue to receive transactional emails (<?php if ( $dtb_is_ecommerce ): ?>order confirmations, shipping updates, etc.<?php else: ?>appointment confirmations, service updates, etc.<?php endif; ?>) even after unsubscribing from marketing emails.</li>
          <?php if ( $dtb_is_ecommerce ): ?>
          <li><strong>Access and update your account information:</strong> Log into your account at any time to view and update your name, email address, shipping addresses, and other account details.</li>
          <li><strong>Request deletion of your account:</strong> Contact us to request that your account and associated data be deleted, subject to our legal retention obligations.</li>
          <?php else: ?>
          <li><strong>Request deletion of your information:</strong> Contact us to request that your personal information be deleted, subject to our legal retention obligations.</li>
          <?php endif; ?>
        </ul>

        <h3>9.2 California Residents (CCPA/CPRA)</h3>
        <p>If you are a California resident, the California Consumer Privacy Act as amended by the California Privacy Rights Act (CCPA/CPRA) provides you with the following rights:</p>
        <ul>
          <li><strong>Right to Know:</strong> You have the right to request that we disclose the categories and specific pieces of personal information we have collected about you, the categories of sources from which we collected it, the business or commercial purposes for collecting or sharing it, and the categories of third parties with whom we share it.</li>
          <li><strong>Right to Delete:</strong> You have the right to request that we delete personal information we have collected from you, subject to certain exceptions (such as completing a transaction, detecting security incidents, complying with legal obligations, or exercising free speech).</li>
          <li><strong>Right to Correct:</strong> You have the right to request that we correct inaccurate personal information we maintain about you.</li>
          <li><strong>Right to Opt Out of Selling/Sharing:</strong> You have the right to opt out of the "sharing" of your personal information for cross-context behavioral advertising. As described in Section 6, our use of the Meta Pixel constitutes sharing. See Section 10 for opt-out instructions.</li>
          <li><strong>Right to Limit Use of Sensitive Personal Information:</strong> We do not use or disclose sensitive personal information for purposes beyond those necessary to provide the goods and services you request.</li>
          <li><strong>Right to Non-Discrimination:</strong> We will not discriminate against you for exercising any of your CCPA/CPRA rights. We will not deny you goods or services, charge you different prices, provide a different level of quality, or suggest that you will receive a different price or quality for exercising your rights.</li>
        </ul>
        <p><strong>Authorized agents:</strong> You may designate an authorized agent to submit a request on your behalf. We will require the agent to provide proof of your written authorization and may require you to verify your identity directly with us.</p>

        <h3>9.3 Virginia Residents (VCDPA)</h3>
        <p>If you are a Virginia resident, the Virginia Consumer Data Protection Act (VCDPA) provides you with the following rights:</p>
        <ul>
          <li><strong>Right to Access:</strong> Confirm whether we are processing your personal data and access that data</li>
          <li><strong>Right to Correct:</strong> Correct inaccuracies in your personal data</li>
          <li><strong>Right to Delete:</strong> Delete personal data you have provided or that we have obtained about you</li>
          <li><strong>Right to Data Portability:</strong> Obtain a copy of your personal data in a portable, readily usable format</li>
          <li><strong>Right to Opt Out:</strong> Opt out of the processing of your personal data for targeted advertising, the sale of personal data, and profiling that produces legal or similarly significant effects</li>
        </ul>
        <p>If we decline your request, you have the right to appeal. To appeal, contact us using the information in Section 14 with the subject line "Privacy Rights Appeal." We will respond to your appeal within 60 days.</p>

        <h3>9.4 Colorado Residents (CPA)</h3>
        <p>If you are a Colorado resident, the Colorado Privacy Act (CPA) provides you with rights similar to those described for Virginia residents, including the right to access, correct, delete, and obtain a portable copy of your personal data, as well as the right to opt out of targeted advertising, the sale of personal data, and certain profiling. Colorado also requires us to honor universal opt-out mechanisms such as Global Privacy Control (GPC).</p>

        <h3>9.5 Connecticut Residents (CTDPA)</h3>
        <p>If you are a Connecticut resident, the Connecticut Data Privacy Act (CTDPA) provides you with rights to access, correct, delete, and obtain a portable copy of your personal data, as well as the right to opt out of targeted advertising, the sale of personal data, and profiling. Connecticut requires us to recognize and honor universal opt-out signals including GPC.</p>

        <h3>9.6 Texas Residents (TDPSA)</h3>
        <p>If you are a Texas resident, the Texas Data Privacy and Security Act (TDPSA) provides you with the right to access, correct, delete, and obtain a portable copy of your personal data. You also have the right to opt out of the processing of your personal data for targeted advertising, the sale of personal data, and profiling that produces legal or similarly significant effects.</p>

        <h3>9.7 Other State Privacy Laws</h3>
        <p>We recognize that privacy laws in additional states — including Oregon, Montana, Iowa, Delaware, New Hampshire, New Jersey, Nebraska, Tennessee, Minnesota, Maryland, Indiana, Kentucky, and Rhode Island — provide residents with varying privacy rights. If you are a resident of any of these states, you may submit a request to exercise your rights by contacting us using the methods described in Section 14, and we will respond in accordance with applicable law. Common rights across these state laws include the right to access, correct, and delete your personal data, obtain a portable copy of your data, and opt out of targeted advertising and the sale of personal data.</p>

        <h3>9.8 How to Exercise Your Rights</h3>
        <p>To exercise any of the privacy rights described above, you may submit a request through any of the following methods:</p>
        <ul>
          <li><strong>Email:</strong> <a href="mailto:[hozio tag='company-email']">[hozio tag="company-email"]</a> — include "Privacy Rights Request" in the subject line</li>
          <li><strong>Phone:</strong> <a href="tel:[hozio_tel tag='company-phone-1']">[hozio tag="company-phone-1"]</a></li>
          <li><strong>Mail:</strong> [hozio tag="client-name"], Attn: Privacy, [hozio tag="company-address"]</li>
        </ul>

        <h3>9.9 Verification</h3>
        <p>To protect your personal information, we must verify your identity before processing your request. We will ask you to provide information that matches what we have on file, such as your name, email address<?php if ( $dtb_is_ecommerce ): ?>, and order history<?php endif; ?>. <?php if ( $dtb_is_ecommerce ): ?>If you have an account with us, we may ask you to log in to verify your identity. <?php endif; ?>If we cannot verify your identity, we may ask for additional documentation.</p>
        <p>We will respond to verifiable requests within 45 days. If we need additional time, we will notify you of the extension and the reason for it. In no case will our response take longer than 90 days from receipt of your request.</p>
      </div>

      <!-- ═══ 10. Opt-Out Mechanisms ════════════════════════════════════ -->
      <div id="section-10">
        <h2>10. Opt-Out Mechanisms</h2>
        <p>We provide multiple ways for you to opt out of data collection and targeted advertising:</p>

        <h3>10.1 Marketing Emails</h3>
        <p>Every marketing email we send includes an "Unsubscribe" link at the bottom. Click that link and follow the instructions to remove your email address from our marketing list. We will process your unsubscribe request within 10 business days as required by the CAN-SPAM Act. You will continue to receive non-marketing transactional emails (<?php if ( $dtb_is_ecommerce ): ?>order confirmations, shipping updates, password resets, etc.<?php else: ?>appointment confirmations, service updates, password resets, etc.<?php endif; ?>).</p>

        <h3>10.2 Meta (Facebook) Pixel — Targeted Advertising</h3>
        <p>You can opt out of Meta's targeted advertising in several ways:</p>
        <ul>
          <li><strong>Facebook Ad Preferences:</strong> Visit <a href="https://www.facebook.com/adpreferences" target="_blank" rel="noopener noreferrer">facebook.com/adpreferences</a> to manage your ad settings and opt out of ads based on your activity on websites and apps outside of Facebook</li>
          <li><strong>Digital Advertising Alliance:</strong> Visit <a href="https://optout.aboutads.info/" target="_blank" rel="noopener noreferrer">optout.aboutads.info</a> to opt out of interest-based advertising from participating companies, including Meta</li>
          <li><strong>Network Advertising Initiative:</strong> Visit <a href="https://optout.networkadvertising.org/" target="_blank" rel="noopener noreferrer">optout.networkadvertising.org</a> to opt out of targeted advertising from NAI member companies</li>
          <li><strong>Global Privacy Control (GPC):</strong> Install a browser or extension that supports GPC (such as Privacy Badger, DuckDuckGo, or Brave). We honor GPC signals as a valid opt-out request for the sharing of your personal information for cross-context behavioral advertising.</li>
        </ul>

        <h3>10.3 Google Analytics</h3>
        <p>You can opt out of Google Analytics data collection by:</p>
        <ul>
          <li>Installing the <a href="https://tools.google.com/dlpage/gaoptout" target="_blank" rel="noopener noreferrer">Google Analytics Opt-out Browser Add-on</a></li>
          <li>Using your browser's cookie settings to block Google Analytics cookies (_ga, _ga_*, _gid)</li>
          <li>Adjusting your <a href="https://myaccount.google.com/data-and-privacy" target="_blank" rel="noopener noreferrer">Google Activity Controls</a> to limit data collected by Google services</li>
        </ul>

        <h3>10.4 Microsoft Clarity</h3>
        <p>You can opt out of Microsoft Clarity session recording and behavioral analytics by:</p>
        <ul>
          <li>Adjusting your privacy settings at <a href="https://account.microsoft.com/privacy" target="_blank" rel="noopener noreferrer">Microsoft Privacy Dashboard</a></li>
          <li>Using your browser's cookie settings to block Microsoft Clarity cookies (_clck, _clsk, CLID, MUID)</li>
          <li>Enabling "Do Not Track" in your browser settings (Microsoft Clarity may honor this signal depending on configuration)</li>
        </ul>

        <h3>10.5 All Cookies</h3>
        <p>You can manage or delete cookies through your browser settings. See Section 4.7 above for browser-specific instructions. Additionally, you can use browser extensions like Privacy Badger, uBlock Origin, or Ghostery to block tracking cookies selectively.</p>

        <h3>10.6 Global Privacy Control (GPC)</h3>
        <p>Global Privacy Control is a browser-level signal that communicates your preference to opt out of the sale or sharing of personal information. Multiple states, including California, Colorado, and Connecticut, require businesses to honor GPC signals. We treat a GPC signal as a valid opt-out request. You can enable GPC through compatible browsers (such as Brave or DuckDuckGo) or browser extensions (such as Privacy Badger or OptMeowt).</p>
      </div>

      <!-- ═══ 11. Children's Privacy ════════════════════════════════════ -->
      <div id="section-11">
        <h2>11. Children's Privacy</h2>
        <p>Our Site is intended for individuals who are 18 years of age or older. We do not knowingly collect, solicit, or maintain personal information from children under the age of 13 as defined by the Children's Online Privacy Protection Act (COPPA), or from minors under 16 as referenced by the CCPA/CPRA.</p>
        <p>We do not direct our Site or <?php if ( $dtb_is_ecommerce ): ?>products<?php else: ?>services<?php endif; ?> to children.</p>
        <p>If we learn that we have collected personal information from a child under 13, we will promptly delete that information. If you believe that a child under 13 has provided us with personal information, please contact us immediately at <a href="mailto:[hozio tag='company-email']">[hozio tag="company-email"]</a> or call <a href="tel:[hozio_tel tag='company-phone-1']">[hozio tag="company-phone-1"]</a>, and we will take steps to delete the information.</p>
      </div>

      <!-- ═══ 12. International Visitors ════════════════════════════════ -->
      <div id="section-12">
        <h2>12. International Visitors</h2>
        <p>Our Site is operated from the United States and is intended for U.S. residents. <?php if ( $dtb_is_ecommerce ): ?>We do not offer international shipping and do not specifically target or market to individuals outside the United States.<?php else: ?>We do not specifically target or market to individuals outside the United States.<?php endif; ?> If you access our Site from outside the United States, please be aware that your information will be transferred to, stored, and processed in the United States, where data protection laws may differ from those in your country of residence. By using our Site, you consent to the transfer of your information to the United States.</p>
      </div>

      <!-- ═══ 13. Changes to This Policy ════════════════════════════════ -->
      <div id="section-13">
        <h2>13. Changes to This Policy</h2>
        <p>We may update this Privacy Policy from time to time to reflect changes in our practices, technologies, legal requirements, or for other operational reasons. When we make material changes, we will:</p>
        <ul>
          <li>Update the "Last Updated" date at the top of this page</li>
          <li>Post the revised Privacy Policy on our Site</li>
          <li>For significant changes that materially affect how we handle your personal information, we may also notify you by email (if you have an account) or through a prominent notice on our Site</li>
        </ul>
        <p>We encourage you to review this Privacy Policy periodically to stay informed about how we protect your personal information. Your continued use of our Site after the posting of changes constitutes your acceptance of those changes.</p>
      </div>

      <!-- ═══ 14. Contact Information ════════════════════════════════════ -->
      <div id="section-14">
        <h2>14. Contact Information</h2>
        <p>If you have any questions, concerns, or requests regarding this Privacy Policy or our data practices, please contact us:</p>
        <ul>
          <li><strong>Email:</strong> <a href="mailto:[hozio tag='company-email']">[hozio tag="company-email"]</a></li>
          <li><strong>Phone:</strong> <a href="tel:[hozio_tel tag='company-phone-1']">[hozio tag="company-phone-1"]</a></li>
          <li><strong>Mail:</strong> [hozio tag="client-name"], Attn: Privacy, [hozio tag="company-address"]</li>
        </ul>
        <p>We will respond to your inquiry as promptly as possible. For privacy rights requests, see Section 9.8 for response timeframes.</p>
      </div>

      <!-- ═══ 15. Supplemental State Disclosures ═══════════════════════ -->
      <div id="section-15">
        <h2>15. Supplemental State Privacy Disclosures</h2>

        <h3>15.1 California Disclosures (CCPA/CPRA and CalOPPA)</h3>

        <h4>Categories of Personal Information Collected</h4>
        <p>In the preceding 12 months, we have collected the following categories of personal information as defined by the CCPA/CPRA:</p>

        <div class="hz-legal-table-wrap">
          <table class="hz-legal-table">
            <thead><tr><th>Category</th><th>Examples</th><th>Collected</th><th>Source</th></tr></thead>
            <tbody>
              <tr><td>A. Identifiers</td><td>Name, email address, postal address, phone number, IP address, unique cookie identifiers</td><td>Yes</td><td>You, cookies, analytics</td></tr>
              <tr><td>B. Personal information (Cal. Civ. Code 1798.80(e))</td><td>Name, address, phone number<?php if ( $dtb_is_ecommerce ): ?>, credit card number (processed by our payment processor, not stored)<?php endif; ?></td><td>Yes</td><td>You</td></tr>
              <tr><td>C. Protected classifications</td><td>Age (18+ verification only)</td><td>No</td><td>N/A</td></tr>
              <?php if ( $dtb_is_ecommerce ): ?>
              <tr><td>D. Commercial information</td><td>Products purchased, order history, shopping cart contents, product views, coupon usage</td><td>Yes</td><td>You, Site activity</td></tr>
              <?php else: ?>
              <tr><td>D. Commercial information</td><td>Service inquiries and engagement records</td><td>Yes</td><td>You, Site activity</td></tr>
              <?php endif; ?>
              <tr><td>F. Internet or electronic network activity</td><td>Browsing history on our Site, pages visited, click behavior, search queries, referral URLs, session recordings</td><td>Yes</td><td>Cookies, analytics tools</td></tr>
              <tr><td>G. Geolocation data</td><td>Approximate location derived from IP address</td><td>Yes</td><td>Automatic (IP-based)</td></tr>
              <tr><td>H. Sensory data</td><td>N/A</td><td>No</td><td>N/A</td></tr>
              <tr><td>I. Professional or employment information</td><td>N/A</td><td>No</td><td>N/A</td></tr>
              <tr><td>K. Inferences</td><td>Preferences based on browsing behavior (generated by Meta and Google, not by us directly)</td><td>Indirectly</td><td>Third-party analytics</td></tr>
            </tbody>
          </table>
        </div>

        <h4>Business Purposes for Collection</h4>
        <p>We collect personal information for the business purposes described in Section 3, including <?php if ( $dtb_is_ecommerce ): ?>order fulfillment<?php else: ?>service delivery<?php endif; ?>, customer service, marketing and advertising, analytics, fraud prevention, and legal compliance.</p>

        <h4>Sharing for Cross-Context Behavioral Advertising</h4>
        <p>As described in Section 6, we share the following categories of personal information with Meta Platforms, Inc. for cross-context behavioral advertising: identifiers (cookie IDs, browser identifiers), internet or electronic network activity information (browsing history<?php if ( $dtb_is_ecommerce ): ?>, product views<?php endif; ?>), and commercial information (<?php if ( $dtb_is_ecommerce ): ?>add-to-cart events, purchase events<?php else: ?>inquiry events<?php endif; ?>).</p>

        <h4>CalOPPA Disclosures</h4>
        <p>In compliance with the California Online Privacy Protection Act (CalOPPA), we disclose the following:</p>
        <ul>
          <li><strong>Do Not Track signals:</strong> We honor Global Privacy Control (GPC) signals as a mechanism for opting out of the sharing of personal information. At this time, there is no universally accepted standard for how websites should respond to the browser-level "Do Not Track" (DNT) signal, and our Site's response to DNT may vary by the third-party services in use. We recommend using GPC for a more reliable opt-out experience.</li>
          <li><strong>Third-party tracking:</strong> Third parties (including Google, Meta, and Microsoft) may collect information about your online activities over time and across different websites when you use our Site, as described in Sections 4 and 5 of this Privacy Policy.</li>
        </ul>

        <h3>15.2 New York Disclosures (SHIELD Act)</h3>
        <p>As described in Section 8.3, we comply with the New York SHIELD Act's data security requirements and breach notification obligations. Private information under the SHIELD Act includes Social Security numbers, driver's license numbers, financial account numbers, biometric information, email addresses combined with passwords or security questions, and — as of March 2025 — medical and health insurance information. We implement reasonable administrative, technical, and physical safeguards to protect this information.</p>

        <h3>15.3 CAN-SPAM Act Compliance</h3>
        <p>In compliance with the CAN-SPAM Act, we confirm that:</p>
        <ul>
          <li>All commercial emails identify [hozio tag="client-name"] as the sender with accurate "From" and "Reply-To" information</li>
          <li>Subject lines accurately reflect the content of the message</li>
          <li>Commercial messages are identified as advertisements where appropriate</li>
          <li>Every commercial email includes our valid physical postal address: [hozio tag="company-address"]</li>
          <li>Every commercial email includes a clear and conspicuous opt-out (unsubscribe) mechanism</li>
          <li>Opt-out requests are honored within 10 business days</li>
          <li>We do not use deceptive subject lines or false header information</li>
          <li>We do not sell, rent, or transfer email addresses to third parties for their own marketing purposes</li>
        </ul>

        <h3>15.4 FTC Act Compliance</h3>
        <p>We are committed to fair and transparent data practices in compliance with Section 5 of the Federal Trade Commission (FTC) Act, which prohibits unfair or deceptive acts or practices in or affecting commerce. Our data collection, use, and disclosure practices are consistent with the representations made in this Privacy Policy. We do not engage in deceptive practices regarding the handling of your personal information.</p>
      </div>

      <!-- Closing -->
      <div class="hz-legal__closing">
        <p>This Privacy Policy constitutes the complete description of our privacy practices as of the date listed above. If you have any questions or concerns, please do not hesitate to contact us.</p>
        <p>
          <strong>[hozio tag="client-name"]</strong><br>
          [hozio tag="company-address"]<br>
          <a href="tel:[hozio_tel tag='company-phone-1']">[hozio tag="company-phone-1"]</a>
          &nbsp;|&nbsp;
          <a href="mailto:[hozio tag='company-email']">[hozio tag="company-email"]</a>
        </p>
      </div>

    </div>
  </div>
</section>

<!-- Legal page styles -->
<style>
/* Hero. Mobile baseline: left-aligned with the meta date row stacked
   into two lines. At 1024px the layout switches to centered with the
   meta dates on a single line separated by a pipe. */
.hz-legal-hero { text-align: left; }
.hz-legal-hero__headline { max-width: 900px; margin: 0 0 var(--space-md); }
.hz-legal-hero__meta { margin: 0; }
.hz-legal-hero__meta-item { display: block; }
.hz-legal-hero__meta-sep { display: none; }
@media (min-width: 1024px) {
  .hz-legal-hero { text-align: center; }
  .hz-legal-hero__headline { margin-left: auto; margin-right: auto; }
  .hz-legal-hero__meta-item { display: inline; }
  .hz-legal-hero__meta-sep { display: inline; }
}

/* Body section spacing for the legal-document content rhythm. */
.hz-legal h2 { margin-top: var(--space-2xl); }
.hz-legal h3 { margin-top: var(--space-xl); }
.hz-legal h4 { margin-top: var(--space-lg); font-size: 16px; }
.hz-legal ul,
.hz-legal ol { padding-left: 1.25rem; }
.hz-legal li { margin-bottom: var(--space-sm); }

/* Closing block — divider above contact details after Section 15. */
.hz-legal__closing { margin-top: var(--space-2xl); padding-top: var(--space-lg); border-top: 1px solid var(--border); }

/* Table of Contents — card styling. List collapses to a single column
   on mobile and expands to two columns at 768px to use the full width
   without producing a tall, sparse list on wider screens. */
.hz-legal-toc { background: var(--bg-alt); padding: var(--space-lg); border-radius: var(--radius-card); margin-bottom: var(--space-2xl); }
.hz-legal-toc__title { font-size: 18px; margin-top: 0; margin-bottom: var(--space-md); }
.hz-legal-toc__list { padding-left: 1.25rem; line-height: 1.9; columns: 1; column-gap: var(--space-xl); }
.hz-legal-toc a { color: var(--brand-text); text-decoration: none; }
.hz-legal-toc a:hover { text-decoration: underline; }
@media (min-width: 768px) {
  .hz-legal-toc__list { columns: 2; }
}

/* Tables — full container width, horizontal scroll if narrower viewport.
   Compact font + padding on phone widths, comfortable spacing above. */
.hz-legal-table-wrap { overflow-x: auto; margin: var(--space-md) 0 var(--space-lg); -webkit-overflow-scrolling: touch; }
.hz-legal-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.hz-legal-table th, .hz-legal-table td { text-align: left; padding: var(--space-sm); border-bottom: 1px solid var(--border); vertical-align: top; }
.hz-legal-table thead th { background: var(--bg-alt); font-weight: 600; }
.hz-legal-table tbody tr:hover { background: var(--bg-alt); }
@media (min-width: 601px) {
  .hz-legal-table { font-size: 14px; }
  .hz-legal-table th, .hz-legal-table td { padding: var(--space-sm) var(--space-md); }
}
</style>
