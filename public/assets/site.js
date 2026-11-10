(function(){
  const menu=document.querySelector('.menu-toggle'), nav=document.getElementById('main-nav');
  menu?.addEventListener('click',()=>{const open=nav.classList.toggle('open');menu.setAttribute('aria-expanded',String(open));menu.textContent=open?'✕':'☰';});
  document.querySelectorAll('#main-nav a').forEach(a=>a.addEventListener('click',()=>{nav?.classList.remove('open');menu?.setAttribute('aria-expanded','false');}));
  const game=document.getElementById('experiment');if(!game)return;
  let step=1, choices=[];
  const rounds=[
   {question:'Look at these two shapes. Which one should be called <em>KIKI</em>?',one:'Spiky',two:'Rounded',label:'KIKI',fact:'Many people match “kiki” with a sharp, angular shape.'},
   {question:'Which shape should be called <em>BOUBA</em>?',one:'Spiky',two:'Rounded',label:'BOUBA',fact:'Many people pair “bouba” with a soft, round shape.'},
   {question:'What would you call a small <em>fluffy</em> creature?',one:'MALUMA',two:'TAKETE',label:'SOUND',fact:'Sound patterns sometimes carry emotional or sensory associations even before we learn a word’s meaning.'}
  ];
  const spiky='<svg viewBox="0 0 200 200" aria-hidden="true"><path d="M99 7 119 54 170 27 151 82 194 104 147 121 175 172 113 150 88 194 72 140 13 165 51 111 6 78 64 79Z" fill="#F6B93B" stroke="#161B31" stroke-width="5"/></svg>';
  const round='<svg viewBox="0 0 200 200" aria-hidden="true"><path d="M99 12C151 7 187 48 175 88S169 167 114 177 18 162 20 109 51 15 99 12Z" fill="#CBE9AC" stroke="#161B31" stroke-width="5"/></svg>';
  const show=(html)=>{game.innerHTML=html;};
  function draw(){const r=rounds[step-1];show(`<div class="experiment-step"><p class="step-label">PART ${step} OF 3</p><h3>${r.question}</h3><div class="shapes" role="group" aria-label="Choose an answer"><button type="button" class="shape-btn" data-choice="0"><span>${step<3?spiky:'✳'}</span><span>A / ${r.one}</span></button><button type="button" class="shape-btn" data-choice="1"><span>${step<3?round:'◡'}</span><span>B / ${r.two}</span></button></div></div>`);}
  function reveal(choice){const r=rounds[step-1];const picked=choice===0?r.one:r.two;choices.push(picked);show(`<p class="step-label">PART ${step} OF 3 — YOUR INSTINCT</p><div class="result-box"><h3>You chose ${picked}.</h3><p>${r.fact}</p><p>There is no grade here: the interesting question is how the sounds made you <em>feel</em>.</p></div><button type="button" class="btn btn-blue experiment-next" data-next>${step===3?'See the big picture':'Keep playing'} ↗</button>`);}
  function finish(){show(`<p class="step-label">THE FINAL SURPRISE</p><h3>You’ve just let sound and imagination do some of the work.</h3><p>Words are more than definitions. Rhythm, shape, memory and feeling can interact in unexpected ways.</p><p><strong>Your choices:</strong> ${choices.join(' • ')}</p><div class="actions"><a class="btn btn-blue" href="?page=playground">Invent a word for the wall ↗</a><button type="button" class="btn btn-outline" data-restart>Play again ↺</button></div>`);}
  draw();
  game.addEventListener('click',(e)=>{const btn=e.target.closest('button');if(!btn)return;if(btn.dataset.choice!==undefined)reveal(Number(btn.dataset.choice));else if(btn.hasAttribute('data-next')){step++;step>3?finish():draw();}else if(btn.hasAttribute('data-restart')){step=1;choices=[];draw();}});
})();