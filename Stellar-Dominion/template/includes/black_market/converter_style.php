<style>
/* Converter facelift — glass, glow, gradients; scoped to first .content-box only */
main .content-box:first-of-type{position:relative;overflow:hidden;}
main .content-box:first-of-type{backdrop-filter:blur(6px);}
main .content-box:first-of-type::before{
  content:"";position:absolute;inset:-1px;pointer-events:none;
  background:
    radial-gradient(120% 80% at 10% 0%, rgba(59,130,246,.30), transparent 42%),
    radial-gradient(120% 80% at 90% 0%, rgba(250,204,21,.25), transparent 42%),
    linear-gradient(90deg, rgba(168,85,247,.22), rgba(6,182,212,.22));
}
main .content-box:first-of-type h2{
  text-shadow:0 2px 16px rgba(6,182,212,.6),0 0 2px rgba(255,255,255,.3);
}
/* subtle tagline under the heading without changing markup */
main .content-box:first-of-type > h2::after{
  content:"Swap with style. Fuel your next bet.";
  display:block;font-size:.875rem;margin-top:.25rem;
  color:#a78bfa;text-shadow:0 0 12px rgba(168,85,247,.45);
}
/* inner cards — animated rainbow glow edge + glass */
main .content-box:first-of-type .grid > div{position:relative;background:rgba(17,24,39,.55);border-color:rgba(148,163,184,.35);}
main .content-box:first-of-type .grid > div::before{
  content:"";position:absolute;inset:-1px;border-radius:.5rem;z-index:0;opacity:.35;filter:blur(10px);
  background:linear-gradient(135deg, rgba(59,130,246,.45), rgba(168,85,247,.45), rgba(34,197,94,.45), rgba(6,182,212,.45));
  background-size:300% 300%;animation:bmGlow 8s ease infinite;
}
main .content-box:first-of-type .grid > div > *{position:relative;z-index:1;}
@keyframes bmGlow{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}

/* inputs + buttons — neon focus */
main .content-box:first-of-type input[type="number"]{box-shadow:inset 0 0 0 1px rgba(148,163,184,.35);}
main .content-box:first-of-type input[type="number"]:focus{outline:0;box-shadow:0 0 0 2px rgba(6,182,212,.6),0 0 20px rgba(59,130,246,.35);}

main .content-box:first-of-type .btn{position:relative;}
main .content-box:first-of-type .btn::after{
  content:"";position:absolute;inset:-2px;border-radius:.5rem;z-index:-1;transition:opacity .2s ease;
  background:linear-gradient(45deg, rgba(99,102,241,.7), rgba(20,184,166,.7));opacity:.5;filter:blur(8px);
}
main .content-box:first-of-type .btn:hover::after{opacity:.9;}

/* High-contrast rate pill (addresses the contrast issue) */
#c2g form + .text-sm.mt-1,
#g2c form + .text-sm.mt-1{
  display:inline-block;margin-top:.5rem;padding:.25rem .5rem;border-radius:9999px;
  background:rgba(2,6,23,.85);border:1px solid rgba(148,163,184,.5);
  color:#e5e7eb;font-weight:600;text-shadow:0 1px 8px rgba(0,240,255,.35);
}

/* Result messages */
#c2g-res,#g2c-res{margin-top:.5rem;font-weight:700;color:#e2e8f0;text-shadow:0 1px 8px rgba(59,130,246,.6);}
#c2g-res.error,#g2c-res.error{color:#fecaca;text-shadow:0 1px 8px rgba(239,68,68,.6);}

</style>
