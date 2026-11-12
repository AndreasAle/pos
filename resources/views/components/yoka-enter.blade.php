@props(['arrive' => false])
{{-- Transisi masuk/keluar mode fokus Yoka AI.
     - Klik tautan [data-yoka-enter] / [data-yoka-exit] -> layar animasi, lalu pindah halaman.
     - arrive=true (halaman Yoka AI): layar animasi sudah tampil saat halaman dibuka lalu memudar. --}}
<style>
    #yoka-enter{position:fixed;inset:0;z-index:2147483000;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:26px;color:#fff;
        background:radial-gradient(1100px 700px at 50% 38%,#0F7A4C 0%,#0A5C3A 45%,#052E1C 100%);opacity:0;visibility:hidden;pointer-events:none;transition:opacity .45s ease,visibility .45s ease}
    #yoka-enter.is-on{opacity:1;visibility:visible;pointer-events:auto}
    #yoka-enter .orb{position:relative;width:132px;height:132px;display:flex;align-items:center;justify-content:center}
    #yoka-enter .orb::before,#yoka-enter .orb::after{content:"";position:absolute;inset:0;border-radius:9999px;border:2px solid rgba(110,231,183,.55);animation:ye-ring 2s ease-out infinite}
    #yoka-enter .orb::after{animation-delay:1s}
    #yoka-enter .core{width:84px;height:84px;border-radius:26px;background:linear-gradient(145deg,#34D399,#16A34A);display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 8px rgba(255,255,255,.08),0 20px 50px rgba(0,0,0,.35);animation:ye-float 2.4s ease-in-out infinite}
    #yoka-enter .core svg{width:42px;height:42px;animation:ye-spin 6s linear infinite}
    #yoka-enter .spark{position:absolute;color:#A7F3D0;animation:ye-twinkle 1.8s ease-in-out infinite}
    #yoka-enter h2{font-family:var(--font-display);font-size:22px;font-weight:800;letter-spacing:-.01em;text-align:center}
    #yoka-enter .sub{height:22px;font-size:14px;font-weight:600;color:#BBF7D0;text-align:center}
    #yoka-enter .bar{width:min(280px,70vw);height:6px;border-radius:9999px;background:rgba(255,255,255,.14);overflow:hidden}
    #yoka-enter .bar i{display:block;height:100%;width:0;border-radius:9999px;background:linear-gradient(90deg,#6EE7B7,#fff);transition:width 1.1s cubic-bezier(.22,.8,.3,1)}
    #yoka-enter.is-on .bar i{width:100%}
    #yoka-enter.is-arrive .bar i{width:100%;transition-duration:.7s}
    @keyframes ye-ring{0%{transform:scale(.7);opacity:.9}100%{transform:scale(1.55);opacity:0}}
    @keyframes ye-float{0%,100%{transform:translateY(0)}50%{transform:translateY(-7px)}}
    @keyframes ye-spin{to{transform:rotate(360deg)}}
    @keyframes ye-twinkle{0%,100%{opacity:.2;transform:scale(.7)}50%{opacity:1;transform:scale(1.15)}}
    @media (prefers-reduced-motion:reduce){#yoka-enter *,#yoka-enter .orb::before,#yoka-enter .orb::after{animation:none!important}}
</style>
<div id="yoka-enter" class="{{ $arrive ? 'is-on is-arrive' : '' }}" role="status" aria-live="polite" aria-label="Memuat">
    <div class="orb">
        <svg class="spark" style="top:-4px;left:6px;width:18px;height:18px" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2 7 7 2-7 2-2 7-2-7-7-2 7-2z"/></svg>
        <svg class="spark" style="bottom:2px;right:-6px;width:14px;height:14px;animation-delay:.6s" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2 7 7 2-7 2-2 7-2-7-7-2 7-2z"/></svg>
        <svg class="spark" style="top:22px;right:-14px;width:10px;height:10px;animation-delay:1.1s" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2 7 7 2-7 2-2 7-2-7-7-2 7-2z"/></svg>
        <div class="core"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5L12 3zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15z"/></svg></div>
    </div>
    <div>
        <h2 id="ye-title">{{ $arrive ? 'Yoka AI siap membantu' : 'Membuka Yoka AI' }}</h2>
        <p class="sub" id="ye-sub">{{ $arrive ? 'Data tokomu sudah terhubung' : 'Menghubungkan ke data tokomu…' }}</p>
    </div>
    <div class="bar"><i></i></div>
</div>
<script>
(function(){
    var el=document.getElementById('yoka-enter');if(!el)return;
    var title=document.getElementById('ye-title'),sub=document.getElementById('ye-sub');
    var go=function(href,t,steps){
        title.textContent=t;var i=0;sub.textContent=steps[0];
        var iv=setInterval(function(){i=(i+1)%steps.length;sub.textContent=steps[i]},420);
        el.classList.remove('is-arrive');el.classList.add('is-on');
        setTimeout(function(){clearInterval(iv);location.href=href},1150);
    };
    document.addEventListener('click',function(e){
        var a=e.target.closest&&e.target.closest('a[data-yoka-enter],a[data-yoka-exit]');
        if(!a||e.defaultPrevented||e.metaKey||e.ctrlKey||e.shiftKey||e.altKey||e.button!==0)return;
        e.preventDefault();e.stopPropagation();
        if(a.hasAttribute('data-yoka-enter'))go(a.href,'Membuka Yoka AI',['Menghubungkan ke data tokomu…','Menyiapkan analisis bisnis…','Hampir siap…']);
        else go(a.href,'Kembali ke Dashboard',['Menyimpan percakapan…','Membuka ruang kerja POS…']);
    },true);
    @if($arrive)
    var leave=function(){setTimeout(function(){el.classList.remove('is-on','is-arrive')},750)};
    if(document.readyState==='complete')leave();else window.addEventListener('load',leave);
    @endif
    window.addEventListener('pageshow',function(e){if(e.persisted){el.classList.remove('is-on','is-arrive')}});
})();
</script>
