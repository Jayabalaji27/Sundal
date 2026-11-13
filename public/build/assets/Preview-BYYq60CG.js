import{r as c,j as r}from"./ui-B3kNf7Ti.js";import{u as _,g as b,G as h,H as k}from"./app-B_xhB4MH.js";import{NewYork as v}from"./NewYork-Cm6O23bl.js";import{Toronto as g}from"./Toronto-DRYz8Nt1.js";import{Rio as P}from"./Rio-IM1ShPbw.js";import{London as T}from"./London-Wp8OyAi3.js";import{Istanbul as A}from"./Istanbul-PpgxNBC8.js";import{Mumbai as D}from"./Mumbai-BUjpwM5v.js";import{HongKong as I}from"./HongKong-BSmnK4UE.js";import{Tokyo as R}from"./Tokyo-DiIUILvH.js";import{Sydney as C}from"./Sydney-Cd-LvbBe.js";import{Paris as E}from"./Paris-Y-o2TZJv.js";import{u as H}from"./usePdfDownload-DYQhhyp2.js";import{f as L}from"./currency-kzm_C5pv.js";import"./utils-DBYZG17H.js";import"./QRCodeGenerator-DSJ02xxP.js";function X(){const{t:i}=_(),{invoice:t,invoiceSettings:o}=b().props,{logoDark:m}=h(),n=c.useRef(null),{downloadPDF:l}=H(),u=(o==null?void 0:o.invoice_qr_display)==="true"||(o==null?void 0:o.invoice_qr_display)===!0,p=(o==null?void 0:o.invoice_footer_title)||"",d=(o==null?void 0:o.invoice_footer_notes)||"",a=(o==null?void 0:o.invoice_template)||"london",f=(o==null?void 0:o.invoice_color)||"#3b82f6",x=o!=null&&o.invoice_logo&&o.invoice_logo.trim()!==""?o.invoice_logo:m,y=s=>L(s);c.useEffect(()=>{const s=setTimeout(()=>{j()},1500);return()=>clearTimeout(s)},[]);const j=async()=>{n.current&&(await l(n.current,`Invoice-${t.invoice_number}.pdf`),window.close())},e={invoice:t,color:f,showQr:u,invoiceUrl:route("invoices.payment",t.payment_token),footerTitle:p,footerNotes:d,remainingAmount:t.balance_due,formatAmount:y,t:i,companyLogo:x},w=()=>{switch(a==null?void 0:a.toLowerCase()){case"new_york":return r.jsx(v,{...e});case"toronto":return r.jsx(g,{...e});case"rio":return r.jsx(P,{...e});case"istanbul":return r.jsx(A,{...e});case"mumbai":return r.jsx(D,{...e});case"hong_kong":return r.jsx(I,{...e});case"tokyo":return r.jsx(R,{...e});case"sydney":return r.jsx(C,{...e});case"paris":return r.jsx(E,{...e});case"london":default:return r.jsx(T,{...e})}};return r.jsxs(r.Fragment,{children:[r.jsxs(k,{children:[r.jsx("title",{children:`${i("Invoice Preview")} - #${t.invoice_number}`}),r.jsx("style",{children:`
                    body {
                        background-color: #f3f4f6;
                    }
                    @media print {
                        body {
                            background-color: #ffffff;
                            print-color-adjust: exact;
                            -webkit-print-color-adjust: exact;
                        }
                        .no-print {
                            display: none !important;
                        }
                        .print-area {
                            box-shadow: none !important;
                            padding: 0 !important;
                            margin: 0 !important;
                            width: 100% !important;
                        }
                    }
                `})]}),r.jsx("div",{className:"min-h-screen py-10 px-4 flex justify-center bg-gray-100",children:r.jsx("div",{ref:n,className:"print-area w-full max-w-[900px] bg-white p-10 shadow-lg rounded-xl border border-gray-200 transition-all duration-200",children:w()})})]})}export{X as default};
