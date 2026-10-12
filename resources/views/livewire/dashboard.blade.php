<!-- Dashboard 视图 -->
<div class="space-y-6">
    <!-- 页面标题 -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">控制台</h1>
            <p class="text-sm text-gray-500 mt-1">欢迎回来！这是您的业务概览。</p>
        </div>
    </div>

    <!-- 统计卡片 -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- 公司数 -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">公司总数</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1">{{ $companyCount }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- 线索数 -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">线索总数</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1">{{ $leadCount }}</p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- 商机总额 -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">商机总额</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1">¥{{ number_format($opportunityValue, 2) }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- 本月新增 -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">本月新增公司</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1">{{ $monthNewCount }}</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- 下部分：两栏 -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- 商机阶段分布 -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 lg:col-span-2">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">商机阶段分布</h3>
            <div class="space-y-4">
                @forelse($stageStats as $stage)
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-700 font-medium">{{ $stage->name }}</span>
                            <span class="text-gray-500">{{ $stage->opportunities_count }} 个商机</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            @php
                                $maxCount = $stageStats->max('opportunities_count') ?: 1;
                                $percentage = ($stage->opportunities_count / $maxCount) * 100;
                            @endphp
                            <div class="h-2.5 rounded-full transition-all duration-500
                                @if($loop->index % 6 == 0) bg-blue-500
                                @elseif($loop->index % 6 == 1) bg-indigo-500
                                @elseif($loop->index % 6 == 2) bg-purple-500
                                @elseif($loop->index % 6 == 3) bg-pink-500
                                @elseif($loop->index % 6 == 4) bg-red-500
                                @else bg-green-500 @endif"
                                 style="width: {{ max($percentage, 3) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 text-sm text-center py-8">暂无商机数据</p>
                @endforelse
            </div>
        </div>

        <!-- 最近活动 -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">最近活动</h3>
            <div class="space-y-3">
                @forelse($recentActivities as $activity)
                    <div class="flex items-start space-x-3 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 truncate">{{ $activity->subject }}</p>
                            <p class="text-xs text-gray-500">
                                {{ $activity->user?->name ?? '系统' }} ·
                                {{ $activity->scheduled_at?->diffForHumans() ?? '未知时间' }}
                            </p>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 text-sm text-center py-8">暂无活动记录</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- 最近商机 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">最近商机</h3>
            <a href="{{ route('opportunities.index') }}" class="text-sm text-indigo-600 hover:text-indigo-500 font-medium">查看全部 →</a>
        </div>
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">商机</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">所属公司</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">阶段</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">金额</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($recentOpportunities as $opp)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <a href="{{ route('opportunities.show', $opp) }}" class="text-sm font-medium text-gray-900 hover:text-indigo-600">
                                {{ $opp->title }}
                            </a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $opp->company?->name ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs font-medium bg-indigo-100 text-indigo-700 rounded-full">
                                {{ $opp->stage?->name ?? '-' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900">¥{{ number_format($opp->value, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-gray-500 text-sm">暂无商机</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
