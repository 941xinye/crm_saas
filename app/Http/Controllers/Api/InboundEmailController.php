<?php

namespace App\Http\Controllers\Api;

use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Imap\ImapTransport;
use Symfony\Component\Mime\Email;

/**
 * 邮件转线索：
 * 1. POST /api/v1/inbound-email/{tenantSlug} 接收 Mailgun/SendGrid Inbound Parse JSON
 * 2. 可选：imap 命令轮询邮箱
 *
 * 接收方职责：
 * - 从邮件头提取 reply-to/to → 邮箱
 * - 从发件人提取姓名
 * - 从邮件体提取公司名
 * - 创建 Lead（状态=new）
 */
class InboundEmailController extends ApiController
{
    /**
     * 接收 Mailgun/SendGrid 风格的 inbound webhook JSON，创建 Lead。
     * 路由需要 auth:sanctum（防止任意人乱塞线索）。
     */
    public function handle(Request $request, string $tenantSlug)
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        // 从 JSON 或 multipart 中提取邮件内容
        $data = $request->all();

        // Mailgun 风格：sender, subject, body-plain / body-html
        $sender = $data['sender'] ?? $data['from'] ?? null;
        $subject = $data['subject'] ?? '';
        $body = $data['body-plain'] ?? $data['body'] ?? ($data['text'] ?? '');

        if (! $sender) {
            return $this->error(__('api.inbound_missing_sender'), 422);
        }

        // 解析发件人："John Doe <john@example.com>" -> 拆姓名 + 邮箱
        [$name, $email] = $this->parseSender($sender);

        // 提取公司名（简单策略：从 Subject 或 Body 中找）
        $company = $this->extractCompany($subject . ' ' . $body);

        $lead = Lead::create([
            'tenant_id' => $tenant->id,
            'name' => $name ?: ($email ?: '未知线索'),
            'email' => $email,
            'source' => 'inbound_email',
            'status' => 'new',
            'company_name' => $company,
        ]);

        return $this->created([
            'lead_id' => $lead->id,
            'message' => __('api.inbound_lead_created', ['name' => $lead->name]),
        ]);
    }

    /**
     * 解析发件人字段：提取姓名和邮箱。
     */
    private function parseSender(string $sender): array
    {
        if (preg_match('/"?([^"<]+)"?\s*<([^>]+)>/', $sender, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        // 纯邮箱：<john@example.com> 或 john@example.com
        if (preg_match('/<([^>]+)>/', $sender, $m)) {
            return ['', $m[1]];
        }
        if (filter_var($sender, FILTER_VALIDATE_EMAIL)) {
            return ['', $sender];
        }

        return [$sender, ''];
    }

    /**
     * 粗略提取公司名：从主题/正文中找首段不含 @ 的短语。
     */
    private function extractCompany(string $text): ?string
    {
        // 匹配常见的 "Company: XXX" 或 "From XXX" 格式
        if (preg_match('/(?:公司|Company|From)\s*[:：]?\s*([^\s@,，]{2,})/i', $text, $m)) {
            return trim($m[1]);
        }

        return null;
    }
}
