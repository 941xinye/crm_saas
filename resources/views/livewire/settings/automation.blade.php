<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">自动化工作流</h1>
            <p class="text-sm text-gray-500 mt-1">按业务事件自动执行动作：建任务 / 发通知 / 改阶段 / 改概率（企业版功能）</p>
        </div>
        @if($canManage && !$showForm)
        <button wire:click="showCreateForm"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
            新建规则
        </button>
        @endif
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
        {{ session('error') }}
    </div>
    @endif

    {{-- 创建/编辑表单 --}}
    @if($showForm)
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">
            {{ $editingId ? '编辑规则' : '新建规则' }}
        </h3>
        <form wire:submit="save" class="space-y-5">
            {{-- 基本信息 --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-500 mb-1">规则名称</label>
                    <input wire:model="name" type="text"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="如：商机停滞自动跟进">
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">目标对象</label>
                    <select wire:model="model"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                        @foreach($models as $class => $label)
                        <option value="{{ $class }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">触发时机</label>
                    <select wire:model="trigger"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                        @foreach($triggers as $trigger)
                        <option value="{{ $trigger }}">{{ $triggerLabels[$trigger] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- 条件 --}}
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-medium text-gray-500">触发条件（全部满足才执行，可留空表示无条件）</label>
                    <button type="button" wire:click="addCondition" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">+ 添加条件</button>
                </div>
                @foreach($conditions as $i => $condition)
                <div class="flex items-center gap-2 mb-2">
                    <select wire:model="conditions.{{ $i }}.field"
                            class="px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                        @foreach($conditionFields as $fieldKey => $fieldLabel)
                        <option value="{{ $fieldKey }}">{{ $fieldLabel }}</option>
                        @endforeach
                    </select>
                    <select wire:model="conditions.{{ $i }}.operator"
                            class="px-2 py-1.5 border border-gray-300 rounded-lg text-sm w-24">
                        @foreach($operators as $operator)
                        <option value="{{ $operator }}">{{ $operator }}</option>
                        @endforeach
                    </select>
                    <input wire:model="conditions.{{ $i }}.value" type="text" placeholder="值"
                           class="flex-1 px-3 py-1.5 border border-gray-300 rounded-lg text-sm">
                    <button type="button" wire:click="removeCondition({{ $i }})"
                            class="text-red-500 hover:text-red-700 text-sm px-2">移除</button>
                </div>
                @error("conditions.{$i}.value") <p class="text-red-500 text-xs -mt-1 mb-2">{{ $message }}</p> @enderror
                @endforeach
            </div>

            {{-- 动作 --}}
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-medium text-gray-500">执行动作（顺序执行，至少一个）</label>
                    <button type="button" wire:click="addAction" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">+ 添加动作</button>
                </div>
                @error('actions') <p class="text-red-500 text-xs mb-2">{{ $message }}</p> @enderror
                @foreach($actions as $i => $action)
                <div class="border border-gray-200 rounded-lg p-4 mb-3 bg-gray-50/50">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="text-xs text-gray-400 font-medium">#{{ $i + 1 }}</span>
                        <select wire:model="actions.{{ $i }}.type"
                                class="px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                            @foreach($actionLabels as $typeKey => $typeLabel)
                            <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="removeAction({{ $i }})"
                                class="ml-auto text-red-500 hover:text-red-700 text-sm">移除</button>
                    </div>

                    @if($action['type'] === 'create_activity')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">活动类型</label>
                            <select wire:model="actions.{{ $i }}.activity_type"
                                    class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                                @foreach(['task' => '任务', 'call' => '电话', 'email' => '邮件', 'meeting' => '会议', 'note' => '笔记'] as $t => $tl)
                                <option value="{{ $t }}">{{ $tl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">标题</label>
                            <input wire:model="actions.{{ $i }}.subject" type="text" placeholder="可用占位符 {title}"
                                   class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">备注</label>
                            <input wire:model="actions.{{ $i }}.notes" type="text" placeholder="如：已停滞 {stalled_days} 天，请及时跟进"
                                   class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">计划时间（支持 '+1 day' 相对格式）</label>
                            <input wire:model="actions.{{ $i }}.scheduled_at" type="text"
                                   class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm">
                        </div>
                    </div>
                    @elseif($action['type'] === 'notify_owner')
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">通知内容</label>
                        <input wire:model="actions.{{ $i }}.message" type="text" placeholder="如：商机「{title}」已停滞 {stalled_days} 天"
                               class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm">
                    </div>
                    @elseif($action['type'] === 'set_stage')
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">目标阶段</label>
                        <select wire:model="actions.{{ $i }}.stage_id"
                                class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm md:w-64">
                            <option value="">请选择阶段</option>
                            @foreach($stages as $stage)
                            <option value="{{ $stage->id }}">{{ $stage->name }}（{{ $stage->probability }}%）</option>
                            @endforeach
                        </select>
                        @error("actions.{$i}.stage_id") <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    @elseif($action['type'] === 'set_probability')
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">赢单概率（0-100）</label>
                        <input wire:model="actions.{{ $i }}.probability" type="number" min="0" max="100"
                               class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm md:w-40">
                        @error("actions.{$i}.probability") <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    @elseif($action['type'] === 'send_webhook')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 w-full">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">事件名（需与 Webhook 订阅的事件一致）</label>
                            <input wire:model="actions.{{ $i }}.event" type="text" placeholder="如 workflow.triggered"
                                   class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm">
                            @error("actions.{$i}.event") <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">附加信息（可选，支持占位符）</label>
                            <input wire:model="actions.{{ $i }}.message" type="text" placeholder="如：商机「{title}」触发自动化"
                                   class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm">
                        </div>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>

            {{-- 条件匹配模式 --}}
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">条件匹配模式</label>
                <select wire:model="conditionMatch" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm md:w-72">
                    @foreach(\App\Livewire\Settings\Automation::MATCH_MODES as $modeKey => $modeLabel)
                        <option value="{{ $modeKey }}">{{ $modeLabel }}</option>
                    @endforeach
                </select>
            </div>

            {{-- 优先级与启用 --}}
            <div class="flex items-center gap-6">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">优先级（越大越先执行）</label>
                    <input wire:model="priority" type="number"
                           class="w-32 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div class="flex items-center gap-2 pt-4">
                    <input type="checkbox" wire:model="isActive"
                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <label class="text-sm text-gray-700">启用</label>
                </div>
            </div>

            <div class="flex gap-3">
                <button type="submit"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
                    保存
                </button>
                <button type="button" wire:click="closeForm"
                        class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
                    取消
                </button>
            </div>
        </form>
    </div>
    @endif

    {{-- 规则列表 --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-3 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700">规则列表 ({{ $workflows->count() }})</h3>
        </div>
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">规则名称</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">对象 / 触发</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">条件</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">动作</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">状态</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">最近触发</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($workflows as $workflow)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 text-sm font-medium text-gray-800">
                        {{ $workflow->name }}
                        @if($workflow->priority > 0)
                        <span class="ml-1 px-1.5 py-0.5 text-xs rounded bg-gray-100 text-gray-500">P{{ $workflow->priority }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-sm text-gray-600 whitespace-nowrap">
                        {{ $models[$workflow->model] ?? $workflow->model }} ·
                        {{ $triggerLabels[$workflow->trigger] ?? $workflow->trigger }}
                    </td>
                    <td class="px-6 py-3 text-sm text-gray-500">
                        @if(count($workflow->conditions ?? []) > 0)
                            {{ count($workflow->conditions) }} 个条件
                        @else
                            <span class="text-gray-400">无条件</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-sm text-gray-600">
                        <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-50 text-indigo-700">
                            {{ count($workflow->actions ?? []) }} 个动作
                        </span>
                    </td>
                    <td class="px-6 py-3 text-sm">
                        @if($workflow->is_active)
                        <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700">启用</span>
                        @else
                        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-500">停用</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-sm text-gray-500">
                        {{ $workflow->last_triggered_at?->format('m-d H:i') ?? '从未' }}
                    </td>
                    <td class="px-6 py-3 text-right space-x-2 whitespace-nowrap">
                        @if($canManage)
                        <button wire:click="edit({{ $workflow->id }})"
                                class="text-gray-600 hover:text-gray-900 text-sm">编辑</button>
                        <button wire:click="toggleActive({{ $workflow->id }})"
                                class="text-blue-600 hover:text-blue-900 text-sm">
                            {{ $workflow->is_active ? '停用' : '启用' }}
                        </button>
                        <button wire:confirm="确定删除此规则？" wire:click="delete({{ $workflow->id }})"
                                class="text-red-600 hover:text-red-900 text-sm">删除</button>
                        @else
                        <span class="text-gray-400 text-xs">只读</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-400">
                        尚未配置自定义规则。系统内置示例规则见 config/workflows.php；创建规则后业务事件将自动触发动作。
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- 说明 --}}
    <div class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 text-xs text-gray-500 space-y-1">
        <p class="font-semibold text-gray-600">占位符：</p>
        <p><code class="font-mono">{title} {owner_id} {stalled_days} {activitable_id}</code> 等会在动作执行时被当前商机字段值替换。停滞扫描规则（stalled）由 <code class="font-mono">crm:run-workflows</code> 命令每日触发。详见工作流文档。</p>
    </div>
</div>
