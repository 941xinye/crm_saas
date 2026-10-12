<?php

return [
    'menu' => 'White-label Email (SMTP)',
    'title' => 'White-label Email (SMTP)',
    'subtitle' => 'Configure your own SMTP server and sender identity so system emails (reminders, invitations) are sent from your brand. Falls back to the platform default when not configured.',

    'status_label' => 'Status',
    'status_desc' => 'Enabled once the host and from-address are both filled in.',
    'status_enabled' => 'Enabled',
    'status_disabled' => 'Not configured',

    'smtp_host' => 'SMTP Host',
    'smtp_port' => 'Port',
    'smtp_encryption' => 'Encryption',
    'encryption_none' => 'None',
    'smtp_username' => 'SMTP Username',
    'smtp_password' => 'SMTP Password',
    'smtp_password_hint' => 'Leave blank to keep the saved password. Stored encrypted, never displayed.',
    'from_address' => 'From Address',
    'from_name' => 'From Name',
    'save_btn' => 'Save',
    'test_mail_btn' => 'Send Test Email',
    'test_mail_hint' => 'The test email will be sent to your current account\'s inbox.',
    'disable_btn' => 'Disable',
    'disable_confirm' => 'Disable white-label email? System emails will fall back to the platform default sender.',

    'test_mail_subject' => '[White-label] SMTP Configuration Test',
    'test_mail_body' => 'This is a test email to verify your tenant white-label SMTP configuration. Receiving it means sending works.',

    'flash_saved' => 'White-label email configuration saved.',
    'flash_disabled' => 'White-label email disabled. System emails fall back to the platform default sender.',
    'flash_only_admin' => 'Only tenant administrators can manage white-label email settings.',
    'flash_not_configured' => 'Save a complete SMTP configuration (host and from address) before sending a test email.',
    'flash_test_sent' => 'Test email sent to :email. Please check your inbox.',
    'flash_test_failed' => 'Test email failed: :error',
];
