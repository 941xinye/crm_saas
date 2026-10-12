<?php

// English translations: notifications (mail / database / robot webhook)
return [
    'greeting' => 'Hello!',

    'robot_title' => 'CRM Notification',
    'view_details' => 'View details: :url',

    'role_admin' => 'Admin',
    'role_manager' => 'Manager',
    'role_sales' => 'Sales',

    'tenant_invitation' => [
        'subject' => 'You are invited to join the ":tenant_name" team',
        'body' => ':tenant_name has invited you to join their team with the role: :role.',
        'action' => 'Accept Invitation',
        'expire_notice' => 'This invitation link will expire in 7 days.',
        'ignore_notice' => 'If you were not expecting this invitation, please ignore this email.',
    ],

    'trial_ending' => [
        'mail_subject' => 'Trial period ending soon: :tenant_name',
        'mail_body' => 'The trial period for tenant ":tenant_name" will end on :ends_at (:days_left days left).',
        'action' => 'View Plan & Subscription',
        'mail_footer' => 'After the trial ends, no new data can be added. Upgrade your plan to keep using all features.',
        'db_message' => 'Tenant ":tenant_name" trial ends on :ends_at (:days_left days left)',
    ],

    'queue_failure' => [
        'mail_subject' => 'System alert: :count failed queue jobs detected',
        'mail_body' => ':count new failed queue jobs were added in the past :window_hours hours. Please investigate as soon as possible.',
        'mail_commands' => 'Commands: `php artisan queue:failed` (view details) / `php artisan queue:retry all` (retry all).',
        'db_message' => ':count new failed queue jobs in the past :window_hours hours, please investigate (queue:failed / queue:retry)',
    ],

    'opportunity_stalled' => [
        'mail_subject' => 'Stalled opportunity: :opportunity_title',
        'mail_intro' => 'Your opportunity has not been updated since :created_date:',
        'action' => 'View Opportunity',
        'mail_footer' => 'This opportunity has made no progress for a long time. Please follow up soon.',
        'db_message' => 'Opportunity ":opportunity_title" has not been updated since :created_date, follow-up suggested',
    ],

    'opportunity_score_drop' => [
        'mail_subject' => 'Opportunity score dropped: :title',
        'mail_body' => 'The AI score for opportunity ":title" dropped from :old_score to :new_score. There may be a closing risk — please follow up soon.',
        'action' => 'View Opportunity',
        'db_message' => 'Opportunity ":title" score dropped from :old_score to :new_score, follow-up suggested',
    ],

    'opportunity_no_activity' => [
        'mail_subject' => 'Follow-up reminder: :opportunity_title',
        'mail_intro' => 'Your opportunity has had no activity records for :days_since_last days:',
        'action' => 'View Opportunity',
        'mail_footer' => 'Please schedule a follow-up activity soon to avoid losing the opportunity.',
        'db_message' => 'Opportunity ":opportunity_title" has had no activity for :days_since_last days, follow-up suggested',
    ],

    'opportunity_close_date' => [
        'mail_subject' => 'Opportunity closing reminder: :opportunity_title',
        'mail_intro' => 'Your opportunity is expected to close on :expected_close_date:',
        'action' => 'View Opportunity',
        'mail_footer' => 'Please follow up to ensure it closes on time.',
        'db_message' => 'Opportunity ":opportunity_title" is expected to close on :expected_close_date',
    ],

    'activity_due' => [
        'mail_subject' => 'Activity due reminder: :activity_subject',
        'mail_intro' => 'You have an activity due at :scheduled_at:',
        'action' => 'View Activity',
        'mail_footer' => 'Please handle it in time.',
        'db_message' => 'Activity ":activity_subject" is due at :scheduled_at',
    ],
];
