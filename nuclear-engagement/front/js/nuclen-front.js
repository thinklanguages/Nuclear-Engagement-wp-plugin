window.NuclenLazyLoadComponent=function(w,d=null){const c=document.getElementById(w);if(!c)return;const m=new IntersectionObserver(l=>{l.forEach(o=>{o.isIntersecting&&(d&&typeof window[d]=="function"&&window[d](),m.disconnect())})},{rootMargin:"0px 0px -200px 0px",threshold:.1});m.observe(c)};function k(w,d){const c=document.querySelector(w);if(!c)return;new IntersectionObserver((l,o)=>{l.forEach(p=>{p.isIntersecting&&p.intersectionRatio===1&&(typeof gtag=="function"&&gtag("event",d),o.unobserve(p.target))})},{root:null,rootMargin:"0px",threshold:1}).observe(c)}const B=new MutationObserver((w,d)=>{const c=document.getElementById("nuclen-quiz-container"),m=document.getElementById("nuclen-summary-container");c&&m&&(k("#nuclen-summary-container","nuclen_summary_view"),k("#nuclen-quiz-container","nuclen_quiz_view"),d.disconnect())});B.observe(document.body,{childList:!0,subtree:!0});window.NuclenLazyLoadComponent("nuclen-quiz-container","nuclearEngagementInitQuiz");function S(){var L,N;const w=parseInt((L=window.NuclenSettings)==null?void 0:L.questions_per_quiz,10)||10,d=parseInt((N=window.NuclenSettings)==null?void 0:N.answers_per_question,10)||4,c=window.NuclenOptinPosition??"with_results",m=window.NuclenOptinMandatory??!1,l=postQuizData.filter(e=>{var t;return e.question.trim()&&((t=e.answers[0])==null?void 0:t.trim())}).slice(0,w).map(e=>({...e,answers:e.answers.slice(0,d)}));let o=0,p=0;const z=[];let v=!1;const r=document.getElementById("nuclen-quiz-container"),h=document.getElementById("nuclen-quiz-question-container"),f=document.getElementById("nuclen-quiz-answers-container"),y=document.getElementById("nuclen-quiz-progress-bar"),q=document.getElementById("nuclen-quiz-final-result-container"),a=document.getElementById("nuclen-quiz-next-button"),g=document.getElementById("nuclen-quiz-explanation-container");if(!r)return;g&&(g.innerHTML=""),a==null||a.addEventListener("click",x);function x(){if(o++,o<l.length){I(),r==null||r.scrollIntoView();return}window.NuclenOptinEnabled&&window.NuclenOptinWebhook&&c==="before_results"&&!v?O():E(),r==null||r.scrollIntoView()}function M(){if(!y)return;const e=(o+1)/l.length*100;y.style.width=`${e}%`}function E(){var u,s;h.innerHTML="",f.innerHTML="",g.innerHTML="",a==null||a.classList.add("nuclen-quiz-hidden");let e="";c==="with_results"&&window.NuclenOptinEnabled&&window.NuclenOptinWebhook&&(e+=`
        <div id="nuclen-optin-container" class="nuclen-optin-with-results">
          <label for="nuclen-optin-name"  class="nuclen-fg">Name</label>
          <input  type="text"  id="nuclen-optin-name">
          <label for="nuclen-optin-email" class="nuclen-fg">Email</label>
          <input  type="email" id="nuclen-optin-email" required>
          <button type="button" id="nuclen-optin-submit">Sign up</button>
        </div>
      `),e+=`
      <div id="nuclen-quiz-results-title"  class="nuclen-fg">Your Score</div>
      <div id="nuclen-quiz-results-score"  class="nuclen-fg">${p} / ${l.length}</div>
    `;const t=p===l.length?"Perfect!":p>l.length/2?"Well done!":"Why not retake the test?";e+=`<div id="nuclen-quiz-score-comment">${t}</div>`,e+='<div id="nuclen-quiz-result-tabs-container">',l.forEach((i,n)=>{e+=`
        <button class="nuclen-quiz-result-tab"
                onclick="nuclearEngagementShowQuizQuestionDetails(${n})">
          ${n+1}
        </button>`}),e+=`</div>
             <div id="nuclen-quiz-result-details-container"
                  class="nuclen-fg dashboard-box"></div>`,NuclenCustomQuizHtmlAfter!=null&&NuclenCustomQuizHtmlAfter.trim()&&(e+=`
        <div id="nuclen-quiz-end-message" class="nuclen-fg">
          ${NuclenCustomQuizHtmlAfter}
        </div>`),e+=`
      <button id="nuclen-quiz-retake-button"
              onclick="nuclearEngagementRetakeQuiz()">Retake Test</button>
    `,q.innerHTML=e,(u=window.nuclearEngagementShowQuizQuestionDetails)==null||u.call(window,0),gtag==null||gtag("event","nuclen_quiz_end"),c==="with_results"&&window.NuclenOptinEnabled&&window.NuclenOptinWebhook&&((s=document.getElementById("nuclen-optin-submit"))==null||s.addEventListener("click",async()=>{const i=document.getElementById("nuclen-optin-name").value.trim(),n=document.getElementById("nuclen-optin-email").value.trim();if(!n){alert("Please enter a valid email");return}gtag==null||gtag("event","nuclen_quiz_optin");try{const b=await fetch(window.NuclenOptinWebhook,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({name:i,email:n})});if(!b.ok)throw new Error(b.statusText);alert(window.NuclenOptinSuccessMessage),document.getElementById("nuclen-optin-name").value="",document.getElementById("nuclen-optin-email").value=""}catch{alert("Unable to submit. Please try later.")}}))}function O(){var e,t;h.innerHTML="",f.innerHTML="",g.innerHTML="",a==null||a.classList.add("nuclen-quiz-hidden"),q.innerHTML=`
      <div id="nuclen-optin-container">
        <p class="nuclen-fg"><strong>${m?"Please enter your details to view your score:":"Optional: join our list to receive more quizzes."}</strong></p>

        <label for="nuclen-optin-name"  class="nuclen-fg">Name</label>
        <input  type="text"  id="nuclen-optin-name">
        <label for="nuclen-optin-email" class="nuclen-fg">Email *</label>
        <input  type="email" id="nuclen-optin-email" required>

        <div style="margin-top:1em;display:flex;gap:10px;">
          <button type="button" id="nuclen-optin-submit">${m?"Submit & view results":"Submit"}</button>
          ${m?"":'<a href="#" id="nuclen-optin-skip" style="align-self:center;font-size:.85em;">Skip & view results</a>'}
        </div>
      </div>
    `,(e=document.getElementById("nuclen-optin-submit"))==null||e.addEventListener("click",async()=>{const u=document.getElementById("nuclen-optin-name").value.trim(),s=document.getElementById("nuclen-optin-email").value.trim();if(!s){alert("Please enter a valid email");return}try{await fetch(window.NuclenOptinWebhook,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({name:u,email:s})}),alert(window.NuclenOptinSuccessMessage),v=!0,E()}catch{alert("Network error – please try again later.")}}),(t=document.getElementById("nuclen-optin-skip"))==null||t.addEventListener("click",u=>{u.preventDefault(),v=!0,E()})}window.nuclearEngagementShowQuizQuestionDetails=e=>{var i;const t=l[e],u=z[e],s=`
      <p class="nuclen-quiz-detail-question">${t.question}</p>
      <p class="nuclen-quiz-detail-correct"><strong>Correct:</strong> ${t.answers[0]}</p>
      ${u===0?`<p class="nuclen-quiz-detail-chosen"><strong>Your answer:</strong> ${t.answers[0]} <span class="nuclen-quiz-checkmark">✔️</span></p>`:`<p class="nuclen-quiz-detail-chosen"><strong>Your answer:</strong> ${t.answers[u]??"[No data]"}</p>`}
      <p class="nuclen-quiz-detail-explanation">${t.explanation}</p>
    `;document.getElementById("nuclen-quiz-result-details-container").innerHTML=s,Array.from(document.getElementsByClassName("nuclen-quiz-result-tab")).forEach(n=>n.classList.remove("nuclen-quiz-result-active-tab")),(i=document.getElementsByClassName("nuclen-quiz-result-tab")[e])==null||i.classList.add("nuclen-quiz-result-active-tab")},window.nuclearEngagementRetakeQuiz=()=>{o=0,p=0,z.length=0,q.innerHTML="",y.style.width=`${1/l.length*100}%`,I()};function Q(e,t,u){var i;e===0&&p++,z.push(e);const s=f.getElementsByTagName("button");for(let n=0;n<s.length;n++)s[n].classList.remove("nuclen-quiz-possible-answer"),n===u?s[n].classList.add("nuclen-quiz-answer-correct","nuclen-quiz-pulse"):n===t?s[n].classList.add("nuclen-quiz-answer-wrong"):s[n].classList.add("nuclen-quiz-answer-not-selected"),s[n].disabled=!0;g.classList.remove("nuclen-quiz-hidden"),g.innerHTML=`<p>${l[o].explanation}</p>`,a.classList.remove("nuclen-quiz-hidden"),r==null||r.scrollIntoView(),typeof gtag=="function"&&(o===0&&(gtag("event","nuclen_quiz_start"),(i=document.getElementById("nuclen-quiz-start-message"))==null||i.remove()),gtag("event","nuclen_quiz_answer"))}function I(){const e=l[o];h.innerHTML=`
      <div id="nuclen-quiz-question-number">${o+1}/${l.length}</div>
      <div class="nuclen-quiz-title">${e.question}</div>
    `;const t=e.answers.map((i,n)=>({ans:i,idx:n})).filter(i=>i.ans.trim()).sort(()=>Math.random()-.5);f.innerHTML=t.map(i=>`
        <button class="nuclen-quiz-answer-button nuclen-quiz-possible-answer"
                data-orig-idx="${i.idx}">
          ${i.ans}
        </button>`).join("");const u=t.findIndex(i=>i.idx===0);a.classList.add("nuclen-quiz-hidden"),g.innerHTML="",M();function s(i){const n=i.target;if(!n.matches("button.nuclen-quiz-answer-button"))return;const b=parseInt(n.getAttribute("data-orig-idx")||"0",10),T=t.findIndex($=>$.idx===b);setTimeout(()=>Q(b,T,u),100),f.removeEventListener("click",s)}f.addEventListener("click",s)}I()}window.nuclearEngagementInitQuiz=S;
