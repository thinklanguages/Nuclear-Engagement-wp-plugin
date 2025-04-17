window.NuclenLazyLoadComponent=function(z,g=null){const i=document.getElementById(z);if(!i)return;const u=new IntersectionObserver(m=>{m.forEach(p=>{p.isIntersecting&&(g&&typeof window[g]=="function"&&window[g](),u.disconnect())})},{rootMargin:"0px 0px -200px 0px",threshold:.1});u.observe(i)};function L(z,g){const i=document.querySelector(z);if(!i)return;new IntersectionObserver((m,p)=>{m.forEach(r=>{r.isIntersecting&&r.intersectionRatio===1&&(typeof gtag=="function"&&gtag("event",g),p.unobserve(r.target))})},{root:null,rootMargin:"0px",threshold:1}).observe(i)}const M=new MutationObserver((z,g)=>{const i=document.getElementById("nuclen-quiz-container"),u=document.getElementById("nuclen-summary-container");i&&u&&(L("#nuclen-summary-container","nuclen_summary_view"),L("#nuclen-quiz-container","nuclen_quiz_view"),g.disconnect())});M.observe(document.body,{childList:!0,subtree:!0});window.NuclenLazyLoadComponent("nuclen-quiz-container","nuclearEngagementInitQuiz");function T(){var I,E;const z=parseInt((I=window.NuclenSettings)==null?void 0:I.questions_per_quiz,10)||10,g=parseInt((E=window.NuclenSettings)==null?void 0:E.answers_per_question,10)||4,i=postQuizData.filter(e=>e.question&&e.question.trim().length>0).map(e=>{const s=e.answers.filter(c=>c&&c.trim().length>0).slice(0,g);return{...e,answers:s}}).filter(e=>e.answers.length>0).slice(0,z);let u=0,m=0;const p=[],r=document.getElementById("nuclen-quiz-container");if(!r)return;const q=document.getElementById("nuclen-quiz-question-container"),a=document.getElementById("nuclen-quiz-answers-container"),b=document.getElementById("nuclen-quiz-progress-bar"),h=document.getElementById("nuclen-quiz-final-result-container"),l=document.getElementById("nuclen-quiz-next-button"),f=document.getElementById("nuclen-quiz-explanation-container");f&&(f.innerHTML=""),l==null||l.addEventListener("click",Q);function Q(){u++,u<i.length?y():k(),r==null||r.scrollIntoView()}function N(){if(!b)return;const e=(u+1)/i.length*100;b.style.width=`${e}%`}function k(){var c;q&&(q.innerHTML=""),a&&(a.innerHTML=""),f&&(f.innerHTML=""),l==null||l.classList.add("nuclen-quiz-hidden");let e=`
      <div id="nuclen-quiz-results-title" class="nuclen-fg">Your Score</div>
      <div id="nuclen-quiz-results-score" class="nuclen-fg">${m} / ${i.length}</div>
    `,s="";if(m===i.length?s="Perfect!":m>i.length/2?s="Well done!":s="Why not retake the test?",e+=`<div id="nuclen-quiz-score-comment">${s}</div>`,e+='<div id="nuclen-quiz-result-tabs-container">',i.forEach((o,n)=>{e+=`
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
    `,h&&(h.innerHTML=e),(c=window.nuclearEngagementShowQuizQuestionDetails)==null||c.call(window,0),typeof gtag=="function"&&gtag("event","nuclen_quiz_end"),window.NuclenOptinEnabled&&window.NuclenOptinWebhook){const o=document.getElementById("nuclen-optin-submit");o==null||o.addEventListener("click",async()=>{const n=document.getElementById("nuclen-optin-name"),t=document.getElementById("nuclen-optin-email"),d=(n==null?void 0:n.value.trim())??"",v=(t==null?void 0:t.value.trim())??"";if(!v){alert("Please enter a valid email address.");return}typeof gtag=="function"&&gtag("event","nuclen_quiz_optin");try{const w=await fetch(window.NuclenOptinWebhook,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({name:d,email:v})});if(!w.ok){console.error("Webhook error:",w.status,w.statusText),alert("Oops! Could not submit your info.");return}alert(window.NuclenOptinSuccessMessage),n&&(n.value=""),t&&(t.value="")}catch(w){console.error("Fetch error:",w),alert("Unable to connect. Please try again later.")}})}}window.nuclearEngagementShowQuizQuestionDetails=function(e){const s=i[e],c=p[e],o=document.getElementById("nuclen-quiz-result-details-container");if(!o)return;let n=`
      <p class="nuclen-quiz-detail-question">${s.question}</p>
      <p class="nuclen-quiz-detail-correct"><strong>Correct answer:</strong> ${s.answers[0]}</p>
    `;if(c===0)n+=`
        <p class="nuclen-quiz-detail-chosen">
          <strong>Your answer:</strong> ${s.answers[0]}
          <span class="nuclen-quiz-checkmark">✔️</span>
        </p>`;else{const d=s.answers[c]??"[No data]";n+=`
        <p class="nuclen-quiz-detail-chosen">
          <strong>Your answer:</strong> ${d}
        </p>`}n+=`<p class="nuclen-quiz-detail-explanation">${s.explanation}</p>`,o.innerHTML=n;const t=document.getElementsByClassName("nuclen-quiz-result-tab");for(let d=0;d<t.length;d++)t[d].classList.remove("nuclen-quiz-result-active-tab");t[e]&&t[e].classList.add("nuclen-quiz-result-active-tab")},window.nuclearEngagementRetakeQuiz=function(){u=0,m=0,p.length=0,h&&(h.innerHTML=""),b&&(b.style.width=`${1/i.length*100}%`),y()};function $(e,s,c){const o=i[u];if(e===0&&m++,p.push(e),!a)return;const n=a.getElementsByTagName("button");for(let t=0;t<n.length;t++)n[t].classList.remove("nuclen-quiz-possible-answer"),t===c?n[t].classList.add("nuclen-quiz-answer-correct","nuclen-quiz-pulse"):t===s?n[t].classList.add("nuclen-quiz-answer-wrong"):n[t].classList.add("nuclen-quiz-answer-not-selected"),n[t].disabled=!0;if(f&&(f.classList.remove("nuclen-quiz-hidden"),f.innerHTML=`<p>${o.explanation}</p>`),l==null||l.classList.remove("nuclen-quiz-hidden"),r==null||r.scrollIntoView(),typeof gtag=="function"){if(u===0){gtag("event","nuclen_quiz_start");const t=document.getElementById("nuclen-quiz-start-message");t&&(t.style.display="none")}gtag("event","nuclen_quiz_answer")}}function y(){const e=i[u];if(!e)return;q&&(q.innerHTML=`
        <div id="nuclen-quiz-question-number">${u+1}/${i.length}</div>
        <div class="nuclen-quiz-title">${e.question}</div>
      `);const s=e.answers.map((o,n)=>({answer:o,originalIndex:n}));s.sort(()=>Math.random()-.5),a&&(a.innerHTML=s.map(({answer:o,originalIndex:n})=>`
          <button
            class="nuclen-quiz-answer-button nuclen-quiz-possible-answer"
            data-original-index="${n}"
          >
            ${o}
          </button>
        `).join(""));const c=s.findIndex(o=>o.originalIndex===0);l==null||l.classList.add("nuclen-quiz-hidden"),f&&(f.innerHTML=""),N(),a==null||a.addEventListener("click",function o(n){const t=n.target;if(t.matches("button.nuclen-quiz-answer-button")){const d=parseInt(t.getAttribute("data-original-index")||"0",10),v=s.findIndex(w=>w.originalIndex===d);setTimeout(()=>{$(d,v,c)},100),a.removeEventListener("click",o)}})}y()}window.nuclearEngagementInitQuiz=T;
