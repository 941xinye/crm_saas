<div class="space-y-6">
    <!-- 标题 + 周期选择 -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">数据报表</h1>
            <p class="text-sm text-gray-500 mt-1">销售漏斗、员工业绩、月度趋势</p>
        </div>
        <div class="flex items-center gap-2">
            <label class="text-sm text-gray-600">趋势周期：</label>
            <select wire:model.live="period" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
                <option value="3">近 3 个月</option>
                <option value="6">近 6 个月</option>
                <option value="12">近 12 个月</option>
            </select>
            <span class="text-sm text-gray-400 ml-2">导出：</span>
            <button wire:click="exportCsv('trend')" class="text-xs px-2 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded transition-colors">趋势</button>
            <button wire:click="exportCsv('performance')" class="text-xs px-2 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded transition-colors">业绩</button>
            <button wire:click="exportCsv('stage')" class="text-xs px-2 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded transition-colors">阶段</button>
            <button wire:click="exportCsv('funnel')" class="text-xs px-2 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded transition-colors">漏斗</button>
        </div>
    </div>

    <!-- 核心指标卡片 -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
            <p class="text-xs text-gray-500">线索→客户转化率</p>
            <p class="text-2xl font-bold text-indigo-600 mt-1">{{ $funnel['conversion_rate'] }}%</p>
            <p class="text-xs text-gray-400 mt-1">{{ $funnel['converted_leads'] }} / {{ $funnel['total_leads'] }} 条线索</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
            <p class="text-xs text-gray-500">商机总额</p>
            <p class="text-2xl font-bold text-green-600 mt-1">¥{{ number_format(collect($stageBreakdown)->sum('total_value'), 2) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ collect($stageBreakdown)->sum('count') }} 个商机</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
            <p class="text-xs text-gray-500">联系人总数</p>
            <p class="text-2xl font-bold text-blue-600 mt-1">{{ $funnel['total_contacts'] }}</p>
            <p class="text-xs text-gray-400 mt-1">线索→联系人 {{ $funnel['contact_rate_from_lead'] }}%</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
            <p class="text-xs text-gray-500">商机总数</p>
            <p class="text-2xl font-bold text-purple-600 mt-1">{{ $funnel['total_opportunities'] }}</p>
            <p class="text-xs text-gray-400 mt-1">联系人→商机 {{ $funnel['opportunity_rate_from_contact'] }}%</p>
        </div>
    </div>

    <!-- 月度趋势图 -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">月度趋势</h3>
        <div id="trendChart" style="height: 360px;"></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- 销售漏斗 -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">销售漏斗</h3>
            <div id="funnelChart" style="height: 320px;"></div>
        </div>

        <!-- 商机阶段金额分布 -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">阶段金额分布</h3>
            <div id="stageChart" style="height: 320px;"></div>
        </div>
    </div>

    <!-- 按人业绩 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900">团队业绩</h3>
        </div>
        @if(count($performance) === 0)
            <p class="text-gray-500 text-sm text-center py-8">暂无商机数据</p>
        @else
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">负责人</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">商机数</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">成交额</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">赢单数</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">赢率</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($performance as $p)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $p['user_name'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-600">{{ $p['opp_count'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900">¥{{ number_format($p['total_value'], 2) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-600">{{ $p['won_count'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <span @class([
                                    'text-sm font-medium',
                                    $p['win_rate'] >= 50 ? 'text-green-600' : ($p['win_rate'] >= 20 ? 'text-yellow-600' : 'text-gray-600'),
                                ])>{{ $p['win_rate'] }}%</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js"></script>
    <script>
        // 月度趋势：双 Y 轴（数量 + 金额）
        const trendChart = echarts.init(document.getElementById('trendChart'));
        trendChart.setOption({
            tooltip: { trigger: 'axis' },
            legend: { data: ['新增线索', '新增公司', '新增商机', '商机总额'] },
            grid: { left: 50, right: 70, top: 40, bottom: 30 },
            xAxis: { type: 'category', data: @json($trend['labels']) },
            yAxis: [
                { type: 'value', name: '数量', minInterval: 1 },
                { type: 'value', name: '金额(¥)', axisLabel: { formatter: (v) => v >= 10000 ? (v/10000) + 'w' : v } },
            ],
            series: [
                { name: '新增线索', type: 'line', smooth: true, data: @json($trend['leads']), itemStyle: { color: '#eab308' } },
                { name: '新增公司', type: 'line', smooth: true, data: @json($trend['companies']), itemStyle: { color: '#3b82f6' } },
                { name: '新增商机', type: 'line', smooth: true, data: @json($trend['opportunities']), itemStyle: { color: '#8b5cf6' } },
                { name: '商机总额', type: 'bar', yAxisIndex: 1, data: @json($trend['values']), itemStyle: { color: '#10b981', opacity: 0.3 } },
            ],
        });

        // 销售漏斗（漏斗图）
        const funnelChart = echarts.init(document.getElementById('funnelChart'));
        const funnelData = [
            { name: '新线索', value: {{ $funnel['lead_by_status']['new'] ?? 0 }} },
            { name: '已联系', value: {{ $funnel['lead_by_status']['contacted'] ?? 0 }} },
            { name: '已验证', value: {{ $funnel['lead_by_status']['qualified'] ?? 0 }} },
            { name: '已转化', value: {{ $funnel['converted_leads'] }} },
        ].filter(d => d.value > 0);
        funnelChart.setOption({
            tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
            series: [{
                type: 'funnel',
                left: '10%',
                top: 10,
                bottom: 10,
                width: '80%',
                sort: 'descending',
                gap: 2,
                label: { show: true, position: 'inside' },
                data: funnelData.length ? funnelData : [{ name: '暂无数据', value: 1 }],
                color: ['#eab308', '#f97316', '#8b5cf6', '#10b981'],
            }],
        });

        // 阶段金额分布（饼图）
        const stageChart = echarts.init(document.getElementById('stageChart'));
        const stageData = @json(collect($stageBreakdown)->map(fn($s) => ['name' => $s['stage_name'], 'value' => round($s['total_value'], 2)])->values()->all());
        stageChart.setOption({
            tooltip: { trigger: 'item', formatter: '{b}: ¥{c} ({d}%)' },
            legend: { orient: 'vertical', right: 10, top: 'center' },
            series: [{
                type: 'pie',
                radius: ['40%', '70%'],
                center: ['40%', '50%'],
                avoidLabelOverlap: true,
                label: { formatter: '{b}\n¥{c}' },
                data: stageData.filter(d => d.value > 0),
            }],
        });

        window.addEventListener('resize', () => {
            trendChart.resize();
            funnelChart.resize();
            stageChart.resize();
        });
    </script>
    @endpush
