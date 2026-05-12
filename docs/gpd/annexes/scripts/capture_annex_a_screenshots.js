const fs = require("fs");
const path = require("path");
const { spawn } = require("child_process");
const puppeteer = require("../.tooling/node_modules/puppeteer");

const repoRoot = path.resolve(__dirname, "../../../..");
const annexDir = path.resolve(repoRoot, "docs/gpd/annexes");
const screenshotDir = path.join(annexDir, "screenshots");
const manifestPath = path.join(screenshotDir, "SCREENSHOT_MANIFEST.md");
const annexAPath = path.join(annexDir, "ANNEX_A_OPERATIONAL_WORKFLOW_SCREENSHOTS.md");
const baseUrl = process.env.KODUS_CAPTURE_BASE_URL || "http://127.0.0.1:8097";
const username = process.env.KODUS_CAPTURE_USERNAME || "annex_capture_admin";
const password = process.env.KODUS_CAPTURE_PASSWORD || "Dswd123$";

const targets = [
  { fig: "A-01", label: "LOGIN", route: "/?local=1", title: "Login", auth: false },
  { fig: "A-02", label: "DASHBOARD", route: "/home", title: "Dashboard", auth: true },
  { fig: "A-03", label: "PROGRAM_TARGETS", route: "/implementation-status/program-targets", title: "Program Targets", auth: true },
  { fig: "A-04", label: "PROGRAM_ACTIVITIES", route: "/implementation-status/program-activities", title: "Program Activities", auth: true },
  { fig: "A-05", label: "LAWA_BINHI_SUMMARY", route: "/implementation-status/lawa-summary", title: "LAWA/BINHI Summary", auth: true },
  { fig: "A-06", label: "MEB_IMPORT", route: "/pages/data-tracking-meb", title: "MEB Import", auth: true },
  { fig: "A-07", label: "MEB_VALIDATION", route: "/pages/data-tracking-meb-validation", title: "MEB Validation", auth: true },
  { fig: "A-08", label: "MEBIS_CONSOLIDATOR", route: "/mebis-consolidator/", title: "MEBIS Consolidator", auth: true },
  { fig: "A-09", label: "DEDUPLICATION", route: "/deduplication/", title: "Deduplication", auth: true },
  { fig: "A-10", label: "CROSSMATCH", route: "/crossmatch/", title: "Crossmatch", auth: true },
  { fig: "A-11", label: "REPORTS", route: "/pages/summary/sectoral", title: "Reports", auth: true },
  { fig: "A-12", label: "INBOX_MESSENGER", route: "/inbox/", title: "Inbox/Messenger", auth: true },
  { fig: "A-13", label: "NOTIFICATIONS", route: "/notifications/", title: "Notifications", auth: true },
  { fig: "A-14", label: "SETTINGS_PROFILE", route: "/settings", title: "Settings/Profile", auth: true },
  { fig: "A-15", label: "AUDIT_LOGS", route: "/admin/audit_logs", title: "Audit Logs", auth: true },
  { fig: "A-16", label: "USER_MANAGEMENT", route: "/admin/users_management", title: "User Management", auth: true },
];

function ensureDir(dir) {
  fs.mkdirSync(dir, { recursive: true });
}

function delay(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function startServer() {
  return new Promise((resolve, reject) => {
    const router = path.join(annexDir, "scripts/php_capture_router.php");
    const server = spawn("php", ["-S", "127.0.0.1:8097", "-t", repoRoot, router], {
      cwd: repoRoot,
      env: {
        ...process.env,
        APP_URL: "http://127.0.0.1:8097",
        APP_PUBLIC_ROOT: "/",
        APP_BASE_PATH: "/",
        KODUS_SOCKET_ENABLED: "false",
      },
      stdio: ["ignore", "pipe", "pipe"],
    });

    let ready = false;
    const timer = setTimeout(() => {
      if (!ready) {
        ready = true;
        resolve(server);
      }
    }, 1200);

    server.on("error", reject);
    server.stderr.on("data", (chunk) => {
      const text = chunk.toString();
      if (!ready && /Development Server|started/i.test(text)) {
        clearTimeout(timer);
        ready = true;
        resolve(server);
      }
    });
  });
}

async function sanitizePage(page) {
  await page.evaluate(() => {
    const style = document.createElement("style");
    style.setAttribute("data-annex-sanitize", "true");
    style.textContent = `
      input[type="password"], input[name*="token" i], input[value*="$"], code, .select2-selection__choice {
        filter: blur(6px) !important;
      }
      table tbody td, table tbody th, .dataTables_wrapper tbody td, .mailbox-message-preview,
      .direct-chat-text, .conversation-preview, .message-preview, .user-panel .info,
      .profile-user-img + h3, .description-block .description-text {
        filter: blur(5px) !important;
      }
      img[src*="avatar"], img[src*="dist/img"], .user-image, .profile-user-img {
        filter: blur(6px) !important;
      }
    `;
    document.head.appendChild(style);

    const sensitivePatterns = [
      /[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi,
      /\b(?:Bearer|token|secret|password|client_secret)\b\s*[:=]\s*[^\\s]+/gi,
      /\b\\d{4}-\\d{2}-\\d{2}\\b/g,
      /\b09\\d{9}\\b/g,
    ];

    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    for (const node of nodes) {
      let value = node.nodeValue || "";
      for (const pattern of sensitivePatterns) {
        value = value.replace(pattern, "[SANITIZED]");
      }
      node.nodeValue = value;
    }
  });
}

async function setYear(page) {
  await page.goto(`${baseUrl}/select_year`, { waitUntil: "networkidle2", timeout: 45000 });
  await page.evaluate(() => {
    const preferred = document.querySelector('input[name="year"][value="2026"]') || document.querySelector('input[name="year"]');
    if (preferred) preferred.checked = true;
  });
  await Promise.all([
    page.waitForNavigation({ waitUntil: "networkidle2", timeout: 45000 }).catch(() => null),
    page.click('button[type="submit"], .year-submit').catch(() => null),
  ]);
}

async function login(page) {
  await page.goto(`${baseUrl}/__capture_login?user=${encodeURIComponent(username)}&year=2026`, { waitUntil: "networkidle2", timeout: 45000 });
  await page.goto(`${baseUrl}/home`, { waitUntil: "networkidle2", timeout: 45000 });
  const url = page.url();
  if (/reset-password|verify-2fa|login|local=1/.test(url)) {
    throw new Error(`Login did not reach authenticated workspace. Current URL: ${url}`);
  }
}

async function captureTarget(page, target) {
  const fileName = `ANNEX_A_FIGURE_${target.fig}_${target.label}.png`;
  const outputPath = path.join(screenshotDir, fileName);
  const routeUrl = `${baseUrl}${target.route}`;
  const row = {
    figure: target.fig,
    filename: fileName,
    route: target.route,
    status: "Captured",
    note: "",
  };

  try {
    await page.goto(routeUrl, { waitUntil: "networkidle2", timeout: 60000 });
    await delay(1800);
    await sanitizePage(page);
    await page.screenshot({ path: outputPath, fullPage: false });
  } catch (error) {
    row.status = "Failed";
    row.note = String(error.message || error);
    const fallbackHtml = `<html><body style="font-family:Arial;padding:48px;color:#7f1d1d"><h1>${target.title}</h1><p>Screenshot capture failed for ${target.route}.</p><p>${row.note.replace(/[<>&]/g, "")}</p></body></html>`;
    await page.setContent(fallbackHtml);
    await page.screenshot({ path: outputPath, fullPage: false });
  }

  return row;
}

function writeManifest(rows) {
  const lines = [
    "# Annex A Screenshot Manifest",
    "",
    "Screenshots were captured at 1366 x 900 using Chromium through Puppeteer. Page-level sanitization blurred table bodies, message previews, profile images, token-like fields, and common personal-data patterns before capture.",
    "",
    "| Figure | Filename | Route/Page | Capture status | Notes |",
    "| --- | --- | --- | --- | --- |",
  ];
  for (const row of rows) {
    lines.push(`| ${row.figure} | ${row.filename} | \`${row.route}\` | ${row.status} | ${row.note ? row.note.replace(/\|/g, "/") : ""} |`);
  }
  fs.writeFileSync(manifestPath, `${lines.join("\n")}\n`);
}

function updateAnnexMarkdown(rows) {
  let content = fs.readFileSync(annexAPath, "utf8");
  for (const row of rows.filter((item) => item.status === "Captured")) {
    const number = Number(row.figure.split("-")[1]);
    const sectionRegex = new RegExp(`(### Figure A-${number}\\. [^\\n]+\\n\\n)(<span style="color:red">\\[MANUAL INPUT REQUIRED:[\\s\\S]*?\\]</span>)`);
    const replacement = `$1![Figure ${row.figure}](screenshots/${row.filename})\n\n$2`;
    content = content.replace(sectionRegex, replacement);
  }
  fs.writeFileSync(annexAPath, content);
}

(async () => {
  ensureDir(screenshotDir);
  const server = await startServer();
  const browser = await puppeteer.launch({
    headless: "new",
    defaultViewport: { width: 1366, height: 900, deviceScaleFactor: 1 },
    args: ["--no-sandbox", "--disable-setuid-sandbox", "--force-device-scale-factor=1"],
  });

  const rows = [];
  try {
    const page = await browser.newPage();
    await page.setRequestInterception(true);
    page.on("request", (request) => {
      const headers = { ...request.headers() };
      if (request.method() !== "GET") {
        delete headers.origin;
        delete headers.referer;
      }
      request.continue({ headers }).catch(() => {});
    });
    await page.emulateMediaFeatures([{ name: "prefers-color-scheme", value: "light" }]);
    await setYear(page);
    await captureTarget(page, targets[0]);
    await login(page);
    for (const target of targets.slice(1)) {
      rows.push(await captureTarget(page, target));
    }
    rows.unshift({ figure: targets[0].fig, filename: `ANNEX_A_FIGURE_${targets[0].fig}_${targets[0].label}.png`, route: targets[0].route, status: "Captured", note: "" });
  } finally {
    await browser.close();
    server.kill();
  }

  writeManifest(rows);
  updateAnnexMarkdown(rows);
  console.log(`Wrote ${rows.length} screenshot records to ${manifestPath}`);
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
