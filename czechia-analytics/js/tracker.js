/* CZECHIA analytics */
(function (w, d, n) {
    'use strict';

    var cfg = w.czechiaAnalytics || {};
    var url = cfg.url || '/wp-admin/admin-ajax.php';
    var delay = typeof cfg.delay === 'number' ? cfg.delay : 1000;

    /* Automatizované prohlížeče, boty a stránky vložené v iframe neměříme. */
    if (n.webdriver) return;
    if (/bot|crawl|spider|slurp|headless|lighthouse|pagespeed|phantom|puppeteer|playwright|selenium/i.test(n.userAgent || '')) return;
    if (w.self !== w.top) return;
    /* Headless prohlížeč s podvrženým User-Agentem: prozradí ho Client Hints nebo chybějící jazyky. */
    if (n.userAgentData && (n.userAgentData.brands || []).some(function (b) { return /headless/i.test(b.brand); })) return;
    if (!n.languages || !n.languages.length) return;

    /* Sledovací parametry by tříštily statistiky stránek – z adresy je vynecháme. */
    var TRACKING_PARAM = /^(utm_\w+|fbclid|gclid|gbraid|wbraid|dclid|msclkid|yclid|twclid|igshid|mc_cid|mc_eid|_ga|_gl|srsltid|ref)$/i;

    function pageInfo() {
        var params = new URLSearchParams(w.location.search);
        var kept = new URLSearchParams();
        params.forEach(function (value, key) {
            if (!TRACKING_PARAM.test(key)) kept.append(key, value);
        });
        var query = kept.toString();
        return {
            path: w.location.pathname + (query ? '?' + query : ''),
            utm: params.get('utm_source') || ''
        };
    }

    function bodyHasClass(name) {
        return !!(d.body && d.body.classList.contains(name));
    }

    function bodyPostId() {
        var match = ((d.body && d.body.className) || '').match(/(?:^|\s)(?:postid|page-id)-(\d+)(?:\s|$)/);
        return match ? match[1] : 0;
    }

    var sent = false;

    function send() {
        if (sent) return;
        sent = true;

        var info = pageInfo();
        var data = new FormData();
        data.append('action', 'czechia_analytics_hit');
        data.append('p', info.path);
        data.append('u', info.utm);
        data.append('t', (d.title || '').slice(0, 255));
        data.append('r', d.referrer || '');
        data.append('sw', w.screen ? w.screen.width : 0);
        data.append('sh', w.screen ? w.screen.height : 0);
        data.append('tp', n.maxTouchPoints || 0);
        data.append('nf', (cfg.is404 || bodyHasClass('error404')) ? 1 : 0);
        data.append('id', cfg.postId || bodyPostId());

        if (n.sendBeacon && n.sendBeacon(url, data)) return;
        if (w.fetch) {
            w.fetch(url, { method: 'POST', body: data, keepalive: true, credentials: 'same-origin' }).catch(function () {});
        }
    }

    var visibleFor = 0;
    var since = 0;
    var timer = null;

    function start() {
        if (sent || timer || d.visibilityState !== 'visible' || d.prerendering) return;
        since = Date.now();
        timer = setTimeout(send, Math.max(0, delay - visibleFor));
    }

    function stop() {
        if (!timer) return;
        clearTimeout(timer);
        timer = null;
        visibleFor += Date.now() - since;
    }

    d.addEventListener('visibilitychange', function () {
        if (d.visibilityState === 'visible') {
            start();
        } else {
            stop();
        }
    });
    d.addEventListener('prerenderingchange', start);
    start();
})(window, document, navigator);
