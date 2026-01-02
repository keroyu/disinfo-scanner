@extends('layouts.app')

@section('title', '消費者須知 - DISINFO_SCANNER')

@section('content')
<div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md p-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">消費者須知</h1>
        <p class="text-sm text-gray-500 mb-8">最後更新日期：2025年1月2日</p>

        <div class="bg-amber-50 border-l-4 border-amber-500 p-4 mb-8">
            <p class="text-amber-700 font-medium">
                購買前請詳細閱讀以下須知。完成付款即表示您已閱讀、瞭解並同意本須知所載之各項規定。
            </p>
        </div>

        <div class="prose prose-blue max-w-none space-y-6 text-gray-700">

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第一條 付款方式與流程</h2>
                <ol class="list-decimal list-inside ml-4 space-y-2">
                    <li>本服務之付費商品透過第三方支付平台「Portaly」進行付款處理。</li>
                    <li>點擊購買後，您將被導向至 Portaly 支付頁面完成付款。</li>
                    <li>付款成功後，系統將自動處理您的訂單，相關權益通常於 1 分鐘內生效。</li>
                    <li>Portaly 支援多種付款方式，詳情請參閱 Portaly 付款頁面說明。</li>
                </ol>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第二條 重要提醒</h2>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-red-700 font-semibold mb-2">請務必注意：</p>
                    <ul class="list-disc list-inside ml-4 space-y-2 text-red-700">
                        <li>付款時請使用與本站註冊<strong>相同的電子郵件地址</strong>，否則系統將無法自動配對您的帳號。</li>
                        <li>若使用不同的電子郵件付款，您的權益將無法自動生效，需聯繫客服進行人工處理。</li>
                        <li>付款前請確認您的網路連線穩定，避免重複付款。</li>
                    </ul>
                </div>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第三條 商品說明</h2>
                <ol class="list-decimal list-inside ml-4 space-y-2">
                    <li>各付費商品之詳細內容、價格及有效期限，以購買頁面所載資訊為準。</li>
                    <li>會員類商品（如高級會員）為訂閱期限制服務，到期後相關權益將終止。</li>
                    <li>本服務採「非自動續訂」制，到期後不會自動扣款，如需繼續使用請手動購買。</li>
                    <li>若您已是會員，再次購買相同類型商品，有效期限將從現有到期日起累加延長。</li>
                </ol>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第四條 數位商品特性聲明</h2>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="text-blue-800">
                        本服務所販售之商品為<strong>數位內容服務</strong>，具有以下特性：
                    </p>
                    <ul class="list-disc list-inside ml-4 mt-2 space-y-1 text-blue-700">
                        <li>購買後立即生效，無法退還或轉讓</li>
                        <li>服務內容依購買時之規格提供</li>
                        <li>因數位商品之特殊性質，一經購買即開始提供服務</li>
                    </ul>
                </div>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第五條 退款政策</h2>
                <p class="mb-4">依據《消費者保護法》第19條第1項但書規定，本服務販售之數位商品屬於「非以有形媒介提供之數位內容」，經消費者事先同意始提供服務，故<strong>不適用七日猶豫期之規定</strong>。</p>

                <h3 class="text-lg font-medium text-gray-800 mt-4 mb-2">5.1 不予退款之情形</h3>
                <ul class="list-disc list-inside ml-4 space-y-1">
                    <li>購買後因個人因素（如不想使用、誤購等）要求退款</li>
                    <li>已使用部分或全部服務權益後要求退款</li>
                    <li>因違反服務條款遭停權後要求退款</li>
                </ul>

                <h3 class="text-lg font-medium text-gray-800 mt-4 mb-2">5.2 可申請退款之情形</h3>
                <ul class="list-disc list-inside ml-4 space-y-1">
                    <li>系統異常導致付款成功但服務權益未正常生效</li>
                    <li>同一筆訂單遭重複扣款</li>
                    <li>其他經客服確認之技術性問題</li>
                </ul>

                <h3 class="text-lg font-medium text-gray-800 mt-4 mb-2">5.3 退款申請流程</h3>
                <ol class="list-decimal list-inside ml-4 space-y-2">
                    <li>請於付款後 7 日內透過客服信箱提出申請</li>
                    <li>申請時請提供：訂單編號、付款電子郵件、問題描述</li>
                    <li>客服將於 3-5 個工作天內回覆審核結果</li>
                    <li>核准之退款將透過原付款方式退還，作業時間依各支付機構規定</li>
                </ol>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第六條 發票說明</h2>
                <ol class="list-decimal list-inside ml-4 space-y-2">
                    <li>本服務依法開立電子發票，發票將於付款完成後寄送至您的付款電子郵件。</li>
                    <li>如需統一編號或其他發票需求，請於付款前聯繫客服。</li>
                    <li>發票相關問題請於付款後 7 日內提出。</li>
                </ol>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第七條 服務中斷與補償</h2>
                <ol class="list-decimal list-inside ml-4 space-y-2">
                    <li>因系統維護導致之計畫性服務中斷，將提前於網站公告。</li>
                    <li>因不可抗力因素（如天災、網路攻擊等）導致之服務中斷，本公司將盡速修復但不負賠償責任。</li>
                    <li>若因本公司系統問題導致連續服務中斷超過 24 小時，將視情況延長會員有效期限作為補償。</li>
                </ol>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第八條 價格變動</h2>
                <ol class="list-decimal list-inside ml-4 space-y-2">
                    <li>商品價格可能因營運需要而調整，調整後之價格適用於調整後之新訂單。</li>
                    <li>已完成付款之訂單不受價格調整影響。</li>
                    <li>重大價格調整將提前於網站公告。</li>
                </ol>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第九條 爭議處理</h2>
                <ol class="list-decimal list-inside ml-4 space-y-2">
                    <li>如對付款或服務有任何疑義，請先透過客服信箱聯繫本公司協商處理。</li>
                    <li>若協商未果，雙方同意依《消費者保護法》規定，向消費者保護官申訴或提起消費爭議調解。</li>
                    <li>因本須知所生之爭議，雙方同意以台灣台北地方法院為第一審管轄法院。</li>
                </ol>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第十條 聯絡資訊</h2>
                <p>如您對本消費者須知有任何疑問，或需要客服協助，請聯絡：</p>
                <div class="bg-gray-50 rounded-lg p-4 mt-2">
                    <p><strong>投好壯壯有限公司</strong></p>
                    <p>客服信箱：themustbig+ds@gmail.com</p>
                    <p class="text-sm text-gray-500 mt-2">客服回覆時間：週一至週五 10:00-18:00（國定假日除外）</p>
                </div>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第十一條 其他規定</h2>
                <ol class="list-decimal list-inside ml-4 space-y-2">
                    <li>本須知為《服務條款》之補充，如有未盡事宜，悉依《服務條款》及相關法令規定辦理。</li>
                    <li>本公司保留隨時修訂本須知之權利，修訂後將於本頁面公告。</li>
                    <li>繼續使用本服務即視為同意修訂後之須知內容。</li>
                </ol>
            </section>

        </div>

        <div class="mt-8 pt-6 border-t border-gray-200 flex flex-wrap gap-4">
            <a href="{{ route('upgrade') }}" class="text-blue-600 hover:text-blue-500">
                &larr; 返回升級頁面
            </a>
            <a href="{{ route('legal.terms') }}" class="text-gray-600 hover:text-gray-900">
                服務條款
            </a>
            <a href="{{ route('legal.privacy') }}" class="text-gray-600 hover:text-gray-900">
                隱私政策
            </a>
        </div>
    </div>
</div>
@endsection
