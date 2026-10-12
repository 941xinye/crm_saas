<?php

// English translations: API & middleware messages
return [
    'auth_invalid_credentials' => 'Email or password is incorrect',
    'auth_email_unverified' => 'Please verify your email address first',
    'auth_platform_admin_forbidden' => 'Platform administrators cannot log in via the API',
    'auth_no_tenant' => 'You are not associated with any tenant',
    'auth_logged_out' => 'Logged out',
    'auth_platform_admin_only' => 'Access restricted to platform administrators',
    'crud_forbidden_resource' => 'You are not allowed to modify this resource',
    'webhook_secret_regenerated' => 'The secret has been regenerated. Please update the receiver promptly.',
    'webhook_not_found' => 'Webhook not found',
    'webhook_test_failed' => 'Test event delivery failed. Please check the URL, signature verification and network connectivity',
    'activity_invalid_morph_type' => 'activitable_type must be one of: company, contact, opportunity',
    'activity_morph_not_found' => 'The related object does not exist or does not belong to the current tenant',
    'inbound_missing_sender' => 'Missing sender field',
    'inbound_lead_created' => 'Lead created from email: :name',
    'stripe_invalid_signature' => 'Invalid signature',
    'stripe_processing_failed' => 'Processing failed',
    'tenancy_tenant_not_found' => 'Tenant not found or deactivated',
    'tenancy_tenant_forbidden' => 'You do not have access to this tenant',
    'tenancy_no_tenant_contact_admin' => 'You are not associated with any tenant. Please contact an administrator',
    'token_missing_scope' => 'API token is missing the :scope scope. Please regenerate your token in the settings.',
];
