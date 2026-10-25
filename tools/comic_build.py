"""Builds the comic "How the BULIG Friends Met" (app/story_pages.json).
Each page is one big SVG scene drawn here (sky, hills, castle, props) with the BULIG friends' pictures placed on it.
Run:  python3 tools/comic_build.py   (then python3 tools/update_checker.py)"""
import math,html,random,json,os
SCRIPT=[]   # (who, text) for every narrator box and speech bubble, in the order they are drawn
CH='assets/images/characters/'
V='?v=2'
W,H=1200,760
FONT='Poppins, Segoe UI, Arial, sans-serif'
def esc(s):return html.escape(s,quote=True)
def defs(p):
    return f'''<defs>
<linearGradient id="{p}skyDusk" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1f1d55"/><stop offset=".35" stop-color="#5b3d8a"/><stop offset=".62" stop-color="#c8669a"/><stop offset=".82" stop-color="#f59a72"/><stop offset="1" stop-color="#ffd79a"/></linearGradient>
<linearGradient id="{p}skyDay" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#5fb8f0"/><stop offset=".6" stop-color="#a9dcff"/><stop offset="1" stop-color="#eaf8ff"/></linearGradient>
<linearGradient id="{p}skyNight" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0a1433"/><stop offset=".7" stop-color="#1c3a72"/><stop offset="1" stop-color="#355f9c"/></linearGradient>
<linearGradient id="{p}mtnFarDusk" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#9a7cc0"/><stop offset="1" stop-color="#c79ac0"/></linearGradient>
<linearGradient id="{p}mtnMidDusk" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#6d5a9e"/><stop offset="1" stop-color="#8f6fa6"/></linearGradient>
<linearGradient id="{p}mtnFarDay" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#9cc9e6"/><stop offset="1" stop-color="#cfe6f2"/></linearGradient>
<linearGradient id="{p}mtnMidDay" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#7fb894"/><stop offset="1" stop-color="#a9d6ae"/></linearGradient>
<linearGradient id="{p}hillA" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#8fd36f"/><stop offset="1" stop-color="#4f9f45"/></linearGradient>
<linearGradient id="{p}hillB" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#7cc463"/><stop offset="1" stop-color="#3d8a3d"/></linearGradient>
<linearGradient id="{p}hillDusk" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#7a9c5c"/><stop offset="1" stop-color="#3c5f3f"/></linearGradient>
<linearGradient id="{p}hillDusk2" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#5f8a50"/><stop offset="1" stop-color="#2d4a33"/></linearGradient>
<linearGradient id="{p}path" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f7e3b0"/><stop offset="1" stop-color="#e2c48a"/></linearGradient>
<linearGradient id="{p}stoneW" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#fff6e0"/><stop offset=".7" stop-color="#efdcb2"/><stop offset="1" stop-color="#d9c08c"/></linearGradient>
<linearGradient id="{p}roofB" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#4f93e0"/><stop offset=".55" stop-color="#2a6cc0"/><stop offset="1" stop-color="#1c4f94"/></linearGradient>
<linearGradient id="{p}roofR" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#ef6a52"/><stop offset=".55" stop-color="#d6452f"/><stop offset="1" stop-color="#a8301f"/></linearGradient>
<linearGradient id="{p}trunk" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#a5672f"/><stop offset="1" stop-color="#6e4019"/></linearGradient>
<linearGradient id="{p}cloud" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#dce8f5"/></linearGradient>
<linearGradient id="{p}cloudDusk" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffd9c8"/><stop offset="1" stop-color="#c9879f"/></linearGradient>
<linearGradient id="{p}plank" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#c88f55"/><stop offset="1" stop-color="#9c6532"/></linearGradient>
<linearGradient id="{p}bench" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#b9783d"/><stop offset="1" stop-color="#7a4a22"/></linearGradient>
<linearGradient id="{p}wood" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#b8763c"/><stop offset="1" stop-color="#7a4a22"/></linearGradient>
<radialGradient id="{p}sunGlow" cx="50%" cy="50%" r="50%"><stop offset="0" stop-color="#fff4c2" stop-opacity=".95"/><stop offset=".35" stop-color="#ffd27a" stop-opacity=".55"/><stop offset="1" stop-color="#ffb36b" stop-opacity="0"/></radialGradient>
<radialGradient id="{p}sunDisk" cx="40%" cy="35%" r="65%"><stop offset="0" stop-color="#fffbe0"/><stop offset=".55" stop-color="#ffe066"/><stop offset="1" stop-color="#f7b500"/></radialGradient>
<radialGradient id="{p}lampGlow" cx="50%" cy="50%" r="50%"><stop offset="0" stop-color="#fff3b0" stop-opacity=".9"/><stop offset=".4" stop-color="#ffd23f" stop-opacity=".45"/><stop offset="1" stop-color="#ffd23f" stop-opacity="0"/></radialGradient>
<radialGradient id="{p}dimGlow" cx="50%" cy="50%" r="50%"><stop offset="0" stop-color="#e2d39a" stop-opacity=".7"/><stop offset="1" stop-color="#e2d39a" stop-opacity="0"/></radialGradient>
<radialGradient id="{p}cone" cx="50%" cy="0%" r="100%" fx="50%" fy="0%"><stop offset="0" stop-color="#fff2c0" stop-opacity=".85"/><stop offset=".6" stop-color="#ffe08a" stop-opacity=".25"/><stop offset="1" stop-color="#ffe08a" stop-opacity="0"/></radialGradient>
<radialGradient id="{p}robotGlow" cx="50%" cy="50%" r="50%"><stop offset="0" stop-color="#fffbe0"/><stop offset=".3" stop-color="#ffe066" stop-opacity=".8"/><stop offset="1" stop-color="#ffb000" stop-opacity="0"/></radialGradient>
<radialGradient id="{p}vig" cx="50%" cy="45%" r="75%"><stop offset=".6" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".38"/></radialGradient>
<radialGradient id="{p}fly" cx="50%" cy="50%" r="50%"><stop offset="0" stop-color="#fffbd0"/><stop offset=".4" stop-color="#ffe866" stop-opacity=".8"/><stop offset="1" stop-color="#ffe866" stop-opacity="0"/></radialGradient>
<pattern id="{p}grass" width="38" height="26" patternUnits="userSpaceOnUse"><path d="M4 26 Q6 14 9 8 M9 26 Q10 16 14 10 M22 26 Q23 15 27 9 M28 26 Q29 18 33 14" stroke="#ffffff" stroke-opacity=".18" stroke-width="2" fill="none" stroke-linecap="round"/></pattern>
<pattern id="{p}grassDk" width="38" height="26" patternUnits="userSpaceOnUse"><path d="M4 26 Q6 14 9 8 M9 26 Q10 16 14 10 M22 26 Q23 15 27 9 M28 26 Q29 18 33 14" stroke="#000" stroke-opacity=".12" stroke-width="2" fill="none" stroke-linecap="round"/></pattern>
<pattern id="{p}bricks" width="40" height="22" patternUnits="userSpaceOnUse"><path d="M0 21.5 H40 M20 0 V11 M0 11 H40 M0 11 V22 M40 11 V22" stroke="#c9ad7a" stroke-opacity=".55" stroke-width="1.5" fill="none"/></pattern>
<pattern id="{p}planks" width="90" height="760" patternUnits="userSpaceOnUse"><path d="M89 0 V760" stroke="#7a4a22" stroke-opacity=".5" stroke-width="3"/><path d="M30 120 q6 6 0 12 M60 420 q6 6 0 12 M20 640 q6 6 0 12" stroke="#7a4a22" stroke-opacity=".35" stroke-width="2" fill="none"/></pattern>
<pattern id="{p}dots" width="12" height="12" patternUnits="userSpaceOnUse"><circle cx="6" cy="6" r="1.7" fill="#fff" opacity=".22"/></pattern>
<filter id="{p}soft" x="-20%" y="-20%" width="140%" height="140%"><feGaussianBlur stdDeviation="6"/></filter>
<filter id="{p}softS" x="-20%" y="-20%" width="140%" height="140%"><feGaussianBlur stdDeviation="2.5"/></filter>
<filter id="{p}drop" x="-20%" y="-20%" width="140%" height="150%"><feDropShadow dx="0" dy="6" stdDeviation="6" flood-color="#000" flood-opacity=".28"/></filter>
<linearGradient id="{p}skyGold" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffb86b"/><stop offset=".5" stop-color="#ffd79a"/><stop offset="1" stop-color="#fff1cf"/></linearGradient>
<linearGradient id="{p}skyRain" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#8fa7bf"/><stop offset=".6" stop-color="#b9cddd"/><stop offset="1" stop-color="#e3eef5"/></linearGradient>
<linearGradient id="{p}cloudGray" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f2f5f8"/><stop offset="1" stop-color="#a9b6c4"/></linearGradient>
<linearGradient id="{p}skyForest" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#7cc4a0"/><stop offset=".5" stop-color="#b4e0c0"/><stop offset="1" stop-color="#e6f5da"/></linearGradient>
<linearGradient id="{p}skyHigh" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3f8fdc"/><stop offset=".6" stop-color="#8cc8f2"/><stop offset="1" stop-color="#d8efff"/></linearGradient>
<linearGradient id="{p}skyDawn" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#4b4a8f"/><stop offset=".35" stop-color="#c46f9a"/><stop offset=".65" stop-color="#ffb37a"/><stop offset="1" stop-color="#ffe6a8"/></linearGradient>
<linearGradient id="{p}skyWind" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#6ec1f0"/><stop offset=".6" stop-color="#b4e2ff"/><stop offset="1" stop-color="#f0faff"/></linearGradient>
<linearGradient id="{p}skyStorm" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#141a33"/><stop offset=".6" stop-color="#2b3558"/><stop offset="1" stop-color="#46507a"/></linearGradient>
<linearGradient id="{p}cloudStorm" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#5b6484"/><stop offset="1" stop-color="#2b3150"/></linearGradient>
<radialGradient id="{p}shield" cx="50%" cy="60%" r="60%"><stop offset="0" stop-color="#bff0ff" stop-opacity=".1"/><stop offset=".8" stop-color="#7fd8ff" stop-opacity=".35"/><stop offset="1" stop-color="#bff0ff" stop-opacity=".6"/></radialGradient>
<pattern id="{p}tiles" width="60" height="40" patternUnits="userSpaceOnUse"><path d="M0 39.5 H60 M30 0 V20 M0 20 H60 M0 20 V40 M60 20 V40" stroke="#c9b48a" stroke-width="2" fill="none"/></pattern>
</defs>'''
# ----- pieces -----
def cloud(p,x,y,s,g='cloud',o=1):
    return (f'<g transform="translate({x} {y}) scale({s})" opacity="{o}"><ellipse cx="0" cy="34" rx="92" ry="14" fill="#000" opacity=".06"/>'
            f'<circle cx="-50" cy="12" r="30" fill="url(#{p}{g})"/><circle cx="-10" cy="-6" r="42" fill="url(#{p}{g})"/><circle cx="36" cy="4" r="34" fill="url(#{p}{g})"/><circle cx="70" cy="18" r="22" fill="url(#{p}{g})"/>'
            f'<rect x="-80" y="14" width="170" height="26" rx="13" fill="url(#{p}{g})"/><circle cx="-22" cy="-18" r="16" fill="#fff" opacity=".45"/></g>')
def tree(p,x,y,s=1,c=('#2f7d3c','#45a04f','#73cf7c')):
    d,m,l=c
    return (f'<g transform="translate({x} {y}) scale({s})"><ellipse cx="6" cy="2" rx="46" ry="11" fill="#000" opacity=".18"/>'
            f'<path d="M-9 0 Q-7 -40 -4 -64 H6 Q8 -40 10 0Z" fill="url(#{p}trunk)"/>'
            f'<circle cx="-30" cy="-70" r="32" fill="{d}"/><circle cx="28" cy="-74" r="34" fill="{d}"/><circle cx="0" cy="-104" r="42" fill="{d}"/>'
            f'<circle cx="-24" cy="-80" r="27" fill="{m}"/><circle cx="22" cy="-86" r="28" fill="{m}"/><circle cx="-4" cy="-112" r="34" fill="{m}"/>'
            f'<circle cx="-16" cy="-122" r="20" fill="{l}"/><circle cx="-34" cy="-90" r="13" fill="{l}"/><circle cx="12" cy="-100" r="12" fill="{l}" opacity=".8"/>'
            f'<circle cx="-22" cy="-130" r="7" fill="#fff" opacity=".28"/></g>')
def pine(p,x,y,s=1,c=('#1f6a37','#2f8a4b','#4fb064')):
    d,m,l=c
    return (f'<g transform="translate({x} {y}) scale({s})"><ellipse cx="4" cy="2" rx="32" ry="8" fill="#000" opacity=".18"/><rect x="-6" y="-22" width="12" height="24" fill="url(#{p}trunk)"/>'
            f'<path d="M0 -150 L-34 -86 H34Z" fill="{d}"/><path d="M0 -122 L-44 -50 H44Z" fill="{d}"/><path d="M0 -92 L-52 -16 H52Z" fill="{d}"/>'
            f'<path d="M0 -150 L-34 -86 H0Z M0 -122 L-44 -50 H0Z M0 -92 L-52 -16 H0Z" fill="{m}"/><path d="M0 -150 L-14 -122 L0 -118Z M0 -122 L-18 -92 L0 -88Z M0 -92 L-20 -60 L0 -56Z" fill="{l}" opacity=".7"/></g>')
def bush(x,y,s=1,c=('#2f7d3c','#45a04f','#73cf7c'),berries=True):
    d,m,l=c;b=''.join(f'<circle cx="{bx}" cy="{by}" r="3.4" fill="#e2463a"/>' for bx,by in [(-10,-14),(8,-20),(22,-8)]) if berries else ''
    return f'<g transform="translate({x} {y}) scale({s})"><ellipse cx="4" cy="2" rx="44" ry="8" fill="#000" opacity=".15"/><circle cx="-22" cy="-10" r="20" fill="{d}"/><circle cx="4" cy="-18" r="26" fill="{m}"/><circle cx="30" cy="-8" r="18" fill="{d}"/><circle cx="-6" cy="-28" r="12" fill="{l}"/>{b}</g>'
def flower(x,y,c,s=1):
    pet=''.join(f'<ellipse cx="{math.cos(math.radians(a))*6:.1f}" cy="{-22+math.sin(math.radians(a))*6:.1f}" rx="5" ry="4" transform="rotate({a} {math.cos(math.radians(a))*6:.1f} {-22+math.sin(math.radians(a))*6:.1f})" fill="{c}"/>' for a in (0,72,144,216,288))
    return f'<g transform="translate({x} {y}) scale({s})"><path d="M0 0 Q-2 -10 0 -18" stroke="#3e9a4a" stroke-width="3" fill="none"/><path d="M0 -8 q-10 -4 -12 -10 q8 0 12 6z" fill="#4fb064"/>{pet}<circle cx="0" cy="-22" r="3.6" fill="#ffd23f" stroke="#e0a800" stroke-width="1"/></g>'
def tuft(x,y,s=1,c='#3d8a3d'):
    return f'<g transform="translate({x} {y}) scale({s})"><path d="M-14 0 Q-12 -18 -6 -28 Q-6 -12 -2 0Z M-4 0 Q0 -26 6 -38 Q6 -16 6 0Z M4 0 Q10 -18 18 -24 Q12 -10 14 0Z" fill="{c}"/></g>'
def castle(p,x,y,s,lamp='dim',night=False):
    win='#fff0a8' if lamp=='bright' else ('#e6c97a' if not night else '#ffe08a')
    g=f'<g transform="translate({x} {y}) scale({s})">'
    g+=f'<circle cx="0" cy="-60" r="{220 if lamp=="bright" else 120}" fill="url(#{p}{"lampGlow" if lamp=="bright" else "dimGlow"})"/>'
    # back towers
    for tx in (-112,112):
        g+=f'<rect x="{tx-30}" y="-150" width="60" height="200" fill="url(#{p}stoneW)"/><rect x="{tx-30}" y="-150" width="60" height="200" fill="url(#{p}bricks)"/>'
        g+=''.join(f'<rect x="{tx-30+i*15}" y="-166" width="10" height="18" fill="url(#{p}stoneW)"/>' for i in range(4))
        g+=f'<path d="M{tx-38} -150 L{tx} -238 L{tx+38} -150Z" fill="url(#{p}roofB)"/><path d="M{tx} -238 L{tx+38} -150 L{tx+14} -150Z" fill="#000" opacity=".15"/>'
        g+=f'<path d="M{tx} -238 V-276" stroke="#6b5a45" stroke-width="3"/><path d="M{tx+1} -276 q14 -6 28 0 q-8 6 0 12 q-14 6 -28 0z" fill="#f6c02c"/>'
        g+=f'<path d="M{tx-10} -96 v-14 a10 10 0 0 1 20 0 v14z" fill="{win}" stroke="#7a5a2a" stroke-width="2.5"/>'
    # main keep
    g+=f'<rect x="-82" y="-110" width="164" height="160" fill="url(#{p}stoneW)"/><rect x="-82" y="-110" width="164" height="160" fill="url(#{p}bricks)"/>'
    g+=''.join(f'<rect x="{-82+i*21}" y="-126" width="14" height="18" fill="url(#{p}stoneW)"/>' for i in range(8))
    g+=f'<path d="M-96 -110 L0 -206 L96 -110Z" fill="url(#{p}roofR)"/><path d="M0 -206 L96 -110 L40 -110Z" fill="#000" opacity=".14"/>'
    g+=f'<path d="M0 -206 V-250" stroke="#6b5a45" stroke-width="3"/><path d="M1 -250 q18 -7 36 0 q-10 7 0 14 q-18 7 -36 0z" fill="#2fa04f"/>'
    # the great lamp window
    g+=f'<circle cx="0" cy="-150" r="24" fill="#5a4a2e" stroke="#7a5a2a" stroke-width="4"/><circle cx="0" cy="-150" r="17" fill="{win}"/>'
    if lamp!='bright':g+=f'<circle cx="0" cy="-150" r="17" fill="#3a3550" opacity=".35"/>'
    for wx in (-48,48):g+=f'<path d="M{wx-11} -40 v-22 a11 11 0 0 1 22 0 v22z" fill="{win}" stroke="#7a5a2a" stroke-width="2.5"/>'
    g+=f'<path d="M-26 50 v-48 a26 26 0 0 1 52 0 v48z" fill="#7a4a22" stroke="#4a2c10" stroke-width="3"/><path d="M-13 50 v-56 M0 50 v-60 M13 50 v-56" stroke="#4a2c10" stroke-width="2"/><circle cx="12" cy="20" r="3" fill="#f6c02c"/>'
    g+=f'<path d="M-82 30 q10 -30 4 -60 q-6 20 -16 30 M82 20 q-10 -24 -2 -50" stroke="#3e9a4a" stroke-width="5" fill="none" stroke-linecap="round"/><circle cx="-80" cy="-20" r="6" fill="#4fb064"/><circle cx="-76" cy="4" r="5" fill="#4fb064"/><circle cx="80" cy="-14" r="5" fill="#4fb064"/>'
    return g+'</g>'
def rays(cx,cy,n,length,col,op,spread=.06,start=0,end=6.283):
    g=''
    for i in range(n):
        a=start+(end-start)*i/n
        g+=f'<path d="M{cx} {cy} L{cx+length*math.cos(a-spread):.1f} {cy+length*math.sin(a-spread):.1f} L{cx+length*math.cos(a+spread):.1f} {cy+length*math.sin(a+spread):.1f}Z" fill="{col}" opacity="{op}"/>'
    return g
def stars(n,seed,ymax=380,big=False):
    r=random.Random(seed);g=''
    for i in range(n):
        x=r.uniform(0,W);y=r.uniform(0,ymax);s=r.uniform(.6,1.8)
        g+=f'<circle cx="{x:.0f}" cy="{y:.0f}" r="{s:.1f}" fill="#fff" opacity="{r.uniform(.4,1):.2f}"/>'
    if big:
        for x,y in [(160,90),(980,60),(700,140)]:g+=f'<path transform="translate({x} {y})" d="M0 -9 Q1.2 -1.2 9 0 Q1.2 1.2 0 9 Q-1.2 1.2 -9 0 Q-1.2 -1.2 0 -9Z" fill="#fff"/>'
    return g
def fireflies(p,pts):return ''.join(f'<circle cx="{x}" cy="{y}" r="{r}" fill="url(#{p}fly)"/><circle cx="{x}" cy="{y}" r="1.8" fill="#fffde0"/>' for x,y,r in pts)
def img(name,x,y,w,h,flip=False,filt=None):
    t=f' transform="translate({2*x+w} 0) scale(-1 1)"' if flip else ''
    f=f' filter="url(#{filt})"' if filt else ''
    return f'<image href="{CH}{name}.webp{V}" x="{x}" y="{y}" width="{w}" height="{h}" preserveAspectRatio="xMidYMax meet"{t}{f}/>'
def ground_shadow(x,y,rx):return f'<ellipse cx="{x}" cy="{y}" rx="{rx}" ry="{rx*.16:.1f}" fill="#000" opacity=".22"/>'
# ----- comic text -----
def tl(lines,x,y,fs,fill='#1d1d1b',anchor='middle',weight=700,lh=1.28):
    return ''.join(f'<text x="{x}" y="{y+i*fs*lh:.1f}" text-anchor="{anchor}" font-size="{fs}" font-weight="{weight}" fill="{fill}">{esc(l)}</text>' for i,l in enumerate(lines))
def bubble(lines,cx,cy,tx,ty,fs=24,who=None,think=False):
    SCRIPT.append((who or '',' '.join(lines)))
    w=max(len(l) for l in lines)*fs*.6+46;h=len(lines)*fs*1.28+34;x0=cx-w/2;y0=cy-h/2
    g='<g class="cm-say">'
    if think:
        g+=f'<ellipse cx="{cx}" cy="{cy}" rx="{w/2+18}" ry="{h/2+16}" fill="#fff" stroke="#1d1d1b" stroke-width="4" filter="url(#P-drop)"/>'
        for k,(f,r) in enumerate([(.55,13),(.82,8)]):g+=f'<circle cx="{cx+(tx-cx)*f:.1f}" cy="{cy+h/2+(ty-cy-h/2)*f:.1f}" r="{r}" fill="#fff" stroke="#1d1d1b" stroke-width="3.5"/>'
    else:
        bx=min(max(tx,x0+40),x0+w-40);by=y0+h if ty>cy else y0
        g+=f'<g filter="url(#P-drop)"><path d="M{bx-18:.1f} {by:.1f} Q{(bx+tx)/2-6:.1f} {(by+ty)/2:.1f} {tx:.1f} {ty:.1f} Q{(bx+tx)/2+10:.1f} {(by+ty)/2:.1f} {bx+18:.1f} {by:.1f}Z" fill="#fff" stroke="#1d1d1b" stroke-width="4" stroke-linejoin="round"/>'
        g+=f'<rect x="{x0:.1f}" y="{y0:.1f}" width="{w:.1f}" height="{h:.1f}" rx="{min(h/2,32):.1f}" fill="#fff" stroke="#1d1d1b" stroke-width="4"/></g>'
        g+=f'<path d="M{bx-16:.1f} {by:.1f} H{bx+16:.1f}" stroke="#fff" stroke-width="8"/>'
    if who:g+=f'<rect x="{x0+18:.1f}" y="{y0-16:.1f}" width="{len(who)*10+24:.1f}" height="30" rx="15" fill="#17633a" stroke="#1d1d1b" stroke-width="3"/><text x="{x0+30:.1f}" y="{y0+5:.1f}" font-size="16" font-weight="700" fill="#fff">{esc(who)}</text>'
    g+=tl(lines,cx,y0+30+fs*.6,fs)
    return g+'</g>'
def narr(lines,x,y,fs=24):
    SCRIPT.append(('Narrator',' '.join(lines)))
    w=max(len(l) for l in lines)*fs*.58+40;h=len(lines)*fs*1.32+28
    return (f'<g class="cm-say"><rect x="{x+7}" y="{y+7}" width="{w:.1f}" height="{h:.1f}" rx="6" fill="#1d1d1b"/><rect x="{x}" y="{y}" width="{w:.1f}" height="{h:.1f}" rx="6" fill="#ffe27a" stroke="#1d1d1b" stroke-width="4"/>'
            f'{tl(lines,x+20,y+22+fs*.78,fs,"#3a2a00","start",600,1.32)}</g>')
def sfx(t,x,y,fs,rot=-8,fill='#ffd23f',stroke='#1d1d1b'):
    return (f'<g class="cm-sfx"><text x="{x+5}" y="{y+6}" text-anchor="middle" font-size="{fs}" font-weight="700" fill="#1d1d1b" transform="rotate({rot} {x} {y})" letter-spacing="2">{esc(t)}</text>'
            f'<text x="{x}" y="{y}" text-anchor="middle" font-size="{fs}" font-weight="700" fill="{fill}" stroke="{stroke}" stroke-width="{fs/8:.1f}" paint-order="stroke" stroke-linejoin="round" letter-spacing="2" transform="rotate({rot} {x} {y})">{esc(t)}</text></g>')
def sparkle(x,y,s,c='#fff'):return f'<path transform="translate({x} {y}) scale({s})" d="M0 -12 Q1.8 -1.8 12 0 Q1.8 1.8 0 12 Q-1.8 1.8 -12 0 Q-1.8 -1.8 0 -12Z" fill="{c}"/>'
def butterfly(x,y,s,c):
    return f'<g transform="translate({x} {y}) scale({s}) rotate(-15)"><ellipse cx="-8" cy="-4" rx="9" ry="7" fill="{c}"/><ellipse cx="8" cy="-4" rx="9" ry="7" fill="{c}"/><ellipse cx="-6" cy="6" rx="6" ry="5" fill="{c}" opacity=".85"/><ellipse cx="6" cy="6" rx="6" ry="5" fill="{c}" opacity=".85"/><rect x="-1.5" y="-8" width="3" height="16" rx="1.5" fill="#3a2a00"/></g>'
def frame(body,pid):
    p=pid+'-'
    b=body(p).replace('url(#P-drop)',f'url(#{p}drop)')
    return (f'<svg class="cm-scene" viewBox="0 0 {W} {H}" xmlns="http://www.w3.org/2000/svg" font-family="{FONT}" role="img">{defs(p)}'
            f'<clipPath id="{p}clip"><rect width="{W}" height="{H}" rx="18"/></clipPath><g clip-path="url(#{p}clip)">{b}</g>'
            f'<rect x="4" y="4" width="{W-8}" height="{H-8}" rx="16" fill="none" stroke="#1d1d1b" stroke-width="8"/></svg>')
# ===================== PAGE 1 =====================
def s1(p):
    g=f'<rect width="{W}" height="{H}" fill="url(#{p}skyDusk)"/>'+stars(70,4,300,True)
    g+=f'<circle cx="250" cy="430" r="260" fill="url(#{p}sunGlow)"/><circle cx="250" cy="440" r="62" fill="url(#{p}sunDisk)"/>'
    g+=f'<path d="M1010 70 a34 34 0 1 0 26 56 a28 28 0 1 1 -26 -56z" fill="#fff4d6"/>'
    g+=cloud(p,180,170,1.1,'cloudDusk',.85)+cloud(p,760,120,.9,'cloudDusk',.75)+cloud(p,1040,230,.7,'cloudDusk',.7)
    g+=f'<path d="M0 470 L120 360 L210 410 L340 300 L470 420 L560 360 L700 440 L860 330 L980 420 L1080 360 L1200 430 V560 H0Z" fill="url(#{p}mtnFarDusk)" opacity=".85"/>'
    g+=f'<path d="M326 312 L340 300 L356 318 L346 322Z M846 342 L860 330 L876 348 L864 350Z M1066 372 L1080 360 L1094 376 L1084 378Z" fill="#ffe3ea"/>'
    g+=f'<path d="M0 520 L150 430 L300 500 L430 440 L600 520 L740 470 L900 530 L1060 470 L1200 520 V600 H0Z" fill="url(#{p}mtnMidDusk)"/>'
    g+=rays(250,440,14,900,'#ffe2a6',.08,.035,math.pi*1.05,math.pi*1.95)
    # castle hill
    g+=f'<path d="M380 760 Q520 520 760 330 Q860 300 960 340 Q1120 420 1200 520 V760Z" fill="url(#{p}hillDusk)"/><path d="M380 760 Q520 520 760 330 Q860 300 960 340 Q1120 420 1200 520" stroke="#ffd9a0" stroke-opacity=".55" stroke-width="5" fill="none"/>'
    g+=f'<path d="M380 760 Q520 520 760 330 Q860 300 960 340 Q1120 420 1200 520 V760Z" fill="url(#{p}grassDk)"/>'
    g+=castle(p,870,322,.78,'dim')
    # winding path up
    path='M300 760 C420 700 560 690 620 620 C680 550 600 520 690 470 C760 430 800 420 830 350'
    g+=f'<path d="{path}" stroke="url(#{p}path)" stroke-width="54" fill="none" stroke-linecap="round"/><path d="{path}" stroke="#c9a66a" stroke-width="4" stroke-dasharray="3 22" fill="none"/>'
    # front meadow
    g+=f'<path d="M0 640 Q240 580 470 640 Q560 670 600 760 H0Z" fill="url(#{p}hillDusk2)"/><path d="M0 640 Q240 580 470 640" stroke="#ffd9a0" stroke-opacity=".5" stroke-width="4" fill="none"/><path d="M0 640 Q240 580 470 640 Q560 670 600 760 H0Z" fill="url(#{p}grassDk)"/>'
    dark=('#1f4a2c','#2d6a3c','#4c8f52')
    g+=pine(p,1150,600,1.1,('#1a3f28','#24563a','#3c7a4a'))+pine(p,1080,640,.8,('#1a3f28','#24563a','#3c7a4a'))+tree(p,560,610,.8,dark)+tree(p,720,520,.55,dark)
    g+=bush(470,700,1,dark)+flower(60,740,'#ff8fab',1.2)+flower(100,750,'#c3a2ff',1.1)+flower(430,748,'#ffd23f',1.1)
    g+=ground_shadow(250,730,120)+img('girl',150,520,150,215)+img('boy',255,525,140,210)
    g+=fireflies(p,[(520,560,10),(640,430,8),(980,600,11),(330,470,9),(1100,520,8),(760,640,10)])
    g+=tuft(30,760,1.6,'#1a3f28')+tuft(110,762,1.3,'#1a3f28')+tuft(1180,762,1.6,'#1a3f28')
    g+=f'<rect width="{W}" height="{H}" fill="url(#{p}vig)"/>'
    g+=narr(['High in the green hills of Bukidnon,','there stands a Reading Castle.','But its great lamp had grown dim.'],36,34,26)
    g+=bubble(['The castle is so far...','Reading is not for me.'],330,430,280,525,24,'Pupil')
    g+=sfx('flicker...',705,175,32,-8,'#f2ecd2','#5a4a2e')
    return g
# ===================== PAGE 2 =====================
def s2(p):
    g=f'<rect width="{W}" height="{H}" fill="url(#{p}plank)"/><rect width="{W}" height="{H}" fill="url(#{p}planks)"/>'
    # window with night
    g+=f'<rect x="840" y="70" width="290" height="250" rx="10" fill="url(#{p}skyNight)"/>'
    g+=f'<g clip-path="url(#{p}winclip)">'+'</g>'
    g+=f'<clipPath id="{p}winclip"><rect x="840" y="70" width="290" height="250" rx="10"/></clipPath><g clip-path="url(#{p}winclip)">'+''.join(f'<circle cx="{840+x}" cy="{70+y}" r="{r}" fill="#fff" opacity="{o}"/>' for x,y,r,o in [(30,30,1.6,.9),(80,60,1.2,.7),(140,24,1.8,1),(220,50,1.3,.8),(260,110,1.5,.9),(60,120,1.1,.6),(190,140,1.4,.8),(110,90,1,.6)])
    g+=f'<circle cx="1060" cy="130" r="34" fill="#fff6d6"/><circle cx="1050" cy="122" r="6" fill="#e9dfb7"/><circle cx="1072" cy="142" r="4" fill="#e9dfb7"/><path d="M840 290 Q920 240 1000 270 Q1060 250 1130 280 V320 H840Z" fill="#14324f"/>'+pine(p,900,300,.45,('#0d2236','#12304a','#1e4566'))+'</g>'
    g+=f'<rect x="830" y="60" width="310" height="270" rx="12" fill="none" stroke="#5a3a12" stroke-width="16"/><path d="M985 64 V326 M834 195 H1136" stroke="#5a3a12" stroke-width="10"/>'
    # shelves left
    g+=f'<rect x="40" y="120" width="260" height="14" fill="#5a3a12"/><rect x="40" y="250" width="260" height="14" fill="#5a3a12"/>'
    for i,(c) in enumerate(['#2a6cc0','#d6452f','#f6c02c','#2fa04f']):g+=f'<rect x="{60+i*40}" y="{76+(i%2)*6}" width="30" height="{44-(i%2)*6}" rx="4" fill="{c}" stroke="#1d1d1b" stroke-width="2"/>'
    g+=f'<circle cx="250" cy="96" r="22" fill="#9aa395" stroke="#1d1d1b" stroke-width="2.5"/><circle cx="250" cy="96" r="8" fill="#5a3a12"/>'+''.join(f'<rect x="{246+math.cos(a)*24-4:.1f}" y="{96+math.sin(a)*24-4:.1f}" width="8" height="8" fill="#9aa395" stroke="#1d1d1b" stroke-width="2" transform="rotate({a*57:.0f} {246+math.cos(a)*24:.1f} {96+math.sin(a)*24:.1f})"/>' for a in [i*math.pi/4 for i in range(8)])
    g+=f'<path d="M70 250 v-50 h46 v50z" fill="#cfe6f2" stroke="#1d1d1b" stroke-width="2.5"/><path d="M76 214 h34" stroke="#1d1d1b" stroke-width="2"/><path d="M150 250 v-36 a18 18 0 0 1 36 0 v36z" fill="#ffe08a" stroke="#1d1d1b" stroke-width="2.5"/><rect x="210" y="210" width="70" height="40" rx="6" fill="#d9bd84" stroke="#1d1d1b" stroke-width="2.5"/>'
    # hanging lamp + cone
    g+=f'<circle cx="610" cy="390" r="230" fill="url(#{p}robotGlow)"/>'+rays(610,390,22,330,'#fff6c8',.35,.05)
    # bench
    g+=f'<rect x="120" y="540" width="960" height="40" rx="6" fill="url(#{p}bench)" stroke="#1d1d1b" stroke-width="4"/><rect x="150" y="580" width="40" height="180" fill="url(#{p}wood)" stroke="#1d1d1b" stroke-width="4"/><rect x="1010" y="580" width="40" height="180" fill="url(#{p}wood)" stroke="#1d1d1b" stroke-width="4"/>'
    g+=f'<path d="M140 556 h920" stroke="#e0a868" stroke-opacity=".5" stroke-width="3"/>'
    # radio, flashlight, bell, bolts, wrench on the bench
    g+=f'<g transform="translate(230 450)"><rect width="150" height="96" rx="14" fill="#7a4a22" stroke="#1d1d1b" stroke-width="4"/><circle cx="44" cy="50" r="28" fill="#e2c48a" stroke="#1d1d1b" stroke-width="3"/><path d="M28 40h32M28 50h32M28 60h32" stroke="#7a4a22" stroke-width="3"/><rect x="86" y="22" width="48" height="18" rx="4" fill="#fff3a0" stroke="#1d1d1b" stroke-width="2.5"/><circle cx="100" cy="66" r="10" fill="#f6c02c" stroke="#1d1d1b" stroke-width="2.5"/><circle cx="126" cy="66" r="10" fill="#f6c02c" stroke="#1d1d1b" stroke-width="2.5"/><path d="M130 0 L160 -50" stroke="#1d1d1b" stroke-width="4"/><circle cx="160" cy="-50" r="5" fill="#d6452f"/></g>'
    g+=f'<g transform="translate(830 510) rotate(-10)"><rect width="100" height="28" rx="8" fill="#2a6cc0" stroke="#1d1d1b" stroke-width="4"/><path d="M100 -6 L128 -14 V42 L100 34Z" fill="#cfd8e0" stroke="#1d1d1b" stroke-width="4"/><rect x="16" y="6" width="14" height="16" rx="3" fill="#f6c02c" stroke="#1d1d1b" stroke-width="2"/></g>'
    g+=f'<g transform="translate(990 520)"><path d="M-30 22 Q-30 -22 0 -26 Q30 -22 30 22Z" fill="#ffd23f" stroke="#1d1d1b" stroke-width="4"/><rect x="-36" y="20" width="72" height="12" rx="4" fill="#c99400" stroke="#1d1d1b" stroke-width="3"/><circle cx="0" cy="-30" r="6" fill="#c99400" stroke="#1d1d1b" stroke-width="2.5"/><path d="M-14 -12 q6 -6 12 -4" stroke="#fff" stroke-width="4" fill="none" stroke-linecap="round" opacity=".7"/></g>'
    for x in (420,440,458,760,782):g+=f'<g transform="translate({x} 540)"><rect x="-5" y="-14" width="10" height="14" fill="#9aa395" stroke="#1d1d1b" stroke-width="2"/><rect x="-9" y="-18" width="18" height="6" rx="2" fill="#b9c1b4" stroke="#1d1d1b" stroke-width="2"/></g>'
    # Jarvis waking on the bench
    g+=ground_shadow(612,540,110)+img('jarvis-hello',470,170,280,372)
    g+=sparkle(470,220,1.6)+sparkle(760,180,1.2)+sparkle(780,420,1)+sparkle(450,430,.9,'#fff3a0')
    # teacher
    g+=ground_shadow(150,756,110)+img('teacher-female-wave',20,360,250,400)
    g+=f'<rect width="{W}" height="{H}" fill="url(#{p}vig)"/>'
    g+=narr(['One night, a teacher built a robot from','an old radio, a flashlight and a bell.'],34,30,24)
    g+=sfx('BEEP BOOP!',770,200,70,-7,'#ffd23f')
    g+=bubble(['Hello! I am...','I am...?'],930,400,750,320,26,'Robot')
    g+=bubble(['You are Jarvis!','In our language,','bulig means help.'],330,330,190,430,24,'Teacher')
    return g
# ===================== PAGE 3 =====================
def signpost(p,x,y):
    g=f'<g transform="translate({x} {y})" filter="url(#{p}drop)"><rect x="-9" y="-210" width="18" height="216" fill="url(#{p}wood)" stroke="#1d1d1b" stroke-width="3"/><path d="M-9 -210 h18 l-9 -12z" fill="#7a4a22" stroke="#1d1d1b" stroke-width="3"/>'
    for i,(t,d) in enumerate([('READING CASTLE',1),('RIVER',-1),('MANGO TREE',1)]):
        yy=-200+i*58;w=len(t)*13+44
        if d==1:g+=f'<path d="M9 {yy} H{9+w} L{30+w} {yy+21} L{9+w} {yy+42} H9Z" fill="#fff4dc" stroke="#7a5a2a" stroke-width="4"/><circle cx="20" cy="{yy+21}" r="3" fill="#7a5a2a"/><text x="30" y="{yy+29}" font-size="20" font-weight="700" fill="#17633a">{t}</text>'
        else:g+=f'<path d="M-9 {yy} H{-9-w} L{-30-w} {yy+21} L{-9-w} {yy+42} H-9Z" fill="#fff4dc" stroke="#7a5a2a" stroke-width="4"/><circle cx="-20" cy="{yy+21}" r="3" fill="#7a5a2a"/><text x="-30" y="{yy+29}" text-anchor="end" font-size="20" font-weight="700" fill="#17633a">{t}</text>'
    return g+'</g>'
def s3(p):
    g=f'<rect width="{W}" height="{H}" fill="url(#{p}skyDay)"/>'
    g+=f'<circle cx="1060" cy="110" r="190" fill="url(#{p}sunGlow)"/>'+rays(1060,110,16,420,'#fff6c8',.18,.04)
    sunr=''.join(f'<rect x="-4" y="-78" width="8" height="22" rx="4" fill="#ffd23f" transform="rotate({i*30})"/>' for i in range(12))
    g+=f'<g transform="translate(1060 110)">{sunr}<circle r="50" fill="url(#{p}sunDisk)"/><circle cx="-16" cy="-6" r="5" fill="#5a3a12"/><circle cx="16" cy="-6" r="5" fill="#5a3a12"/><path d="M-17 12q17 16 34 0" stroke="#5a3a12" stroke-width="4.5" fill="none" stroke-linecap="round"/><circle cx="-28" cy="8" r="8" fill="#ff8fab" opacity=".6"/><circle cx="28" cy="8" r="8" fill="#ff8fab" opacity=".6"/></g>'
    g+=cloud(p,200,110,1.2)+cloud(p,640,80,.9)+cloud(p,860,200,.7,'cloud',.9)
    g+=f'<path d="M0 420 L130 300 L250 370 L400 250 L540 380 L680 290 L820 390 L960 300 L1100 380 L1200 330 V520 H0Z" fill="url(#{p}mtnFarDay)"/><path d="M384 266 L400 250 L418 270 L406 274Z M664 306 L680 290 L698 310 L686 312Z M944 316 L960 300 L976 320 L964 322Z" fill="#fff"/>'
    g+=f'<path d="M0 470 L170 380 L330 450 L520 370 L700 460 L880 390 L1050 460 L1200 410 V560 H0Z" fill="url(#{p}mtnMidDay)"/>'
    g+=f'<path d="M640 470 Q800 330 960 340 Q1100 350 1200 420 V560 H640Z" fill="url(#{p}hillB)"/>'+castle(p,1095,372,.36,'dim')
    g+=f'<path d="M0 540 Q300 450 620 500 Q900 540 1200 470 V760 H0Z" fill="url(#{p}hillA)"/><path d="M0 540 Q300 450 620 500 Q900 540 1200 470" stroke="#d6f5b8" stroke-width="5" fill="none" opacity=".8"/><path d="M0 540 Q300 450 620 500 Q900 540 1200 470 V760 H0Z" fill="url(#{p}grass)"/>'
    pa='M-40 760 C200 680 420 640 600 610 C760 585 880 540 1000 500'
    pb='M600 610 C700 640 820 690 1240 700'
    pc='M600 610 C560 570 600 540 680 520'
    for d in (pa,pb):g+=f'<path d="{d}" stroke="url(#{p}path)" stroke-width="60" fill="none" stroke-linecap="round"/>'
    for d in (pa,pb):g+=f'<path d="{d}" stroke="#c9a66a" stroke-width="4" stroke-dasharray="3 26" fill="none"/>'
    g+=tree(p,90,560,1.05)+pine(p,190,520,.9)+tree(p,1150,600,.8,('#2f7d3c','#45a04f','#9ad386'))+bush(800,560,.9)+bush(320,575,.9)
    g+=signpost(p,590,650)
    g+=flower(420,740,'#ff8fab',1.3)+flower(470,752,'#c3a2ff',1.2)+flower(1090,742,'#ffd23f',1.3)+flower(1140,755,'#ff9f5a',1.2)+flower(860,700,'#9ad0ff',1.1)
    g+=butterfly(560,330,1.4,'#ff8fab')+butterfly(820,250,1.1,'#ffd23f')
    g+=ground_shadow(330,745,120)+img('george-map',190,330,290,420)
    g+=ground_shadow(990,748,110)+img('jarvis-point',860,380,270,370,flip=True)
    g+=tuft(20,762,1.7,'#3d8a3d')+tuft(1185,762,1.7,'#3d8a3d')+tuft(560,764,1.3,'#3d8a3d')
    g+=f'<rect width="{W}" height="{H}" fill="url(#{p}vig)" opacity=".7"/>'
    g+=narr(['George the Explorer was the','first friend Jarvis found.'],34,30,26)
    g+=bubble(['I know every path up the hill...','but I have no one to explore with.'],420,230,330,350,23,'George')
    g+=bubble(['Beep! Come with me.','Children need a guide','on every page!'],860,240,960,395,23,'Jarvis')
    return g
# ===================== COVER =====================
def s0(p):
    g=f'<rect width="{W}" height="{H}" fill="url(#{p}skyDay)"/>'
    g+=f'<circle cx="600" cy="300" r="420" fill="url(#{p}sunGlow)"/>'+rays(600,300,26,900,'#fff3b0',.22,.04)
    g+=cloud(p,120,120,1.1)+cloud(p,1060,110,1)+cloud(p,330,230,.7,'cloud',.9)+cloud(p,900,240,.75,'cloud',.9)
    g+=f'<path d="M0 470 L140 380 L260 430 L400 340 L520 420 L680 350 L820 430 L960 360 L1080 420 L1200 380 V600 H0Z" fill="#9cc3e6" opacity=".8"/>'
    g+=f'<path d="M0 520 Q300 440 600 470 Q900 440 1200 510 V760 H0Z" fill="url(#{p}hillA)"/><path d="M0 520 Q300 440 600 470 Q900 440 1200 510" stroke="#e8ffd0" stroke-width="5" fill="none" opacity=".7"/>'
    g+=castle(p,600,452,.62,'bright')
    g+=f'<path d="M0 600 Q600 540 1200 600 V760 H0Z" fill="url(#{p}hillB)"/><path d="M0 600 Q600 540 1200 600 V760 H0Z" fill="url(#{p}grassDk)" opacity=".5"/>'
    g+=tree(p,60,600,1)+pine(p,150,570,.8)+tree(p,1140,600,.95)+pine(p,1060,570,.75)
    # back row (smaller), front row (bigger), Jarvis in the middle
    back=['max-hello','dracs-book','mia-blocks','zion-emblem','roxanne-time','kai-letter']
    for i,n in enumerate(back):
        x=[135,295,420,670,795,955][i];g+=ground_shadow(x+60,640,50)+img(n,x,445,120,196)
    front=['george-map','bruno-cheer','arah-cheer','toby-flag','sofie-mirror','claude-shield']
    xs=[40,200,360,720,880,1040]
    for x,n in zip(xs,front):g+=ground_shadow(x+70,752,60)+img(n,x,520,140,232)
    g+=f'<circle cx="600" cy="600" r="140" fill="url(#{p}robotGlow)"/>'+ground_shadow(600,754,85)+img('jarvis-cheer',490,470,220,290)
    g+=sparkle(470,520,1.2)+sparkle(740,500,1)+sparkle(300,470,.8,'#fff3b0')+sparkle(920,470,.9,'#fff3b0')
    g+=flower(330,752,'#ff8fab',1.2)+flower(860,750,'#ffd23f',1.2)+tuft(20,762,1.6,'#3d8a3d')+tuft(1180,762,1.6,'#3d8a3d')
    # title banner
    g+=(f'<g filter="url(#P-drop)"><path d="M160 150 L80 120 L110 175 L80 230 L190 222Z" fill="#a8322a" stroke="#1d1d1b" stroke-width="4" stroke-linejoin="round"/>'
        f'<path d="M1040 150 L1120 120 L1090 175 L1120 230 L1010 222Z" fill="#a8322a" stroke="#1d1d1b" stroke-width="4" stroke-linejoin="round"/>'
        f'<path d="M150 112 Q600 66 1050 112 L1050 222 Q600 176 150 222Z" fill="#d6452f" stroke="#1d1d1b" stroke-width="5" stroke-linejoin="round"/></g>'
        f'<path d="M168 128 Q600 84 1032 128" stroke="#ff9a7a" stroke-width="5" fill="none" opacity=".8"/>')
    g+=f'<path id="{p}arc" d="M150 192 Q600 142 1050 192" fill="none"/>'
    g+=(f'<text font-size="54" font-weight="700" text-anchor="middle" letter-spacing="1"><textPath href="#{p}arc" startOffset="50%" fill="#1d1d1b" dy="6">How the BULIG Friends Met</textPath></text>'
        f'<text font-size="54" font-weight="700" text-anchor="middle" letter-spacing="1" fill="#ffd23f" stroke="#1d1d1b" stroke-width="8" paint-order="stroke" stroke-linejoin="round"><textPath href="#{p}arc" startOffset="50%">How the BULIG Friends Met</textPath></text>')
    g+=(f'<rect x="420" y="38" width="360" height="44" rx="22" fill="#17633a" stroke="#1d1d1b" stroke-width="4"/>'
        f'<text x="600" y="68" text-anchor="middle" font-size="20" font-weight="700" fill="#fff" letter-spacing="3">A BULIG COMIC STORY</text>')
    g+=f'<rect width="{W}" height="{H}" fill="url(#{p}vig)" opacity=".5"/>'
    return g
# ===================== more pieces =====================
def bird(x,y,s=1,c='#2a6cc0'):
    return (f'<g transform="translate({x} {y}) scale({s})"><ellipse cx="0" cy="0" rx="18" ry="13" fill="{c}" stroke="#1d1d1b" stroke-width="2.5"/>'
            f'<circle cx="12" cy="-10" r="10" fill="{c}" stroke="#1d1d1b" stroke-width="2.5"/><circle cx="15" cy="-12" r="2.4" fill="#1d1d1b"/>'
            f'<path d="M21 -10 L31 -7 L21 -5Z" fill="#ffb000" stroke="#1d1d1b" stroke-width="2"/><path d="M-6 -4 Q-20 -22 -28 -6 Q-16 0 -6 2Z" fill="#fff" opacity=".55"/>'
            f'<path d="M-16 4 L-30 10 L-17 10Z" fill="{c}" stroke="#1d1d1b" stroke-width="2"/><path d="M-2 13 v8 M5 13 v8" stroke="#ffb000" stroke-width="2.5"/></g>')
def note(x,y,s=1,c='#17633a'):
    return f'<g transform="translate({x} {y}) scale({s})" opacity=".9"><ellipse cx="0" cy="0" rx="9" ry="7" transform="rotate(-20)" fill="{c}"/><path d="M8 -2 V-34 Q18 -30 22 -20" stroke="{c}" stroke-width="3.5" fill="none" stroke-linecap="round"/></g>'
def sky(p,kind):return f'<rect width="{W}" height="{H}" fill="url(#{p}sky{kind})"/>'
def sun(p,x,y,face=True):
    r=''.join(f'<rect x="-4" y="-78" width="8" height="22" rx="4" fill="#ffd23f" transform="rotate({i*30})"/>' for i in range(12))
    f=('<circle cx="-16" cy="-6" r="5" fill="#5a3a12"/><circle cx="16" cy="-6" r="5" fill="#5a3a12"/><path d="M-17 12q17 16 34 0" stroke="#5a3a12" stroke-width="4.5" fill="none" stroke-linecap="round"/>'
       '<circle cx="-28" cy="8" r="7" fill="#ff9e9e" opacity=".6"/><circle cx="28" cy="8" r="7" fill="#ff9e9e" opacity=".6"/>') if face else ''
    return f'<circle cx="{x}" cy="{y}" r="190" fill="url(#{p}sunGlow)"/><g transform="translate({x} {y})">{r}<circle r="50" fill="url(#{p}sunDisk)"/>{f}</g>'
def hills_day(p,y1=430,y2=480):
    return (f'<path d="M0 {y1} L130 {y1-110} L250 {y1-50} L400 {y1-170} L540 {y1-40} L680 {y1-130} L820 {y1-30} L960 {y1-120} L1100 {y1-40} L1200 {y1-90} V{y1+120} H0Z" fill="url(#{p}mtnFarDay)"/>'
            f'<path d="M0 {y2} L170 {y2-90} L330 {y2-20} L520 {y2-100} L700 {y2-10} L880 {y2-80} L1050 {y2-10} L1200 {y2-60} V{y2+120} H0Z" fill="url(#{p}mtnMidDay)"/>')
def ground(p,top,grad='hillA',edge='#d6f5b8'):
    return (f'<path d="M0 {top} Q300 {top-60} 620 {top-20} Q900 {top+10} 1200 {top-50} V760 H0Z" fill="url(#{p}{grad})"/>'
            f'<path d="M0 {top} Q300 {top-60} 620 {top-20} Q900 {top+10} 1200 {top-50}" stroke="{edge}" stroke-width="5" fill="none" opacity=".8"/>'
            f'<path d="M0 {top} Q300 {top-60} 620 {top-20} Q900 {top+10} 1200 {top-50} V760 H0Z" fill="url(#{p}grass)"/>')
def chick(x,y,s=1,flip=False):
    t=f' scale(-1 1)' if flip else ''
    return (f'<g transform="translate({x} {y}) scale({s}){t}"><ellipse cx="0" cy="0" rx="16" ry="14" fill="#ffd23f" stroke="#1d1d1b" stroke-width="2.5"/><circle cx="10" cy="-12" r="9" fill="#ffd23f" stroke="#1d1d1b" stroke-width="2.5"/>'
            f'<circle cx="13" cy="-14" r="2" fill="#1d1d1b"/><path d="M18 -12 L25 -10 L18 -8Z" fill="#ff8a00"/><path d="M-4 -2 q-8 -8 -12 2" stroke="#e0a800" stroke-width="2.5" fill="none"/><path d="M-3 13 v6 M5 13 v6" stroke="#ff8a00" stroke-width="2.5"/></g>')
def mango_tree(p,x,y,s=1):
    g=f'<g transform="translate({x} {y}) scale({s})"><ellipse cx="0" cy="4" rx="230" ry="28" fill="#000" opacity=".18"/>'
    g+=f'<path d="M-40 0 Q-30 -120 -60 -220 L-30 -230 Q0 -150 10 -230 L40 -226 Q30 -120 46 0Z" fill="url(#{p}trunk)" stroke="#4a2c10" stroke-width="3"/>'
    g+=f'<path d="M-50 -200 Q-120 -250 -170 -260 M30 -220 Q120 -260 180 -250" stroke="#6e4019" stroke-width="18" fill="none" stroke-linecap="round"/>'
    for cx,cy,r,c in [(-180,-300,90,'#1f6a37'),(170,-300,95,'#1f6a37'),(0,-380,120,'#1f6a37'),(-100,-410,80,'#1f6a37'),(110,-420,85,'#1f6a37'),
                      (-160,-320,70,'#2f8a4b'),(160,-320,75,'#2f8a4b'),(0,-400,100,'#2f8a4b'),(-90,-430,62,'#2f8a4b'),(100,-440,66,'#2f8a4b'),
                      (-40,-460,50,'#4fb064'),(70,-470,40,'#4fb064'),(-150,-350,36,'#4fb064'),(180,-340,32,'#4fb064')]:
        g+=f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="{c}"/>'
    for mx,my in [(-150,-250),(-60,-300),(40,-280),(140,-250),(200,-300),(-210,-290),(90,-330),(-110,-350)]:
        g+=f'<path d="M{mx} {my} v10" stroke="#4a2c10" stroke-width="2"/><ellipse cx="{mx+2}" cy="{my+22}" rx="10" ry="14" fill="#ffb000" stroke="#a86a00" stroke-width="2"/><ellipse cx="{mx-2}" cy="{my+17}" rx="3" ry="5" fill="#fff3b0" opacity=".7"/>'
    return g+'</g>'
def block(x,y,sz,letter,c):
    return (f'<g transform="translate({x} {y})"><rect width="{sz}" height="{sz}" rx="6" fill="{c}" stroke="#1d1d1b" stroke-width="3.5"/>'
            f'<path d="M0 {sz} L{sz*.18:.1f} {sz*1.14:.1f} H{sz*1.18:.1f} V{sz*.18:.1f} L{sz} 0 V{sz}Z" fill="#000" opacity=".22"/><rect x="5" y="5" width="{sz-10}" height="{sz-10}" rx="4" fill="none" stroke="#fff" stroke-opacity=".5" stroke-width="2"/>'
            f'<text x="{sz/2}" y="{sz*.72:.1f}" text-anchor="middle" font-size="{sz*.62:.0f}" font-weight="700" fill="#fff" stroke="#1d1d1b" stroke-width="2.5" paint-order="stroke">{letter}</text></g>')
def nipa_hut(p,x,y,s=1):
    return (f'<g transform="translate({x} {y}) scale({s})"><ellipse cx="0" cy="4" rx="110" ry="14" fill="#000" opacity=".15"/>'
            f'<path d="M-80 0 V-30 M80 0 V-30 M-40 0 V-30 M40 0 V-30" stroke="#6e4019" stroke-width="8"/><rect x="-88" y="-34" width="176" height="10" fill="#7a4a22"/>'
            f'<rect x="-76" y="-110" width="152" height="78" fill="#d9b26a" stroke="#7a5a2a" stroke-width="3"/><path d="M-76 -90 H76 M-76 -70 H76 M-76 -50 H76" stroke="#b88f45" stroke-width="2"/>'
            f'<rect x="-20" y="-100" width="40" height="40" fill="#5a3a12"/><path d="M-20 -80 H20 M0 -100 V-60" stroke="#d9b26a" stroke-width="3"/>'
            f'<path d="M-112 -100 L0 -180 L112 -100Z" fill="#c9a24e" stroke="#7a5a2a" stroke-width="3"/><path d="M-90 -110 L0 -172 M-50 -106 L0 -160 M50 -106 L0 -160 M90 -110 L0 -172" stroke="#a8823a" stroke-width="2"/></g>')
def house(x,y,w,h,wall,roof):
    return (f'<g transform="translate({x} {y})"><rect x="0" y="{-h}" width="{w}" height="{h}" fill="{wall}" stroke="#1d1d1b" stroke-width="3"/>'
            f'<path d="M-10 {-h} L{w/2} {-h-w*.45:.0f} L{w+10} {-h}Z" fill="{roof}" stroke="#1d1d1b" stroke-width="3"/>'
            f'<rect x="{w*.15:.0f}" y="{-h*.7:.0f}" width="{w*.25:.0f}" height="{w*.22:.0f}" fill="#bfe3ff" stroke="#1d1d1b" stroke-width="2.5"/><rect x="{w*.58:.0f}" y="{-h*.55:.0f}" width="{w*.26:.0f}" height="{h*.55:.0f}" fill="#8a5a2b" stroke="#1d1d1b" stroke-width="2.5"/></g>')
def bunting(x1,y1,x2,y2,n=10):
    g=f'<path d="M{x1} {y1} Q{(x1+x2)/2} {max(y1,y2)+40} {x2} {y2}" stroke="#5a3a12" stroke-width="2.5" fill="none"/>'
    cols=['#d6452f','#ffd23f','#2a6cc0','#2fa04f','#ff8fab']
    for i in range(n):
        t=(i+.5)/n;x=(1-t)**2*x1+2*(1-t)*t*(x1+x2)/2+t*t*x2;y=(1-t)**2*y1+2*(1-t)*t*(max(y1,y2)+40)+t*t*y2
        g+=f'<path d="M{x-12:.0f} {y:.0f} L{x+12:.0f} {y:.0f} L{x:.0f} {y+26:.0f}Z" fill="{cols[i%5]}" stroke="#1d1d1b" stroke-width="1.5"/>'
    return g
def envelope(x,y,s=1,rot=0):
    return (f'<g transform="translate({x} {y}) rotate({rot}) scale({s})"><rect x="-30" y="-20" width="60" height="40" rx="4" fill="#fffaf0" stroke="#1d1d1b" stroke-width="3"/>'
            f'<path d="M-30 -20 L0 4 L30 -20" stroke="#1d1d1b" stroke-width="3" fill="none"/><circle cx="0" cy="4" r="6" fill="#d6452f"/></g>')
def wind(x,y,s=1):
    return f'<g transform="translate({x} {y}) scale({s})" stroke="#fff" stroke-width="5" fill="none" stroke-linecap="round" opacity=".85"><path d="M0 0 H90 q24 0 24 -18 q0 -14 -14 -14"/><path d="M30 26 H140 q20 0 20 16 q0 12 -12 12"/><path d="M-20 52 H70"/></g>'
def lantern(x,y,c='#ff6b4a'):
    return f'<g transform="translate({x} {y})"><path d="M0 -40 V-14" stroke="#5a3a12" stroke-width="2"/><circle cx="0" cy="6" r="34" fill="{c}" opacity=".25"/><ellipse cx="0" cy="6" rx="16" ry="20" fill="{c}" stroke="#1d1d1b" stroke-width="2.5"/><path d="M-16 6 H16 M0 -14 V26" stroke="#1d1d1b" stroke-opacity=".3" stroke-width="2"/><rect x="-6" y="-16" width="12" height="5" fill="#5a3a12"/></g>'
def bolt(x,y,s=1):
    return f'<path transform="translate({x} {y}) scale({s})" d="M0 0 L-30 70 H-6 L-26 140 L34 50 H8 L26 0Z" fill="#fff59a" stroke="#ffd23f" stroke-width="4" stroke-linejoin="round"/>'
def rain(seed,n=90,op=.5):
    r=random.Random(seed);return ''.join(f'<path d="M{(x:=r.uniform(0,W)):.0f} {(y:=r.uniform(0,H)):.0f} l-10 26" stroke="#bcd3ff" stroke-width="2.5" opacity="{op}" stroke-linecap="round"/>' for _ in range(n))
def firework(x,y,r,c):
    g=''.join(f'<path d="M{x+math.cos(a)*r*.25:.0f} {y+math.sin(a)*r*.25:.0f} L{x+math.cos(a)*r:.0f} {y+math.sin(a)*r:.0f}" stroke="{c}" stroke-width="4" stroke-linecap="round"/><circle cx="{x+math.cos(a)*r*1.12:.0f}" cy="{y+math.sin(a)*r*1.12:.0f}" r="4" fill="{c}"/>' for a in [i*math.pi/8 for i in range(16)])
    return g+f'<circle cx="{x}" cy="{y}" r="{r*.18:.0f}" fill="#fff" opacity=".9"/>'
def confetti(seed,n=60,ymax=500):
    r=random.Random(seed);cols=['#d6452f','#ffd23f','#2a6cc0','#2fa04f','#ff8fab','#c3a2ff']
    return ''.join(f'<rect x="{r.uniform(0,W):.0f}" y="{r.uniform(0,ymax):.0f}" width="{r.uniform(6,12):.0f}" height="{r.uniform(10,18):.0f}" rx="2" fill="{r.choice(cols)}" transform="rotate({r.uniform(0,180):.0f} {r.uniform(0,W):.0f} {r.uniform(0,ymax):.0f})" opacity=".9"/>' for _ in range(n))
def friend(name,cx,base,h,flip=False,glow=False):
    w=h*.75;return ground_shadow(cx,base-4,w*.42)+img(name,cx-w/2,base-h,w,h,flip)
# ===================== PAGE 4 · Max =====================
def s4(p):
    g=sky(p,'Day')+sun(p,180,120)+cloud(p,520,100,1)+cloud(p,950,150,.8)+hills_day(p)
    g+=ground(p,560)
    river='M760 760 C800 640 900 600 1000 560 C1080 530 1140 520 1240 510 L1240 760Z'
    g+=f'<path d="{river}" fill="#4aa3df"/><path d="M820 760 C860 660 940 620 1030 585 C1100 560 1150 552 1240 548" stroke="#9fd6ff" stroke-width="10" fill="none" opacity=".7"/>'
    g+=''.join(f'<path d="M{x} {y} q14 -8 28 0 q14 8 28 0" stroke="#e8f6ff" stroke-width="4" fill="none" stroke-linecap="round" opacity=".9"/>' for x,y in [(900,680),(1000,620),(1080,700),(1120,580),(960,740)])
    g+=f'<path d="M760 760 C800 640 900 600 1000 560 C1080 530 1140 520 1240 510" stroke="#7a5a2a" stroke-width="6" fill="none" opacity=".35"/>'
    for x,y,s in [(780,690,1),(870,610,.8)]:g+=f'<ellipse cx="{x}" cy="{y}" rx="{34*s}" ry="{18*s}" fill="#9aa395" stroke="#1d1d1b" stroke-width="3"/><ellipse cx="{x-8*s}" cy="{y-6*s}" rx="{12*s}" ry="{5*s}" fill="#fff" opacity=".35"/>'
    for x in (730,750,770):g+=f'<path d="M{x} 760 Q{x-6} 680 {x+4} 620" stroke="#2f7d3c" stroke-width="5" fill="none"/><ellipse cx="{x+4}" cy="630" rx="6" ry="18" fill="#7a4a22"/>'
    g+=tree(p,1050,500,1.1)+tree(p,90,560,.9)
    g+=f'<path d="M1010 380 Q1060 370 1120 390" stroke="#6e4019" stroke-width="10" fill="none" stroke-linecap="round"/>'+bird(1080,360,1.2)
    g+=note(380,300,1.3)+note(450,250,1,'#2a6cc0')+note(1000,300,1.1,'#d6452f')+note(640,220,1.2,'#7a3fb0')
    g+=friend('max-mic',330,745,360)+friend('jarvis-hello',620,745,320,True)
    g+=tuft(30,762,1.6)+tuft(500,762,1.4)+flower(450,750,'#ff8fab',1.2)+flower(560,745,'#ffd23f',1.1)
    g+=f'<rect width="{W}" height="{H}" fill="url(#{p}vig)" opacity=".55"/>'
    g+=narr(['By the river, they met Max.','Max could hear every little sound.'],34,30,25)
    g+=sfx('tweet tweet!',1060,290,34,-6,'#9fd6ff')+sfx('splish splash',1000,520,30,-4,'#e8f6ff')
    g+=bubble(['Listen! The river says splash,','and the bird says tweet!'],330,280,320,390,22,'Max')
    g+=bubble(['Reading starts with','good listening.','Join us, Max!'],760,250,640,430,22,'Jarvis')
    return g
# ===================== PAGE 5 · Dracs =====================
def s5(p):
    g=f'<rect width="{W}" height="{H}" fill="url(#{p}skyGold)"/>'+f'<circle cx="980" cy="260" r="300" fill="url(#{p}sunGlow)"/>'
    g+=cloud(p,240,120,.9,'cloudDusk',.8)+cloud(p,1000,110,.8,'cloudDusk',.8)
    g+=f'<path d="M0 470 L170 390 L330 440 L520 370 L700 450 L880 380 L1050 450 L1200 400 V560 H0Z" fill="#c9a36a" opacity=".55"/>'
    g+=ground(p,560,'hillA','#fff0b0')
    g+=mango_tree(p,330,640,1)
    g+=f'<ellipse cx="330" cy="700" rx="200" ry="30" fill="#2d5a2d" opacity=".25"/>'
    g+=friend('dracs-book',330,720,330)
    g+=friend('boy-cheer',140,740,190)+friend('girl-cheer',530,740,190,True)+chick(80,742,1.1)+chick(590,744,.9,True)+chick(220,750,.8)
    g+=friend('george-point',820,745,330,True)+friend('jarvis-cheer',1040,745,300,True)
    g+=flower(650,750,'#ff8fab',1.2)+flower(700,740,'#c3a2ff',1.1)+tuft(20,762,1.5)+tuft(1180,762,1.5)
    g+=f'<rect width="{W}" height="{H}" fill="url(#{p}vig)" opacity=".55"/>'
    g+=narr(['Under the old mango tree, Dracs told','stories to anyone who would sit.'],600,26,24)
    g+=bubble(['Once upon a time...','Oh! New friends!'],330,140,330,380,23,'Dracs')
    g+=bubble(['Will you tell stories','at the Reading Castle?'],860,250,840,410,23,'George')
    return g
# ===================== PAGE 6 · Bruno =====================
def s6(p):
    g=f'<rect width="{W}" height="{H}" fill="url(#{p}skyRain)"/>'+sun(p,1080,110,False)
    g+=cloud(p,200,110,1.2,'cloudGray',.95)+cloud(p,640,90,1,'cloudGray',.9)
    g+=f'<path d="M0 760 V420 Q300 300 620 380 Q900 450 1200 360 V760Z" fill="url(#{p}hillB)"/><path d="M0 420 Q300 300 620 380 Q900 450 1200 360" stroke="#d6f5b8" stroke-width="5" fill="none" opacity=".6"/>'
    mud='M-40 760 C200 640 420 600 600 560 C760 520 900 470 1240 430 L1240 520 C900 560 760 610 620 660 C440 720 260 740 160 780Z'
    g+=f'<path d="{mud}" fill="#8a5a2b"/><path d="{mud}" fill="url(#{p}dots)"/>'
    for x,y,rx in [(300,700,60),(700,600,50),(1000,500,40)]:g+=f'<ellipse cx="{x}" cy="{y}" rx="{rx}" ry="{rx*.25}" fill="#6b4522"/><ellipse cx="{x-10}" cy="{y-3}" rx="{rx*.5}" ry="{rx*.1}" fill="#bfe3ff" opacity=".6"/>'
    for i in range(10):a=math.pi*(1.05+i*.09);g+=f'<circle cx="{500+math.cos(a)*120:.0f}" cy="{640+math.sin(a)*70:.0f}" r="{8+i%3*4}" fill="#8a5a2b" stroke="#5a3a12" stroke-width="2"/>'
    g+=pine(p,1100,420,.9)+tree(p,80,450,.9)
    g+=friend('bruno-tryagain',500,720,350)+friend('jarvis-thumbs',920,700,300,True)
    g+=f'<rect width="{W}" height="{H}" fill="url(#{p}vig)" opacity=".55"/>'
    g+=narr(['On the muddy hill, Bruno slipped...','again... and again... and again!'],34,30,25)
    g+=sfx('SPLAT!',330,560,62,-10,'#c88f55')
    g+=bubble(['Oops! That is okay.','I will try again!'],560,260,520,390,24,'Bruno')
    g+=bubble(['You never give up.','We need a helper','like you!'],960,250,930,400,23,'Jarvis')
    return g
# ===================== PAGE 7 · Mia =====================
def s7(p):
    g=sky(p,'Day')+cloud(p,180,110,1)+cloud(p,760,80,.9)+cloud(p,1060,160,.7)+hills_day(p,420,470)
    g+=f'<path d="M0 520 Q300 480 520 520 L470 760 H0Z" fill="url(#{p}hillA)"/><path d="M700 520 Q950 470 1200 500 V760 H760Z" fill="url(#{p}hillA)"/>'
    g+=f'<path d="M520 520 Q610 500 700 520 L760 760 H470Z" fill="#4aa3df"/>'+''.join(f'<path d="M{x} {y} q12 -7 24 0 q12 7 24 0" stroke="#e8f6ff" stroke-width="4" fill="none" stroke-linecap="round"/>' for x,y in [(530,600),(600,680),(640,560),(560,730)])
    g+=f'<path d="M520 520 L470 760 M700 520 L760 760" stroke="#7a5a2a" stroke-width="6" opacity=".4"/>'
    cols=['#d6452f','#2a6cc0','#ffd23f','#2fa04f','#7a3fb0']
    for i,(l,c) in enumerate(zip('BULIG',cols)):g+=block(440+i*66,560-[0,18,26,18,0][i],62,l,c)
    g+=block(1100,680,52,'C','#2fa04f')
    g+=friend('mia-blocks',250,745,330)+friend('george-cheer',950,740,320,True)
    g+=tree(p,1130,520,.9)+bush(80,560,1)+flower(820,740,'#ff8fab',1.2)+tuft(20,762,1.5)+tuft(1180,762,1.5)
    g+=f'<rect width="{W}" height="{H}" fill="url(#{p}vig)" opacity=".5"/>'
    g+=narr(['A stream blocked the way.','Mia built a bridge, block by block.'],34,30,25)
    g+=sfx('click! clack!',600,470,40,-6,'#ffd23f')
    g+=bubble(['Letters are like blocks.','Put them together','to make words!'],290,250,260,410,23,'Mia')
    g+=bubble(['B-U-L-I-G!','We can cross now!'],930,260,940,420,24,'George')
    return g
# ===================== PAGE 8 · Toby =====================
def s8(p):
    g=f'<rect width="{W}" height="{H}" fill="url(#{p}skyForest)"/>'
    g+=rays(600,-40,9,900,'#fff6c8',.16,.05,math.pi*.25,math.pi*.75)
    for i,x in enumerate(range(-40,1260,110)):g+=pine(p,x,470+(i%3)*14,1.6,('#1a4a2c','#24603a','#2f7d48'))
    g+=f'<path d="M0 480 H1200 V760 H0Z" fill="url(#{p}hillDusk2)"/><path d="M0 480 H1200 V760 H0Z" fill="url(#{p}grassDk)"/>'
    pa='M600 760 C600 650 600 590 600 540';pb='M600 545 C520 520 380 500 200 490';pc='M600 545 C680 520 820 500 1000 490'
    for d in (pa,pb,pc):g+=f'<path d="{d}" stroke="url(#{p}path)" stroke-width="58" fill="none" stroke-linecap="round"/>'
    for i,x in enumerate(range(-60,1260,150)):g+=pine(p,x,800,1.9,('#123a22','#1b4d2e','#286a3e')) if x<330 or x>870 else ''
    g+=f'<g transform="translate(640 560)"><path d="M0 0 V-150" stroke="#6e4019" stroke-width="7"/><path d="M3 -150 Q50 -170 92 -150 Q60 -128 92 -106 Q50 -88 3 -106Z" fill="#d6452f" stroke="#1d1d1b" stroke-width="3"/><path d="M28 -140 l8 16 l17 2 l-12 12 l3 17 l-16 -8 l-16 8 l3 -17 l-12 -12 l17 -2z" fill="#ffd23f"/></g>'
    g+=friend('toby-flag',420,745,360)+friend('jarvis-idea',860,745,320,True)
    g+=fireflies(p,[(200,380,8),(1000,350,9),(700,300,7),(320,560,8),(1100,520,7)])
    g+=f'<rect width="{W}" height="{H}" fill="url(#{p}vig)" opacity=".7"/>'
    g+=narr(['Deep in the forest, the trail split in two.','Which way to the castle?'],34,30,24)
    g+=bubble(['This way! I will go first','so no one gets lost.'],380,250,410,390,23,'Toby')
    g+=bubble(['A trailblazer!','Lead the way, Toby!'],920,270,880,430,23,'Jarvis')
    return g
# ===================== PAGE 9 · Zion =====================
def s9(p):
    g=f'<rect width="{W}" height="{H}" fill="url(#{p}skyHigh)"/>'+cloud(p,180,330,1.2,'cloud',.85)+cloud(p,980,380,1,'cloud',.85)
    g+=f'<path d="M0 760 V520 L200 360 L420 470 L640 260 L860 420 L1050 300 L1200 380 V760Z" fill="#8c8f9c"/><path d="M640 260 L600 330 L660 320 L700 300Z M1050 300 L1020 350 L1070 340Z" fill="#fff"/>'
    g+=f'<path d="M0 760 V600 L240 520 L460 580 L700 500 L940 570 L1200 520 V760Z" fill="#a6a9b4"/>'
    for i in range(6):
        x=120+i*150;y=740-i*52
        g+=f'<path d="M{x} {y} h220 v{60+i*52} h-220Z" fill="#c7c1b2" stroke="#1d1d1b" stroke-width="3"/><path d="M{x} {y} h220 v12 h-220Z" fill="#e4dfd2"/><path d="M{x+40} {y+28} l18 -6 M{x+150} {y+40} l14 4" stroke="#8a8478" stroke-width="3"/>'
        g+=f'<g transform="translate({x+190} {y-6})"><circle r="13" fill="#ffd23f" stroke="#1d1d1b" stroke-width="2.5"/><path d="M0 -7 l2 5 h5 l-4 3 l2 5 l-5 -3 l-5 3 l2 -5 l-4 -3 h5z" fill="#fff"/></g>' if i<5 else ''
    g+=friend('zion-emblem',890,480,300,True)+friend('jarvis-point',280,700,280)
    g+=sparkle(830,240,1.6)+sparkle(960,200,1.1)+sparkle(1020,300,.9,'#fff3b0')
    g+=f'<rect width="{W}" height="{H}" fill="url(#{p}vig)" opacity=".5"/>'
    g+=narr(['High on the stone steps, Zion was','polishing a shining emblem.'],34,30,25)
    g+=sfx('shine!',1050,200,46,-8,'#fff3b0')
    g+=bubble(['Every step up','is a new rank.','One step at a time!'],640,230,820,250,23,'Zion')
    g+=bubble(['Climb with us to','the Reading Castle!'],300,330,280,430,22,'Jarvis')
    return g
# ===================== PAGE 10 · Arah =====================
def s10(p):
    g=f'<rect width="{W}" height="{H}" fill="url(#{p}skyDawn)"/>'+f'<circle cx="600" cy="470" r="380" fill="url(#{p}sunGlow)"/>'+rays(600,470,20,900,'#fff3b0',.18,.04,math.pi,2*math.pi)
    g+=f'<circle cx="600" cy="470" r="80" fill="url(#{p}sunDisk)"/>'
    g+=f'<path d="M0 470 L160 400 L320 440 L500 380 L700 450 L880 390 L1060 440 L1200 410 V560 H0Z" fill="#7d6aa6" opacity=".6"/>'
    for i in range(5):
        y=480+i*58;g+=f'<path d="M0 {y} Q300 {y-30} 600 {y-8} Q900 {y+14} 1200 {y-20} V{y+60} H0Z" fill="{["#6fa65a","#79b062","#83ba6a","#8ec474","#99ce7e"][i]}" stroke="#4f7f3f" stroke-width="3"/>'
        g+=''.join(f'<path d="M{x} {y+18} v-14 M{x+6} {y+18} v-18 M{x+12} {y+18} v-12" stroke="#4f8f3f" stroke-width="2.5"/>' for x in range(30+i*20,1200,90))
    g+=nipa_hut(p,170,520,.9)
    g+=f'<g transform="translate(1040 560)"><ellipse cx="0" cy="0" rx="26" ry="20" fill="#d6452f" stroke="#1d1d1b" stroke-width="3"/><circle cx="18" cy="-22" r="12" fill="#d6452f" stroke="#1d1d1b" stroke-width="3"/><path d="M12 -34 q4 -12 10 -2 q4 -10 8 2" fill="#ffd23f" stroke="#1d1d1b" stroke-width="2"/><path d="M28 -22 L40 -18 L28 -14Z" fill="#ffb000"/><circle cx="21" cy="-24" r="2" fill="#1d1d1b"/><path d="M-24 -8 Q-50 -40 -36 -2 Q-52 -24 -30 6" fill="#2fa04f" stroke="#1d1d1b" stroke-width="2"/><path d="M-6 18 v16 M8 18 v16" stroke="#ffb000" stroke-width="3"/></g>'
    g+=friend('arah-calendar',480,745,350)+friend('jarvis-hello',820,745,300,True)
    g+=f'<rect width="{W}" height="{H}" fill="url(#{p}vig)" opacity=".5"/>'
    g+=narr(['Every morning at sunrise,','Arah marked her calendar.'],34,30,25)
    g+=sfx('KOKOROKO!',1030,440,44,-8,'#ffd23f')
    g+=bubble(['A little reading every day','keeps the streak alive!'],520,240,500,390,23,'Arah')
    g+=bubble(['Day 1 of our','big adventure!'],870,320,840,450,22,'Jarvis')
    return g
# ===================== PAGE 11 · Roxanne =====================
def s11(p):
    g=sky(p,'Day')+cloud(p,180,90,.9)+cloud(p,980,120,.8)
    g+=house(20,520,170,140,'#ffe8b8','#d6452f')+house(220,540,150,120,'#cfe6f2','#2a6cc0')+house(860,540,150,120,'#ffd6e0','#7a3fb0')+house(1030,520,160,140,'#e2f5d0','#2f8a4b')
    g+=f'<g transform="translate(600 520)"><rect x="-70" y="-380" width="140" height="380" fill="url(#{p}stoneW)" stroke="#1d1d1b" stroke-width="4"/><rect x="-70" y="-380" width="140" height="380" fill="url(#{p}bricks)"/>'
    g+=f'<path d="M-90 -380 L0 -470 L90 -380Z" fill="url(#{p}roofR)" stroke="#1d1d1b" stroke-width="4"/><circle cx="0" cy="-290" r="58" fill="#fffaf0" stroke="#1d1d1b" stroke-width="6"/>'
    g+=''.join(f'<rect x="-2" y="-344" width="4" height="10" fill="#1d1d1b" transform="rotate({i*30} 0 -290)"/>' for i in range(12))
    g+=f'<path d="M0 -290 L0 -326 M0 -290 L24 -276" stroke="#1d1d1b" stroke-width="6" stroke-linecap="round"/><circle cx="0" cy="-290" r="6" fill="#d6452f"/>'
    g+=f'<path d="M-30 0 v-80 a30 30 0 0 1 60 0 v80z" fill="#7a4a22" stroke="#1d1d1b" stroke-width="3"/></g>'
    g+=f'<path d="M0 560 H1200 V760 H0Z" fill="#e7d7b4"/><path d="M0 560 H1200 V760 H0Z" fill="url(#{p}tiles)"/>'
    g+=bunting(0,200,530,240)+bunting(670,240,1200,200)
    g+=friend('roxanne-time',330,745,330)+friend('kai-hello',830,745,300,True)+friend('jarvis-cheer',1040,745,280,True)
    g+=f'<rect width="{W}" height="{H}" fill="url(#{p}vig)" opacity=".5"/>'
    g+=narr(['In the town plaza, the big clock','had stopped. Everyone was late!'],34,30,24)
    g+=sfx('TICK TOCK!',790,250,50,-6,'#ffd23f')
    g+=bubble(['I will keep our time,','so no one is late!'],330,360,330,430,23,'Roxanne')
    g+=bubble(['Thank you,','Roxanne!'],930,330,900,450,24,'Kai')
    return g
# ===================== PAGE 12 · Kai =====================
def s12(p):
    g=sky(p,'Wind')+cloud(p,240,120,1)+cloud(p,860,90,.8)
    g+=hills_day(p,440,500)
    g+=f'<path d="M0 560 Q400 500 800 560 Q1000 590 1200 540 V760 H0Z" fill="url(#{p}hillA)"/><path d="M0 560 Q400 500 800 560 Q1000 590 1200 540 V760 H0Z" fill="url(#{p}grass)"/>'
    road='M-40 700 C300 640 600 640 1240 600'
    g+=f'<path d="{road}" stroke="url(#{p}path)" stroke-width="70" fill="none"/>'
    g+=castle(p,1060,470,.32,'dim')
    g+=wind(60,360,1.1)+wind(540,250,.9)+envelope(200,250,1,-18)+envelope(700,190,.8,14)
    g+=friend('kai-letter',360,740,350)
    g+=friend('bruno-hello',780,730,250,True)+friend('mia-hello',900,725,240,True)+friend('toby-hello',1020,730,250,True)+friend('george-hello',1130,735,240,True)
    g+=f'<rect width="{W}" height="{H}" fill="url(#{p}vig)" opacity=".5"/>'
    g+=narr(['Then Kai came running with','a letter from the Reading Castle.'],34,30,25)
    g+=sfx('WHOOSH!',600,420,54,-8,'#e8f6ff')
    g+=bubble(['Message for everyone:','the great lamp','is almost out!'],420,230,380,400,23,'Kai')
    g+=bubble(['Oh no!','We must hurry!'],900,300,860,470,24,'Bruno')
    return g
# ===================== PAGE 13 · Sofie =====================
def s13(p):
    g=f'<rect width="{W}" height="{H}" fill="url(#{p}skyDusk)"/>'+stars(30,13,200)
    g+=castle(p,600,500,.75,'dim')
    g+=f'<path d="M0 520 Q600 470 1200 520 V760 H0Z" fill="url(#{p}hillA)"/><path d="M0 520 Q600 470 1200 520 V760 H0Z" fill="url(#{p}grass)"/>'
    g+=f'<path d="M420 760 Q420 540 600 520 Q780 540 780 760" stroke="#2f8a4b" stroke-width="22" fill="none"/>'
    for i in range(14):a=math.pi*(1+i/13);g+=flower(600+math.cos(a)*175,640+math.sin(a)*120,['#ff8fab','#ffd23f','#c3a2ff','#ff9f5a'][i%4],1.4)
    g+=bunting(80,250,560,270)+bunting(640,270,1120,250)
    g+=lantern(160,330)+lantern(1040,330,'#ffd23f')+lantern(330,300,'#7ac0ff')+lantern(870,300,'#ff8fab')
    for x in range(30,1200,70):g+=flower(x,752+(x%3)*4,['#ff8fab','#ffd23f','#c3a2ff','#ff9f5a','#9ad0ff'][x%5],1.2)
    g+=friend('sofie-mirror',280,740,340)+friend('dracs-cheer',930,740,320,True)
    g+=sparkle(420,420,1.3)+sparkle(780,400,1.1)+sparkle(250,280,.9,'#fff3b0')+butterfly(1100,450,1.3,'#ff8fab')+butterfly(130,470,1.1,'#ffd23f')
    g+=f'<rect width="{W}" height="{H}" fill="url(#{p}vig)" opacity=".45"/>'
    g+=narr(['At the castle garden, Sofie made','everything bright and happy.'],34,30,25)
    g+=bubble(['A happy page is','easy to read. Let me','add some color!'],310,250,290,400,23,'Sofie')
    g+=bubble(['So pretty!'],930,330,920,430,26,'Dracs')
    return g
# ===================== PAGE 14 · Claude =====================
def s14(p):
    g=f'<rect width="{W}" height="{H}" fill="url(#{p}skyStorm)"/>'+cloud(p,180,90,1.4,'cloudStorm')+cloud(p,620,60,1.2,'cloudStorm')+cloud(p,1050,100,1.3,'cloudStorm')
    g+=bolt(320,150,1.2)+bolt(980,170,.9)+rain(14,110,.55)
    g+=castle(p,600,470,.95,'dim',True)
    g+=f'<path d="M0 560 Q600 520 1200 560 V760 H0Z" fill="#2d4a33"/><path d="M0 560 Q600 520 1200 560 V760 H0Z" fill="url(#{p}grassDk)"/>'
    g+=f'<ellipse cx="600" cy="640" rx="330" ry="250" fill="url(#{p}shield)" stroke="#9fe0ff" stroke-width="5" opacity=".85"/><path d="M290 600 Q600 360 910 600" stroke="#e6f9ff" stroke-width="3" fill="none" opacity=".7"/>'
    g+=friend('claude-shield',600,750,330)
    g+=friend('max-hello',380,745,220)+friend('arah-hello',480,750,200)+friend('roxanne-hello',730,750,210,True)+friend('zion-hello',830,745,220,True)
    g+=f'<rect width="{W}" height="{H}" fill="url(#{p}vig)" opacity=".7"/>'
    g+=narr(['At the castle gate, a dark storm came.','Claude raised his shield.'],34,30,25)
    g+=sfx('KRAKOOM!',930,330,58,-8,'#fff59a')
    g+=bubble(['Stay close, friends.','I will keep','everyone safe.'],240,330,470,470,23,'Claude')
    return g
# ===================== PAGE 15 · Bob (v178) =====================
def cone(x,y,s=1):
    return (f'<g transform="translate({x} {y}) scale({s})"><path d="M-26 0 L-8 -64 L8 -64 L26 0Z" fill="#ff7a1a" stroke="#1d1d1b" stroke-width="3"/>'
            f'<path d="M-18 -26 L18 -26 L14 -40 L-14 -40Z" fill="#fff"/><rect x="-34" y="-6" width="68" height="10" rx="3" fill="#ff7a1a" stroke="#1d1d1b" stroke-width="3"/></g>')
def barrier(x,y,w):
    st=''.join(f'<path d="M{x+i} {y} l20 0 l-20 26 l-20 0z" fill="#1d1d1b"/>' for i in range(10,w,40))
    return (f'<rect x="{x}" y="{y}" width="{w}" height="26" fill="#ffd23f" stroke="#1d1d1b" stroke-width="3"/>{st}'
            f'<rect x="{x+12}" y="{y+26}" width="10" height="44" fill="#6b4a2a"/><rect x="{x+w-22}" y="{y+26}" width="10" height="44" fill="#6b4a2a"/>')
def rainbow(cx,cy,r):
    return ''.join(f'<path d="M{cx-r+i*14} {cy} A{r-i*14} {r-i*14} 0 0 1 {cx+r-i*14} {cy}" stroke="{c}" stroke-width="14" fill="none" opacity=".55"/>' for i,c in enumerate(['#ff6b6b','#ffa94d','#ffd43b','#69db7c','#4dabf7','#9775fa']))
def s15b(p):
    g=sky(p,'Day')+rainbow(860,470,330)+cloud(p,160,90,.9)+cloud(p,1040,80,.7,'cloudStorm')
    g+=castle(p,760,440,.42,'dim')
    g+=f'<path d="M0 520 Q600 480 1200 520 V760 H0Z" fill="url(#{p}hillA)"/><path d="M0 520 Q600 480 1200 520 V760 H0Z" fill="url(#{p}grass)"/>'
    g+=f'<path d="M560 760 C600 650 640 600 700 560 L860 560 C800 600 780 660 800 760Z" fill="#4aa3df" stroke="#2a6cc0" stroke-width="4"/>'
    g+=''.join(f'<path d="M{x} {y} q12 -7 24 0 q12 7 24 0" stroke="#e8f6ff" stroke-width="4" fill="none" stroke-linecap="round"/>' for x,y in [(650,640),(700,700),(720,600)])
    planks=''.join(f'<rect x="{560+i*34}" y="{596-i*2}" width="30" height="22" rx="3" fill="#b07a3c" stroke="#1d1d1b" stroke-width="3" transform="rotate(-4 {575+i*34} {607-i*2})"/>' for i in range(6))
    g+=f'<rect x="548" y="616" width="230" height="10" fill="#6b4a2a"/>'+planks
    g+=f'<rect x="776" y="574" width="34" height="22" rx="3" fill="#d9a35b" stroke="#1d1d1b" stroke-width="3" stroke-dasharray="6 5" transform="rotate(-14 793 585)"/>'
    g+=cone(470,720,1)+cone(900,700,.9)+cone(1080,740,1.1)+barrier(60,640,220)
    g+=friend('bob-blueprint',330,750,360)+friend('jarvis-hello',1000,750,280,True)+friend('claude-thumbs',1120,750,250,True)
    g+=sparkle(700,520,1.2,'#fff3b0')+sparkle(560,470,.9)
    g+=f'<rect width="{W}" height="{H}" fill="url(#{p}vig)" opacity=".45"/>'
    g+=narr(['The storm broke the bridge to the castle.','Then a new friend came: Bob the Builder!'],34,30,24)
    g+=sfx('BANG! BANG!',640,430,50,-8,'#ffd23f')
    g+=bubble(['Do not worry!','I have a plan. I will build it','again, better than before!'],330,250,330,390,22,'Bob')
    g+=bubble(['Hooray! Now we can','reach the great lamp!'],1010,270,1000,500,22,'Jarvis')
    return g
# ===================== PAGE 16 · The lamp shines =====================
def s15(p):
    g=f'<rect width="{W}" height="{H}" fill="url(#{p}skyNight)"/>'+stars(80,15,400,True)
    g+=firework(200,170,70,'#ff8fab')+firework(1000,150,80,'#ffd23f')+firework(820,260,50,'#7ac0ff')+firework(370,290,45,'#9ad386')
    g+=rays(600,300,24,700,'#fff3b0',.16,.04)
    g+=castle(p,600,470,.9,'bright')
    g+=f'<path d="M0 560 Q600 500 1200 560 V760 H0Z" fill="url(#{p}hillB)"/><path d="M0 560 Q600 500 1200 560 V760 H0Z" fill="url(#{p}grass)"/>'
    g+=confetti(15,70,520)
    row=[('george-cheer',80),('max-cheer',180),('dracs-cheer',280),('bruno-cheer',380),('mia-cheer',820),('toby-cheer',920),('zion-cheer',1020),('arah-cheer',1120)]
    for n,x in row:g+=friend(n,x,700,200,x>600)
    row2=[('roxanne-cheer',140),('kai-cheer',260),('bob-cheer',360),('sofie-cheer',940),('claude-thumbs',1060)]
    for n,x in row2:g+=friend(n,x,760,220,x>600)
    g+=friend('girl-cheer',470,760,190)+friend('boy-cheer',730,760,190,True)+friend('jarvis-cheer',600,760,260)
    g+=f'<rect width="{W}" height="{H}" fill="url(#{p}vig)" opacity=".4"/>'
    g+=narr(['Together, the friends lit the great lamp.','The Reading Castle shone again!'],34,30,24)
    g+=bubble(['Now reading is','for me too!'],270,360,450,560,22,'Pupil')
    g+=bubble(['That is what BULIG means:','we help each other read!'],880,360,640,500,22,'Jarvis')
    return g

PAGES=[('0','How the BULIG Friends Met',s0),('1','The Sleeping Castle',s1),('2','A Robot Wakes Up',s2),('3','George the Explorer',s3),
 ('4','Max the Listener',s4),('5','Dracs the Storyteller',s5),('6','Bruno the Helper',s6),('7','Mia the Builder',s7),('8','Toby the Trailblazer',s8),
 ('9','Zion the Rank Keeper',s9),('10','Arah the Streak Keeper',s10),('11','Roxanne the Timekeeper',s11),('12','Kai the Messenger',s12),
 ('13','Sofie the Stylist',s13),('14','Claude the Protector',s14),('15','Bob the Builder',s15b),('16','The Lamp Shines Again',s15)]
if __name__=='__main__':
    out=[]
    for n,title,fn in PAGES:
        SCRIPT.clear();svg=frame(fn,'s'+n)
        k=0
        while '<g class="cm-say">' in svg:svg=svg.replace('<g class="cm-say">',f'<g class="cm-say" data-line="{k}">',1);k+=1
        svg=svg.replace(' role="img">',f' role="img" aria-label="{esc(title)}">',1)
        out.append({'n':int(n),'title':title,'svg':svg,'script':[{'who':w,'text':t} for w,t in SCRIPT]})
    dst=os.path.join(os.path.dirname(os.path.abspath(__file__)),'..','app','story_pages.json')
    with open(dst,'w') as f:json.dump(out,f,ensure_ascii=False,separators=(',',':'))
    print(len(out),'pages,',os.path.getsize(dst)//1024,'KB')
