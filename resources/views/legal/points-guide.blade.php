@extends('layouts.app')

@section('title', '積分系統說明 - DISINFO_SCANNER')

@section('content')
<div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md p-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">積分系統說明</h1>
        <p class="text-sm text-gray-500 mb-8">最後更新日期：2026年1月2日</p>

        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-8">
            <p class="text-blue-700 font-medium">
                積分系統是本平台為感謝用戶貢獻而設計的獎勵機制。透過參與平台活動累積積分，高級會員可將積分兌換為會員期限延長。
            </p>
        </div>

        <div class="prose prose-blue max-w-none space-y-6 text-gray-700">

            {{-- 系統概述 --}}
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第一條 系統概述</h2>
                <p>積分系統讓您透過對平台的貢獻獲得回饋：</p>
                <div class="grid md:grid-cols-2 gap-4 mt-4">
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <h3 class="font-semibold text-green-800 mb-2">獲取積分</h3>
                        <ul class="list-disc list-inside text-green-700 space-y-1">
                            <li>透過 U-API 導入影片 (+1 積分/影片)</li>
                            <li>回報貼文內容 (+1 積分/貼文)</li>
                        </ul>
                    </div>
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                        <h3 class="font-semibold text-purple-800 mb-2">使用積分</h3>
                        <ul class="list-disc list-inside text-purple-700 space-y-1">
                            <li>兌換高級會員期限延長</li>
                            <li>目前匯率：{{ $pointsPerDay ?? 10 }} 積分 = 1 天</li>
                        </ul>
                    </div>
                </div>
            </section>

            {{-- 如何獲得積分 --}}
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第二條 如何獲得積分</h2>

                <h3 class="text-lg font-medium text-gray-800 mt-4 mb-2">2.1 U-API 導入影片</h3>
                <ol class="list-decimal list-inside ml-4 space-y-2">
                    <li>進入「影片列表」頁面</li>
                    <li>點擊右上角「U-API 導入」按鈕</li>
                    <li>貼上 YouTube 影片網址或 urtubeapi 連結</li>
                    <li>確認匯入後，系統自動發放 +1 積分</li>
                </ol>
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mt-3">
                    <p class="text-amber-700 text-sm">
                        <strong>注意：</strong>重複導入相同影片（無新增留言）不會獲得積分。
                    </p>
                </div>

                <h3 class="text-lg font-medium text-gray-800 mt-4 mb-2">2.2 回報貼文</h3>
                <ol class="list-decimal list-inside ml-4 space-y-2">
                    <li>在 Threads 平台發現可疑內容</li>
                    <li>透過官方管道回報至本平台</li>
                    <li>經審核確認後，系統自動發放 +1 積分</li>
                </ol>
            </section>

            {{-- 如何使用積分 --}}
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第三條 如何使用積分</h2>

                <h3 class="text-lg font-medium text-gray-800 mt-4 mb-2">3.1 兌換資格</h3>
                <ul class="list-disc list-inside ml-4 space-y-1">
                    <li>僅限<strong>高級會員</strong>可兌換積分</li>
                    <li>一般會員可累積積分，升級後即可兌換</li>
                </ul>

                <h3 class="text-lg font-medium text-gray-800 mt-4 mb-2">3.2 兌換流程</h3>
                <ol class="list-decimal list-inside ml-4 space-y-2">
                    <li>前往「設定」頁面</li>
                    <li>在「積分系統」區塊查看您的積分餘額</li>
                    <li>點擊「兌換全部」按鈕</li>
                    <li>確認後，系統一次性扣除所有可兌換積分並延長會員期限</li>
                </ol>

                <h3 class="text-lg font-medium text-gray-800 mt-4 mb-2">3.3 兌換規則</h3>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <ul class="list-disc list-inside space-y-2 text-blue-700">
                        <li>目前兌換比例：<strong>{{ $pointsPerDay ?? 10 }} 積分 = 1 天</strong> 會員期限延長</li>
                        <li>採「批次兌換」機制，一次兌換所有可用積分</li>
                        <li>不足 {{ $pointsPerDay ?? 10 }} 積分的餘額將保留至下次兌換</li>
                        <li>兌換比例可能因營運調整而變動，以設定頁面顯示為準</li>
                    </ul>
                </div>
            </section>

            {{-- 查看積分記錄 --}}
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第四條 查看積分記錄</h2>
                <ol class="list-decimal list-inside ml-4 space-y-2">
                    <li>前往「設定」頁面</li>
                    <li>在「積分系統」區塊點擊「查看記錄」按鈕</li>
                    <li>可查看所有積分獲取與兌換的歷史紀錄</li>
                </ol>
                <p class="mt-3">記錄包含：</p>
                <ul class="list-disc list-inside ml-4 space-y-1">
                    <li>時間戳記（GMT+8 台灣時間）</li>
                    <li>積分變動類型（U-API 導入、回報貼文、兌換期限）</li>
                    <li>積分變動數量</li>
                </ul>
            </section>

            {{-- 常見問題 --}}
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第五條 常見問題</h2>

                <div class="space-y-4">
                    <div class="border-l-4 border-gray-300 pl-4">
                        <p class="font-medium text-gray-900">Q：積分會過期嗎？</p>
                        <p class="text-gray-600 mt-1">A：目前積分不設有效期限，可長期累積使用。本公司保留未來調整此政策之權利。</p>
                    </div>

                    <div class="border-l-4 border-gray-300 pl-4">
                        <p class="font-medium text-gray-900">Q：一般會員可以累積積分嗎？</p>
                        <p class="text-gray-600 mt-1">A：可以。一般會員可透過 U-API 導入影片等方式累積積分，待升級為高級會員後即可兌換。</p>
                    </div>

                    <div class="border-l-4 border-gray-300 pl-4">
                        <p class="font-medium text-gray-900">Q：為什麼我導入影片沒有獲得積分？</p>
                        <p class="text-gray-600 mt-1">A：若該影片已存在於系統中且沒有新增留言，則視為重複導入，不會發放積分。積分僅在成功導入新內容時發放。</p>
                    </div>

                    <div class="border-l-4 border-gray-300 pl-4">
                        <p class="font-medium text-gray-900">Q：積分可以轉讓或折現嗎？</p>
                        <p class="text-gray-600 mt-1">A：不可以。積分僅限帳號本人使用，不可轉讓他人，亦不可兌換現金或其他有價物品。</p>
                    </div>

                    <div class="border-l-4 border-gray-300 pl-4">
                        <p class="font-medium text-gray-900">Q：帳號被停權，積分會怎樣？</p>
                        <p class="text-gray-600 mt-1">A：若因違反服務條款遭停權，帳號內積分將同時失效，且不予退還或補償。</p>
                    </div>
                </div>
            </section>

            {{-- 免責聲明 --}}
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第六條 免責聲明</h2>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <ol class="list-decimal list-inside space-y-3 text-gray-700">
                        <li>
                            <strong>積分無財產價值：</strong>積分為本平台之虛擬獎勵機制，不具有法定貨幣或財產價值。積分不可兌換現金、不可轉讓、不可繼承。
                        </li>
                        <li>
                            <strong>規則變更權利：</strong>本公司保留隨時修改積分獲取規則、兌換比例、使用方式之權利，修改後將於本頁面公告。繼續使用本服務即視為同意修改後之規則。
                        </li>
                        <li>
                            <strong>服務終止處理：</strong>若本服務終止營運，帳號內未使用之積分將失效，本公司不負任何補償或賠償責任。
                        </li>
                        <li>
                            <strong>系統異常處理：</strong>因系統異常導致之積分異動，本公司保留事後調整之權利。如發現積分異常增加，請主動聯繫客服，否則本公司有權逕行扣除。
                        </li>
                        <li>
                            <strong>濫用行為處理：</strong>如發現用戶透過不正當手段（包括但不限於自動化工具、多帳號濫用、虛假回報等）獲取積分，本公司有權取消該積分並停權帳號，不另行通知。
                        </li>
                        <li>
                            <strong>準據法與管轄：</strong>本說明之解釋與適用以中華民國法律為準據法，如有爭議，雙方同意以台灣台北地方法院為第一審管轄法院。
                        </li>
                    </ol>
                </div>
            </section>

            {{-- 其他規定 --}}
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第七條 其他規定</h2>
                <ol class="list-decimal list-inside ml-4 space-y-2">
                    <li>本說明為《服務條款》之補充，如有未盡事宜，悉依《服務條款》及相關法令規定辦理。</li>
                    <li>本公司保留最終解釋權。</li>
                    <li>如有任何疑問，請聯繫客服：themustbig+ds@gmail.com</li>
                </ol>
            </section>

        </div>

        <div class="mt-8 pt-6 border-t border-gray-200 flex flex-wrap gap-4">
            <a href="{{ route('settings.index') }}" class="text-blue-600 hover:text-blue-500">
                &larr; 前往設定頁面
            </a>
            <a href="{{ route('legal.terms') }}" class="text-gray-600 hover:text-gray-900">
                服務條款
            </a>
            <a href="{{ route('legal.consumer') }}" class="text-gray-600 hover:text-gray-900">
                消費者須知
            </a>
        </div>
    </div>
</div>
@endsection
