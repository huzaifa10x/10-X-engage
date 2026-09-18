import{j as o}from"./app-C9Pge6RS.js";import{b as r}from"./format-DgmBE7Dk.js";import{c}from"./app-logo-icon-DF1uPnwZ.js";import{c as i}from"./createLucideIcon-B_dQqsrX.js";/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const l=[["rect",{width:"18",height:"11",x:"3",y:"11",rx:"2",ry:"2",key:"1w4ew1"}],["path",{d:"M7 11V7a5 5 0 0 1 9.9-1",key:"1mm8w8"}]],m=i("LockOpen",l);/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const p=[["rect",{width:"18",height:"11",x:"3",y:"11",rx:"2",ry:"2",key:"1w4ew1"}],["path",{d:"M7 11V7a5 5 0 0 1 10 0v4",key:"fwvmzm"}]],f=i("Lock",p);function w({window:n,secondsLeft:a,compact:d=!1,className:s}){const t=a??n.seconds_left,e=n.open&&t>0;return d?o.jsx("span",{className:c("inline-block size-2 rounded-full",e?"bg-brand":"bg-zinc-300",s),title:e?`Window open · ${r(t)} left`:"Window closed"}):o.jsxs("span",{className:c("inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium",e?"border-brand bg-brand-soft text-[#2b4a08]":"border-zinc-200 bg-zinc-50 text-zinc-600",s),children:[e?o.jsx(m,{className:"size-3"}):o.jsx(f,{className:"size-3"}),e?`Window open · ${r(t)} left`:n.has_history?"24-hour window closed":"No conversation yet"]})}export{f as L,w as W};
