<?php

// English translations: settings_tokens
return [
    'title' => 'API Tokens',
    'subtitle' => 'Create and manage tokens for the REST API. Send them with requests as',
    'new_token_title' => 'Token created, please save it now (it cannot be viewed again after closing):',
    'name_placeholder' => 'Token name (e.g. Report integration)',
    'create_token' => 'Create Token',
    'scopes_label' => 'Scopes',
    'scope_read' => 'Read (GET list/detail/download)',
    'scope_write' => 'Write (create/update/delete/upload)',
    'scopes_hint' => 'Read-only integrations (e.g. reports) should only check "Read"; read-write operations (create/update/delete/upload) require "Write".',
    'col_name' => 'Name',
    'col_scopes' => 'Scopes',
    'col_created_at' => 'Created At',
    'col_last_used' => 'Last Used',
    'never_used' => 'Never used',
    'confirm_revoke' => 'Revoke ":name"? Integrations using this Token will stop working immediately.',
    'revoke' => 'Revoke',
    'empty_state' => 'No tokens yet',
];
