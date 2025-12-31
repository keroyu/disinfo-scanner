@extends('layouts.app')

@section('title', '服務條款 - DISINFO_SCANNER')

@section('content')
<div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md p-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">服務條款</h1>
        <p class="text-sm text-gray-500 mb-8">最後更新日期：2025年1月1日</p>

        <div class="prose prose-blue max-w-none space-y-6 text-gray-700">

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第一條 總則</h2>
                <p>歡迎使用 DISINFO_SCANNER（以下簡稱「本服務」），本服務由投好壯壯有限公司（以下簡稱「本公司」）提供。在您註冊、登入或使用本服務前，請詳細閱讀以下服務條款。當您完成註冊程序或開始使用本服務時，即表示您已閱讀、瞭解並同意接受本服務條款之所有內容。</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第二條 服務說明</h2>
                <p>本服務為 YouTube 影片留言分析與存檔平台，主要功能包括：</p>
                <ul class="list-disc list-inside ml-4 space-y-1">
                    <li>匯入並存檔 YouTube 影片留言資料</li>
                    <li>分析留言模式，識別可疑的協同操作或自動化行為</li>
                    <li>提供留言統計與視覺化分析報告</li>
                    <li>留言資料匯出功能</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第三條 會員註冊與帳號管理</h2>
                <ol class="list-decimal list-inside ml-4 space-y-2">
                    <li>您於註冊時應提供正確、最新且完整的個人資料。</li>
                    <li>您有責任維護帳號及密碼的機密性，並對該帳號所進行的一切活動負完全責任。</li>
                    <li>若您發現帳號遭到未經授權的使用，應立即通知本公司。</li>
                    <li>本公司保留拒絕服務、終止帳號或刪除內容的權利。</li>
                </ol>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第四條 使用規範</h2>
                <p>使用本服務時，您同意遵守以下規範：</p>
                <ol class="list-decimal list-inside ml-4 space-y-2">
                    <li>不得利用本服務從事任何違法或侵害他人權益之行為。</li>
                    <li>不得干擾或破壞本服務之正常運作。</li>
                    <li>不得未經授權存取本服務之系統或資料。</li>
                    <li>不得散布惡意程式碼或進行任何可能危害系統安全之行為。</li>
                    <li>不得將本服務用於騷擾、誹謗或侵犯他人隱私之目的。</li>
                    <li>不得將本服務所取得之資料用於商業販售。</li>
                </ol>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第五條 智慧財產權</h2>
                <ol class="list-decimal list-inside ml-4 space-y-2">
                    <li>本服務所提供之軟體、程式、畫面設計、分析演算法等，其著作權、商標權及其他智慧財產權均屬本公司所有。</li>
                    <li>經由本服務匯入之 YouTube 留言資料，其原始著作權歸屬於各留言作者及 YouTube。</li>
                    <li>本服務產生之分析結果與統計資料，使用者得於合理範圍內使用，但不得主張為自有之智慧財產。</li>
                </ol>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第六條 YouTube API 服務條款</h2>
                <p>本服務使用 YouTube API 服務。使用本服務即表示您同意遵守：</p>
                <ul class="list-disc list-inside ml-4 space-y-1">
                    <li><a href="https://www.youtube.com/t/terms" class="text-blue-600 hover:text-blue-500" target="_blank" rel="noopener noreferrer">YouTube 服務條款</a></li>
                    <li><a href="https://policies.google.com/privacy" class="text-blue-600 hover:text-blue-500" target="_blank" rel="noopener noreferrer">Google 隱私權政策</a></li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第七條 免責聲明</h2>
                <ol class="list-decimal list-inside ml-4 space-y-2">
                    <li>本服務之分析結果僅供參考，不代表絕對正確之判斷。本公司不對分析結果之準確性提供任何明示或暗示之保證。</li>
                    <li>本公司不對因使用或無法使用本服務所造成之任何直接、間接、附帶或特殊損害負責。</li>
                    <li>對於第三方（包括 YouTube）服務之變更、中斷或終止，本公司不負任何責任。</li>
                    <li>本公司得隨時修改或終止全部或部分服務，無需事先通知。</li>
                </ol>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第八條 付費服務與退款</h2>
                <ol class="list-decimal list-inside ml-4 space-y-2">
                    <li>本服務可能包含付費功能或進階會員方案。</li>
                    <li>付費後如因系統問題導致無法使用，可依據消費者保護法規定申請退款。</li>
                    <li>因使用者個人因素要求退款，本公司將依個案評估處理。</li>
                </ol>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第九條 條款修訂</h2>
                <p>本公司保留隨時修訂本服務條款之權利。條款修訂後，將於本頁面公告。若您於修訂後繼續使用本服務，即視為您已同意接受修訂後之條款。建議您定期查閱本條款以瞭解最新內容。</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第十條 準據法與管轄</h2>
                <ol class="list-decimal list-inside ml-4 space-y-2">
                    <li>本服務條款之解釋與適用，以中華民國法律為準據法。</li>
                    <li>因本服務條款所生之爭議，雙方同意以台灣台北地方法院為第一審管轄法院。</li>
                </ol>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第十一條 聯絡資訊</h2>
                <p>如您對本服務條款有任何疑問，請聯絡：</p>
                <div class="bg-gray-50 rounded-lg p-4 mt-2">
                    <p><strong>投好壯壯有限公司</strong></p>
                    <p>電子郵件：themustbig+ds@gmail.com</p>
                </div>
            </section>

        </div>

        <div class="mt-8 pt-6 border-t border-gray-200">
            <a href="{{ route('register') }}" class="text-blue-600 hover:text-blue-500">
                &larr; 返回註冊頁面
            </a>
        </div>
    </div>
</div>
@endsection
