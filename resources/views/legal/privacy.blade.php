@extends('layouts.app')

@section('title', '隱私政策 - DISINFO_SCANNER')

@section('content')
<div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md p-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">隱私政策</h1>
        <p class="text-sm text-gray-500 mb-8">最後更新日期：2025年1月1日</p>

        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-8">
            <p class="text-blue-700 font-medium">
                我們重視您的隱私。本公司承諾：所有經由會員系統註冊收集的資料（包括電子郵件、密碼、身分驗證用資料、YouTube API 金鑰等）均僅由投好壯壯有限公司保管，絕不會洩漏或販售給任何第三方。
            </p>
        </div>

        <div class="prose prose-blue max-w-none space-y-6 text-gray-700">

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第一條 總則</h2>
                <p>本隱私政策說明投好壯壯有限公司（以下簡稱「本公司」）如何收集、使用、保護及處理您使用 DISINFO_SCANNER 服務（以下簡稱「本服務」）時所提供的個人資料。請您詳細閱讀本政策，以瞭解我們對您個人資料的處理方式。</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第二條 資料收集範圍</h2>
                <p>本服務可能收集以下類型的資料：</p>

                <h3 class="text-lg font-medium text-gray-800 mt-4 mb-2">2.1 帳號資料</h3>
                <ul class="list-disc list-inside ml-4 space-y-1">
                    <li>電子郵件地址（用於帳號註冊及通訊）</li>
                    <li>密碼（經加密儲存，無法被任何人讀取原始內容）</li>
                    <li>使用者姓名或暱稱</li>
                </ul>

                <h3 class="text-lg font-medium text-gray-800 mt-4 mb-2">2.2 身分驗證資料</h3>
                <ul class="list-disc list-inside ml-4 space-y-1">
                    <li>電子郵件驗證紀錄</li>
                    <li>登入驗證 Token</li>
                    <li>雙重驗證相關資訊（若啟用）</li>
                </ul>

                <h3 class="text-lg font-medium text-gray-800 mt-4 mb-2">2.3 技術資料</h3>
                <ul class="list-disc list-inside ml-4 space-y-1">
                    <li>IP 位址</li>
                    <li>瀏覽器類型及版本</li>
                    <li>作業系統資訊</li>
                    <li>存取時間及日期</li>
                    <li>Session 及 Cookie 資料</li>
                </ul>

                <h3 class="text-lg font-medium text-gray-800 mt-4 mb-2">2.4 YouTube API 資料</h3>
                <ul class="list-disc list-inside ml-4 space-y-1">
                    <li>您所查詢之 YouTube 影片資訊</li>
                    <li>影片留言內容及相關元資料</li>
                    <li>留言者公開顯示名稱及頻道資訊</li>
                    <li>您提供之 YouTube API 金鑰（經加密儲存）</li>
                </ul>

                <h3 class="text-lg font-medium text-gray-800 mt-4 mb-2">2.5 使用紀錄</h3>
                <ul class="list-disc list-inside ml-4 space-y-1">
                    <li>服務使用記錄（查詢、匯出等操作）</li>
                    <li>API 配額使用量</li>
                    <li>積分與會員狀態變更紀錄</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第三條 資料使用目的</h2>
                <p>本公司收集之資料僅用於以下目的：</p>
                <ol class="list-decimal list-inside ml-4 space-y-2">
                    <li><strong>提供服務：</strong>處理您的請求、提供留言分析功能。</li>
                    <li><strong>帳號管理：</strong>驗證身分、管理會員權限、處理密碼重設。</li>
                    <li><strong>服務改善：</strong>分析使用情況以優化服務體驗。</li>
                    <li><strong>安全維護：</strong>偵測並防止詐騙、濫用及安全威脅。</li>
                    <li><strong>法律遵循：</strong>遵守法律義務或回應合法之法律程序。</li>
                    <li><strong>客戶服務：</strong>回覆您的詢問及提供技術支援。</li>
                </ol>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第四條 資料保護承諾</h2>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-green-800 mb-2">核心承諾</h3>
                    <p class="text-green-700 mb-3">本公司鄭重承諾：</p>
                    <ul class="list-disc list-inside ml-4 space-y-2 text-green-700">
                        <li><strong>所有收集的資料僅由投好壯壯有限公司保管</strong></li>
                        <li><strong>絕不販售、交換或洩漏您的個人資料給任何第三方</strong></li>
                        <li><strong>絕不將您的資料用於廣告行銷目的</strong></li>
                        <li><strong>員工均簽署保密協議，違者將追究法律責任</strong></li>
                    </ul>
                </div>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第五條 資料安全措施</h2>
                <p>本公司採取以下措施保護您的資料安全：</p>
                <ul class="list-disc list-inside ml-4 space-y-2">
                    <li><strong>加密儲存：</strong>密碼、API 金鑰等敏感資料採用業界標準加密演算法儲存。</li>
                    <li><strong>傳輸加密：</strong>所有資料傳輸均使用 HTTPS/TLS 加密協定。</li>
                    <li><strong>存取控制：</strong>嚴格限制資料存取權限，僅授權人員可存取。</li>
                    <li><strong>定期備份：</strong>資料定期備份並存放於安全環境。</li>
                    <li><strong>安全稽核：</strong>定期進行安全檢測及漏洞掃描。</li>
                    <li><strong>日誌監控：</strong>系統存取日誌持續監控異常行為。</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第六條 資料保留期間</h2>
                <ul class="list-disc list-inside ml-4 space-y-2">
                    <li><strong>帳號資料：</strong>保留至您主動刪除帳號或要求刪除為止。</li>
                    <li><strong>留言分析資料：</strong>依服務功能需求保留，您可隨時刪除個人查詢紀錄。</li>
                    <li><strong>技術日誌：</strong>保留 90 天後自動刪除。</li>
                    <li><strong>法律要求資料：</strong>依法律規定之期間保留。</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第七條 第三方服務</h2>
                <p>本服務使用以下第三方服務，這些服務有其各自的隱私政策：</p>
                <ul class="list-disc list-inside ml-4 space-y-2">
                    <li>
                        <strong>YouTube API：</strong>本服務透過 YouTube API 取得公開影片及留言資料。
                        <br>
                        <span class="text-sm text-gray-500">請參閱：<a href="https://policies.google.com/privacy" class="text-blue-600 hover:text-blue-500" target="_blank" rel="noopener noreferrer">Google 隱私權政策</a></span>
                    </li>
                    <li>
                        <strong>電子郵件服務：</strong>用於發送驗證郵件及通知。
                        <br>
                        <span class="text-sm text-gray-500">僅傳遞必要之郵件地址及內容，不分享其他資料。</span>
                    </li>
                </ul>
                <p class="mt-2 text-sm italic">注意：本公司不會將您的個人資料提供給上述服務用於其自身目的。</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第八條 Cookie 使用</h2>
                <p>本服務使用 Cookie 技術以提供更好的使用體驗：</p>
                <ul class="list-disc list-inside ml-4 space-y-2">
                    <li><strong>必要性 Cookie：</strong>維持登入狀態、記錄偏好設定。</li>
                    <li><strong>安全性 Cookie：</strong>CSRF 保護、Session 管理。</li>
                </ul>
                <p class="mt-2">本服務不使用追蹤型或廣告型 Cookie。</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第九條 您的權利</h2>
                <p>依據個人資料保護法，您享有以下權利：</p>
                <ol class="list-decimal list-inside ml-4 space-y-2">
                    <li><strong>查詢及閱覽：</strong>您可查詢本公司是否持有您的個人資料。</li>
                    <li><strong>複製：</strong>您可請求取得您個人資料的副本。</li>
                    <li><strong>更正：</strong>您可請求更正不正確或不完整的資料。</li>
                    <li><strong>刪除：</strong>您可請求刪除您的個人資料（帳號刪除）。</li>
                    <li><strong>停止處理：</strong>您可請求停止處理您的個人資料。</li>
                </ol>
                <p class="mt-2">如需行使上述權利，請透過本政策末端之聯絡方式與我們聯繫。</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第十條 兒童隱私</h2>
                <p>本服務不針對未滿 16 歲之兒童提供。若您未滿 16 歲，請勿使用本服務或提供任何個人資料。若本公司發現已收集未滿 16 歲兒童之個人資料，將立即刪除該等資料。</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第十一條 政策修訂</h2>
                <p>本公司保留隨時修訂本隱私政策之權利。修訂後之政策將於本頁面公告，並更新「最後更新日期」。重大變更時，我們將透過電子郵件或服務內通知告知您。建議您定期查閱本政策以瞭解最新內容。</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第十二條 聯絡資訊</h2>
                <p>如您對本隱私政策有任何疑問或需行使個人資料相關權利，請聯絡：</p>
                <div class="bg-gray-50 rounded-lg p-4 mt-2">
                    <p><strong>投好壯壯有限公司</strong></p>
                    <p>個人資料保護聯絡窗口</p>
                    <p>電子郵件：themustbig+ds@gmail.com</p>
                </div>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">第十三條 準據法</h2>
                <p>本隱私政策之解釋與適用，以中華民國法律為準據法，並遵循中華民國個人資料保護法之規定。</p>
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
