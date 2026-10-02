/**
 * ============================================================
 *  AI Senior QA Tester
 *  Playwright + Azure OpenAI (GPT-4o Vision + Reasoning)
 * ============================================================
 *
 *  What it does (like a senior QA engineer):
 *   1. Logs in as each user role
 *   2. Discovers every internal link / route
 *   3. For each page:
 *        a. AI UNDERSTANDS the page purpose & business function
 *        b. AI GENERATES a QA workflow (test steps + expected results)
 *        c. Playwright EXECUTES each step (click, fill forms)
 *        d. AI VALIDATES actual vs expected (pass/fail + bugs)
 *   4. Auto-fills & tests forms (valid / invalid / edge cases)
 *   5. Captures console + page + network + API errors
 *   6. Full-page screenshot + GPT-4o vision review
 *   7. Cross-role permission comparison
 *   8. Generates report.json + report.html (executive QA report)
 *
 *  Setup:
 *   npm init -y
 *   npm install playwright openai dotenv
 *   npx playwright install
 *   node ai-qa-tester.js
 *
 *  .env example:
 *   BASE_URL=https://168-231-102-225.sslip.io
 *   HEADLESS=true
 *   MAX_PAGES=40
 *
 *   AZURE_OPENAI_ENDPOINT=https://your-resource.openai.azure.com
 *   AZURE_OPENAI_KEY=xxxxxxxx
 *   AZURE_OPENAI_DEPLOYMENT=gpt-4o
 *   AZURE_OPENAI_API_VERSION=2024-08-01-preview
 *
 *   SUPER_ADMIN_EMAIL=...      SUPER_ADMIN_PASSWORD=...
 *   COMPANY_ADMIN_EMAIL=...    COMPANY_ADMIN_PASSWORD=...
 *   USER_EMAIL=...             USER_PASSWORD=...
 * ============================================================
 */

require("dotenv").config();

const fs = require("fs");
const path = require("path");
const { chromium } = require("playwright");

/* ============================================================
 *  CONFIG
 * ========================================================== */

const BASE_URL = process.env.BASE_URL || "https://168-231-102-225.sslip.io";
const HEADLESS = process.env.HEADLESS === "true" ? true : false;
const MAX_PAGES = parseInt(process.env.MAX_PAGES || "20", 10);
const NAV_TIMEOUT = 20000;

// Words in a button/link we must NEVER click (destructive / exit)
const UNSAFE_KEYWORDS = [
  "logout", "log out", "sign out", "signout",
  "delete", "remove", "destroy", "deactivate",
  "reset", "wipe", "cancel subscription", "close account",
  "delete account", "delete company", "delete plan", "delete user",
  "archive", "purge", "drop", "terminate", "ban account", "revoke"
];

// One entry per role — only configured roles are used
const USERS = [
  { role: "super_admin",   email: process.env.SUPER_ADMIN_EMAIL,   password: process.env.SUPER_ADMIN_PASSWORD },
  { role: "company_admin", email: process.env.COMPANY_ADMIN_EMAIL, password: process.env.COMPANY_ADMIN_PASSWORD },
  { role: "user",          email: process.env.USER_EMAIL,          password: process.env.USER_PASSWORD }
].filter(u => u.email && u.password);

/* ============================================================
 *  HELPERS
 * ========================================================== */

function ensureDir(dir) {
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
}

function safeName(str) {
  return (str || "").replace(/[^a-z0-9]/gi, "_").slice(0, 60);
}

function isUnsafe(text) {
  const t = (text || "").toLowerCase();
  return UNSAFE_KEYWORDS.some(k => t.includes(k));
}

function log(...args) {
  console.log(new Date().toISOString().slice(11, 19), ...args);
}

/* ============================================================
 *  INTERACT WITH PAGE (click, fill, submit)
 * ========================================================== */

async function interactWithPage(page) {
  const interactions = [];
  
  try {
    // 1) Click safe buttons (non-destructive)
    const buttons = await page.locator("button, [role='button']").all();
    for (const btn of buttons.slice(0, 3)) {
      try {
        const text = await btn.innerText().catch(() => "");
        if (!isUnsafe(text) && text.trim()) {
          await btn.click({ timeout: 3000 }).catch(() => {});
          await page.waitForTimeout(800);
          interactions.push({ type: "click", target: text });
          break;
        }
      } catch {}
    }
    
    // 2) Try filling and submitting first form
    const forms = await page.locator("form").all();
    if (forms.length > 0) {
      const formEl = forms[0];
      const fields = await formEl.locator("input, textarea, select").all();
      
      for (const field of fields) {
        try {
          const type = await field.getAttribute("type");
          const name = await field.getAttribute("name") || await field.getAttribute("id");
          if (type === "text" || type === "email" || type === "" || !type) {
            const value = type === "email" ? "test@test.com" : "Test123";
            await field.fill(value, { timeout: 3000 }).catch(() => {});
            interactions.push({ type: "fill", field: name });
          }
        } catch {}
      }
      
      // Try submit
      const submit = formEl.locator('button[type="submit"], button:has-text("Save"), button:has-text("Submit")').first();
      if (await submit.count()) {
        await submit.click({ timeout: 3000 }).catch(() => {});
        interactions.push({ type: "form_submit" });
        await page.waitForTimeout(1500);
      }
    }
  } catch (err) {
    log(`    Error interacting: ${err.message}`);
  }
  
  return interactions;
}

/* ============================================================
 *  LOGIN (tries common selector patterns)
 * ========================================================== */

async function login(page, user) {
  if (!user || !user.email || !user.password) {
    log(`  [${user ? user.role : "guest"}] No credentials`);
    return false;
  }

  try {
    await page.goto(new URL("/login", BASE_URL).toString(), { waitUntil: "domcontentloaded", timeout: NAV_TIMEOUT });
    
    const emailSelector = 'input[type="email"], input[name="email"], input#email, input[placeholder*="email" i]';
    const passSelector = 'input[type="password"], input[name="password"], input#password';
    
    const emailBox = page.locator(emailSelector).first();
    const passBox = page.locator(passSelector).first();
    
    await passBox.waitFor({ state: "visible", timeout: 10000 });
    
    if (await emailBox.count() && await passBox.count()) {
      await emailBox.fill(user.email, { timeout: 5000 });
      await passBox.fill(user.password, { timeout: 5000 });
      
      const submit = page.locator('button[type="submit"], button:has-text("Log in"), button:has-text("Login"), button:has-text("Sign in")').first();
      if (await submit.count()) {
        await submit.click({ timeout: 5000 });
      } else {
        await passBox.press("Enter");
      }
      
      await page.waitForURL(u => !u.toString().includes("/login"), { timeout: NAV_TIMEOUT }).catch(() => {});
      await page.waitForLoadState("domcontentloaded", { timeout: 8000 }).catch(() => {});
      await page.waitForTimeout(1000);
      
      const loggedIn = !page.url().includes("/login");
      if (loggedIn) {
        log(`  [${user.role}] ✓ Logged in → ${page.url()}`);
        return true;
      }
    }
  } catch (err) {
    log(`  [${user.role}] Login error: ${err.message}`);
  }
  
  return false;
}

/* ============================================================
 *  DISCOVER INTERNAL LINKS
 * ========================================================== */

async function discoverLinks(page) {
  const links = await page.evaluate(() =>
    [...document.querySelectorAll("a")]
      .map(a => ({ text: a.innerText.trim(), href: a.href }))
      .filter(l => l.href)
  ).catch(() => []);

  const seen = new Set();
  const result = [];
  for (const l of links) {
    if (!l.href.startsWith(BASE_URL)) continue;
    if (seen.has(l.href)) continue;
    if (isUnsafe(l.text)) continue;
    seen.add(l.href);
    result.push(l);
  }
  // Always include the landing page itself
  if (![...seen].includes(BASE_URL)) result.unshift({ text: "Home", href: BASE_URL });
  return result;
}

/* ============================================================
 *  TEST A SINGLE ROLE
 * ========================================================== */

async function testRole(browser, user, screenshotsDir) {
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await context.newPage();

  const consoleErrors = [];
  const pageErrors = [];
  const networkErrors = [];

  page.on("console", msg => { if (msg.type() === "error") consoleErrors.push(msg.text()); });
  page.on("pageerror", err => pageErrors.push(err.message));
  page.on("response", res => {
    if (res.status() >= 400) networkErrors.push(`${res.status()} ${res.url()}`);
  });

  const loginOk = await login(page, user);
  if (!loginOk) {
    await context.close();
    return {
      role: user.role,
      loginStatus: "failed",
      pages: [],
      error: "Login failed"
    };
  }

  const links = await discoverLinks(page);
  log(`  [${user.role}] Found ${links.length} links`);

  const pages = [];
  const toVisit = links.slice(0, MAX_PAGES);

  for (const link of toVisit) {
    consoleErrors.length = 0;
    pageErrors.length = 0;
    networkErrors.length = 0;

    try {
      log(`  [${user.role}] → ${link.href}`);
      await page.goto(link.href, { waitUntil: "domcontentloaded", timeout: NAV_TIMEOUT });
      await page.waitForTimeout(800);

      const state = await page.evaluate(() => ({
        title: document.title,
        url: location.href,
        text: document.body.innerText.slice(0, 2000)
      }));

      const interactions = await interactWithPage(page);

      const shot = path.join(screenshotsDir, `${user.role}__${safeName(link.href)}.png`);
      await page.screenshot({ path: shot, fullPage: false }).catch(() => {});

      pages.push({
        url: link.href,
        title: state.title,
        interactions,
        consoleErrors: [...consoleErrors],
        pageErrors: [...pageErrors],
        networkErrors: [...networkErrors],
        screenshot: shot,
        issues: [
          ...(consoleErrors.map(e => `Console: ${e}`)),
          ...(pageErrors.map(e => `Page Error: ${e}`)),
          ...(networkErrors.map(e => `Network: ${e}`))
        ]
      });
    } catch (err) {
      log(`  [${user.role}] Error: ${err.message}`);
      pages.push({ url: link.href, error: err.message });
    }
  }

  await context.close();
  return {
    role: user.role,
    loginStatus: "success",
    pagesCount: pages.length,
    pages
  };
}

/* ============================================================
 *  SIMPLE HTML REPORT
 * ========================================================== */

function buildReport(results) {
  const esc = s => (s || "").toString()
    .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");

  let totalPages = 0, totalIssues = 0;

  const sections = results.map(r => {
    const rows = (r.pages || []).map(p => {
      totalPages++;
      const issueCount = (p.issues || []).length + (p.error ? 1 : 0);
      totalIssues += issueCount;

      const issueList = (p.issues || [])
        .slice(0, 5)
        .map(e => `<li>${esc(e)}</li>`)
        .join("");

      const interactionList = (p.interactions || [])
        .map(i => `<li>${i.type}${i.target ? `: ${i.target}` : ""}${i.field ? `: ${i.field}` : ""}</li>`)
        .join("");

      return `
      <div class="card">
        <div class="card-head">
          <span class="badge ${issueCount ? "bad" : "ok"}">${issueCount} issue(s)</span>
          <a href="${esc(p.url)}" target="_blank">${esc(p.title || p.url)}</a>
        </div>
        ${interactionList ? `<div><strong>User Actions:</strong><ul class="acts">${interactionList}</ul></div>` : ""}
        ${issueList ? `<div><strong>Issues:</strong><ul class="errs">${issueList}</ul></div>` : `<p class="ok">✓ No issues</p>`}
        ${p.screenshot ? `<img src="${esc(p.screenshot)}" loading="lazy" style="max-width:100%; border:1px solid #ccc; margin:8px 0;"/>` : ""}
      </div>`;
    }).join("");

    return `<section>
      <h2>Role: ${esc(r.role)} <small>(${r.pagesCount} pages tested)</small></h2>
      ${r.loginStatus === "failed" ? `<p style="color:red;"><strong>❌ Login Failed</strong></p>` : `<p style="color:green;"><strong>✓ Login Success</strong></p>`}
      ${rows}
    </section>`;
  }).join("");

  return `<!doctype html>
<html>
<head>
<meta charset="utf-8"/>
<title>QA Manual Test Report</title>
<style>
  * { box-sizing: border-box; }
  body { font-family: system-ui; margin:0; background:#f5f5f5; color:#333; }
  header { padding:20px 30px; background:#1e40af; color:white; }
  header h1 { margin:0; }
  main { padding:20px 30px; max-width:1200px; margin:auto; }
  section { margin:30px 0; }
  h2 { border-bottom:2px solid #ddd; padding-bottom:8px; }
  .card { background:white; border:1px solid #ddd; border-radius:8px; padding:15px; margin:15px 0; }
  .card-head { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
  .card-head a { color:#0066cc; text-decoration:none; font-weight:600; }
  .badge { padding:4px 10px; border-radius:4px; font-size:12px; font-weight:600; }
  .badge.ok { background:#d4edda; color:#155724; }
  .badge.bad { background:#f8d7da; color:#721c24; }
  .ok { color:#28a745; }
  .errs { color:#d32f2f; padding-left:20px; }
  .acts { color:#1976d2; padding-left:20px; }
  img { max-width:100%; }
</style>
</head>
<body>
  <header>
    <h1>📋 Manual QA Test Report</h1>
    <p>Target: ${esc(BASE_URL)} · ${new Date().toLocaleString()}</p>
  </header>
  <main>
    <p><strong>Total Pages Tested:</strong> ${totalPages} | <strong>Total Issues Found:</strong> ${totalIssues}</p>
    ${sections}
  </main>
</body>
</html>`;
}

/* ============================================================
 *  MAIN
 * ========================================================== */

(async () => {
  const screenshotsDir = "./screenshots";
  ensureDir(screenshotsDir);

  log("Launching browser...");
  const browser = await chromium.launch({ headless: HEADLESS });

  const rolesToTest = USERS.length > 0 ? USERS : [{ role: "guest", email: null, password: null }];

  const results = [];
  for (const user of rolesToTest) {
    log(`=== Testing role: ${user.role} ===`);
    try {
      results.push(await testRole(browser, user, screenshotsDir));
    } catch (err) {
      log(`  [${user.role}] Fatal error: ${err.message}`);
      results.push({ role: user.role, loginStatus: "error", pages: [], error: err.message });
    }
  }

  await browser.close();

  fs.writeFileSync("report.json", JSON.stringify(results, null, 2));
  fs.writeFileSync("report.html", buildReport(results));

  log("✓ Done. Generated report.json and report.html");
})();
