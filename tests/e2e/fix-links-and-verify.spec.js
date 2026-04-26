/**
 * Fix Internal Links + BLC verification (with auto-remediation)
 *
 * Step 1 — Opens Title Optimiser, clicks "Fix Internal Links" (redirect-table based).
 * Step 2 — Full BLC scan. Categorises results:
 *            - Internal 404s on our domain   → actionable, auto-remediated
 *            - External dead (404/conn-fail)  → logged as warnings
 *            - External 403/405              → false-positives, ignored
 * Step 3 — For every remaining internal 404, looks up the current post at that
 *           date via the WP REST API, adds a manual redirect via the plugin admin,
 *           then runs "Fix Internal Links" again.
 * Step 4 — Re-runs BLC and asserts 0 internal broken links.
 *
 * Run via:
 *   bash run-ui-tests.sh tests/e2e/fix-links-and-verify.spec.js
 */
const { test, expect, request } = require('@playwright/test');

const PLUGIN_PAGE = '/wp-admin/tools.php?page=cs-seo-optimizer';
const base        = () => process.env.WP_BASE_URL;

// ── helpers ───────────────────────────────────────────────────────────────────

async function goToPlugin(page) {
    await page.goto(base() + PLUGIN_PAGE);
    await page.waitForSelector('.ab-tabs', { timeout: 15000 });
}

async function openTab(page, tabKey) {
    await page.click(`[data-tab="${tabKey}"]`);
    await page.waitForSelector(`#ab-pane-${tabKey}.active`, { timeout: 10000 });
}

async function clickFixInternalLinks(page) {
    await openTab(page, 'titleopt');
    await page.waitForSelector(
        '#ab-titleopt-posts-wrap table, #ab-titleopt-posts-wrap p',
        { timeout: 20000 }
    );

    // Step 1: Run the scan so the Fix button becomes enabled.
    const scanBtn = page.locator('#ab-titleopt-scan-links');
    await expect(scanBtn).toBeVisible({ timeout: 5000 });
    await scanBtn.click();

    // Wait for the scan to finish (log entry appears and fix button is either
    // enabled or still disabled if there is nothing to fix).
    const logWrap = page.locator('#ab-titleopt-log-wrap');
    await expect(logWrap).toBeVisible({ timeout: 10000 });
    const scanEntry = page.locator('#ab-titleopt-log .ab-log-entry').first();
    await expect(scanEntry).toBeVisible({ timeout: 30000 });
    console.log('[scan-links]', (await page.locator('#ab-titleopt-log').innerText()).trim());

    // Step 2: If the Fix button is enabled there are broken links to repair.
    const fixBtn = page.locator('#ab-titleopt-fix-links');
    await expect(fixBtn).toBeVisible({ timeout: 5000 });
    const isDisabled = await fixBtn.isDisabled();
    if (isDisabled) {
        console.log('[fix-links] No broken internal links found — skipping fix.');
        return 'no broken links found';
    }

    page.once('dialog', async d => { await d.accept(); });
    await fixBtn.click();

    await expect(logWrap).toBeVisible({ timeout: 10000 });
    const fixEntry = page.locator('#ab-titleopt-log .ab-log-entry').first();
    await expect(fixEntry).toBeVisible({ timeout: 20000 });

    const txt = await page.locator('#ab-titleopt-log').innerText();
    console.log('[fix-links]', txt.trim());
    return txt;
}

/** Run a full BLC scan and return categorised results. */
async function runBlcScan(page) {
    await openTab(page, 'blc');

    const scanBtn = page.locator('#blc-scan-btn');
    await expect(scanBtn).toBeVisible({ timeout: 5000 });
    await scanBtn.click();
    console.log('[BLC] scan started…');

    // Poll until "Done" appears in status
    const status   = page.locator('#blc-status');
    const deadline = Date.now() + 540_000;
    let statusTxt  = '';
    while (Date.now() < deadline) {
        statusTxt = await status.textContent().catch(() => '');
        if (/done/i.test(statusTxt) || /error/i.test(statusTxt)) break;
        await page.waitForTimeout(3000);
    }
    console.log('[BLC] final status:', statusTxt);

    const postsScanned  = await page.locator('#blc-total-posts').textContent().catch(() => '?');
    const linksChecked  = await page.locator('#blc-total-links').textContent().catch(() => '?');
    const brokenCount   = await page.locator('#blc-broken-count').textContent().catch(() => '0');
    const redirectCount = await page.locator('#blc-redirect-count').textContent().catch(() => '0');

    const ownDomain = new URL(base()).hostname;
    const rows      = await page.locator('#blc-tbody tr').all();

    const internalBroken  = [];
    const externalFalsePos = [];
    const externalDead    = [];

    for (const row of rows) {
        const cells  = await row.locator('td').allInnerTexts();
        // Use href attribute for the full URL (inner text is truncated at 70 chars)
        const href   = await row.locator('td:nth-child(2) a').getAttribute('href').catch(() => '');
        const status = (cells[3] || '').trim();

        let linkHost = '';
        try { linkHost = new URL(href).hostname; } catch (_) {}

        const isFalsePos = /403|405/i.test(status);
        const entry = { post: (cells[0] || '').trim(), url: href, anchor: (cells[2] || '').trim(), status };

        if (linkHost === ownDomain) {
            internalBroken.push(entry);
        } else if (isFalsePos) {
            externalFalsePos.push(entry);
        } else {
            externalDead.push(entry);
        }
    }

    console.log('┌─────────────────────────────────────────┐');
    console.log(`│  Posts scanned            : ${String(postsScanned).padStart(7)} │`);
    console.log(`│  URLs checked             : ${String(linksChecked).padStart(7)} │`);
    console.log(`│  Broken link instances    : ${String(brokenCount).padStart(7)} │`);
    console.log(`│  Redirect instances       : ${String(redirectCount).padStart(7)} │`);
    console.log('├─────────────────────────────────────────┤');
    console.log(`│  Internal broken (ours)   : ${String(internalBroken.length).padStart(7)} │`);
    console.log(`│  External dead (404/conn) : ${String(externalDead.length).padStart(7)} │`);
    console.log(`│  External false-pos(403)  : ${String(externalFalsePos.length).padStart(7)} │`);
    console.log('└─────────────────────────────────────────┘');

    return { internalBroken, externalDead, externalFalsePos };
}

/**
 * For an internal broken URL, queries the WP REST API to find the current post
 * at the same date, then returns its permalink (or null if not found).
 */
async function lookupCurrentUrl(brokenUrl) {
    try {
        const u    = new URL(brokenUrl);
        // Permalink structure: /YYYY/MM/DD/slug/
        const m    = u.pathname.match(/^\/(\d{4})\/(\d{2})\/(\d{2})\//);
        if (!m) return null;

        const [, y, mo, d] = m;
        const after  = `${y}-${mo}-${d}T00:00:00`;
        const before = `${y}-${mo}-${d}T23:59:59`;
        const apiUrl = `${base()}/wp-json/wp/v2/posts?after=${after}&before=${before}&per_page=10&_fields=id,slug,link,title&status=publish`;

        const resp = await fetch(apiUrl);
        if (!resp.ok) return null;
        const posts = await resp.json();
        if (!posts.length) return null;

        // If there is only one post on that date it must be the renamed one.
        // If multiple, try to pick the one whose slug overlaps most with the broken slug.
        if (posts.length === 1) return posts[0].link;

        const brokenSlug = u.pathname.replace(/\//g, ' ').trim().split(' ').pop();
        const brokenWords = brokenSlug.split('-');
        let best = null, bestScore = -1;
        for (const p of posts) {
            const words = p.slug.split('-');
            const score = brokenWords.filter(w => words.includes(w)).length;
            if (score > bestScore) { bestScore = score; best = p.link; }
        }
        return best;
    } catch (e) {
        console.warn('[lookup]', e.message);
        return null;
    }
}

/**
 * Adds a manual redirect via the plugin's Redirects tab.
 * from = path only, e.g. /2026/03/22/old-slug/
 * to   = full URL
 */
async function addManualRedirect(page, fromPath, toUrl) {
    // Redirects live inside the 'sitemap' tab
    await openTab(page, 'sitemap');

    const fromInput = page.locator('#cs-add-from');
    await fromInput.scrollIntoViewIfNeeded();
    await fromInput.fill(fromPath);
    await page.locator('#cs-add-to').fill(toUrl);

    const addBtn = page.locator('#cs-add-redirect');
    await addBtn.click();

    // Wait for success message
    const msg = page.locator('#cs-add-redirect-msg');
    await expect(msg).toBeVisible({ timeout: 8000 });
    const msgTxt = await msg.textContent();
    console.log(`[redirect] added ${fromPath} → ${toUrl} : ${msgTxt}`);
}

// ── tests ─────────────────────────────────────────────────────────────────────

test.describe('Fix Internal Links + BLC Verification', () => {

    test.setTimeout(1_200_000); // 20 min total — two BLC scans + remediation

    test('fix all broken internal links and confirm BLC clean', async ({ page }) => {

        // ── Step 1: Redirect-table based fix ─────────────────────────────────
        console.log('\n═══ Step 1: Fix Internal Links (redirect table) ═══');
        await goToPlugin(page);
        const fixLog = await clickFixInternalLinks(page);
        const updMatch = fixLog.match(/(\d+)\s+post/i);
        if (updMatch) console.log(`[INFO] ${updMatch[1]} post(s) updated from redirect table`);

        // ── Step 2: First BLC scan ────────────────────────────────────────────
        console.log('\n═══ Step 2: BLC scan pass 1 ═══');
        await goToPlugin(page);
        const { internalBroken, externalDead } = await runBlcScan(page);

        if (externalDead.length > 0) {
            console.warn(`\n[WARN] ${externalDead.length} genuinely dead external link(s) — fix manually:`);
            externalDead.forEach((e, i) => console.warn(`  [DEAD #${i+1}] ${e.post} | ${e.url} | ${e.status}`));
        }

        // ── Step 3: Auto-remediate remaining internal 404s ────────────────────
        if (internalBroken.length > 0) {
            console.log(`\n═══ Step 3: Auto-remediate ${internalBroken.length} internal broken link(s) ═══`);

            // De-duplicate by URL
            const seen     = new Set();
            const uniqueBroken = internalBroken.filter(e => {
                if (seen.has(e.url)) return false;
                seen.add(e.url); return true;
            });

            for (const entry of uniqueBroken) {
                console.log(`[INTERNAL-BROKEN] ${entry.post} | ${entry.url}`);
                const currentUrl = await lookupCurrentUrl(entry.url);
                if (!currentUrl) {
                    console.warn(`[SKIP] Could not find current post for: ${entry.url}`);
                    continue;
                }
                console.log(`[FOUND] current URL: ${currentUrl}`);
                const fromPath = new URL(entry.url).pathname;
                await goToPlugin(page);
                await addManualRedirect(page, fromPath, currentUrl);
            }

            // Re-run Fix Internal Links so post_content is updated
            console.log('\n═══ Step 3b: Re-run Fix Internal Links after adding redirects ═══');
            await goToPlugin(page);
            await clickFixInternalLinks(page);

        } else {
            console.log('\n[INFO] No internal broken links — skipping remediation step.');
        }

        // ── Step 4: Second BLC scan — final assertion ────────────────────────
        console.log('\n═══ Step 4: BLC scan pass 2 (final verification) ═══');
        await goToPlugin(page);
        const final = await runBlcScan(page);

        if (final.internalBroken.length > 0) {
            console.error('\n[FAIL] Still broken internal links after remediation:');
            final.internalBroken.forEach((e, i) =>
                console.error(`  [#${i+1}] Post: "${e.post}" | URL: ${e.url} | ${e.status}`)
            );
        }

        if (final.externalDead.length > 0) {
            console.warn('\n[WARN] External dead links (not our domain — fix manually):');
            final.externalDead.forEach((e, i) =>
                console.warn(`  [#${i+1}] Post: "${e.post}" | URL: ${e.url} | ${e.status}`)
            );
        }

        // Pass if no broken links on our own domain
        expect(final.internalBroken.length,
            `${final.internalBroken.length} internal broken link(s) remain on ${new URL(base()).hostname}`
        ).toBe(0);

        console.log('\n[PASS] ✅ Zero broken internal links — all good.');
    });

});
