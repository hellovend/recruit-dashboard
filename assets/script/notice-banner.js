/**
 * notice-banner.js
 * 공지 배너 로더 — 페이지 상단에 활성 공지를 자동으로 표시합니다.
 *
 * 사용법:
 *   1. <link rel="stylesheet" href="../assets/css/notice-banner.css">
 *   2. <script src="../assets/script/notice-banner.js"></script>
 *      (darkmode.js 이후에 포함하면 다크모드 테마 상태를 유지합니다)
 *
 * 커스터마이징:
 *   window.NOTICE_API_PATH = '../api/notices.php';  // 기본값
 */

(function () {
    'use strict';

    /* ── 설정 ── */
    var API_PATH = (typeof window.NOTICE_API_PATH !== 'undefined')
        ? window.NOTICE_API_PATH
        : '../api/notices.php';

    /* ── 아이콘 맵 ── */
    var ICONS = {
        info:    '📘',
        success: '✅',
        warning: '⚠️',
        danger:  '🚨',
    };

    /* ── 세션스토리지 키 ── */
    var DISMISSED_KEY = 'notice_dismissed';

    /* ── 닫혔던 공지 ID 로드 ── */
    function getDismissed() {
        try {
            return JSON.parse(sessionStorage.getItem(DISMISSED_KEY) || '[]');
        } catch (e) { return []; }
    }

    /* ── 닫힌 공지 ID 저장 ── */
    function saveDismissed(arr) {
        try { sessionStorage.setItem(DISMISSED_KEY, JSON.stringify(arr)); } catch (e) {}
    }

    /* ── 배너 영역 생성 ── */
    function ensureBannerArea() {
        var area = document.getElementById('notice-banner-area');
        if (!area) {
            area = document.createElement('div');
            area.id = 'notice-banner-area';
            document.body.insertBefore(area, document.body.firstChild);
        }
        return area;
    }

    /* ── 배너 하나 렌더링 ── */
    function renderBanner(notice, area) {
        var dismissed = getDismissed();
        if (dismissed.indexOf(notice.id) !== -1) return; // 이미 닫은 것

        var banner = document.createElement('div');
        banner.className = 'notice-banner type-' + (notice.type || 'info');
        banner.dataset.noticeId = notice.id;

        var icon = ICONS[notice.type] || '📢';
        var contentHTML = notice.content
            ? '<div class="nb-content">' + escapeHtml(notice.content) + '</div>'
            : '';

        banner.innerHTML =
            '<span class="nb-icon">' + icon + '</span>' +
            '<div class="nb-body">' +
              '<div class="nb-title">' + escapeHtml(notice.title) + '</div>' +
              contentHTML +
            '</div>' +
            '<button class="nb-close" aria-label="닫기">&times;</button>';

        /* 닫기 버튼 */
        banner.querySelector('.nb-close').addEventListener('click', function () {
            closeBanner(banner, notice.id);
        });

        area.appendChild(banner);

        /* 배너 높이만큼 본문 밀기 */
        adjustBodyOffset();
    }

    /* ── 배너 닫기 ── */
    function closeBanner(banner, id) {
        banner.classList.add('closing');
        setTimeout(function () {
            if (banner.parentNode) banner.parentNode.removeChild(banner);
            adjustBodyOffset();
        }, 280);

        /* 세션 내에서 다시 표시 안 함 */
        var dismissed = getDismissed();
        if (dismissed.indexOf(id) === -1) {
            dismissed.push(id);
            saveDismissed(dismissed);
        }
    }

    /* ── 본문 상단 여백 조정 ── */
    function adjustBodyOffset() {
        setTimeout(function () {
            var area = document.getElementById('notice-banner-area');
            var h = area ? area.offsetHeight : 0;

            /* wrap1 / wrap2 등 절대위치 컨테이너 위치 보정 */
            var targets = document.querySelectorAll('#wrap, #wrap1, #wrap2');
            targets.forEach(function (el) {
                /* 원래 top이 50%인 경우만 보정 */
                var style = window.getComputedStyle(el);
                if (style.position === 'absolute' || style.position === 'fixed') {
                    el.style.marginTop = h > 0 ? (h / 2) + 'px' : '';
                }
            });

            document.body.classList.toggle('has-notice', h > 0);
        }, 10);
    }

    /* ── XSS 방지 ── */
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /* ── 메인: 공지 API 호출 ── */
    function loadNotices() {
        fetch(API_PATH)
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (data) {
                if (!data.success || !Array.isArray(data.notices)) return;
                if (data.notices.length === 0) return;

                var area = ensureBannerArea();
                data.notices.forEach(function (notice) {
                    renderBanner(notice, area);
                });
            })
            .catch(function (e) {
                /* 공지 로드 실패는 조용히 무시 — UX 방해 없음 */
                console.debug('[notice-banner] 로드 실패:', e.message);
            });
    }

    /* ── DOM 준비 후 실행 ── */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadNotices);
    } else {
        loadNotices();
    }

})();

