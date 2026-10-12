<?php

return [
    'menu' => '白标邮件（SMTP）',
    'title' => '白标邮件（SMTP）',
    'subtitle' => '配置租户自己的 SMTP 服务器与发件人身份，提醒、邀请等系统邮件将从你的品牌发出；未配置时使用平台默认发件。',

    'status_label' => '当前状态',
    'status_desc' => 'host 与发件地址填写完整即视为已启用。',
    'status_enabled' => '已启用',
    'status_disabled' => '未启用',

    'smtp_host' => 'SMTP 服务器',
    'smtp_port' => '端口',
    'smtp_encryption' => '加密方式',
    'encryption_none' => '不加密',
    'smtp_username' => 'SMTP 用户名',
    'smtp_password' => 'SMTP 密码',
    'smtp_password_hint' => '留空表示保留已保存的密码；密码加密存储，不会回显。',
    'from_address' => '发件地址（From）',
    'from_name' => '发件人名称',
    'save_btn' => '保存配置',
    'test_mail_btn' => '发送测试邮件',
    'test_mail_hint' => '测试邮件将发送到你当前登录账号的邮箱。',
    'disable_btn' => '停用白标',
    'disable_confirm' => '确定停用白标邮件？停用后系统邮件恢复为平台默认发件。',

    'test_mail_subject' => '【白标邮件】SMTP 配置测试',
    'test_mail_body' => '这是一封测试邮件，用于验证租户白标 SMTP 配置是否生效。收到即代表发送成功。',

    'flash_saved' => '白标邮件配置已保存。',
    'flash_disabled' => '白标邮件已停用，系统邮件恢复平台默认发件。',
    'flash_only_admin' => '仅租户管理员可以操作白标邮件设置。',
    'flash_not_configured' => '请先保存完整的 SMTP 配置（服务器与发件地址）再发送测试邮件。',
    'flash_test_sent' => '测试邮件已发送至 :email，请查收。',
    'flash_test_failed' => '测试邮件发送失败：:error',
];
