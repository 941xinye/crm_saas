<?php

// 中文（简体）翻译：API 与中间件消息
return [
    'auth_invalid_credentials' => '邮箱或密码不正确',
    'auth_email_unverified' => '请先验证邮箱',
    'auth_platform_admin_forbidden' => '平台超管不可通过 API 登录',
    'auth_no_tenant' => '您尚未关联租户',
    'auth_logged_out' => '已注销',
    'auth_platform_admin_only' => '仅限平台管理员访问',
    'crud_forbidden_resource' => '无权操作此资源',
    'webhook_secret_regenerated' => 'Secret 已重新生成，请及时更新接收方。',
    'webhook_not_found' => 'Webhook 不存在',
    'webhook_test_failed' => '测试事件推送失败，请检查 URL、签名验证和网络连通性',
    'activity_invalid_morph_type' => 'activitable_type 必须是 company、contact 或 opportunity 之一',
    'activity_morph_not_found' => '关联对象不存在或不属于当前租户',
    'inbound_missing_sender' => '缺少 sender 字段',
    'inbound_lead_created' => '已从邮件创建线索「:name」',
    'stripe_invalid_signature' => '无效签名',
    'stripe_processing_failed' => '处理失败',
    'tenancy_tenant_not_found' => '租户不存在或已停用',
    'tenancy_tenant_forbidden' => '您无权访问该租户',
    'tenancy_no_tenant_contact_admin' => '您尚未关联租户，请联系管理员',
    'token_missing_scope' => 'API Token 缺少 :scope scope，请在设置中重新生成 Token。',
];
