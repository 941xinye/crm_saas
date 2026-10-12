<?php

// English translations: automation workflow config sample rules ({title} / {stalled_days} placeholders are replaced by WorkflowEngine)
return [
    'rule_stalled' => [
        'name' => 'Auto follow-up for stalled opportunities',
        'activity_subject' => 'Follow up on stalled opportunity "{title}"',
        'activity_notes' => 'Auto reminder: opportunity "{title}" has been inactive for over {stalled_days} days. Please follow up promptly.',
        'notify_message' => 'Opportunity "{title}" has been stalled for {stalled_days} days. A follow-up task has been created for you.',
    ],

    'rule_high_probability_stage' => [
        'name' => 'High-probability stage reminder',
        'activity_subject' => 'Opportunity "{title}" entered a high-probability stage',
        'activity_notes' => 'Opportunity "{title}" win probability ≥70%. Consider arranging contract review and legal involvement soon.',
    ],
];
