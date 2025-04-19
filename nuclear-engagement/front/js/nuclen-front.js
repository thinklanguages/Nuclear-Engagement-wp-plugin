window.NuclenLazyLoadComponent=function(z,d=null){const i=document.getElementById(z);if(!i)return;const u=new IntersectionObserver(g=>{g.forEach(p=>{p.isIntersecting&&(d&&typeof window[d]=="function"&&window[d](),u.disconnect())})},{rootMargin:"0px 0px -200px 0px",threshold:.1});u.observe(i)};function L(z,d){const i=document.querySelector(z);if(!i)return;new IntersectionObserver((g,p)=>{g.forEach(c=>{c.isIntersecting&&c.intersectionRatio===1&&(typeof gtag=="function"&&gtag("event",d),p.unobserve(c.target))})},{root:null,rootMargin:"0px",threshold:1}).observe(i)}const M=new MutationObserver((z,d)=>{const i=document.getElementById("nuclen-quiz-container"),u=document.getElementById("nuclen-summary-container");i&&u&&(L("#nuclen-summary-container","nuclen_summary_view"),L("#nuclen-quiz-container","nuclen_quiz_view"),d.disconnect())});M.observe(document.body,{childList:!0,subtree:!0});window.NuclenLazyLoadComponent("nuclen-quiz-container","nuclearEngagementInitQuiz");function T(){var I,E;const z=parseInt((I=window.NuclenSettings)==null?void 0:I.questions_per_quiz,10)||10,d=parseInt((E=window.NuclenSettings)==null?void 0:E.answers_per_question,10)||4,i=postQuizData.filter(e=>e.question.trim()!==""&&e.answers[0]&&e.answers[0].trim()!=="").slice(0,z).map(e=>({...e,answers:e.answers.slice(0,d)}));let u=0,g=0;const p=[],c=document.getElementById("nuclen-quiz-container");if(!c)return;const q=document.getElementById("nuclen-quiz-question-container"),a=document.getElementById("nuclen-quiz-answers-container"),b=document.getElementById("nuclen-quiz-progress-bar"),h=document.getElementById("nuclen-quiz-final-result-container"),l=document.getElementById("nuclen-quiz-next-button"),m=document.getElementById("nuclen-quiz-explanation-container");m&&(m.innerHTML=""),l==null||l.addEventListener("click",Q);function Q(){u++,u<i.length?y():k(),c==null||c.scrollIntoView()}function N(){if(!b)return;const e=(u+1)/i.length*100;b.style.width=`${e}%`}function k(){var f;q&&(q.innerHTML=""),a&&(a.innerHTML=""),m&&(m.innerHTML=""),l==null||l.classList.add("nuclen-quiz-hidden");let e=`
      <div id="nuclen-quiz-results-title" class="nuclen-fg">Your Score</div>
      <div id="nuclen-quiz-results-score" class="nuclen-fg">${g} / ${i.length}</div>
    `,o="";if(g===i.length?o="Perfect!":g>i.length/2?o="Well done!":o="Why not retake the test?",e+=`<div id="nuclen-quiz-score-comment">${o}</div>`,e+='<div id="nuclen-quiz-result-tabs-container">',i.forEach((s,n)=>{e+=`
        <button 
          class="nuclen-quiz-result-tab" 
          onclick="nuclearEngagementShowQuizQuestionDetails(${n})"
        >
          ${n+1}
        </button>`}),e+="</div>",e+='<div id="nuclen-quiz-result-details-container" class="nuclen-fg dashboard-box"></div>',typeof NuclenCustomQuizHtmlAfter=="string"&&NuclenCustomQuizHtmlAfter.trim()!==""&&(e+=`
        <div id="nuclen-quiz-end-message" class="nuclen-fg">
          ${NuclenCustomQuizHtmlAfter}
        </div>
      `),window.NuclenOptinEnabled&&window.NuclenOptinWebhook&&(e+=`
        <div id="nuclen-optin-container">
          <label for="nuclen-optin-name" class="nuclen-fg">Name</label>
          <input type="text" id="nuclen-optin-name">

          <label for="nuclen-optin-email" class="nuclen-fg">Email</label>
          <input type="email" id="nuclen-optin-email" required>

          <button type="button" id="nuclen-optin-submit">Sign up</button>
        </div>
      `),e+=`
      <button id="nuclen-quiz-retake-button" onclick="nuclearEngagementRetakeQuiz()">
        Retake Test
      </button>
    `,h&&(h.innerHTML=e),(f=window.nuclearEngagementShowQuizQuestionDetails)==null||f.call(window,0),typeof gtag=="function"&&gtag("event","nuclen_quiz_end"),window.NuclenOptinEnabled&&window.NuclenOptinWebhook){const s=document.getElementById("nuclen-optin-submit");s==null||s.addEventListener("click",async()=>{const n=document.getElementById("nuclen-optin-name"),t=document.getElementById("nuclen-optin-email"),r=(n==null?void 0:n.value.trim())??"",v=(t==null?void 0:t.value.trim())??"";if(!v){alert("Please enter a valid email address.");return}typeof gtag=="function"&&gtag("event","nuclen_quiz_optin");try{const w=await fetch(window.NuclenOptinWebhook,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({name:r,email:v})});if(!w.ok){console.error("Webhook error:",w.status,w.statusText),alert("Oops! Could not submit your info.");return}alert(window.NuclenOptinSuccessMessage),n&&(n.value=""),t&&(t.value="")}catch(w){console.error("Fetch error:",w),alert("Unable to connect. Please try again later.")}})}}window.nuclearEngagementShowQuizQuestionDetails=function(e){const o=i[e],f=p[e],s=document.getElementById("nuclen-quiz-result-details-container");if(!s)return;let n=`
      <p class="nuclen-quiz-detail-question">${o.question}</p>
      <p class="nuclen-quiz-detail-correct"><strong>Correct answer:</strong> ${o.answers[0]}</p>
    `;if(f===0)n+=`
        <p class="nuclen-quiz-detail-chosen">
          <strong>Your answer:</strong> ${o.answers[0]}
          <span class="nuclen-quiz-checkmark">✔️</span>
        </p>`;else{const r=o.answers[f]??"[No data]";n+=`
        <p class="nuclen-quiz-detail-chosen">
          <strong>Your answer:</strong> ${r}
        </p>`}n+=`<p class="nuclen-quiz-detail-explanation">${o.explanation}</p>`,s.innerHTML=n;const t=document.getElementsByClassName("nuclen-quiz-result-tab");for(let r=0;r<t.length;r++)t[r].classList.remove("nuclen-quiz-result-active-tab");t[e]&&t[e].classList.add("nuclen-quiz-result-active-tab")},window.nuclearEngagementRetakeQuiz=function(){u=0,g=0,p.length=0,h&&(h.innerHTML=""),b&&(b.style.width=`${1/i.length*100}%`),y()};function $(e,o,f){const s=i[u];if(e===0&&g++,p.push(e),!a)return;const n=a.getElementsByTagName("button");for(let t=0;t<n.length;t++)n[t].classList.remove("nuclen-quiz-possible-answer"),t===f?n[t].classList.add("nuclen-quiz-answer-correct","nuclen-quiz-pulse"):t===o?n[t].classList.add("nuclen-quiz-answer-wrong"):n[t].classList.add("nuclen-quiz-answer-not-selected"),n[t].disabled=!0;if(m&&(m.classList.remove("nuclen-quiz-hidden"),m.innerHTML=`<p>${s.explanation}</p>`),l==null||l.classList.remove("nuclen-quiz-hidden"),c==null||c.scrollIntoView(),typeof gtag=="function"){if(u===0){gtag("event","nuclen_quiz_start");const t=document.getElementById("nuclen-quiz-start-message");t&&(t.style.display="none")}gtag("event","nuclen_quiz_answer")}}function y(){const e=i[u];if(!e)return;q&&(q.innerHTML=`
        <div id="nuclen-quiz-question-number">${u+1}/${i.length}</div>
        <div class="nuclen-quiz-title">${e.question}</div>
      `);const o=e.answers.map((s,n)=>({answer:s,originalIndex:n})).filter(s=>s.answer.trim()!=="");o.sort(()=>Math.random()-.5),a&&(a.innerHTML=o.map(({answer:s,originalIndex:n})=>`
          <button
            class="nuclen-quiz-answer-button nuclen-quiz-possible-answer"
            data-original-index="${n}"
          >
            ${s}
          </button>
        `).join(""));const f=o.findIndex(s=>s.originalIndex===0);l==null||l.classList.add("nuclen-quiz-hidden"),m&&(m.innerHTML=""),N(),a==null||a.addEventListener("click",function s(n){const t=n.target;if(t.matches("button.nuclen-quiz-answer-button")){const r=parseInt(t.getAttribute("data-original-index")||"0",10),v=o.findIndex(w=>w.originalIndex===r);setTimeout(()=>{$(r,v,f)},100),a.removeEventListener("click",s)}})}y()}window.nuclearEngagementInitQuiz=T;
