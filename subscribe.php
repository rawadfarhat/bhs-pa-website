<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Stay Connected | BHS Parents Association';
$activePage = 'connect';
require __DIR__ . '/includes/header.php';
$grades = [
    'KG1' => 'KG1', 'KG2' => 'KG2', 'KG3' => 'KG3',
    'G1' => 'Grade 1', 'G2' => 'Grade 2', 'G3' => 'Grade 3',
    'G4' => 'Grade 4', 'G5' => 'Grade 5', 'G6' => 'Grade 6',
    'G7' => 'Grade 7', 'G8' => 'Grade 8', 'G9' => 'Grade 9',
    'G10' => 'Grade 10', 'G11' => 'Grade 11', 'G12' => 'Grade 12',
];
?>
<section class="page-hero compact"><div class="shell narrow"><p class="eyebrow">Parent contact details</p><h1>Stay connected</h1><p>Share your preferred details so you receive clear and timely Parents Association updates.</p></div></section>
<section class="section"><div class="shell narrow"><form class="contact-form" action="<?= e(url('submit-contact.php')) ?>" method="post" data-contact-form>
<div class="form-status" role="status" aria-live="polite" hidden></div>
<input type="text" name="website" class="honeypot" tabindex="-1" autocomplete="off" aria-hidden="true">
<fieldset><legend>Family information</legend><div class="form-grid"><label class="field field-wide"><span>Parent or guardian full name *</span><input name="name" type="text" autocomplete="name" maxlength="190" required></label><label class="field"><span>Primary email *</span><input name="email" type="email" autocomplete="email" maxlength="190" required></label><label class="field"><span>Family BHS ID *</span><input name="parentBHSID" type="text" maxlength="40" required></label><label class="field"><span>Phone / WhatsApp number *</span><input name="phone" type="tel" autocomplete="tel" maxlength="50" required></label><div class="field"><span>Preferred contact method</span><div class="choice-row"><label><input type="radio" name="preferred_channel" value="Email" checked> Email</label><label><input type="radio" name="preferred_channel" value="Phone / WhatsApp"> Phone / WhatsApp</label></div></div></div></fieldset>
<fieldset><div class="fieldset-heading"><legend>Children</legend><button class="button button-secondary button-small" type="button" data-add-child>Add another child</button></div><div data-children><div class="child-row"><label class="field"><span>Child name *</span><input name="child_names[]" type="text" maxlength="190" required></label><label class="field"><span>Grade *</span><select name="child_grades[]" required><option value="">Select grade</option><?php foreach ($grades as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label><button class="icon-button remove-child" type="button" aria-label="Remove child" hidden>×</button></div></div></fieldset>
<label class="consent"><input type="checkbox" name="gdpr" value="1" required><span>I agree to the use of this information as described in the <a href="<?= e(url('terms.php')) ?>">Terms &amp; Conditions</a>.</span></label>
<button class="button submit-button" type="submit">Submit details</button><p class="form-note">Your information is stored securely and used only for official PA communication.</p>
</form></div></section>
<template id="child-template"><div class="child-row"><label class="field"><span>Child name *</span><input name="child_names[]" type="text" maxlength="190" required></label><label class="field"><span>Grade *</span><select name="child_grades[]" required><option value="">Select grade</option><?php foreach ($grades as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label><button class="icon-button remove-child" type="button" aria-label="Remove child">×</button></div></template>
<?php require __DIR__ . '/includes/footer.php'; ?>

