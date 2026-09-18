import{j as e}from"./app-C9Pge6RS.js";import{c as o}from"./app-logo-icon-DF1uPnwZ.js";import{c as a}from"./createLucideIcon-B_dQqsrX.js";import{R as m}from"./reply-B-O_7p1N.js";/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const l=[["path",{d:"M15 3h6v6",key:"1q9fwt"}],["path",{d:"M10 14 21 3",key:"gplh6r"}],["path",{d:"M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6",key:"a6xqqp"}]],p=a("ExternalLink",l);/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const y=[["path",{d:"M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z",key:"1rqfz7"}],["path",{d:"M14 2v4a2 2 0 0 0 2 2h4",key:"tnqrlb"}],["path",{d:"M10 9H8",key:"b1mrlr"}],["path",{d:"M16 13H8",key:"t4e002"}],["path",{d:"M16 17H8",key:"z1uh3a"}]],h=a("FileText",y);/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const N=[["rect",{width:"18",height:"18",x:"3",y:"3",rx:"2",ry:"2",key:"1m3agn"}],["circle",{cx:"9",cy:"9",r:"2",key:"af1f0g"}],["path",{d:"m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21",key:"1xmnt7"}]],g=a("Image",N);/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const j=[["path",{d:"M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0",key:"1r0f0z"}],["circle",{cx:"12",cy:"10",r:"3",key:"ilqhr7"}]],f=a("MapPin",j);/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const k=[["path",{d:"M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z",key:"foiqr5"}]],M=a("Phone",k);/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const v=[["path",{d:"m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5",key:"ftymec"}],["rect",{x:"2",y:"6",width:"14",height:"12",rx:"2",key:"158x01"}]],_=a("Video",v);function b({header:s,body:i,footer:r,buttons:c=[],className:d}){const x={IMAGE:e.jsx(g,{className:"size-8 text-gray-400"}),VIDEO:e.jsx(_,{className:"size-8 text-gray-400"}),DOCUMENT:e.jsx(h,{className:"size-8 text-gray-400"}),LOCATION:e.jsx(f,{className:"size-8 text-gray-400"})};return e.jsx("div",{className:o("rounded-xl bg-[#e5ddd5] p-4",d),children:e.jsxs("div",{className:"max-w-xs rounded-lg bg-white shadow-sm",children:[s&&s.format!=="NONE"&&e.jsx("div",{className:"border-b border-gray-100 p-2",children:s.format==="TEXT"?e.jsx("p",{className:"px-1 text-sm font-semibold break-words text-gray-900",children:s.text||"Header"}):e.jsx("div",{className:"flex h-28 items-center justify-center rounded-md bg-gray-100",children:x[s.format]})}),e.jsxs("div",{className:"space-y-1 px-3 py-2",children:[e.jsx("p",{className:"text-sm break-words whitespace-pre-wrap text-gray-900",children:i||"Message body…"}),r&&e.jsx("p",{className:"text-xs text-gray-500",children:r}),e.jsx("p",{className:"text-right text-[10px] text-gray-400",children:"12:00"})]}),c.length>0&&e.jsx("div",{className:"divide-y divide-gray-100 border-t border-gray-100",children:c.map((t,n)=>e.jsxs("div",{className:"flex items-center justify-center gap-1.5 py-2 text-sm font-medium text-[#0a7cd6]",children:[t.type==="URL"&&e.jsx(p,{className:"size-3.5"}),t.type==="PHONE_NUMBER"&&e.jsx(M,{className:"size-3.5"}),t.type==="QUICK_REPLY"&&e.jsx(m,{className:"size-3.5"}),t.text||t.type.replace("_"," ")]},n))})]})})}export{h as F,g as I,f as M,_ as V,b as W};
