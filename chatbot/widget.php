<?php
// chatbot/widget.php
// ============================================================
// Floating chatbot UI. Included from includes/header.php.
// Base URL is auto-detected so this works whether the project
// is installed at /medibook/, /medibooks/, /dabs/, etc.
// ============================================================

// Derive base path from the current script. Examples:
//   /medibooks/patient/dashboard.php  -> /medibooks
//   /dabs/patient/find_doctor.php     -> /dabs
//   /patient/dashboard.php            -> ""  (project at web root)
$mbScriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$mbBase = preg_replace('#/(patient|doctor|admin)/[^/]+$#', '', $mbScriptName);
if ($mbBase === $mbScriptName) $mbBase = ''; // no role folder found

$mbChatApi  = $mbBase . '/chatbot/api.php';
$mbBookBase = $mbBase . '/patient/book_appointment.php';
?>

<!-- ── Chatbot launcher button ───────────────────────────── -->
<button id="mb-chat-launcher" type="button" aria-label="Open symptom checker"
        title="Symptom Checker">
  <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
       xmlns="http://www.w3.org/2000/svg">
    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7
             8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8
             8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"
          stroke="white" stroke-width="2" stroke-linecap="round"
          stroke-linejoin="round"/>
  </svg>
</button>

<!-- ── Chat panel (hidden by default) ────────────────────── -->
<div id="mb-chat-panel" aria-hidden="true">
  <div class="mb-chat-header">
    <div class="mb-chat-title">
      <div class="mb-chat-avatar">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
             xmlns="http://www.w3.org/2000/svg">
          <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83
                   M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"
                stroke="white" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </div>
      <div>
        <div class="mb-chat-name">Symptom Checker</div>
        <div class="mb-chat-sub">Not a diagnosis</div>
      </div>
    </div>
    <button id="mb-chat-close" type="button" aria-label="Close">×</button>
  </div>

  <div class="mb-chat-disclaimer" id="mb-chat-disclaimer">
    ⚠ For information only — not a medical diagnosis. Call emergency services for any life-threatening symptoms.
  </div>

  <div id="mb-chat-messages" class="mb-chat-messages"></div>

  <form id="mb-chat-form" class="mb-chat-form" autocomplete="off">
    <input id="mb-chat-input" type="text" placeholder="Describe your symptoms…"
           maxlength="1000" required />
    <button id="mb-chat-send" type="submit" aria-label="Send">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
           xmlns="http://www.w3.org/2000/svg">
        <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"
              stroke="white" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>
  </form>
</div>

<style>
#mb-chat-launcher{
  position:fixed;right:24px;bottom:24px;width:56px;height:56px;
  border-radius:50%;border:none;cursor:pointer;z-index:9999;
  background:linear-gradient(135deg,#1A6FD4,#0D4FA0);
  box-shadow:0 6px 20px rgba(13,79,160,.35);
  display:flex;align-items:center;justify-content:center;
  transition:transform .15s ease, box-shadow .15s ease;
}
#mb-chat-launcher:hover{transform:scale(1.06);box-shadow:0 8px 24px rgba(13,79,160,.45)}
#mb-chat-launcher.mb-hidden{display:none}

#mb-chat-panel{
  position:fixed;right:24px;bottom:24px;width:380px;max-width:calc(100vw - 32px);
  height:560px;max-height:calc(100vh - 48px);
  background:#fff;border-radius:14px;
  box-shadow:0 12px 40px rgba(0,0,0,.18);
  display:none;flex-direction:column;z-index:9999;
  font-family:'Outfit',sans-serif;overflow:hidden;
  border:1px solid #E2E8F0;
}
#mb-chat-panel.mb-open{display:flex;animation:mb-chat-in .2s ease}
@keyframes mb-chat-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}

.mb-chat-header{
  background:linear-gradient(135deg,#1A6FD4,#0D4FA0);color:#fff;
  padding:12px 14px;display:flex;align-items:center;justify-content:space-between;
  flex-shrink:0;
}
.mb-chat-title{display:flex;align-items:center;gap:10px}
.mb-chat-avatar{
  width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,.2);
  display:flex;align-items:center;justify-content:center;
}
.mb-chat-name{font-size:14px;font-weight:700;line-height:1.2}
.mb-chat-sub{font-size:11px;opacity:.85;margin-top:1px}
#mb-chat-close{
  background:transparent;border:none;color:#fff;font-size:24px;
  line-height:1;cursor:pointer;padding:0 4px;opacity:.8;
}
#mb-chat-close:hover{opacity:1}

.mb-chat-disclaimer{
  background:#FEF3C7;color:#92400E;font-size:11px;padding:8px 14px;
  border-bottom:1px solid #FDE68A;line-height:1.4;flex-shrink:0;
}

.mb-chat-messages{
  flex:1;overflow-y:auto;padding:14px;background:#F8FAFC;
  display:flex;flex-direction:column;gap:10px;
}

.mb-chat-msg{max-width:85%;font-size:13px;line-height:1.45;word-wrap:break-word}
.mb-chat-msg.user{
  align-self:flex-end;background:#1A6FD4;color:#fff;
  padding:8px 12px;border-radius:14px 14px 4px 14px;
}
.mb-chat-msg.bot{
  align-self:flex-start;background:#fff;color:#0F172A;
  padding:8px 12px;border-radius:14px 14px 14px 4px;
  border:1px solid #E2E8F0;
}
.mb-chat-msg.bot.red-flag{
  background:#FEE2E2;color:#7F1D1D;border-color:#FCA5A5;font-weight:600;
}

.mb-chat-disease{
  align-self:stretch;background:#fff;border:1px solid #E2E8F0;border-radius:10px;
  padding:10px 12px;font-size:12px;
}
.mb-chat-disease + .mb-chat-disease{margin-top:6px}
.mb-chat-disease-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px}
.mb-chat-disease-name{font-weight:700;color:#0F172A;font-size:13px}
.mb-chat-disease-conf{
  font-size:10px;font-weight:700;padding:2px 7px;border-radius:99px;
  background:#EBF3FF;color:#1A6FD4;
}
.mb-chat-disease-conf.urg-high{background:#FEE2E2;color:#DC2626}
.mb-chat-disease-conf.urg-moderate{background:#FEF3C7;color:#D97706}
.mb-chat-disease-desc{color:#475569;font-size:11px;line-height:1.45;margin:4px 0 6px}
.mb-chat-disease-advice{
  background:#F8FAFC;border-left:3px solid #1A6FD4;
  padding:6px 8px;font-size:11px;color:#334155;line-height:1.45;
  border-radius:0 6px 6px 0;margin-top:6px;
}
.mb-chat-matched{font-size:10px;color:#94A3B8;margin-top:6px}
.mb-chat-matched span{
  display:inline-block;background:#F1F5F9;padding:1px 6px;border-radius:99px;
  margin:2px 3px 0 0;color:#475569;
}

.mb-chat-doctors-title{font-size:11px;font-weight:700;color:#475569;
  text-transform:uppercase;letter-spacing:.4px;margin:6px 2px 0;}
.mb-chat-doctor{
  align-self:stretch;background:#fff;border:1px solid #E2E8F0;border-radius:10px;
  padding:10px 12px;display:flex;align-items:center;gap:10px;
}
.mb-chat-doctor-av{
  width:34px;height:34px;border-radius:50%;background:#EBF3FF;color:#1A6FD4;
  display:flex;align-items:center;justify-content:center;
  font-weight:700;font-size:12px;flex-shrink:0;
}
.mb-chat-doctor-info{flex:1;min-width:0}
.mb-chat-doctor-name{font-size:13px;font-weight:700;color:#0F172A}
.mb-chat-doctor-spec{font-size:11px;color:#475569;margin-top:1px}
.mb-chat-doctor-meta{font-size:10px;color:#94A3B8;margin-top:2px}
.mb-chat-doctor-book{
  background:#1A6FD4;color:#fff;text-decoration:none;font-size:11px;font-weight:600;
  padding:6px 10px;border-radius:7px;flex-shrink:0;
}
.mb-chat-doctor-book:hover{background:#0D4FA0}

.mb-chat-form{
  display:flex;gap:8px;padding:10px;background:#fff;border-top:1px solid #E2E8F0;
  flex-shrink:0;
}
#mb-chat-input{
  flex:1;border:1px solid #CBD5E1;border-radius:8px;padding:9px 12px;
  font-size:13px;font-family:inherit;outline:none;
}
#mb-chat-input:focus{border-color:#1A6FD4;box-shadow:0 0 0 3px rgba(26,111,212,.15)}
#mb-chat-send{
  background:#1A6FD4;color:#fff;border:none;border-radius:8px;
  width:38px;display:flex;align-items:center;justify-content:center;cursor:pointer;
}
#mb-chat-send:hover{background:#0D4FA0}
#mb-chat-send:disabled{background:#94A3B8;cursor:not-allowed}

.mb-chat-typing{
  align-self:flex-start;background:#fff;border:1px solid #E2E8F0;
  padding:10px 14px;border-radius:14px 14px 14px 4px;
  display:flex;gap:4px;
}
.mb-chat-typing span{
  width:6px;height:6px;border-radius:50%;background:#94A3B8;
  animation:mb-bounce 1.2s infinite;
}
.mb-chat-typing span:nth-child(2){animation-delay:.15s}
.mb-chat-typing span:nth-child(3){animation-delay:.3s}
@keyframes mb-bounce{
  0%,60%,100%{transform:translateY(0);opacity:.4}
  30%{transform:translateY(-5px);opacity:1}
}

@media (max-width:480px){
  #mb-chat-panel{
    right:8px;bottom:8px;width:calc(100vw - 16px);height:calc(100vh - 16px);
  }
}
</style>

<script>
(function(){
  const API_URL  = <?= json_encode($mbChatApi) ?>;
  const BOOK_URL = <?= json_encode($mbBookBase) ?>;
  const launcher = document.getElementById('mb-chat-launcher');
  const panel    = document.getElementById('mb-chat-panel');
  const closeBtn = document.getElementById('mb-chat-close');
  const form     = document.getElementById('mb-chat-form');
  const input    = document.getElementById('mb-chat-input');
  const sendBtn  = document.getElementById('mb-chat-send');
  const messages = document.getElementById('mb-chat-messages');

  let hasGreeted = false;

  function open(){
    panel.classList.add('mb-open');
    panel.setAttribute('aria-hidden','false');
    launcher.classList.add('mb-hidden');
    if (!hasGreeted) {
      addBot("Hi — tell me what you're feeling and I'll suggest possibilities and doctors who can help. "
           + "I'm not a doctor, so please don't use this for emergencies.");
      hasGreeted = true;
    }
    setTimeout(() => input.focus(), 50);
  }
  function close(){
    panel.classList.remove('mb-open');
    panel.setAttribute('aria-hidden','true');
    launcher.classList.remove('mb-hidden');
  }

  launcher.addEventListener('click', open);
  closeBtn.addEventListener('click', close);

  function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, c => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[c]));
  }

  function addUser(text){
    const el = document.createElement('div');
    el.className = 'mb-chat-msg user';
    el.textContent = text;
    messages.appendChild(el);
    scrollToBottom();
  }

  function addBot(text, opts){
    opts = opts || {};
    const el = document.createElement('div');
    el.className = 'mb-chat-msg bot' + (opts.redFlag ? ' red-flag' : '');
    el.textContent = text;
    messages.appendChild(el);
    scrollToBottom();
  }

  function addDiseaseCard(d){
    const card = document.createElement('div');
    card.className = 'mb-chat-disease';
    const urgencyClass = d.urgency === 'high' ? 'urg-high'
                       : d.urgency === 'moderate' ? 'urg-moderate' : '';
    let matchedHtml = '';
    if (d.matched_symptoms && d.matched_symptoms.length) {
      matchedHtml = '<div class="mb-chat-matched">Matched: '
        + d.matched_symptoms.map(s => '<span>'+escapeHtml(s)+'</span>').join(' ')
        + '</div>';
    }
    card.innerHTML = `
      <div class="mb-chat-disease-head">
        <div class="mb-chat-disease-name">${escapeHtml(d.name)}</div>
        <div class="mb-chat-disease-conf ${urgencyClass}">${d.confidence}% match</div>
      </div>
      <div class="mb-chat-disease-desc">${escapeHtml(d.description)}</div>
      <div class="mb-chat-disease-advice">${escapeHtml(d.advice)}</div>
      ${matchedHtml}
    `;
    messages.appendChild(card);
    scrollToBottom();
  }

  function addDoctorsTitle(){
    const t = document.createElement('div');
    t.className = 'mb-chat-doctors-title';
    t.textContent = 'Recommended doctors';
    messages.appendChild(t);
  }

  function addDoctorCard(doc){
    const card = document.createElement('div');
    card.className = 'mb-chat-doctor';
    const initials = (doc.name || '').replace(/^Dr\.?\s*/i,'').slice(0,2).toUpperCase();
    const fee = doc.fee ? `Rs. ${doc.fee.toFixed(0)}` : '';
    const exp = doc.experience ? `${doc.experience} yrs` : '';
    const meta = [exp, doc.clinic_name].filter(Boolean).join(' · ');
    card.innerHTML = `
      <div class="mb-chat-doctor-av">${escapeHtml(initials || 'DR')}</div>
      <div class="mb-chat-doctor-info">
        <div class="mb-chat-doctor-name">${escapeHtml(doc.name)}</div>
        <div class="mb-chat-doctor-spec">${escapeHtml(doc.specialisation)}${fee ? ' · '+fee : ''}</div>
        <div class="mb-chat-doctor-meta">${escapeHtml(meta)}</div>
      </div>
      <a class="mb-chat-doctor-book"
         href="${BOOK_URL}?doctor_id=${doc.id}">Book</a>
    `;
    messages.appendChild(card);
    scrollToBottom();
  }

  function addTyping(){
    const el = document.createElement('div');
    el.className = 'mb-chat-typing';
    el.id = 'mb-chat-typing';
    el.innerHTML = '<span></span><span></span><span></span>';
    messages.appendChild(el);
    scrollToBottom();
  }
  function removeTyping(){
    const t = document.getElementById('mb-chat-typing');
    if (t) t.remove();
  }
  function scrollToBottom(){ messages.scrollTop = messages.scrollHeight; }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;

    addUser(text);
    input.value = '';
    sendBtn.disabled = true;
    addTyping();

    try {
      const res = await fetch(API_URL, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({ message: text })
      });

      removeTyping();

      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        addBot(err.error || 'Something went wrong. Please try again.');
        sendBtn.disabled = false;
        return;
      }

      const data = await res.json();

      if (data.red_flag) {
        addBot('URGENT: ' + (data.red_flag_label || 'Possible emergency'),
               { redFlag: true });
        addBot(data.message, { redFlag: true });
        if (data.doctors && data.doctors.length) {
          addBot('Once you are safe, you can also follow up with one of these specialists:');
          addDoctorsTitle();
          data.doctors.forEach(addDoctorCard);
        }
      } else {
        addBot(data.message || '');
        (data.diseases || []).forEach(addDiseaseCard);
        if (data.doctors && data.doctors.length) {
          addDoctorsTitle();
          data.doctors.forEach(addDoctorCard);
        } else if (data.diseases && data.diseases.length) {
          addBot('No matching doctors are currently available in the system. '
               + 'Please check back later or visit "Find a Doctor".');
        }
      }
    } catch (err) {
      removeTyping();
      addBot('Network error — please check your connection and try again.');
    } finally {
      sendBtn.disabled = false;
      input.focus();
    }
  });
})();
</script>
