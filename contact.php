<?php
require_once __DIR__ . '/base_url.php';
require_once __DIR__ . '/security.php';

security_bootstrap_session();

if (isset($_SESSION['user_id'])) {
    header('Location: ' . $base_url . 'kodus/inbox/?compose=1');
    exit;
}

include('header.php');
include('sidenav.php');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header('Location: ./');
    exit;
}

$csrfToken = security_get_csrf_token();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>KODUS | Contact</title>
  <link rel="shortcut icon" href="<?php echo $base_url; ?>kodus/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css">
  <style>
    .contact-page {
      --chat-accent: #0f766e;
      --chat-accent-2: #1d4ed8;
      --chat-surface: rgba(255,255,255,0.96);
      --chat-surface-muted: rgba(248,250,252,0.92);
      --chat-panel-soft: rgba(255,255,255,0.78);
      --chat-input-bg: #ffffff;
      --chat-text: #0f172a;
      --chat-text-muted: #64748b;
      --chat-border: rgba(148, 163, 184, 0.18);
      --chat-shadow: 0 24px 56px rgba(15, 23, 42, 0.16);
    }

    body.dark-mode.contact-page,
    body[data-theme="dark"].contact-page {
      --chat-surface: rgba(31, 42, 55, 0.96);
      --chat-surface-muted: rgba(36, 49, 64, 0.94);
      --chat-panel-soft: rgba(42, 54, 69, 0.88);
      --chat-input-bg: rgba(20, 29, 38, 0.92);
      --chat-text: #f3f4f6;
      --chat-text-muted: #c0cad5;
      --chat-border: rgba(255,255,255,0.1);
      --chat-shadow: 0 24px 56px rgba(0, 0, 0, 0.28);
    }

    .contact-page .content-wrapper {
      background:
        radial-gradient(circle at top right, rgba(29, 78, 216, 0.16), transparent 30%),
        radial-gradient(circle at left bottom, rgba(15, 118, 110, 0.14), transparent 26%),
        linear-gradient(180deg, #edf3fb 0%, #f8fafc 46%, #eef4f7 100%);
    }

    body.dark-mode.contact-page .content-wrapper,
    body[data-theme="dark"].contact-page .content-wrapper {
      background:
        radial-gradient(circle at top right, rgba(59, 130, 246, 0.18), transparent 30%),
        radial-gradient(circle at left bottom, rgba(20, 184, 166, 0.16), transparent 26%),
        linear-gradient(180deg, #16202b 0%, #1b2633 46%, #13212c 100%);
    }

    .contact-page .messenger-shell {
      display: grid;
      grid-template-columns: minmax(280px, 320px) minmax(0, 1fr);
      gap: 1.35rem;
      align-items: stretch;
    }

    .contact-page .messenger-sidebar,
    .contact-page .messenger-panel {
      border: 1px solid var(--chat-border);
      border-radius: 1.4rem;
      background: linear-gradient(180deg, var(--chat-surface), var(--chat-surface-muted));
      box-shadow: var(--chat-shadow);
      overflow: hidden;
      backdrop-filter: blur(12px);
    }

    .contact-page .messenger-sidebar {
      position: sticky;
      top: 1rem;
    }

    .contact-page .sidebar-head {
      padding: 1.45rem 1.35rem;
      background: linear-gradient(135deg, rgba(29,78,216,0.96), rgba(15,118,110,0.92));
      color: #fff;
    }

    .contact-page .sidebar-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      border-radius: 999px;
      padding: 0.38rem 0.82rem;
      background: rgba(255,255,255,0.16);
      font-size: 0.85rem;
      margin-bottom: 0.9rem;
    }

    .contact-page .sidebar-head h2 {
      font-size: 1.75rem;
      line-height: 1.1;
      margin-bottom: 0.55rem;
    }

    .contact-page .sidebar-head p {
      margin-bottom: 0;
      color: rgba(255,255,255,0.9);
    }

    .contact-page .sidebar-body {
      padding: 1.3rem;
    }

    .contact-page .sidebar-card {
      padding: 0.95rem 1rem;
      border-radius: 1rem;
      background: var(--chat-panel-soft);
      border: 1px solid rgba(148, 163, 184, 0.16);
      margin-bottom: 0.85rem;
    }

    .contact-page .sidebar-card small {
      display: block;
      color: var(--chat-text-muted);
      text-transform: uppercase;
      letter-spacing: 0.06em;
      margin-bottom: 0.2rem;
      font-weight: 700;
    }

    .contact-page .sidebar-card strong {
      color: var(--chat-text);
      font-size: 1rem;
    }

    .contact-page .sidebar-list {
      margin: 0;
      padding-left: 1.1rem;
      color: var(--chat-text-muted);
    }

    .contact-page .messenger-header {
      padding: 1.2rem 1.3rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      border-bottom: 1px solid rgba(148, 163, 184, 0.18);
      background: color-mix(in srgb, var(--chat-surface) 90%, transparent);
    }

    .contact-page .messenger-persona {
      display: flex;
      align-items: center;
      gap: 0.9rem;
    }

    .contact-page .persona-avatar {
      width: 52px;
      height: 52px;
      border-radius: 1rem;
      background: linear-gradient(135deg, var(--chat-accent-2), var(--chat-accent));
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      font-weight: 700;
      box-shadow: 0 14px 28px rgba(29, 78, 216, 0.2);
    }

    .contact-page .persona-copy h3 {
      margin-bottom: 0.2rem;
      color: var(--chat-text);
      font-size: 1.1rem;
    }

    .contact-page .persona-copy p {
      margin: 0;
      color: var(--chat-text-muted);
      font-size: 0.92rem;
    }

    .contact-page .messenger-status {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.5rem 0.85rem;
      border-radius: 999px;
      font-size: 0.86rem;
      font-weight: 700;
      color: #1d4ed8;
      background: rgba(29,78,216,0.1);
    }

    .contact-page .messenger-body {
      padding: 1.25rem;
      display: flex;
      flex-direction: column;
      gap: 1rem;
      min-height: 620px;
    }

    .contact-page .chat-day-label {
      align-self: center;
      padding: 0.42rem 0.8rem;
      border-radius: 999px;
      background: rgba(148, 163, 184, 0.12);
      color: var(--chat-text-muted);
      font-size: 0.8rem;
      font-weight: 700;
    }

    .contact-page .chat-row {
      display: flex;
      align-items: flex-end;
      gap: 1rem;
    }

    .contact-page .chat-row.incoming {
      justify-content: flex-start;
    }

    .contact-page .chat-row.outgoing {
      justify-content: flex-end;
    }

    .contact-page .chat-bubble {
      max-width: min(72%, 640px);
      border-radius: 1rem;
      padding: 0.95rem 1rem;
      box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08);
      border: 1px solid rgba(148, 163, 184, 0.12);
    }

    .contact-page .chat-row.incoming .chat-bubble {
      background: color-mix(in srgb, var(--chat-surface) 92%, transparent);
      color: var(--chat-text);
      border-bottom-left-radius: 0.25rem;
    }

    .contact-page .chat-row.outgoing .chat-bubble {
      background: linear-gradient(135deg, var(--chat-accent-2), var(--chat-accent));
      color: #fff;
      border-bottom-right-radius: 0.25rem;
    }

    .contact-page .chat-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      font-size: 0.76rem;
      margin-bottom: 0.35rem;
      opacity: 0.88;
    }

    .contact-page .chat-preview-text {
      white-space: pre-line;
      line-height: 1.55;
    }

    .contact-page .chat-bubble-subject {
      font-weight: 700;
      margin-bottom: 0.45rem;
    }

    .contact-page .composer-wrap {
      margin-top: auto;
      border: 1px solid rgba(148, 163, 184, 0.18);
      border-radius: 1.2rem;
      background: color-mix(in srgb, var(--chat-surface) 94%, transparent);
      box-shadow: inset 0 1px 0 rgba(255,255,255,0.8);
    }

    .contact-page .composer-top {
      padding: 1rem 1rem 0.75rem;
      border-bottom: 1px solid rgba(148, 163, 184, 0.14);
    }

    .contact-page .composer-grid {
      display: grid;
      grid-template-columns: 1.1fr 0.9fr;
      gap: 0.9rem;
    }

    .contact-page .composer-field label,
    .contact-page .composer-option label,
    .contact-page .composer-main label {
      color: var(--chat-text);
      font-weight: 700;
      margin-bottom: 0.4rem;
    }

    .contact-page .composer-main {
      padding: 1rem;
    }

    .contact-page .composer-wrap .form-control,
    .contact-page .composer-wrap .custom-file-label {
      border-radius: 0.95rem;
      border: 1px solid rgba(148, 163, 184, 0.34);
      box-shadow: none;
      background: var(--chat-input-bg);
      color: var(--chat-text);
    }

    .contact-page .composer-wrap .form-control:focus,
    .contact-page .composer-wrap .custom-file-input:focus ~ .custom-file-label {
      border-color: rgba(29, 78, 216, 0.48);
      box-shadow: 0 0 0 0.22rem rgba(29, 78, 216, 0.12);
    }

    .contact-page .composer-wrap textarea.form-control {
      min-height: 136px;
      resize: vertical;
    }

    .contact-page .composer-label-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      margin-bottom: 0.4rem;
    }

    .contact-page .emoji-tools {
      position: relative;
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
    }

    .contact-page .emoji-trigger {
      width: 38px;
      height: 38px;
      border-radius: 999px;
      border: 1px solid rgba(148, 163, 184, 0.28);
      background: var(--chat-input-bg);
      color: var(--chat-text);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 10px 18px rgba(15, 23, 42, 0.08);
    }

    .contact-page .emoji-trigger:hover,
    .contact-page .emoji-trigger:focus {
      color: var(--chat-text);
      background: var(--chat-surface-muted);
      outline: none;
    }

    .contact-page .emoji-menu {
      position: absolute;
      right: 0;
      top: calc(100% + 0.45rem);
      display: grid;
      grid-template-columns: repeat(5, minmax(0, 1fr));
      gap: 0.35rem;
      width: 220px;
      padding: 0.7rem;
      border-radius: 1rem;
      background: color-mix(in srgb, var(--chat-surface) 96%, transparent);
      border: 1px solid rgba(148, 163, 184, 0.2);
      box-shadow: 0 20px 40px rgba(15, 23, 42, 0.16);
      z-index: 20;
    }

    .contact-page .emoji-menu[hidden] {
      display: none;
    }

    .contact-page .emoji-item {
      border: 0;
      border-radius: 0.7rem;
      background: transparent;
      font-size: 1.15rem;
      line-height: 1;
      padding: 0.42rem;
    }

    .contact-page .emoji-item:hover,
    .contact-page .emoji-item:focus {
      background: rgba(29, 78, 216, 0.1);
      outline: none;
    }

    .contact-page .composer-stats {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      flex-wrap: wrap;
      margin-top: 0.55rem;
      color: var(--chat-text-muted);
      font-size: 0.86rem;
    }

    .contact-page .attachment-bar {
      margin-top: 0.95rem;
      padding: 0.9rem;
      border: 1px dashed rgba(29, 78, 216, 0.26);
      border-radius: 1rem;
      background: linear-gradient(180deg, color-mix(in srgb, var(--chat-surface-muted) 82%, rgba(239,246,255,0.6) 18%), color-mix(in srgb, var(--chat-surface) 92%, transparent));
    }

    .contact-page .attachment-bar .custom-file-input,
    .contact-page .attachment-bar .custom-file-label::after {
      display: none;
    }

    .contact-page .attachment-bar .custom-file-label {
      position: static;
      height: auto;
      margin: 0;
      padding: 0.9rem 1rem;
      border-style: dashed;
    }

    .contact-page .file-summary {
      margin-top: 0.75rem;
      color: var(--chat-text-muted);
      font-size: 0.9rem;
    }

    .contact-page .file-preview {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
      margin-top: 0.9rem;
    }

    .contact-page .file-card {
      width: 86px;
      height: 86px;
      border-radius: 1rem;
      border: 1px solid rgba(148, 163, 184, 0.22);
      background: color-mix(in srgb, var(--chat-surface) 96%, transparent);
      box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .contact-page .file-card img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .contact-page .file-card i {
      font-size: 1.85rem;
      color: var(--chat-text-muted);
    }

    .contact-page .file-card.more {
      background: linear-gradient(135deg, var(--chat-accent-2), var(--chat-accent));
      color: #fff;
      font-weight: 700;
      font-size: 1rem;
    }

    .contact-page .composer-footer {
      padding: 0 1rem 1rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .contact-page .composer-option {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      color: var(--chat-text);
    }

    .contact-page .composer-actions {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      flex-wrap: wrap;
    }

    .contact-page .btn-chat-soft,
    .contact-page .btn-chat-send {
      border-radius: 999px;
      padding: 0.78rem 1.2rem;
      font-weight: 700;
    }

    .contact-page .btn-chat-soft {
      color: #1e3a8a;
      background: rgba(29,78,216,0.1);
      border: 1px solid rgba(29,78,216,0.14);
    }

    body.dark-mode.contact-page .composer-wrap,
    body[data-theme="dark"].contact-page .composer-wrap {
      box-shadow: none;
    }

    body.dark-mode.contact-page .attachment-bar,
    body[data-theme="dark"].contact-page .attachment-bar {
      border-color: rgba(96, 165, 250, 0.24);
    }

    .contact-page .btn-chat-send {
      box-shadow: 0 12px 24px rgba(29, 78, 216, 0.2);
    }

    @media (max-width: 991.98px) {
      .contact-page .messenger-shell {
        grid-template-columns: 1fr;
      }

      .contact-page .messenger-sidebar {
        position: static;
      }

      .contact-page .composer-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 767.98px) {
      .contact-page .messenger-header,
      .contact-page .messenger-body,
      .contact-page .composer-top,
      .contact-page .composer-main,
      .contact-page .composer-footer {
        padding-left: 0.9rem;
        padding-right: 0.9rem;
      }

      .contact-page .chat-bubble {
        max-width: 88%;
      }

      .contact-page .composer-label-row {
        align-items: flex-start;
      }

      .contact-page .composer-stats {
        align-items: flex-start;
      }

      .contact-page .attachment-bar {
        padding: 0.8rem;
      }

      .contact-page .attachment-bar .custom-file-label {
        padding: 0.8rem 0.9rem;
      }

      .contact-page .composer-footer,
      .contact-page .composer-actions {
        flex-direction: column;
        align-items: stretch;
      }

      .contact-page .composer-option {
        width: 100%;
      }

      .contact-page .composer-actions .btn {
        width: 100%;
      }

      .contact-page .emoji-menu {
        width: min(220px, calc(100vw - 2.6rem));
      }
    }

    @media (max-width: 575.98px) {
      .contact-page .composer-grid {
        grid-template-columns: 1fr;
      }

      .contact-page .composer-label-row {
        flex-direction: column;
        align-items: stretch;
      }

      .contact-page .emoji-tools {
        justify-content: flex-end;
      }

      .contact-page .emoji-trigger {
        width: 34px;
        height: 34px;
      }

      .contact-page .emoji-menu {
        width: min(210px, calc(100vw - 2.2rem));
        grid-template-columns: repeat(4, minmax(0, 1fr));
      }

      .contact-page .composer-stats {
        font-size: 0.81rem;
      }
    }
  </style>
</head>
<body class="contact-page">

<div class="wrapper">


  <div class="content-wrapper">

    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Contact</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>kodus/home">Home</a></li>
              <li class="breadcrumb-item active">Contact</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="container-fluid">
        <div class="messenger-shell">
          <aside class="messenger-sidebar">
            <div class="sidebar-head">
              <div class="sidebar-badge"><i class="fas fa-comments"></i> Direct Messaging</div>
              <h2>Send it like a conversation.</h2>
              <p>Compose in a messenger-style layout while keeping the same KODUS delivery flow behind it.</p>
            </div>
            <div class="sidebar-body">
              <div class="sidebar-card">
                <small>Sender</small>
                <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Unknown User') ?></strong>
              </div>
              <div class="sidebar-card">
                <small>Style</small>
                <strong>Messenger-like compose with live preview</strong>
              </div>
              <div class="sidebar-card">
                <small>Attachment Limits</small>
                <strong>Up to 25 MB each for supported document and image files</strong>
              </div>
              <div class="mt-4">
                <div class="messenger-status mb-3"><i class="fas fa-bolt"></i> Better chat flow</div>
                <ul class="sidebar-list">
                  <li>Pick the recipient first so the header updates instantly.</li>
                  <li>Use the subject like a conversation topic.</li>
                  <li>Your message bubble previews live as you type.</li>
                </ul>
              </div>
            </div>
          </aside>

          <section class="messenger-panel">
            <div class="messenger-header">
              <div class="messenger-persona">
                <div class="persona-avatar" id="recipientAvatar">AD</div>
                <div class="persona-copy">
                  <h3 id="chatRecipientName">Admin</h3>
                  <p id="recipientHint">Choose a target audience or a single account.</p>
                </div>
              </div>
              <div class="messenger-status"><i class="fas fa-shield-alt"></i> Secure submission</div>
            </div>

            <div class="messenger-body">
              <div class="chat-day-label">Message Preview</div>

              <div class="chat-row incoming">
                <div class="chat-bubble">
                  <div class="chat-meta">
                    <strong id="previewIncomingLabel">Recipient</strong>
                    <span>Ready to receive</span>
                  </div>
                  <div class="chat-preview-text">Your selected recipient or audience will appear here.</div>
                </div>
              </div>

              <div class="chat-row outgoing">
                <div class="chat-bubble">
                  <div class="chat-meta">
                    <strong><?= htmlspecialchars($_SESSION['username'] ?? 'You') ?></strong>
                    <span id="previewTimestamp">Draft</span>
                  </div>
                  <div class="chat-bubble-subject" id="previewSubject">No subject yet</div>
                  <div class="chat-preview-text" id="previewMessage">Start typing and your message preview will appear here.</div>
                </div>
              </div>

              <div class="composer-wrap">
                <form id="contactForm" action="send_contact.php" method="POST" enctype="multipart/form-data">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

                  <div class="composer-top">
                    <div class="composer-grid">
                      <div class="composer-field">
                        <label for="recipient">To</label>
                        <select name="recipient" id="recipient" class="form-control" required>
                          <?php if ($_SESSION['user_type'] === 'admin'): ?>
                          <option value="all">All Users & Admins</option>
                          <option value="users">All Users Only</option>
                          <?php endif; ?>
                          <option value="admins">Admin</option>
                          <?php
                            $result = $conn->query("SELECT id, username, email FROM users ORDER BY username");
                            while ($row = $result->fetch_assoc()):
                          ?>
                            <option value="user_<?php echo $row['id']; ?>">
                              <?php echo htmlspecialchars($row['username']) . " (" . htmlspecialchars($row['email']) . ")"; ?>
                            </option>
                          <?php endwhile; ?>
                        </select>
                      </div>
                      <div class="composer-field">
                        <label for="subject">Topic</label>
                        <input type="text" name="subject" id="subject" class="form-control" maxlength="180" placeholder="Type a conversation topic..." required>
                      </div>
                    </div>
                    <div class="composer-stats">
                      <span id="recipientModeLabel">Direct</span>
                      <span><strong><span id="subjectCount">0</span></strong>/180 subject characters</span>
                    </div>
                  </div>

                  <div class="composer-main">
                    <div class="form-group mb-0">
                      <div class="composer-label-row">
                        <label for="message" class="mb-0">Message</label>
                        <div class="emoji-tools">
                          <button type="button" class="emoji-trigger" id="messageEmojiTrigger" aria-label="Insert emoji" aria-expanded="false">
                            <i class="far fa-smile"></i>
                          </button>
                          <div class="emoji-menu" id="messageEmojiMenu" hidden></div>
                        </div>
                      </div>
                      <textarea name="message" id="message" class="form-control" rows="6" maxlength="5000" placeholder="Write your message like a chat..."></textarea>
                      <div class="composer-stats">
                        <span>Messages are also saved into the internal inbox thread. Attachments can be sent without message text.</span>
                        <span><strong><span id="messageCount">0</span></strong>/5000 message characters</span>
                      </div>
                    </div>

                    <div class="attachment-bar">
                      <div class="custom-file">
                        <input type="file" name="attachments[]" id="attachments" class="custom-file-input" multiple>
                        <label class="custom-file-label" for="attachments">
                          <i class="fas fa-paperclip mr-2"></i>
                          Attach supporting files just like you would in a chat thread.
                        </label>
                      </div>
                      <div class="file-summary" id="fileSummary">No files selected yet.</div>
                      <div id="filePreview" class="file-preview"></div>
                    </div>
                  </div>

                  <div class="composer-footer">
                    <div class="composer-option">
                      <input class="form-check-input mt-0" type="checkbox" name="send_copy" id="send_copy" value="1">
                      <label class="form-check-label mb-0" for="send_copy">Send me a copy of this message</label>
                    </div>
                    <div class="composer-actions">
                      <button type="button" class="btn btn-chat-soft" id="resetContactForm">
                        <i class="fas fa-undo-alt mr-1"></i> Clear
                      </button>
                      <button type="submit" class="btn btn-primary btn-chat-send">
                        <i class="fas fa-paper-plane mr-1"></i> Send
                      </button>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>

  </div>

  <footer class="main-footer">
    <div class="float-right d-none d-sm-inline">
      Anything you want
    </div>
    <strong>Copyright &copy; 2014-2021 <a href="https://adminlte.io">AdminLTE.io</a>.</strong> All rights reserved.
  </footer>

</div>

<script>
const contactForm = document.getElementById('contactForm');
const subjectInput = document.getElementById('subject');
const messageInput = document.getElementById('message');
const recipientInput = document.getElementById('recipient');
const attachmentsInput = document.getElementById('attachments');
const filePreview = document.getElementById('filePreview');
const fileSummary = document.getElementById('fileSummary');
const subjectCount = document.getElementById('subjectCount');
const messageCount = document.getElementById('messageCount');
const chatRecipientName = document.getElementById('chatRecipientName');
const recipientAvatar = document.getElementById('recipientAvatar');
const previewIncomingLabel = document.getElementById('previewIncomingLabel');
const previewSubject = document.getElementById('previewSubject');
const previewMessage = document.getElementById('previewMessage');
const previewTimestamp = document.getElementById('previewTimestamp');
const recipientHint = document.getElementById('recipientHint');
const recipientModeLabel = document.getElementById('recipientModeLabel');
const resetButton = document.getElementById('resetContactForm');
const messageEmojiTrigger = document.getElementById('messageEmojiTrigger');
const messageEmojiMenu = document.getElementById('messageEmojiMenu');
const commonEmojis = ['😀','😂','😊','😍','😉','👍','👏','🙏','🎉','🔥','❤️','✨','📎','📩','✅','🤝','🙌','😎','📌','💡'];

function insertTextAtCursor(field, text) {
  if (!field) {
    return;
  }

  const start = field.selectionStart ?? field.value.length;
  const end = field.selectionEnd ?? field.value.length;
  const nextValue = field.value.slice(0, start) + text + field.value.slice(end);
  field.value = nextValue;
  field.focus();
  const cursor = start + text.length;
  field.setSelectionRange(cursor, cursor);
  field.dispatchEvent(new Event('input', { bubbles: true }));
}

function closeEmojiMenu() {
  if (messageEmojiMenu) {
    messageEmojiMenu.hidden = true;
  }
  if (messageEmojiTrigger) {
    messageEmojiTrigger.setAttribute('aria-expanded', 'false');
  }
}

function buildEmojiMenu(menu, targetField) {
  if (!menu || !targetField) {
    return;
  }

  menu.innerHTML = '';
  commonEmojis.forEach(emoji => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'emoji-item';
    button.textContent = emoji;
    button.addEventListener('click', function() {
      insertTextAtCursor(targetField, emoji);
      closeEmojiMenu();
    });
    menu.appendChild(button);
  });
}

function updateCounts() {
  subjectCount.textContent = (subjectInput.value || '').length;
  messageCount.textContent = (messageInput.value || '').length;
  previewSubject.textContent = (subjectInput.value || '').trim() || 'No subject yet';
  previewMessage.textContent = (messageInput.value || '').trim() || 'Start typing and your message preview will appear here.';
}

function updateRecipientMeta() {
  const value = recipientInput.value || '';
  let hint = 'Choose a target audience or a single account.';
  let mode = 'Direct';
  let display = 'Admin';

  if (value === 'all') {
    hint = 'This message will be addressed to all users and admins.';
    mode = 'Broadcast';
    display = 'All Users & Admins';
  } else if (value === 'users') {
    hint = 'This message will go to all regular users only.';
    mode = 'Group';
    display = 'All Users Only';
  } else if (value === 'admins') {
    hint = 'This message will go to the admin channel.';
    mode = 'Admin';
    display = 'Admin';
  } else if (value.indexOf('user_') === 0) {
    const option = recipientInput.options[recipientInput.selectedIndex];
    const label = option ? option.text : 'selected user';
    hint = 'This message will go to ' + label + '.';
    mode = 'Direct';
    display = label.split(' (')[0] || label;
  }

  const initials = display
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map(part => part.charAt(0).toUpperCase())
    .join('') || 'AD';

  recipientHint.textContent = hint;
  recipientModeLabel.textContent = mode;
  chatRecipientName.textContent = display;
  previewIncomingLabel.textContent = display;
  recipientAvatar.textContent = initials;
}

function clearFilePreview() {
  filePreview.innerHTML = '';
  fileSummary.textContent = 'No files selected yet.';
}

function updateAttachments() {
  filePreview.innerHTML = '';

  const files = Array.prototype.slice.call(attachmentsInput.files || []);
  const maxPreview = 4;

  if (!files.length) {
    clearFilePreview();
    return;
  }

  const totalSizeMb = (files.reduce((sum, file) => sum + file.size, 0) / (1024 * 1024)).toFixed(2);
  fileSummary.textContent = files.length + ' file(s) selected | ' + totalSizeMb + ' MB total';

  files.slice(0, maxPreview).forEach(file => {
    const card = document.createElement('div');
    card.classList.add('file-card');

    if (file.type.startsWith('image/')) {
      const img = document.createElement('img');
      img.src = URL.createObjectURL(file);
      card.appendChild(img);
    } else {
      const icon = document.createElement('i');
      icon.classList.add('fas', file.type.includes('pdf') ? 'fa-file-pdf' : 'fa-file-alt');
      card.appendChild(icon);
    }

    filePreview.appendChild(card);
  });

  if (files.length > maxPreview) {
    const moreCard = document.createElement('div');
    moreCard.classList.add('file-card', 'more');
    moreCard.textContent = '+' + (files.length - maxPreview);
    filePreview.appendChild(moreCard);
  }
}

contactForm.addEventListener('submit', function() {
  const btn = this.querySelector('[type=submit]');
  if (btn) btn.disabled = true;
  Swal.fire({
    icon: 'info',
    title: 'Sending...',
    html: 'Please wait while we send your message.',
    allowOutsideClick: false,
    didOpen: () => Swal.showLoading()
  });
});

attachmentsInput.addEventListener('change', updateAttachments);
subjectInput.addEventListener('input', updateCounts);
messageInput.addEventListener('input', updateCounts);
recipientInput.addEventListener('change', updateRecipientMeta);
messageEmojiTrigger?.addEventListener('click', function(e) {
  e.preventDefault();
  e.stopPropagation();
  const shouldOpen = messageEmojiMenu.hidden;
  closeEmojiMenu();
  messageEmojiMenu.hidden = !shouldOpen;
  this.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
});
messageEmojiMenu?.addEventListener('click', function(e) {
  e.stopPropagation();
});
document.addEventListener('click', closeEmojiMenu);
resetButton.addEventListener('click', function() {
  contactForm.reset();
  clearFilePreview();
  updateCounts();
  updateRecipientMeta();
  closeEmojiMenu();
});

previewTimestamp.textContent = new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
buildEmojiMenu(messageEmojiMenu, messageInput);
updateCounts();
updateRecipientMeta();
clearFilePreview();
</script>

<script src="<?php echo $base_url; ?>kodus/plugins/jquery/jquery.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo $base_url; ?>kodus/dist/js/adminlte.min.js"></script>

</body>
</html>
