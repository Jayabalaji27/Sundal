import{r as c,j as r}from"./ui-y7Jq8JNG.js";import{u as _,g as b,y as h,H as k}from"./app-BO4vziBu.js";import{NewYork as v}from"./NewYork-kaW155LK.js";import{Toronto as g}from"./Toronto-BgY_nZiC.js";import{Rio as P}from"./Rio-BAjLi1qt.js";import{London as T}from"./London-Ckq0QjmL.js";import{Istanbul as A}from"./Istanbul-DYdqRdMz.js";import{Mumbai as D}from"./Mumbai-BwcBE1Gs.js";import{HongKong as I}from"./HongKong-D5-GBFwj.js";import{Tokyo as R}from"./Tokyo-Cp-_saO6.js";import{Sydney as C}from"./Sydney-DJRb6aFX.js";import{Paris as E}from"./Paris-B5rQnbyd.js";import{u as H}from"./usePdfDownload-SdB0wPyP.js";import{f as L}from"./currency-kzm_C5pv.js";import"./utils-DBYZG17H.js";import"./QRCodeGenerator-CL2rkbfJ.js";function X(){const{t:i}=_(),{invoice:t,invoiceSettings:o}=b().props,{logoDark:m}=h(),n=c.useRef(null),{downloadPDF:l}=H(),u=(o==null?void 0:o.invoice_qr_display)==="true"||(o==null?void 0:o.invoice_qr_display)===!0,p=(o==null?void 0:o.invoice_footer_title)||"",d=(o==null?void 0:o.invoice_footer_notes)||"",a=(o==null?void 0:o.invoice_template)||"london",f=(o==null?void 0:o.invoice_color)||"#3b82f6",x=o!=null&&o.invoice_logo&&o.invoice_logo.trim()!==""?o.invoice_logo:m,y=s=>L(s);c.useEffect(()=>{const s=setTimeout(()=>{j()},1500);return()=>clearTimeout(s)},[]);const j=async()=>{n.current&&(await l(n.current,`Invoice-${t.invoice_number}.pdf`),window.close())},e={invoice:t,color:f,showQr:u,invoiceUrl:route("invoices.payment",t.payment_token),footerTitle:p,footerNotes:d,remainingAmount:t.balance_due,formatAmount:y,t:i,companyLogo:x},w=()=>{switch(a==null?void 0:a.toLowerCase()){case"new_york":return r.jsx(v,{...e});case"toronto":return r.jsx(g,{...e});case"rio":return r.jsx(P,{...e});case"istanbul":return r.jsx(A,{...e});case"mumbai":return r.jsx(D,{...e});case"hong_kong":return r.jsx(I,{...e});case"tokyo":return r.jsx(R,{...e});case"sydney":return r.jsx(C,{...e});case"paris":return r.jsx(E,{...e});case"london":default:return r.jsx(T,{...e})}};return r.jsxs(r.Fragment,{children:[r.jsxs(k,{children:[r.jsx("title",{children:`${i("Invoice Preview")} - #${t.invoice_number}`}),r.jsx("style",{children:`
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
