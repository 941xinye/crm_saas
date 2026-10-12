<?php

// 中文（简体）翻译：设置 - API Tokens
return [
    'title' => 'API Tokens',
    'subtitle' => '创建与管理访问 REST API 的令牌，请求时携带',
    'new_token_title' => 'Token 已创建，请立即保存（关闭后将无法再次查看）：',
    'name_placeholder' => 'Token 名称（如：报表集成）',
    'create_token' => '创建 Token',
    'scopes_label' => '权限范围（Scopes）',
    'scope_read' => '读取（GET 列表/详情/下载）',
    'scope_write' => '写入（创建/更新/删除/上传）',
    'scopes_hint' => '只读集成（如报表）建议仅勾选「读取」；读写操作（创建/更新/删除/上传）需要「写入」。',
    'col_name' => '名称',
    'col_scopes' => '权限范围',
    'col_created_at' => '创建时间',
    'col_last_used' => '最近使用',
    'never_used' => '从未使用',
    'confirm_revoke' => '确定吊销「:name」？使用该 Token 的集成将立即失效。',
    'revoke' => '吊销',
    'empty_state' => '还没有 Token',
];
