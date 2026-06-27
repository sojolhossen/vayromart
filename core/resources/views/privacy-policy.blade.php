<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy – VayroMart</title>
    <meta name="description" content="VayroMart's Privacy Policy – how we collect, use, and protect your personal data including our Facebook Messenger integration.">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f5f6fa;
            color: #222;
            line-height: 1.75;
        }
        header {
            background: #e84118;
            color: #fff;
            padding: 18px 0;
            text-align: center;
        }
        header a { color: #fff; text-decoration: none; font-size: 1.5rem; font-weight: 700; letter-spacing: 1px; }
        .container {
            max-width: 860px;
            margin: 36px auto 60px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 24px rgba(0,0,0,.08);
            padding: 48px 56px;
        }
        h1 { font-size: 2rem; color: #e84118; margin-bottom: 6px; }
        .updated { color: #888; font-size: .9rem; margin-bottom: 32px; display: block; }
        h2 { font-size: 1.18rem; color: #c0392b; margin: 32px 0 10px; padding-left: 12px; border-left: 4px solid #e84118; }
        p { margin-bottom: 14px; }
        ul { padding-left: 22px; margin-bottom: 14px; }
        ul li { margin-bottom: 6px; }
        .highlight-box {
            background: #fff8f0;
            border: 1px solid #f7b733;
            border-radius: 8px;
            padding: 20px 24px;
            margin: 28px 0;
        }
        .highlight-box h3 { color: #e84118; margin-bottom: 8px; font-size: 1rem; }
        a { color: #e84118; }
        .deletion-box {
            background: #f0f4ff;
            border: 1px solid #3498db;
            border-radius: 8px;
            padding: 20px 24px;
            margin: 28px 0;
        }
        .deletion-box h3 { color: #2980b9; margin-bottom: 8px; }
        footer {
            text-align: center;
            color: #aaa;
            font-size: .85rem;
            padding: 24px;
        }
        @media(max-width: 640px){
            .container { padding: 28px 18px; }
        }
    </style>
</head>
<body>

<header>
    <a href="{{ url('/') }}">🛒 VayroMart</a>
</header>

<div class="container">
    <h1>Privacy Policy</h1>
    <span class="updated">Last Updated: June 27, 2026 &nbsp;|&nbsp; Effective immediately</span>

    <p>
        At <strong>VayroMart</strong> (<a href="https://vayromart.com">www.vayromart.com</a>), we value your trust and are deeply committed to protecting your personal privacy. This Privacy Policy outlines how we collect, use, disclose, and safeguard your personal information when you visit our website and make a purchase, including through our Facebook Messenger AI customer support chatbot.
    </p>
    <p>By using our website or messaging our Facebook Page, you consent to the data practices described in this policy.</p>

    <h2>1. Information We Collect</h2>
    <p>We collect information necessary to process your orders and enhance your shopping experience:</p>
    <ul>
        <li><strong>Personal Data:</strong> Name, Shipping Address, Email Address, and Phone Number — collected when you create an account, place an order, or contact us.</li>
        <li><strong>Payment Details:</strong> For manual payments (bKash/Nagad), we collect your sender mobile number and Transaction ID for verification only. We do not store wallet PINs or account passwords.</li>
        <li><strong>Device & Log Data:</strong> IP address, browser type, operating system, and pages viewed for traffic analysis and site improvement.</li>
        <li><strong>Facebook Messenger Data:</strong> When you interact with our Facebook Page via Messenger, we receive your Facebook Page-scoped User ID, and the text content of your messages to provide automated customer support. This data is described in detail in Section 8 below.</li>
    </ul>

    <h2>2. How We Use Your Information</h2>
    <ul>
        <li>To process, fulfill, and deliver your orders (sharing address/phone with courier partners).</li>
        <li>To verify mobile banking payments and prevent fraudulent transactions.</li>
        <li>To communicate order updates, tracking details, and customer support responses — including via our AI chatbot on Facebook Messenger.</li>
        <li>To send promotional offers or newsletter updates (you can opt-out at any time).</li>
        <li>To optimize and improve our website's user experience.</li>
    </ul>

    <h2>3. Information Sharing and Disclosure</h2>
    <p>We never sell, rent, or trade your personal data to third parties. We only share your information with:</p>
    <ul>
        <li><strong>Delivery & Courier Services:</strong> Your name, phone number, and address to facilitate product delivery.</li>
        <li><strong>AI Service Providers:</strong> To power our Messenger chatbot, anonymized conversation context is sent to NVIDIA NIM API (an AI inference platform). No personally identifiable information beyond the conversation text is transmitted.</li>
        <li><strong>Legal Compliance:</strong> If required by law or valid requests by public authorities in Bangladesh.</li>
    </ul>

    <h2>4. Data Security</h2>
    <p>We implement strict technical and organizational security measures to protect your data from unauthorized access, alteration, or disclosure. Account data is password-protected. However, no internet transmission method is 100% secure.</p>

    <h2>5. Cookies and Tracking Technologies</h2>
    <p>VayroMart uses cookies to improve site functionality, remember your cart, and understand your preferences. You may disable cookies in your browser settings, though this may limit certain features.</p>

    <h2>6. Your Rights</h2>
    <p>You have the right to access, update, or correct your personal information at any time by logging into your profile. To delete your account or remove data from our active database, please contact our support team.</p>

    <h2>7. Changes to This Privacy Policy</h2>
    <p>We reserve the right to update this policy at any time. Changes will be posted on this page with an updated modification date. We encourage periodic review.</p>

    {{-- ================================================================ --}}
    {{-- META / FACEBOOK PLATFORM REQUIRED SECTIONS --}}
    {{-- ================================================================ --}}

    <h2>8. Facebook Messenger Integration & Data Usage</h2>
    <div class="highlight-box">
        <h3>📘 Required Disclosure for Meta Platform Usage</h3>
        <p>
            VayroMart operates an AI-powered customer support chatbot on our official Facebook Page. This chatbot is built using the <strong>Meta (Facebook) Messenger Platform API</strong> and the <strong>NVIDIA NIM AI API</strong>.
        </p>
        <p><strong>What data we receive from Facebook:</strong></p>
        <ul>
            <li>Your <strong>Page-scoped User ID</strong> (a unique, anonymized identifier assigned by Facebook to your interaction with our Page — not your personal Facebook ID).</li>
            <li>The <strong>text content</strong> of messages you send to our Facebook Page.</li>
        </ul>
        <p><strong>How we use this data:</strong></p>
        <ul>
            <li>To respond to your product inquiries, provide order status updates, and assist with Cash on Delivery orders entirely within Messenger.</li>
            <li>To temporarily store conversation context (session cache) for up to 2 hours to maintain conversation continuity.</li>
            <li>To log conversations in our internal database for quality assurance and customer support purposes.</li>
        </ul>
        <p><strong>What we do NOT do:</strong></p>
        <ul>
            <li>We do not share your Messenger conversation data with any third party other than our AI processing partner (NVIDIA NIM) as described above.</li>
            <li>We do not use your Messenger data for advertising or profiling purposes.</li>
            <li>We do not access your personal Facebook profile, friends list, or any data beyond what you send to our Page.</li>
        </ul>
        <p><strong>Data Retention:</strong> Messenger conversation logs are retained for up to <strong>90 days</strong> for customer support purposes. Temporary session cache expires automatically within 2 hours.</p>
    </div>

    <h2>9. Data Deletion Instructions (Facebook / Meta Required)</h2>
    <div class="deletion-box">
        <h3>🗑️ How to Request Data Deletion</h3>
        <p>
            In compliance with Meta Platform Policy, you have the right to request the deletion of any personal data we have collected from your interactions with our Facebook Page or Messenger chatbot.
        </p>
        <p><strong>To request data deletion, please use one of the following methods:</strong></p>
        <ul>
            <li>
                <strong>Email Request:</strong> Send an email to <a href="mailto:support@vayromart.com">support@vayromart.com</a> with the subject line <em>"Data Deletion Request – Facebook"</em>. Include your Facebook Page name or the approximate date(s) of your conversation. We will process your request within <strong>30 days</strong>.
            </li>
            <li>
                <strong>Facebook Settings:</strong> You can also remove VayroMart's app permissions directly from your Facebook account by going to <em>Facebook Settings → Apps and Websites</em> and removing VayroMart from the connected apps list.
            </li>
            <li>
                <strong>Contact Form:</strong> Visit our <a href="{{ url('/contact') }}">Contact Us</a> page and submit a data deletion request through the support form.
            </li>
        </ul>
        <p>
            Upon receiving your request, we will delete the following data associated with your Facebook Page-scoped User ID:
        </p>
        <ul>
            <li>All stored chatbot conversation messages and logs.</li>
            <li>Any cached order information linked to your Messenger session.</li>
            <li>Any guest account data created on your behalf during an in-chat order placement.</li>
        </ul>
        <p style="margin-top:12px; font-style: italic; color: #555;">
            Note: Certain data may be retained if required by law or for fraud prevention purposes. Order records may be retained for accounting and legal compliance.
        </p>
    </div>

    <h2>10. Contact Us</h2>
    <p>If you have any questions, concerns, or requests regarding this Privacy Policy or how your data is handled, please reach out to us:</p>
    <ul>
        <li><strong>Website:</strong> <a href="https://vayromart.com/contact">https://vayromart.com/contact</a></li>
        <li><strong>Email:</strong> <a href="mailto:support@vayromart.com">support@vayromart.com</a></li>
        <li><strong>Facebook Page:</strong> <a href="https://www.facebook.com/VayroMart" target="_blank">facebook.com/VayroMart</a></li>
    </ul>
</div>

<footer>
    &copy; {{ date('Y') }} VayroMart. All rights reserved. &nbsp;|&nbsp;
    <a href="{{ url('/') }}">Home</a>
</footer>

</body>
</html>
