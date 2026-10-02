const n=()=>{const t=new Date,e=t.getTimezoneOffset()*6e4;return new Date(t.getTime()-e).toISOString().split("T")[0]};export{n as t};
