import{R as d,j as t}from"./ui-y7Jq8JNG.js";import{g as l,H as T}from"./app-Kvrv2-BS.js";import z from"./Header-BjtoPMdo.js";import O from"./Footer-C1YPH08y.js";import{u as A}from"./use-favicon-DBisPcZm.js";import{g as R,i as J}from"./cookie-utils-DqeW5lwk.js";import"./utils-DBYZG17H.js";import"./helpers-BEXGkAQo.js";import"./menu-DWlRdUaY.js";import"./mail-DD-EXAM7.js";import"./phone-ywdYDeaD.js";import"./map-pin-C_bX-dDW.js";import"./instagram-C1h__Ewh.js";import"./twitter-B8a5G2u7.js";function te(){var y,j,b,_,L,k,D,v,N,w,E,C,M;const F=`
    .prose h1, .prose h2, .prose h3, .prose h4, .prose h5, .prose h6 {
      color: #1f2937;
      font-weight: 600;
      margin-top: 2rem;
      margin-bottom: 1rem;
    }
    
    .prose h1 { font-size: 2.25rem; }
    .prose h2 { font-size: 1.875rem; }
    .prose h3 { font-size: 1.5rem; }
    
    .prose p {
      margin-bottom: 1.5rem;
      line-height: 1.75;
    }
    
    .prose ul, .prose ol {
      margin: 1.5rem 0;
      padding-left: 1.5rem;
    }
    
    .prose li {
      margin-bottom: 0.5rem;
    }
    
    .prose a {
      color: var(--primary-color);
      text-decoration: underline;
    }
    
    .prose blockquote {
      border-left: 4px solid var(--primary-color);
      padding-left: 1rem;
      margin: 1.5rem 0;
      font-style: italic;
      background-color: #f9fafb;
      padding: 1rem;
    }
    
    .prose img {
      max-width: 100%;
      height: auto;
      border-radius: 0.5rem;
      margin: 1.5rem 0;
    }
  `,{page:c,customPages:P=[],settings:o,logoLight:p,logoDark:u}=l().props,i=((j=(y=o==null?void 0:o.config_sections)==null?void 0:y.theme)==null?void 0:j.primary_color)||"#3b82f6",h=((_=(b=o==null?void 0:o.config_sections)==null?void 0:b.theme)==null?void 0:_.secondary_color)||"#8b5cf6",f=((k=(L=o==null?void 0:o.config_sections)==null?void 0:L.theme)==null?void 0:k.accent_color)||"#10B77F",e=l().props.globalSettings,x=l().props.userLanguage;A();const g=d.useCallback(()=>{const r=(e==null?void 0:e.is_demo)||!1,s=x||(e==null?void 0:e.defaultLanguage)||"en",m=["ar","he"].includes(s);let a="ltr",n=R("brandSettings");if(n)try{n=JSON.parse(n).layoutDirection}catch{n=null}const H=(r?n:e==null?void 0:e.layoutDirection)==="right";return(m||H)&&(a="rtl"),document.documentElement.dir=a,document.documentElement.setAttribute("dir",a),document.body.dir=a,a},[x,e==null?void 0:e.defaultLanguage,e==null?void 0:e.is_demo,e==null?void 0:e.layoutDirection]);return d.useLayoutEffect(()=>{const r=g(),s=new MutationObserver(()=>{document.documentElement.dir!==r&&(document.documentElement.dir=r,document.documentElement.setAttribute("dir",r))});return s.observe(document.documentElement,{attributes:!0,attributeFilter:["dir"]}),()=>s.disconnect()},[g]),d.useEffect(()=>{let r="light";if(J())try{const a=R("themeSettings");a&&(r=JSON.parse(a).appearance||"light")}catch{}else r=(e==null?void 0:e.themeMode)||"light";const s=window.matchMedia("(prefers-color-scheme: dark)").matches,m=r==="dark"||r==="system"&&s;document.documentElement.classList.toggle("dark",m),document.body.classList.toggle("dark",m)},[e==null?void 0:e.themeMode]),t.jsxs(t.Fragment,{children:[t.jsxs(T,{children:[t.jsx("title",{children:c.meta_title||c.title}),c.meta_description&&t.jsx("meta",{name:"description",content:c.meta_description}),t.jsx("style",{children:F})]}),t.jsxs("div",{className:"min-h-screen bg-white",style:{"--primary-color":i,"--secondary-color":h,"--accent-color":f,"--primary-color-rgb":((D=i.replace("#","").match(/.{2}/g))==null?void 0:D.map(r=>parseInt(r,16)).join(", "))||"59, 130, 246","--secondary-color-rgb":((v=h.replace("#","").match(/.{2}/g))==null?void 0:v.map(r=>parseInt(r,16)).join(", "))||"139, 92, 246","--accent-color-rgb":((N=f.replace("#","").match(/.{2}/g))==null?void 0:N.map(r=>parseInt(r,16)).join(", "))||"16, 185, 129"},children:[t.jsx(z,{settings:o,customPages:P,sectionData:((E=(w=o==null?void 0:o.config_sections)==null?void 0:w.sections)==null?void 0:E.find(r=>r.key==="header"))||{},brandColor:i,logoLight:p,logoDark:u}),t.jsx("main",{className:"pt-16",children:t.jsx("div",{className:"max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12",children:t.jsxs("div",{className:"max-w-4xl mx-auto",children:[t.jsxs("header",{className:"text-center mb-12",children:[t.jsx("h1",{className:"text-4xl font-bold text-gray-900 mb-4",children:c.title}),t.jsx("div",{className:"w-24 h-1 bg-gradient-to-r from-blue-500 to-purple-600 mx-auto rounded-full"})]}),t.jsx("article",{className:"max-w-none",children:t.jsx("div",{className:"text-gray-700 leading-relaxed text-lg",dangerouslySetInnerHTML:{__html:c.content}})})]})})}),t.jsx(O,{settings:o,sectionData:((M=(C=o==null?void 0:o.config_sections)==null?void 0:C.sections)==null?void 0:M.find(r=>r.key==="footer"))||{},brandColor:i,logoLight:p,logoDark:u})]})]})}export{te as default};
