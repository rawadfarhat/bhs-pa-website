<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Privacy Policy | BHS Parents Association';
$activePage = 'privacy';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero compact"><div class="shell narrow"><p class="eyebrow">Website information</p><h1>Privacy Policy</h1><p>Policy updated: October 12, 2026; effective when published. How BHS Parents Association handles information in BHS PA Portal, public event forms, Google Sign-In, and optional Google Forms survey integration. Contact: weserve@bhs-pa.com.</p></div></section>
<section class="section"><div class="shell narrow prose">
      <h2>1) Information we collect</h2>
      <ul>
        <li>Account information (name, email, role, and authentication metadata).</li>
        <li>Operational records (transactions, approvals, fund requests, donations, vendor and event records).</li>
        <li>System activity (audit logs, session history, and error logs needed for security and troubleshooting).</li>
        <li>Attachments and supporting documents uploaded by authorized users.</li>
        <li>Parent and complaint records: family and student details supplied to the portal, complaint descriptions, messages, supporting files, workflow decisions, and access history. An anonymous complaint hides identity from routine handlers; the portal still retains identity for authorized access, safety, and audit purposes.</li>
        <li>Event form submissions: name, email, phone, additional numeric or text responses identified on the form, submission and update timestamps, and a consent timestamp when consent is requested and given.</li>
      </ul>

      <h2>2) Why we process data</h2>
      <p>Data is processed only to operate PA workflows, maintain financial accountability, enforce role-based access controls, generate reports, and support transparency and compliance. Event submissions are used to administer the relevant event, manage participation, and communicate about that event. Event organizers must explain the purpose of additional fields on the form and request only information needed for that purpose. Event submissions are not permission for unrelated marketing.</p>

      <h2>3) Access and sharing</h2>
      <p>Access is limited to authenticated users and controlled by roles/permissions. Data is shared internally on a need-to-know basis for PA operations. Individual event submissions are available only to authorized PA staff with Event Data access, including administrators; they are not published as participant lists. Information is not sold.</p>

      <h2>4) Security controls</h2>
      <p>The application uses authentication, permission checks, activity logging, and other technical safeguards designed to reduce unauthorized access or misuse. No system can guarantee absolute security.</p>

      <h2>5) Retention</h2>
      <p>Records are retained as long as needed for PA governance, audit history, legal obligations, or dispute resolution. Retention periods may vary by record type and policy requirements. Event submissions are retained for event administration and necessary follow-up, and longer where needed for accounting, legal obligations, or disputes. This does not imply automatic deletion on a fixed schedule.</p>

      <h2>6) Your responsibilities</h2>
      <p>Users must protect credentials, avoid sharing sensitive data outside authorized channels, and ensure entered information is accurate and up to date.</p>

      <h2>7) Third-party services</h2>
      <p>Where integrated services are used (for example, email delivery or infrastructure providers), data processing may occur solely to support core app functionality under appropriate safeguards.</p>

      <h2>8) Privacy requests and contact</h2>
      <p>To request access to, correction of, or deletion of your personal information (including account, Google connection, survey, complaint, or event data), or to withdraw consent where provided, email <a href="mailto:weserve@bhs-pa.com?subject=Privacy%20request">weserve@bhs-pa.com</a> and identify the relevant account, survey, complaint, or event and the contact details used. You can ask us to remove a Google account link and its stored credentials. Tell us which imported information your request concerns; revoking access alone does not erase local copies. Do not send sensitive documents unless requested through an appropriate channel. PA may need to verify your identity. Requests are reviewed in light of applicable obligations and any necessary record retention.</p>

      <h2>9) Policy updates</h2>
      <p>This Privacy Policy may be updated periodically. The version posted on this page is effective when published and supersedes prior versions.</p>
      <h2>10) Google Sign-In and Google Forms permissions</h2>
      <p>Google Sign-In uses the openid, email, and profile permissions to verify your Google account and match it to an authorized portal account. Google supplies an account identifier, email address and verification status, and may supply basic profile information such as your name and picture. We use identity information for authentication and account linking. We do not receive your Google password.</p>
      <p>The optional survey integration is a separate authorization for an authorized PA Google account. It requests openid and email to identify the connected account, https://www.googleapis.com/auth/forms.body.readonly to read the definitions of Forms selected for import, and https://www.googleapis.com/auth/forms.responses.readonly to read their responses. These permissions can cover Forms accessible to that account; the portal imports the Forms registered by authorized personnel. This integration does not request Gmail, Contacts, Calendar, Drive, or Sheets permissions and does not modify Forms or submit responses.</p>
      <p>Imported data includes form identifiers, titles, descriptions, questions, answer options and settings; response identifiers, submission and update timestamps, answers and comments; and respondent email addresses when the source Form collects them. Answers can contain personal information entered by respondents. The portal does not download survey file-upload attachments. We store the connected Google account identifier, granted permissions, connection status, and encrypted account email and OAuth access/refresh tokens. Refresh tokens allow synchronization while the authorizing user is offline.</p>

      <h2>11) Use, access, and protection of Google data</h2>
      <p>Google identity data supports sign-in and connection management. Google Forms data supports PA survey imports, synchronization, feedback review, aggregate analysis, reports, and authorized exports. Imported records are stored in the portal database on its hosting infrastructure. Survey OAuth tokens and separately stored respondent identity information are encrypted at the application level. Role and permission checks control response, identity, and export access; administrators have broader access. Free-text answers may themselves identify respondents, even where email collection is disabled.</p>
      <p>Authorized PA personnel may review survey content for these purposes. Hosting and infrastructure providers may process stored data to operate the portal. Information may be disclosed where required by law or needed to investigate abuse or protect security. Authorized report recipients may receive approved reports or exports; exporting staff are responsible for protecting those copies. Google processes authorization and API requests under its own policies. The pages also request fonts from Google Fonts, which receives the network information needed to serve them.</p>
      <p>We do not sell Google user data, use it for advertising or unrelated marketing, or transfer it to data brokers. We do not use Google user data to train generalized AI or machine-learning models. Survey AI processing is currently disabled. Our use and transfer of information received from Google APIs adheres to the <a href="https://developers.google.com/terms/api-services-user-data-policy">Google API Services User Data Policy</a>, including its Limited Use requirements.</p>

      <h2>12) Google data retention, disconnection, and deletion</h2>
      <p>Imported survey responses are retained until administrator deletion; there is no automatic age-based deletion schedule. Disconnecting an account in the survey integration removes its locally stored OAuth tokens and connection email, disables synchronization for its surveys, and attempts to revoke the Google grant. If Google revocation cannot be confirmed, you can revoke access directly through <a href="https://myaccount.google.com/connections">your Google Account connections</a>. Revocation prevents future authorized API access but does not delete previously imported survey data, portal account records, audit history, or exported copies.</p>
      <p>To request deletion of imported responses, respondent identity information, or account-link records, contact weserve@bhs-pa.com as described above. We review requests after identity and authority checks and explain any retention needed for governance, security, legal obligations, or disputes. Deleting local survey information does not delete the original Google Form or its responses. Copies in backups or independently downloaded exports may remain after deletion from active records; contact us about the scope of your request.</p>

      <h2>13) Browser storage and user choices</h2>
      <p>The portal uses browser storage and session information to keep you signed in and remember application preferences such as your theme. Protect shared devices and log out when finished. You may decline Google authorization; Google-connected features then remain unavailable. Revoking Google Sign-In access may require another enabled sign-in method to reach your portal account. You can contact us for account assistance and privacy requests.</p>
    </div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
